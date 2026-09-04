<?php

declare(strict_types=1);

namespace App\Services\Media;

use App\Models\Media;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Turns a stored object into a URL somebody's phone can actually load.
 *
 * Two kinds, deliberately not one:
 *
 * Public media is served from the custom domain, cached at Cloudflare's edge
 * and free to fetch. That is right for a tribe's logo and wrong for anything
 * else, because such a URL is permanent, unauthenticated and un-revocable — a
 * link that escapes once has escaped for good.
 *
 * Private media — the default for every upload — is served as a signed URL
 * that expires. Somebody who is entitled to see a photograph today gets a link
 * that stops working, which is the difference between showing a family album
 * to a member and publishing it.
 *
 * That guarantee is only as good as the bucket. An R2 custom domain serves the
 * *whole* bucket anonymously, not the objects an application considers public,
 * so attaching one to the bucket that also holds private media makes every
 * private photograph permanently fetchable by anyone holding its path — and
 * the path is the readable half of every signed URL ever issued. Verified
 * against production: the object came back byte-identical over the custom
 * domain with no credentials at all. Private media therefore belongs in a
 * bucket with no custom domain attached, and R2_URL should be left empty
 * unless a separate public bucket exists.
 *
 * The entitlement check itself is not here: this answers "what URL", never
 * "may they". The controller decides that before asking.
 */
class MediaUrlResolver
{
    /**
     * How long a private link lives.
     *
     * Long enough to open a gallery and scroll it; short enough that a URL
     * copied out of a network log is useless by the time it is pasted.
     */
    public const SIGNED_URL_MINUTES = 15;

    public function url(Media $media): ?string
    {
        $disk = $this->disk($media);

        if ($disk === null) {
            return null;
        }

        // Only when a public base URL is actually configured. With none, the
        // s3 driver still returns a URL — built from the endpoint, unsigned
        // and useless — and handing that out for public media would look like
        // it worked while serving nothing.
        if (! $media->is_private && filled(config("filesystems.disks.{$this->diskName($media)}.url"))) {
            return $disk->url($media->path);
        }

        try {
            return $disk->temporaryUrl(
                $media->path,
                now()->addMinutes(self::SIGNED_URL_MINUTES),
            );
        } catch (Throwable) {
            // A disk with no signing support — the local driver in a test, or
            // a deployment with no R2 credentials yet. Returning null renders
            // as a missing image, which is honest; returning the unsigned path
            // would hand out a permanent link to a private photograph.
            return null;
        }
    }

    private function disk(Media $media): ?Filesystem
    {
        $name = $this->diskName($media);

        return config("filesystems.disks.{$name}") === null
            ? null
            : Storage::disk($name);
    }

    private function diskName(Media $media): string
    {
        return $media->disk ?: 'r2';
    }
}
