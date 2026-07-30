<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappCallLog extends Model
{
    use BelongsToTenant;

    public const STATUS_RINGING = 'ringing';

    public const STATUS_ANSWERED = 'answered';

    public const STATUS_AUTO_REJECTED = 'auto_rejected';

    public const STATUS_MANUALLY_REJECTED = 'manually_rejected';

    public const STATUS_MISSED = 'missed';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'account_id',
        'channel_id',
        'call_id',
        'caller_phone',
        'is_video',
        'status',
        'reply_type',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'is_video' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
