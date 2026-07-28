<?php

namespace App\Services\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Resolves which disk/bucket a media upload actually goes to, keyed by
 * "purpose" (whatsapp_media, exports, ...) — see config/filesystems.php
 * ('media.provider' / 'media.buckets'). Lets different kinds of media live
 * in different buckets under the same provider account without a hardcoded
 * disk per purpose in config — adding a new purpose is just new env vars +
 * one config array entry.
 */
class MediaStorage
{
    public const PURPOSES = ['whatsapp_media', 'exports'];

    public static function disk(string $purpose): Filesystem
    {
        $provider = config('filesystems.media.provider');

        if ($provider === 'public') {
            return Storage::disk('public');
        }

        $bucketConfig = config("filesystems.media.buckets.{$purpose}.{$provider}");

        if (! $bucketConfig || ! $bucketConfig['bucket']) {
            throw new RuntimeException("No {$provider} bucket configured for media purpose [{$purpose}] — set the matching env vars.");
        }

        return Storage::build([
            ...config("filesystems.disks.{$provider}"),
            'bucket' => $bucketConfig['bucket'],
            'url' => $bucketConfig['url'],
        ]);
    }

    /**
     * Path prefix within the disk. The local "public" fallback has no
     * per-purpose bucket to separate files, so it prefixes by purpose
     * instead to keep the same organization in dev.
     */
    public static function pathPrefix(string $purpose): string
    {
        return config('filesystems.media.provider') === 'public' ? "media/{$purpose}" : 'media';
    }

    /**
     * The provider name at upload time — purely informational on
     * `media_files.disk` (shown in the UI / useful for auditing). Deletion
     * always re-resolves via `disk(purpose)` against the *current* config,
     * since r2/s3 have no bucket baked in until a purpose picks one — so if
     * the active provider is changed later, older rows' files won't be
     * reachable for deletion until switched back. Not handling that
     * migration case here; flag it if a provider switch is ever planned.
     */
    public static function diskName(): string
    {
        return config('filesystems.media.provider');
    }
}
