<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Account extends Model
{
    public const TYPE_SUPER_ADMIN = 'super_admin';

    public const TYPE_RESELLER = 'reseller';

    public const TYPE_CLIENT = 'client';

    protected $fillable = [
        'account_type',
        'parent_account_id',
        'plan_id',
        'name',
        'trial_expires_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'trial_expires_at' => 'datetime',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * This account's id plus every descendant account id (its own subtree).
     * A reseller's subtree includes its client accounts; a client's subtree is just itself.
     *
     * Deliberately bypasses any global scope — this computes the raw hierarchy,
     * so it must not be filtered by the very scope that depends on it.
     *
     * @return array<int, int>
     */
    public function subtreeIds(): array
    {
        $ids = [$this->id];

        $childIds = self::withoutGlobalScopes()
            ->where('parent_account_id', $this->id)
            ->pluck('id');

        foreach ($childIds as $childId) {
            $ids = [...$ids, ...self::withoutGlobalScopes()->findOrFail($childId)->subtreeIds()];
        }

        return $ids;
    }

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function ($builder) {
            $user = Auth::user();

            if (! $user || ! $user->account) {
                return;
            }

            // Super Admin sees every account; everyone else is scoped to their own subtree.
            if ($user->account->account_type !== self::TYPE_SUPER_ADMIN) {
                $builder->whereIn('id', $user->account->subtreeIds());
            }
        });
    }
}
