<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsappGroup extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'account_id',
        'channel_id',
        'group_jid',
        'name',
        'participant_count',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(WhatsappGroupParticipant::class, 'group_id');
    }
}
