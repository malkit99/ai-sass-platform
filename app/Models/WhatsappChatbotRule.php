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

    public const TARGET_ALL = 'all';

    public const TARGET_INDIVIDUAL = 'individual';

    public const TARGET_GROUP = 'group';

    protected $fillable = [
        'account_id',
        'channel_id',
        'enabled',
        'target',
        'target_phone',
        'contact_group_id',
        'match_type',
        'name',
        'keywords',
        'message_type',
        'body',
        'media_url',
        'interactive_config',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'keywords' => 'array',
            'interactive_config' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function contactGroup(): BelongsTo
    {
        return $this->belongsTo(WhatsappContactGroup::class, 'contact_group_id');
    }

    /**
     * True if this rule should fire for an inbound message: the keyword
     * match per match_type (whole-message equality vs. substring containment,
     * case-insensitive) AND, when $phone is given, the all/individual/group
     * "Send to" scope (screenshot 89) — same target semantics as
     * WhatsappAutoresponder::appliesTo(), minus except-contacts/resubmit,
     * which aren't chatbot-item fields.
     */
    public function matches(?string $text, ?string $phone = null): bool
    {
        if (! $this->enabled || $text === null || $text === '') {
            return false;
        }

        if ($phone !== null && ! $this->appliesToTarget($phone)) {
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

    private function appliesToTarget(string $phone): bool
    {
        return match ($this->target) {
            self::TARGET_INDIVIDUAL => $this->target_phone === $phone,
            self::TARGET_GROUP => $this->contactGroup?->contacts()->where('phone', $phone)->exists() ?? false,
            default => true,
        };
    }
}
