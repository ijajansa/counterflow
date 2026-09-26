<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Plan::firstOrCreate(['slug' => 'starter'], [
            'name' => 'Starter', 'monthly_price' => 999,
            'branch_limit' => 1, 'staff_limit' => 5,
            'modules' => ['sales', 'inventory'], 'is_active' => true,
        ]);
        Plan::firstOrCreate(['slug' => 'growth'], [
            'name' => 'Growth', 'monthly_price' => 2499,
            'branch_limit' => 5, 'staff_limit' => 25,
            'modules' => ['sales', 'inventory', 'restaurant', 'clothing'], 'is_active' => true,
        ]);
    }
}
