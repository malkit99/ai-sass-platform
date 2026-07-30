<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappApiSettings extends Model
{
    protected $table = 'whatsapp_api_settings';

    public const ALL_GROUPS = ['instance', 'messages', 'groups'];

    protected $fillable = [
        'reseller_account_id',
        'enabled_groups',
    ];

    protected function casts(): array
    {
        return [
            'enabled_groups' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $settings) {
            $account = Account::withoutGlobalScopes()->find($settings->reseller_account_id);

            if (! $account || $account->account_type !== Account::TYPE_RESELLER) {
                throw new \InvalidArgumentException('API settings can only be attached to a reseller account.');
            }
        });
    }

    public function resellerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'reseller_account_id');
    }

    /**
     * No row / null enabled_groups both mean "not customized yet" — every
     * group is enabled by default, so an account with no reseller-configured
     * restriction is never silently blocked.
     */
    public function groupEnabled(string $group): bool
    {
        return in_array($group, $this->enabled_groups ?? self::ALL_GROUPS, true);
    }
}
