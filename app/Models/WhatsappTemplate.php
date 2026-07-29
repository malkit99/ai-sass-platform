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

    // Both single-send (MessageController) and bulk campaigns (CampaignController)
    // additionally allow poll (reliable) and buttons/list — re-enabled for
    // testing after the bridge switched engines from @whiskeysockets/baileys
    // to @itsliaaa/baileys, whose own shorthand (templateButtons/sections) is
    // a different, more complete implementation than six manually-built proto
    // constructions that all failed live on vanilla Baileys. Not yet confirmed
    // working — see whatsapp-module-status memory for current test status.
    // Carousel needs Meta's newer native-flow interactive-message protocol on
    // top of that, so it stays unsendable regardless.
    public const ALL_SENDABLE_TYPES = [
        self::TYPE_TEXT,
        self::TYPE_TEXT_IMAGE,
        self::TYPE_TEXT_VIDEO,
        self::TYPE_TEXT_DOCUMENT,
        self::TYPE_TEXT_AUDIO,
        self::TYPE_TEXT_BUTTONS,
        self::TYPE_TEXT_LISTS,
        self::TYPE_TEXT_POLL,
        self::TYPE_INTERACTIVE_BUTTONS,
    ];

    public const INTERACTIVE_KIND_POLL = 'poll';

    public const INTERACTIVE_KIND_BUTTONS = 'buttons';

    public const INTERACTIVE_KIND_LIST = 'list';

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

    /**
     * Which interactive send path this template needs — null for plain
     * text/media, which keep going through the ordinary body/media_url path.
     */
    public function interactiveKind(): ?string
    {
        return match ($this->type) {
            self::TYPE_TEXT_POLL => self::INTERACTIVE_KIND_POLL,
            self::TYPE_TEXT_BUTTONS, self::TYPE_INTERACTIVE_BUTTONS => self::INTERACTIVE_KIND_BUTTONS,
            self::TYPE_TEXT_LISTS => self::INTERACTIVE_KIND_LIST,
            default => null,
        };
    }

    /**
     * Normalizes this template's own `config` shape into what the bridge
     * expects, snapshotted onto the campaign at dispatch time (see
     * CampaignController::store()) the same way body/media_url already are.
     */
    public function buildInteractiveConfig(): ?array
    {
        return match ($this->interactiveKind()) {
            self::INTERACTIVE_KIND_POLL => [
                'options' => $this->config['poll_options'] ?? [],
            ],
            self::INTERACTIVE_KIND_BUTTONS => [
                'footer' => $this->footer,
                'header_type' => $this->config['header_type'] ?? null,
                'header_text' => $this->config['header_text'] ?? null,
                'buttons' => $this->normalizedButtons(),
            ],
            self::INTERACTIVE_KIND_LIST => [
                'footer' => $this->footer,
                'button_text' => $this->config['button_text'] ?? 'View Options',
                'sections' => $this->config['sections'] ?? [],
            ],
            default => null,
        };
    }

    /**
     * text_buttons stores plain label strings (combobox chips); interactive_buttons
     * stores {type, label, value} objects. The bridge only builds legacy
     * quick-reply buttons — WhatsApp's call/url button subtypes need the newer
     * native-flow protocol this Baileys version doesn't expose — so both
     * config shapes collapse to a plain label list here.
     */
    private function normalizedButtons(): array
    {
        return collect($this->config['buttons'] ?? [])
            ->map(fn ($button) => is_string($button) ? $button : ($button['label'] ?? ''))
            ->filter()
            ->values()
            ->all();
    }
}
