<?php

declare(strict_types=1);

namespace App\Services\Media;

/**
 * Removes where a photograph was taken, and nothing else.
 *
 * A phone writes GPS coordinates into every picture it takes. In a family
 * archive that is a photograph of somebody's house carrying its address to
 * everyone entitled to view it, which is not what "share a photo with the
 * family" is understood to mean.
 *
 * Only the GPS block goes. Capture date and camera are left alone: when a
 * photograph was taken is genealogical evidence, and an archive that discards
 * it is worse at its job.
 *
 * The edit is made in place on the JPEG byte stream rather than by re-encoding
 * through GD. Re-encoding would drop the GPS as a side effect, but it also
 * loses quality on every upload, discards the capture date, and — because the
 * orientation tag goes with it — silently turns portrait photographs sideways.
 * The file's length is deliberately unchanged, so every offset elsewhere in the
 * EXIF block stays valid.
 */
class LocationMetadataStripper
{
    /** IFD0 entry pointing at the GPS sub-IFD. */
    private const TAG_GPS_IFD = 0x8825;

    /** Bytes per component, indexed by TIFF field type. */
    private const TYPE_SIZES = [
        1 => 1,  // BYTE
        2 => 1,  // ASCII
        3 => 2,  // SHORT
        4 => 4,  // LONG
        5 => 8,  // RATIONAL
        6 => 1,  // SBYTE
        7 => 1,  // UNDEFINED
        8 => 2,  // SSHORT
        9 => 4,  // SLONG
        10 => 8, // SRATIONAL
        11 => 4, // FLOAT
        12 => 8, // DOUBLE
    ];

    /**
     * Strips location from the file at [$path], in place.
     *
     * Returns true when something was removed. False means there was nothing
     * to remove OR that this file is a format we do not rewrite — the caller
     * must not read it as "this file is now known to be clean". Use
     * [hasLocation] for that question.
     */
    public function strip(string $path): bool
    {
        $bytes = @file_get_contents($path);

        if ($bytes === false || ! $this->isJpeg($bytes)) {
            return false;
        }

        $stripped = $this->stripJpeg($bytes);

        if ($stripped === null) {
            return false;
        }

        return @file_put_contents($path, $stripped) !== false;
    }

    /**
     * Whether the file still carries location.
     *
     * Deliberately independent of [strip]: a check that shares its parser
     * would agree with it about a file they had both misread.
     */
    public function hasLocation(string $path): bool
    {
        if (! function_exists('exif_read_data')) {
            return false;
        }

        $exif = @exif_read_data($path, 'GPS', true);

        if (! is_array($exif) || ! isset($exif['GPS']) || ! is_array($exif['GPS'])) {
            return false;
        }

        foreach ($exif['GPS'] as $key => $value) {
            if (str_starts_with((string) $key, 'GPS') && $value !== '' && $value !== null) {
                return true;
            }
        }

        return false;
    }

    private function isJpeg(string $bytes): bool
    {
        return strlen($bytes) > 4 && substr($bytes, 0, 2) === "\xFF\xD8";
    }

    /** Returns the rewritten file, or null when there was nothing to change. */
    private function stripJpeg(string $bytes): ?string
    {
        $length = strlen($bytes);
        $pos = 2;
        $changed = false;

        // Walk the segment chain. Stop at start-of-scan: everything after it is
        // compressed image data, not metadata.
        while ($pos + 4 <= $length && $bytes[$pos] === "\xFF") {
            $marker = ord($bytes[$pos + 1]);

            if ($marker === 0xDA || $marker === 0xD9) {
                break;
            }

            $segmentLength = (ord($bytes[$pos + 2]) << 8) | ord($bytes[$pos + 3]);

            if ($segmentLength < 2 || $pos + 2 + $segmentLength > $length) {
                break;
            }

            if ($marker === 0xE1 && substr($bytes, $pos + 4, 6) === "Exif\x00\x00") {
                // TIFF offsets are all relative to the start of the TIFF
                // header, which is why it is tracked separately from $pos.
                $changed = $this->stripExif($bytes, $pos + 10, $pos + 2 + $segmentLength) || $changed;
            }

            $pos += 2 + $segmentLength;
        }

        return $changed ? $bytes : null;
    }

