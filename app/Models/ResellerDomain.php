<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerDomain extends Model
{
    protected $fillable = [
        'reseller_account_id',
        'domain',
        'ssl_status',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $domain) {
            $account = Account::withoutGlobalScopes()->find($domain->reseller_account_id);

            if (! $account || $account->account_type !== Account::TYPE_RESELLER) {
                throw new \InvalidArgumentException('A domain can only be attached to a reseller account.');
            }
        });
    }

    public function resellerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'reseller_account_id');
    }
}
