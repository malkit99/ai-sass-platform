<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappBotFlow extends Model
{
    use BelongsToTenant;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const SOURCE_SCRATCH = 'scratch';

    public const SOURCE_TEMPLATE = 'template';

    public const SOURCE_IMPORTED = 'imported';

    protected $fillable = [
        'account_id',
        'channel_id',
        'name',
        'trigger_keywords',
        'flow_definition',
        'status',
        'source',
        'run_count',
        'completion_count',
    ];

    protected function casts(): array
    {
        return [
            'trigger_keywords' => 'array',
            'flow_definition' => 'array',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(WhatsappBotSession::class, 'bot_flow_id');
    }

    /**
     * Case-insensitive contains match against trigger_keywords, same
     * semantics as WhatsappChatbotRule::matches() (MATCH_CONTAINS variant) —
     * only active flows can be triggered by an inbound message.
     */
    public function matchesTrigger(?string $text): bool
    {
        if ($this->status !== self::STATUS_ACTIVE || $text === null || $text === '') {
            return false;
        }

        $haystack = mb_strtolower($text);

        foreach ($this->trigger_keywords ?? [] as $keyword) {
            $needle = mb_strtolower((string) $keyword);

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
