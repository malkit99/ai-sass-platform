<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Plan;
use App\Models\ResellerBranding;
use App\Models\ResellerDomain;
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
        $starterPlan = Plan::where('name', 'Reseller Starter')->first();
        $proPlan = Plan::where('name', 'Reseller Pro')->first();
        $clientTrialPlan = Plan::where('name', 'Client Trial')->first();

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
        $resellerA->update(['plan_id' => $starterPlan->id, 'trial_expires_at' => now()->addDays(14)]);
        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'reseller-a@example.com'],
            ['name' => 'Reseller A Owner', 'password' => bcrypt('password'), 'account_id' => $resellerA->id, 'role' => 'owner'],
        );

        ResellerDomain::withoutGlobalScopes()->firstOrCreate(
            ['domain' => 'acme.localhost'],
            ['reseller_account_id' => $resellerA->id, 'ssl_status' => 'active', 'verified_at' => now()],
        );
        ResellerBranding::withoutGlobalScopes()->updateOrCreate(
            ['reseller_account_id' => $resellerA->id],
            ['product_name' => 'Acme CRM', 'primary_color' => '#1976D2', 'support_email' => 'support@acme.example'],
        );

        $clientA1 = Account::withoutGlobalScopes()->firstOrCreate(
            ['account_type' => Account::TYPE_CLIENT, 'name' => 'Acme Client One', 'parent_account_id' => $resellerA->id],
        );
        $clientA1->update(['plan_id' => $clientTrialPlan->id, 'trial_expires_at' => now()->addDays(14)]);
        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'client-a1@example.com'],
            ['name' => 'Client A1 Owner', 'password' => bcrypt('password'), 'account_id' => $clientA1->id, 'role' => 'owner'],
        );

        $resellerB = Account::withoutGlobalScopes()->firstOrCreate(
            ['account_type' => Account::TYPE_RESELLER, 'name' => 'Beta Reseller'],
        );
        // Deliberately expired trial — exercises the trial-expiry check.
        $resellerB->update(['plan_id' => $proPlan->id, 'trial_expires_at' => now()->subDays(3)]);
        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'reseller-b@example.com'],
            ['name' => 'Reseller B Owner', 'password' => bcrypt('password'), 'account_id' => $resellerB->id, 'role' => 'owner'],
        );

        ResellerDomain::withoutGlobalScopes()->firstOrCreate(
            ['domain' => 'beta.localhost'],
            ['reseller_account_id' => $resellerB->id, 'ssl_status' => 'active', 'verified_at' => now()],
        );
        ResellerBranding::withoutGlobalScopes()->updateOrCreate(
            ['reseller_account_id' => $resellerB->id],
            ['product_name' => 'Beta CRM', 'primary_color' => '#43A047', 'support_email' => 'support@beta.example'],
        );

        $clientB1 = Account::withoutGlobalScopes()->firstOrCreate(
            ['account_type' => Account::TYPE_CLIENT, 'name' => 'Beta Client One', 'parent_account_id' => $resellerB->id],
        );
        $clientB1->update(['plan_id' => $clientTrialPlan->id, 'trial_expires_at' => now()->addDays(14)]);
        User::withoutGlobalScopes()->updateOrCreate(
            ['email' => 'client-b1@example.com'],
            ['name' => 'Client B1 Owner', 'password' => bcrypt('password'), 'account_id' => $clientB1->id, 'role' => 'owner'],
        );

        $this->command->info('Hierarchy seeded: Platform -> {Acme Reseller -> Acme Client One}, {Beta Reseller -> Beta Client One}');
    }
}
