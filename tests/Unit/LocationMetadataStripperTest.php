<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Media\LocationMetadataStripper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The photograph is built here rather than committed as a fixture: a real
 * picture with real coordinates in it is exactly the thing this class exists
 * to stop being shared.
 */
class LocationMetadataStripperTest extends TestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('imagejpeg') || ! function_exists('exif_read_data')) {
            $this->markTestSkipped('Needs the gd and exif extensions.');
        }

        $this->file = tempnam(sys_get_temp_dir(), 'gps').'.jpg';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    #[Test]
    public function it_removes_the_coordinates(): void
    {
        $this->writePhotograph($this->file);

        $stripper = new LocationMetadataStripper;

        $this->assertTrue($stripper->hasLocation($this->file), 'the fixture should start with GPS');
        $this->assertTrue($stripper->strip($this->file));
        $this->assertFalse($stripper->hasLocation($this->file));
    }

    #[Test]
    public function the_coordinates_are_gone_from_the_bytes_not_merely_unreferenced(): void
    {
        $this->writePhotograph($this->file);

        (new LocationMetadataStripper)->strip($this->file);

        // 51/1 30/1 0/1 — the latitude rationals, which live outside the entry
        // because eight bytes will not fit in four. Clearing only the directory
        // would leave these sitting in the file for anyone reading the bytes.
        $bytes = file_get_contents($this->file);

        $this->assertStringNotContainsString(
            pack('V', 51).pack('V', 1).pack('V', 30).pack('V', 1),
            $bytes,
            'the latitude itself is still in the file',
        );
    }

    #[Test]
    public function it_keeps_the_photograph_and_its_date(): void
    {
        $this->writePhotograph($this->file);

        $before = getimagesize($this->file);
        $sizeBefore = filesize($this->file);

        (new LocationMetadataStripper)->strip($this->file);

        clearstatcache();
        $after = getimagesize($this->file);

        $this->assertNotFalse($after, 'the file must still be a readable image');
        $this->assertSame($before[0], $after[0]);
        $this->assertSame($before[1], $after[1]);

        // Unchanged length is what keeps every other EXIF offset valid.
        $this->assertSame($sizeBefore, filesize($this->file));

        // When a photograph was taken is evidence, and worth keeping.
        $exif = exif_read_data($this->file, 'IFD0', true);
        $this->assertSame('2026:08:30 10:29:08', $exif['IFD0']['DateTime'] ?? null);
    }

    #[Test]
    public function a_photograph_with_no_location_is_left_alone(): void
    {
        $this->writePhotograph($this->file, withGps: false);

        $before = file_get_contents($this->file);

        $this->assertFalse((new LocationMetadataStripper)->strip($this->file));
        $this->assertSame($before, file_get_contents($this->file), 'nothing to do means touch nothing');
    }

    #[Test]
    public function a_file_that_is_not_a_jpeg_is_refused_rather_than_mangled(): void
    {
        file_put_contents($this->file, 'not an image at all');

        $this->assertFalse((new LocationMetadataStripper)->strip($this->file));
        $this->assertSame('not an image at all', file_get_contents($this->file));
    }

    /** A real JPEG with a hand-built EXIF block spliced in after the SOI. */
    private function writePhotograph(string $path, bool $withGps = true): void
    {
        $image = imagecreatetruecolor(8, 6);
        ob_start();
        imagejpeg($image, null, 90);
        $jpeg = (string) ob_get_clean();

        $exif = $this->exifBlock($withGps);
        $segment = "\xFF\xE1".pack('n', strlen($exif) + 2).$exif;

        file_put_contents($path, "\xFF\xD8".$segment.substr($jpeg, 2));
    }

    /**
     * "Exif\0\0" then a little-endian TIFF block: IFD0 carrying a DateTime and
     * (optionally) a pointer to a GPS IFD, with the rationals stored after both
     * directories because they are too wide to sit inside an entry.
     */
    private function exifBlock(bool $withGps): string
    {
        $entry = static fn (int $tag, int $type, int $count, string $value): string => pack('v', $tag)
            .pack('v', $type)
            .pack('V', $count)
            .str_pad($value, 4, "\x00");

        $date = "2026:08:30 10:29:08\x00";

        // Layout, all offsets relative to the start of the TIFF header:
        //   0   header (8)
        //   8   IFD0
        //   ..  GPS IFD
        //   ..  values (date, latitude)
        $ifd0Count = $withGps ? 2 : 1;
        $ifd0Size = 2 + ($ifd0Count * 12) + 4;
        $gpsOffset = 8 + $ifd0Size;
        $gpsSize = $withGps ? 2 + (1 * 12) + 4 : 0;

        $dateOffset = $gpsOffset + $gpsSize;
        $latOffset = $dateOffset + strlen($date);

        $ifd0 = pack('v', $ifd0Count)
            .$entry(0x0132, 2, strlen($date), pack('V', $dateOffset));

        if ($withGps) {
            $ifd0 .= $entry(0x8825, 4, 1, pack('V', $gpsOffset));
        }

        $ifd0 .= pack('V', 0);

        $gps = '';

        if ($withGps) {
            // GPSLatitude: three RATIONALs, 24 bytes, stored out of line.
            $gps = pack('v', 1)
                .$entry(0x0002, 5, 3, pack('V', $latOffset))
                .pack('V', 0);
        }

        $latitude = $withGps
            ? pack('V', 51).pack('V', 1).pack('V', 30).pack('V', 1).pack('V', 0).pack('V', 1)
            : '';

        return "Exif\x00\x00".'II'.pack('v', 42).pack('V', 8).$ifd0.$gps.$date.$latitude;
    }
}
