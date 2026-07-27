<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;

/**
 * Combines with the tenant scope on Account (which already restricts *which*
 * accounts a query can return) to decide what a user's role permits *doing*
 * to an account within their own visible subtree. See
 * .claude/build-plan/08-employees-roles.md for the role tables this follows.
 */
class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        // Tenant scope already restricts results to the user's own subtree.
        return true;
    }

    public function view(User $user, Account $account): bool
    {
        // Tenant scope already prevents fetching accounts outside the subtree.
        return true;
    }

    public function create(User $user): bool
    {
        return match ($user->accountType()) {
            Account::TYPE_SUPER_ADMIN => in_array($user->role, ['owner', 'platform_sales'], true),
            Account::TYPE_RESELLER => in_array($user->role, ['owner', 'sales'], true),
            default => false, // client-tier users cannot create sub-accounts
        };
    }

    public function update(User $user, Account $account): bool
    {
        return match ($user->accountType()) {
            Account::TYPE_SUPER_ADMIN => $user->role === 'owner',
            Account::TYPE_RESELLER => $user->role === 'owner',
            Account::TYPE_CLIENT => $user->role === 'owner' && $account->id === $user->account_id,
            default => false,
        };
    }

    public function delete(User $user, Account $account): bool
    {
        return match ($user->accountType()) {
            Account::TYPE_SUPER_ADMIN => $user->role === 'owner' && $account->id !== $user->account_id,
            Account::TYPE_RESELLER => $user->role === 'owner' && $account->id !== $user->account_id,
            default => false, // clients cannot delete accounts
        };
    }
}
