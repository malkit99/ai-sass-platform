<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Fast-read cached credit balance, kept as its own table rather than a column on
 * `accounts` (see [[schema-design-preference]]). `WhatsappCreditLedger` is the
 * audit trail; this table is the number the UI actually reads.
 */
class WhatsappCreditBalance extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'account_id',
        'credits_remaining',
    ];

    /**
     * First touch for an account seeds its starting balance from
     * `plans.limits['whatsapp_credits']` (falls back to 0 if the plan sets
     * none) — otherwise every account would start stuck at 0 credits with no
     * way to send a single message.
     */
    public static function forAccount(Account $account): self
    {
        return self::withoutGlobalScopes()->firstOrCreate(
            ['account_id' => $account->id],
            ['credits_remaining' => $account->plan?->limits['whatsapp_credits'] ?? 0],
        );
    }
}
