<?php

namespace Database\Seeders;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
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
        $platformPlan = PlatformPlan::create(['name' => 'Profissional', 'price_cents' => 14990, 'customer_limit' => 1000, 'user_limit' => 10]);
        $tenant = Tenant::create(['name' => 'Instituto Horizonte', 'platform_plan_id' => $platformPlan->id, 'contact_email' => 'contato@institutohorizonte.test']);
        User::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Marina Costa', 'email' => 'admin@mensaliza.test', 'role' => 'superadmin']);
        $plan = Plan::create(['tenant_id' => $tenant->id, 'name' => 'Plano Essencial', 'amount_cents' => 48900, 'periodicity' => 'monthly']);
        $names = ['Ana Clara Ribeiro', 'Bruno Martins', 'Camila Nogueira', 'Daniel Souza', 'Eduarda Lima', 'Felipe Rocha', 'Gabriela Alves', 'Henrique Barros'];
        foreach ($names as $i => $name) {
            $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => $name, 'email' => strtolower(str_replace(' ', '.', iconv('UTF-8', 'ASCII//TRANSLIT', $name))).'@example.com', 'phone' => '(11) 9'.str_pad((string) (81000000 + $i * 137), 8, '0', STR_PAD_LEFT), 'category' => $i % 3 === 0 ? 'Aluno' : 'Cliente']);
            $contract = Contract::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'payer_id' => $customer->id, 'plan_id' => $plan->id, 'starts_at' => now()->subMonths(5), 'due_day' => [5, 10, 15, 20, 31][$i % 5], 'status' => 'active']);
            $charge = Charge::create(['tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'customer_id' => $customer->id, 'competence' => now()->startOfMonth(), 'due_date' => now()->startOfMonth()->addDays(($i % 5) * 5 + 4), 'description' => 'Mensalidade · '.now()->translatedFormat('F/Y'), 'amount_cents' => 48900, 'status' => 'open']);
            if ($i === 0) {
                Payment::create(['tenant_id' => $tenant->id, 'charge_id' => $charge->id, 'amount_cents' => 48900, 'method' => 'pix', 'status' => 'confirmed', 'confirmed_at' => now()->subDays(3)]);
            }
            if ($i === 2) {
                Payment::create(['tenant_id' => $tenant->id, 'charge_id' => $charge->id, 'amount_cents' => 20000, 'method' => 'transfer', 'status' => 'confirmed', 'confirmed_at' => now()->subDay()]);
            }
        }
    }
}
