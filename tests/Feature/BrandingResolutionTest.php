<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\ResellerBranding;
use App\Models\ResellerDomain;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingResolutionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test: a logged-in client's tenant subtree doesn't include its
     * own parent reseller, so resolving the reseller account for public
     * branding must bypass Account's tenant scope entirely — otherwise the
     * reseller "disappears" for anyone logged in as one of its own clients.
     */
    public function test_branding_resolves_correctly_even_when_a_client_is_logged_in(): void
    {
        $reseller = Account::withoutGlobalScopes()->create([
            'account_type' => Account::TYPE_RESELLER,
            'name' => 'Acme Reseller',
            'status' => 'active',
        ]);

        ResellerDomain::withoutGlobalScopes()->create([
            'reseller_account_id' => $reseller->id,
            'domain' => 'acme.test',
            'ssl_status' => 'active',
        ]);

        ResellerBranding::withoutGlobalScopes()->create([
            'reseller_account_id' => $reseller->id,
            'product_name' => 'Acme CRM',
            'primary_color' => '#1976D2',
        ]);

        $client = Account::withoutGlobalScopes()->create([
            'account_type' => Account::TYPE_CLIENT,
            'parent_account_id' => $reseller->id,
            'name' => 'Acme Client',
            'status' => 'active',
        ]);

        $clientOwner = User::withoutGlobalScopes()->create([
            'name' => 'Client Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
            'account_id' => $client->id,
            'role' => 'owner',
        ]);

        $response = $this->actingAs($clientOwner)
            ->getJson('http://acme.test/api/branding');

        $response->assertOk();
        $response->assertJsonPath('product_name', 'Acme CRM');
    }

    public function test_branding_falls_back_to_default_for_an_unrecognized_domain(): void
    {
        $response = $this->getJson('http://unknown.test/api/branding');

        $response->assertOk();
        $response->assertJsonPath('product_name', 'CRM Platform');
    }
}
