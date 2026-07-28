<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Media\MediaStorage;
use Illuminate\Database\Eloquent\Model;

/**
 * The shared media library — reusable across any module that needs to attach
 * a file (WhatsApp templates today, others later) rather than a
 * WhatsApp-specific table, since "all media store through media library" per
 * the user's request. Grouped by `purpose` (whatsapp_media/exports/...) so
 * different kinds of media can live in different buckets — see MediaStorage.
 */
class MediaFile extends Model
{
    use BelongsToTenant;

    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPE_AUDIO = 'audio';

    public const TYPE_DOCUMENT = 'document';

    protected $fillable = [
        'account_id',
        'purpose',
        'disk',
        'path',
        'url',
        'name',
        'mime_type',
        'size',
        'type',
    ];

    public static function typeFromMime(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => self::TYPE_IMAGE,
            str_starts_with($mime, 'video/') => self::TYPE_VIDEO,
            str_starts_with($mime, 'audio/') => self::TYPE_AUDIO,
            default => self::TYPE_DOCUMENT,
        };
    }

    /**
     * Total bytes this account has stored across every purpose — compared
     * against `plans.limits['storage_limit_mb']` to enforce the per-plan
     * storage cap (see Api\Media\MediaFileController).
     */
    public static function storageUsedBytes(int $accountId): int
    {
        return (int) self::withoutGlobalScopes()->where('account_id', $accountId)->sum('size');
    }

    protected static function booted(): void
    {
        static::deleting(function (self $file) {
            MediaStorage::disk($file->purpose)->delete($file->path);
        });
    }
}
