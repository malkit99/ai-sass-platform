<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Audit trail of every WhatsApp credit change. `WhatsappCreditBalance` holds the
 * fast-read cached balance; this table exists so a disputed "why did my
 * credits drop" question always has an answer (see 11-unofficial-whatsapp.md).
 */
class WhatsappCreditLedger extends Model
{
    use BelongsToTenant;

    // Migration created `whatsapp_credit_ledger` (singular) — doesn't match
    // Eloquent's default pluralized-snake-case guess of `whatsapp_credit_ledgers`.
    protected $table = 'whatsapp_credit_ledger';

    public const REASON_MESSAGE_SENT = 'message_sent';

    public const REASON_BULK_SENT = 'bulk_sent';

    public const REASON_PLAN_RESET = 'plan_reset';

    public const REASON_MANUAL_ADJUSTMENT = 'manual_adjustment';

    protected $fillable = [
        'account_id',
        'delta',
        'reason',
        'balance_after',
    ];

    /**
     * Atomically decrements (or increments) the account's cached credit balance
     * (WhatsappCreditBalance) and writes the matching ledger row in one transaction.
     */
    public static function record(Account $account, int $delta, string $reason): self
    {
        return \DB::transaction(function () use ($account, $delta, $reason) {
            WhatsappCreditBalance::forAccount($account);

            $balance = WhatsappCreditBalance::withoutGlobalScopes()
                ->where('account_id', $account->id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceAfter = max(0, $balance->credits_remaining + $delta);
            $balance->update(['credits_remaining' => $balanceAfter]);

            return self::create([
                'account_id' => $account->id,
                'delta' => $delta,
                'reason' => $reason,
                'balance_after' => $balanceAfter,
            ]);
        });
    }
}
