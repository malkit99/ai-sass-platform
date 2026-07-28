<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappChatbotRule extends Model
{
    use BelongsToTenant;

    public const MATCH_CONTAINS = 'contains';

    public const MATCH_EXACT = 'exact';

    protected $fillable = [
        'account_id',
        'channel_id',
        'enabled',
        'target',
        'match_type',
        'name',
        'keywords',
        'message_type',
        'body',
        'media_url',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'keywords' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * True if any of this rule's keywords match the given inbound message text,
     * per its match_type (whole-message equality vs. substring containment).
     * Case-insensitive either way — WhatsApp users don't type consistent casing.
     */
    public function matches(?string $text): bool
    {
        if (! $this->enabled || $text === null || $text === '') {
            return false;
        }

        $haystack = mb_strtolower($text);

        foreach ($this->keywords as $keyword) {
            $needle = mb_strtolower((string) $keyword);

            if ($needle === '') {
                continue;
            }

            if ($this->match_type === self::MATCH_EXACT ? $haystack === $needle : str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
