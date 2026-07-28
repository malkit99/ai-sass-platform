<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmScopingTest extends TestCase
{
    use RefreshDatabase;

    private function makeClientWithOwner(string $name): array
    {
        $reseller = Account::withoutGlobalScopes()->create([
            'account_type' => Account::TYPE_RESELLER,
            'name' => "$name Reseller",
            'status' => 'active',
        ]);

        $client = Account::withoutGlobalScopes()->create([
            'account_type' => Account::TYPE_CLIENT,
            'parent_account_id' => $reseller->id,
            'name' => "$name Client",
            'status' => 'active',
        ]);

        $owner = User::withoutGlobalScopes()->create([
            'name' => "$name Owner",
            'email' => uniqid()."-$name@example.com",
            'password' => bcrypt('password'),
            'account_id' => $client->id,
            'role' => 'owner',
        ]);

        return [$client, $owner];
    }

    public function test_creating_a_client_account_auto_creates_a_default_pipeline(): void
    {
        [$client] = $this->makeClientWithOwner('Acme');

        $pipeline = \App\Models\Pipeline::withoutGlobalScopes()->where('account_id', $client->id)->first();

        $this->assertNotNull($pipeline);
        $this->assertTrue($pipeline->is_default);
        $this->assertEquals(
            ['New Lead', 'Contacted', 'Qualified', 'Won', 'Lost'],
            $pipeline->stages()->pluck('name')->all(),
        );
    }

    public function test_lead_created_via_api_is_scoped_to_the_creators_account(): void
    {
        [, $owner] = $this->makeClientWithOwner('Acme');

        $response = $this->actingAs($owner)->postJson('/api/leads', ['name' => 'Jane Prospect']);

        $response->assertCreated();
        $this->assertDatabaseHas('leads', ['name' => 'Jane Prospect', 'account_id' => $owner->account_id]);
    }

    public function test_a_client_cannot_see_another_clients_leads(): void
    {
        [, $ownerA] = $this->makeClientWithOwner('Acme');
        [, $ownerB] = $this->makeClientWithOwner('Beta');

        $lead = $this->actingAs($ownerA)->postJson('/api/leads', ['name' => 'Acme Lead'])->json();

        $listResponse = $this->actingAs($ownerB)->getJson('/api/leads');
        $listResponse->assertOk();
        $this->assertCount(0, $listResponse->json());

        $showResponse = $this->actingAs($ownerB)->getJson("/api/leads/{$lead['id']}");
        $showResponse->assertNotFound();
    }

    public function test_moving_a_lead_to_another_stage_updates_it(): void
    {
        [$client, $owner] = $this->makeClientWithOwner('Acme');
        $pipeline = \App\Models\Pipeline::withoutGlobalScopes()->where('account_id', $client->id)->first();
        $qualifiedStageId = $pipeline->stages()->where('name', 'Qualified')->value('id');

        $lead = $this->actingAs($owner)->postJson('/api/leads', ['name' => 'Jane Prospect'])->json();

        $response = $this->actingAs($owner)->patchJson("/api/leads/{$lead['id']}", [
            'stage_id' => $qualifiedStageId,
            'is_hot' => true,
        ]);

        $response->assertOk();
        $response->assertJsonPath('stage_id', $qualifiedStageId);
        $response->assertJsonPath('is_hot', true);
    }
}
