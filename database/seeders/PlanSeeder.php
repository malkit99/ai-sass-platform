<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(
            ['name' => 'Reseller Starter'],
            ['price' => 49, 'limits' => [
                'max_client_accounts' => 3, 'max_seats' => 5, 'whatsapp_credits' => 500,
                'whatsapp_max_numbers' => 3, 'storage_limit_mb' => 500,
            ]],
        );

        Plan::updateOrCreate(
            ['name' => 'Reseller Pro'],
            ['price' => 199, 'limits' => [
                'max_client_accounts' => 50, 'max_seats' => 50, 'whatsapp_credits' => 2000,
                'whatsapp_max_numbers' => 10, 'storage_limit_mb' => 2000,
            ]],
        );

        Plan::updateOrCreate(
            ['name' => 'Client Trial'],
            // storage_limit_mb: 100 — the default mentioned for every plan;
            // paid tiers scale it up the same way credits/numbers already do.
            ['price' => 0, 'limits' => [
                'max_seats' => 2, 'whatsapp_credits' => 100,
                'whatsapp_max_numbers' => 1, 'storage_limit_mb' => 100,
            ]],
        );
    }
}
