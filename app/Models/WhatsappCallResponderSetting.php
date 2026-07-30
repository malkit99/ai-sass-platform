<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappCallResponderSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'account_id',
        'channel_id',
        'enabled',
        'auto_reject_enabled',
        'reply_delay_seconds',
        'missed_call_reply',
        'after_call_reply',
        'rejected_call_reply',
        'missed_before_answer_reply',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'auto_reject_enabled' => 'boolean',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
