<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Valid roles per account tier (see .claude/build-plan/08-employees-roles.md).
     * Fixed sets on purpose — not a dynamic permission builder.
     */
    public const ROLES = [
        Account::TYPE_SUPER_ADMIN => ['owner', 'platform_support', 'platform_sales', 'platform_billing', 'platform_developer'],
        Account::TYPE_RESELLER => ['owner', 'support_agent', 'sales', 'billing'],
        Account::TYPE_CLIENT => ['owner', 'agent'],
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'account_id',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    /**
     * The raw account row, bypassing the account() relation and any global
     * scope on Account deliberately — safe to call from inside scope closures.
     */
    public function rawAccount(): ?Account
    {
        return Account::withoutGlobalScopes()->find($this->account_id);
    }

    /**
     * The tier of the account this user belongs to (super_admin/reseller/client).
     */
    public function accountType(): ?string
    {
        return $this->rawAccount()?->account_type;
    }

    protected static function booted(): void
    {
        static::saving(function (self $user) {
            if (! $user->account_id || ! $user->role) {
                return;
            }

            $accountType = Account::withoutGlobalScopes()->find($user->account_id)?->account_type;
            $validRoles = self::ROLES[$accountType] ?? [];

            if (! in_array($user->role, $validRoles, true)) {
                throw new \InvalidArgumentException(
                    "Role '{$user->role}' is not valid for an account of type '{$accountType}'."
                );
            }
        });
    }
}
