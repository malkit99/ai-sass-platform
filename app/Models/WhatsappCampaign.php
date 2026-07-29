<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappCampaign extends Model
{
    use BelongsToTenant;

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const RECURRING_FREQUENCIES = ['daily', 'weekly', 'monthly'];

    protected $fillable = [
        'account_id',
        'channel_id',
        'contact_group_id',
        'parent_campaign_id',
        'name',
        'message_type',
        'body',
        'media_url',
        'media_type',
        'interactive_config',
        'spintax_enabled',
        'emoji_randomizer',
        'warm_up_mode',
        'min_interval_seconds',
        'max_interval_seconds',
        'status',
        'scheduled_at',
        'allowed_hours',
        'recurring_frequency',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'spintax_enabled' => 'boolean',
            'emoji_randomizer' => 'boolean',
            'warm_up_mode' => 'boolean',
            'allowed_hours' => 'array',
            'interactive_config' => 'array',
            'scheduled_at' => 'datetime',
            'next_run_at' => 'datetime',
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

    public function parentCampaign(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_campaign_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(WhatsappCampaignRecipient::class, 'campaign_id');
    }
}
