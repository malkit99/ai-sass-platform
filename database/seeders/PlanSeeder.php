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
            ['price' => 49, 'limits' => ['max_client_accounts' => 3, 'max_seats' => 5]],
        );

        Plan::updateOrCreate(
            ['name' => 'Reseller Pro'],
            ['price' => 199, 'limits' => ['max_client_accounts' => 50, 'max_seats' => 50]],
        );

        Plan::updateOrCreate(
            ['name' => 'Client Trial'],
            ['price' => 0, 'limits' => ['max_seats' => 2]],
        );
    }
}
