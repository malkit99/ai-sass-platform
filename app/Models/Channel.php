<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use BelongsToTenant;

    public const TYPE_WHATSAPP_UNOFFICIAL = 'whatsapp_unofficial';

    public const STATUS_DISCONNECTED = 'disconnected';

    public const STATUS_CONNECTING = 'connecting';

    public const STATUS_CONNECTED = 'connected';

    protected $fillable = [
        'account_id',
        'type',
        'name',
        'profile_name',
        'profile_phone',
        'access_token',
        'credentials',
        'status',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'connected_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'credentials',
    ];

    protected $appends = [
        'display_name',
        'whatsapp_jid',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $channel) {
            $channel->access_token ??= bin2hex(random_bytes(20));
        });
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * "Macroword(919628061241)" when the user has given the account a label
     * (`name`) and the bridge has reported its connected phone number
     * (`profile_phone`). WhatsApp's multi-device protocol never exposes the
     * account's own display name over the socket (confirmed by inspecting a
     * real session's saved credentials — only `id`/`lid`, no `name` field
     * exists anywhere), so `profile_name` is not auto-populated; the phone
     * number is the only thing reliably known automatically.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->name && $this->profile_phone) {
            return "{$this->name}({$this->profile_phone})";
        }

        return $this->profile_phone ?: ($this->name ?: "Account #{$this->id}");
    }

    /**
     * "919216100188@s.whatsapp.net" — the raw WhatsApp JID format shown on
     * the Profile screen (screenshot 32), null until the bridge reports a
     * connected phone number.
     */
    public function getWhatsappJidAttribute(): ?string
    {
        return $this->profile_phone ? "{$this->profile_phone}@s.whatsapp.net" : null;
    }
}