    /** Rewrites the TIFF block in $bytes between $tiff and $end. */
    private function stripExif(string &$bytes, int $tiff, int $end): bool
    {
        if ($tiff + 8 > $end) {
            return false;
        }

        $order = substr($bytes, $tiff, 2);

        if ($order !== 'II' && $order !== 'MM') {
            return false;
        }

        $little = $order === 'II';
        $ifd0 = $tiff + $this->long($bytes, $tiff + 4, $little);

        if ($ifd0 + 2 > $end) {
            return false;
        }

        $count = $this->short($bytes, $ifd0, $little);
        $entriesEnd = $ifd0 + 2 + ($count * 12);

        if ($entriesEnd + 4 > $end) {
            return false;
        }

        for ($i = 0; $i < $count; $i++) {
            $entry = $ifd0 + 2 + ($i * 12);

            if ($this->short($bytes, $entry, $little) !== self::TAG_GPS_IFD) {
                continue;
            }

            $gps = $tiff + $this->long($bytes, $entry + 8, $little);

            // The coordinates themselves, wherever they were put.
            $this->eraseIfd($bytes, $gps, $tiff, $end, $little);

            // Then the entry that points at them. Removing it keeps the file
            // the same length: the later entries and the next-IFD offset shift
            // back by one entry, and the twelve bytes that frees are zeroed.
            // Values live at absolute offsets, so nothing else moves.
            $tail = substr($bytes, $entry + 12, $entriesEnd + 4 - ($entry + 12));

            $bytes = substr_replace($bytes, $tail, $entry, strlen($tail));
            $bytes = substr_replace($bytes, str_repeat("\x00", 12), $entry + strlen($tail), 12);

            $this->writeShort($bytes, $ifd0, $count - 1, $little);

            return true;
        }

        return false;
    }

    /**
     * Zeroes an IFD and every value it points outside itself.
     *
     * The out-of-line values matter: a coordinate is a RATIONAL triplet, far
     * too big for the four bytes in the entry, so the numbers themselves sit
     * elsewhere in the block. Clearing only the directory would leave the
     * latitude and longitude sitting in the file, unreferenced but readable by
     * anyone who looked at the bytes.
     */
    private function eraseIfd(string &$bytes, int $ifd, int $tiff, int $end, bool $little): void
    {
        if ($ifd + 2 > $end || $ifd <= $tiff) {
            return;
        }

        $count = $this->short($bytes, $ifd, $little);
        $size = 2 + ($count * 12) + 4;

        if ($ifd + $size > $end) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $entry = $ifd + 2 + ($i * 12);
            $type = $this->short($bytes, $entry + 2, $little);
            $components = $this->long($bytes, $entry + 4, $little);
            $width = self::TYPE_SIZES[$type] ?? 0;
            $bytesNeeded = $width * $components;

            if ($width === 0 || $bytesNeeded <= 4) {
                continue;
            }

            $at = $tiff + $this->long($bytes, $entry + 8, $little);

            if ($at > $tiff && $at + $bytesNeeded <= $end) {
                $bytes = substr_replace($bytes, str_repeat("\x00", $bytesNeeded), $at, $bytesNeeded);
            }
        }

        $bytes = substr_replace($bytes, str_repeat("\x00", $size), $ifd, $size);
    }

    private function short(string $bytes, int $at, bool $little): int
    {
        $pair = substr($bytes, $at, 2);

        return (int) (unpack($little ? 'v' : 'n', $pair)[1] ?? 0);
    }

    private function long(string $bytes, int $at, bool $little): int
    {
        $quad = substr($bytes, $at, 4);

        return (int) (unpack($little ? 'V' : 'N', $quad)[1] ?? 0);
    }

    private function writeShort(string &$bytes, int $at, int $value, bool $little): void
    {
        $bytes = substr_replace($bytes, pack($little ? 'v' : 'n', $value), $at, 2);
    }
}
