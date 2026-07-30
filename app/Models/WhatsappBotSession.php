<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per conversation, reused (overwritten in place) across every bot
 * interaction that conversation ever has. No BelongsToTenant — scoped
 * transitively via `conversation`, same as Message.
 */
class WhatsappBotSession extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABANDONED = 'abandoned';

    protected $fillable = [
        'conversation_id',
        'bot_flow_id',
        'current_node_id',
        'variables',
        'status',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function botFlow(): BelongsTo
    {
        return $this->belongsTo(WhatsappBotFlow::class, 'bot_flow_id');
    }
}
