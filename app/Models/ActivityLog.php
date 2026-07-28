<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'account_id',
        'user_id',
        'subject_type',
        'subject_id',
        'action',
        'description',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Record one activity entry for a subject model (e.g. a Lead). The
     * subject's class/id are stored, not a real polymorphic relation with an
     * FK constraint, so the log survives after the subject is deleted.
     */
    public static function record(Model $subject, string $action, string $description): self
    {
        return static::create([
            'user_id' => Auth::id(),
            'subject_type' => class_basename($subject),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'description' => $description,
        ]);
    }
}
