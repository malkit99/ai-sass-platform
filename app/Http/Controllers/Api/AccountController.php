<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', Account::class);

        return Account::query()->get(['id', 'name', 'account_type', 'parent_account_id', 'status', 'trial_expires_at']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', Account::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        // Reseller-tier users can only create client accounts under themselves;
        // Super Admin-tier users create new top-level reseller accounts.
        $accountType = $request->user()->accountType() === Account::TYPE_SUPER_ADMIN
            ? Account::TYPE_RESELLER
            : Account::TYPE_CLIENT;

        $parentId = $accountType === Account::TYPE_CLIENT ? $request->user()->account_id : null;

        if ($accountType === Account::TYPE_CLIENT) {
            $reseller = Account::withoutGlobalScopes()->find($parentId);
            $maxClients = $reseller->plan?->limits['max_client_accounts'] ?? null;

            if ($maxClients !== null) {
                $currentCount = Account::withoutGlobalScopes()->where('parent_account_id', $parentId)->count();

                if ($currentCount >= $maxClients) {
                    throw ValidationException::withMessages([
                        'name' => ["Client account limit ({$maxClients}) reached for your plan."],
                    ]);
                }
            }
        }

        return Account::create([
            'account_type' => $accountType,
            'parent_account_id' => $parentId,
            'name' => $data['name'],
            'status' => 'active',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Account $account)
    {
        $this->authorize('view', $account);

        return $account;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Account $account)
    {
        $this->authorize('update', $account);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:active,suspended'],
        ]);

        $account->update($data);

        return $account;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Account $account)
    {
        $this->authorize('delete', $account);

        $account->delete();

        return response()->noContent();
    }
}
