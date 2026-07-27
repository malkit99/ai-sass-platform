<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantScopingTest extends TestCase
{
    use RefreshDatabase;

    private function makeAccount(string $type, ?int $parentId = null): Account
    {
        return Account::withoutGlobalScopes()->create([
            'account_type' => $type,
            'parent_account_id' => $parentId,
            'name' => ucfirst($type).' '.uniqid(),
            'status' => 'active',
        ]);
    }

    private function makeUser(Account $account, string $role = 'owner'): User
    {
        return User::withoutGlobalScopes()->create([
            'name' => 'Test User',
            'email' => uniqid().'@example.com',
            'password' => bcrypt('password'),
            'account_id' => $account->id,
            'role' => $role,
        ]);
    }

    public function test_super_admin_sees_every_account(): void
    {
        $superAdmin = $this->makeAccount(Account::TYPE_SUPER_ADMIN);
        $superAdminUser = $this->makeUser($superAdmin);

        $resellerA = $this->makeAccount(Account::TYPE_RESELLER);
        $this->makeAccount(Account::TYPE_CLIENT, $resellerA->id);
        $resellerB = $this->makeAccount(Account::TYPE_RESELLER);
        $this->makeAccount(Account::TYPE_CLIENT, $resellerB->id);

        $response = $this->actingAs($superAdminUser)->getJson('/api/accounts');

        $response->assertOk();
        $this->assertCount(5, $response->json()); // super admin + 2 resellers + 2 clients
    }

    public function test_reseller_sees_only_its_own_subtree(): void
    {
        $superAdmin = $this->makeAccount(Account::TYPE_SUPER_ADMIN);
        $this->makeUser($superAdmin);

        $resellerA = $this->makeAccount(Account::TYPE_RESELLER);
        $resellerAUser = $this->makeUser($resellerA);
        $this->makeAccount(Account::TYPE_CLIENT, $resellerA->id);

        $resellerB = $this->makeAccount(Account::TYPE_RESELLER);
        $this->makeAccount(Account::TYPE_CLIENT, $resellerB->id);

        $response = $this->actingAs($resellerAUser)->getJson('/api/accounts');

        $response->assertOk();
        $names = collect($response->json())->pluck('name');
        $this->assertCount(2, $names); // reseller A + its one client
        $this->assertTrue($names->contains($resellerA->name));
        $this->assertFalse($names->contains($resellerB->name));
    }

    public function test_reseller_cannot_fetch_another_resellers_account(): void
    {
        $resellerA = $this->makeAccount(Account::TYPE_RESELLER);
        $resellerAUser = $this->makeUser($resellerA);

        $resellerB = $this->makeAccount(Account::TYPE_RESELLER);

        $response = $this->actingAs($resellerAUser)->getJson("/api/accounts/{$resellerB->id}");

        $response->assertNotFound();
    }

    public function test_client_tier_user_cannot_create_an_account(): void
    {
        $resellerA = $this->makeAccount(Account::TYPE_RESELLER);
        $clientA1 = $this->makeAccount(Account::TYPE_CLIENT, $resellerA->id);
        $clientUser = $this->makeUser($clientA1);

        $response = $this->actingAs($clientUser)->postJson('/api/accounts', ['name' => 'Should Fail']);

        $response->assertForbidden();
    }
}
