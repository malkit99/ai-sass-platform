<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class WhatsappTemplate extends Model
{
    use BelongsToTenant;

    public const TYPE_TEXT = 'text';

    public const TYPE_TEXT_IMAGE = 'text_image';

    public const TYPE_TEXT_VIDEO = 'text_video';

    public const TYPE_TEXT_DOCUMENT = 'text_document';

    public const TYPE_TEXT_AUDIO = 'text_audio';

    public const TYPE_TEXT_BUTTONS = 'text_buttons';

    public const TYPE_TEXT_LISTS = 'text_lists';

    public const TYPE_TEXT_POLL = 'text_poll';

    public const TYPE_INTERACTIVE_BUTTONS = 'interactive_buttons';

    public const TYPE_TEXT_CAROUSEL = 'text_carousel';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXT_IMAGE,
        self::TYPE_TEXT_VIDEO,
        self::TYPE_TEXT_DOCUMENT,
        self::TYPE_TEXT_AUDIO,
        self::TYPE_TEXT_BUTTONS,
        self::TYPE_TEXT_LISTS,
        self::TYPE_TEXT_POLL,
        self::TYPE_INTERACTIVE_BUTTONS,
        self::TYPE_TEXT_CAROUSEL,
    ];

    // Types this platform can actually send today via BridgeClient (plain
    // text/media) — the rest are modeled but not yet wired to a send path
    // (see 11-unofficial-whatsapp.md Phase 1c/1d note on interactive types).
    public const SENDABLE_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXT_IMAGE,
        self::TYPE_TEXT_VIDEO,
        self::TYPE_TEXT_DOCUMENT,
        self::TYPE_TEXT_AUDIO,
    ];

    protected $fillable = [
        'account_id',
        'name',
        'type',
        'body',
        'footer',
        'media_url',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    /**
     * Which Baileys media key this template's own type maps to — null for
     * plain text. Kept authoritative here (rather than sniffed from the
     * media_url's file extension at send time) so the bridge is told exactly
     * what to send instead of guessing.
     */
    public function mediaKind(): ?string
    {
        return match ($this->type) {
            self::TYPE_TEXT_IMAGE => 'image',
            self::TYPE_TEXT_VIDEO => 'video',
            self::TYPE_TEXT_DOCUMENT => 'document',
            self::TYPE_TEXT_AUDIO => 'audio',
            default => null,
        };
    }
}
