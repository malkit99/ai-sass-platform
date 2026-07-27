<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerBranding extends Model
{
    protected $table = 'reseller_branding';

    protected $fillable = [
        'reseller_account_id',
        'product_name',
        'logo_url',
        'primary_color',
        'support_email',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $branding) {
            $account = Account::withoutGlobalScopes()->find($branding->reseller_account_id);

            if (! $account || $account->account_type !== Account::TYPE_RESELLER) {
                throw new \InvalidArgumentException('Branding can only be attached to a reseller account.');
            }
        });
    }

    public function resellerAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'reseller_account_id');
    }
}
