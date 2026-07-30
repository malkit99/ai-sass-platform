<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * No BelongsToTenant — submissions come from public, unauthenticated
 * visitors (no logged-in user to scope against), so account_id is stamped
 * explicitly from the parent form at creation time instead (see
 * FormPublicController::submit).
 */
class WhatsappFormSubmission extends Model
{
    protected $fillable = [
        'account_id',
        'form_id',
        'lead_id',
        'data',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(WhatsappForm::class, 'form_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}
