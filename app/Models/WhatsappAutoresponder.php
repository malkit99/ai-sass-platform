<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappAutoresponder extends Model
{
    use BelongsToTenant;

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
        'message_type',
        'body',
        'media_url',
        'interactive_config',
        'resubmit_after_minutes',
        'except_contacts',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'interactive_config' => 'array',
            'except_contacts' => 'array',
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
     * Whether this rule should fire for an inbound message from $phone —
     * except-list first (applies regardless of target), then the target
     * scope itself.
     */
    public function appliesTo(string $phone): bool
    {
        if (in_array($phone, $this->except_contacts ?? [], true)) {
            return false;
        }

        return match ($this->target) {
            self::TARGET_INDIVIDUAL => $this->target_phone === $phone,
            self::TARGET_GROUP => $this->contactGroup?->contacts()->where('phone', $phone)->exists() ?? false,
            default => true,
        };
    }
}
