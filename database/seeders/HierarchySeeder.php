<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds a minimal two-tier hierarchy so tenant scoping can be verified:
 *
 *   Super Admin (Platform)
 *     Reseller A -> Client A1
 *     Reseller B -> Client B1
 *
 * Reseller A must never see Reseller B's data, and vice versa.
 */
class HierarchySeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Account::withoutGlobalScopes()->firstOrCreate(
            ['account_type' => Account::TYPE_SUPER_ADMIN, 'name' => 'Platform'],
        );

        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Super Admin', 'password' => bcrypt('password'), 'account_id' => $superAdmin->id, 'role' => 'owner'],
        );

        $resellerA = Account::withoutGlobalScopes()->firstOrCreate(
            ['account_type' => Account::TYPE_RESELLER, 'name' => 'Acme Reseller'],
        );
        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'reseller-a@example.com'],
            ['name' => 'Reseller A Owner', 'password' => bcrypt('password'), 'account_id' => $resellerA->id, 'role' => 'owner'],
        );

        $clientA1 = Account::withoutGlobalScopes()->firstOrCreate(
            ['account_type' => Account::TYPE_CLIENT, 'name' => 'Acme Client One', 'parent_account_id' => $resellerA->id],
        );
        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'client-a1@example.com'],
            ['name' => 'Client A1 Owner', 'password' => bcrypt('password'), 'account_id' => $clientA1->id, 'role' => 'owner'],
        );

        $resellerB = Account::withoutGlobalScopes()->firstOrCreate(
            ['account_type' => Account::TYPE_RESELLER, 'name' => 'Beta Reseller'],
        );
        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'reseller-b@example.com'],
            ['name' => 'Reseller B Owner', 'password' => bcrypt('password'), 'account_id' => $resellerB->id, 'role' => 'owner'],
        );

        $clientB1 = Account::withoutGlobalScopes()->firstOrCreate(
            ['account_type' => Account::TYPE_CLIENT, 'name' => 'Beta Client One', 'parent_account_id' => $resellerB->id],
        );
        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'client-b1@example.com'],
            ['name' => 'Client B1 Owner', 'password' => bcrypt('password'), 'account_id' => $clientB1->id, 'role' => 'owner'],
        );

        $this->command->info('Hierarchy seeded: Platform -> {Acme Reseller -> Acme Client One}, {Beta Reseller -> Beta Client One}');
    }
}
