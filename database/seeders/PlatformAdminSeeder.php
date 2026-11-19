<?php

namespace Database\Seeders;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class PlatformAdminSeeder extends Seeder
{
    public function run(): void
    {
        $plan = PlatformPlan::firstOrCreate(['name' => 'Profissional'], ['price_cents' => 14990, 'customer_limit' => 1000, 'user_limit' => 10, 'active' => true]);
        Tenant::whereKey(1)->update(['platform_plan_id' => $plan->id, 'contact_email' => 'contato@institutohorizonte.test']);
        User::where('email', 'admin@mensaliza.test')->update(['role' => 'superadmin']);
    }
}
