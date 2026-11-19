<?php

namespace Tests\Feature;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Tenant;
use App\Services\DueDateCalculator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancialDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_day_31_returns_after_short_month(): void
    {
        $calc = new DueDateCalculator;
        $this->assertSame('2027-02-28', $calc->forMonth(2027, 2, 31)->toDateString());
        $this->assertSame('2027-03-31', $calc->forMonth(2027, 3, 31)->toDateString());
    }

    public function test_competence_is_unique_per_tenant_and_contract(): void
    {
        [$tenant,$contract,$customer] = $this->fixtures();
        $data = ['tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'customer_id' => $customer->id, 'competence' => '2026-11-01', 'due_date' => '2026-11-10', 'description' => 'Mensalidade', 'amount_cents' => 10000];
        Charge::create($data);
        $this->expectException(UniqueConstraintViolationException::class);
        Charge::create($data);
    }

    public function test_partial_payment_keeps_correct_overdue_balance(): void
    {
        [$tenant,$contract,$customer] = $this->fixtures();
        $charge = Charge::create(['tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'customer_id' => $customer->id, 'competence' => '2026-10-01', 'due_date' => '2026-10-10', 'description' => 'Mensalidade', 'amount_cents' => 10000]);
        $charge->payments()->create(['tenant_id' => $tenant->id, 'amount_cents' => 3500, 'method' => 'pix', 'status' => 'confirmed', 'confirmed_at' => now()]);
        $this->assertSame(6500, $charge->balance_cents);
        $this->assertSame('Parcial e vencido', $charge->display_status);
    }

    public function test_charge_from_another_tenant_is_not_accessible(): void
    {
        [$tenant,$contract,$customer] = $this->fixtures();
        $charge = Charge::create(['tenant_id' => $tenant->id, 'contract_id' => $contract->id, 'customer_id' => $customer->id, 'competence' => '2026-11-01', 'due_date' => '2026-11-10', 'description' => 'Mensalidade', 'amount_cents' => 10000]);
        $this->assertSame(404, $this->get('/cobrancas/'.$charge->id)->status());
    }

    public function test_contract_form_creates_contract_and_first_charge(): void
    {
        Tenant::create(['name' => 'Instituto Horizonte']);
        $customer = Customer::create(['tenant_id' => 1, 'name' => 'Cliente A']);
        $plan = Plan::create(['tenant_id' => 1, 'name' => 'Plano A', 'amount_cents' => 15990]);

        $response = $this->post('/contratos', ['customer_id' => $customer->id, 'payer_id' => $customer->id, 'plan_id' => $plan->id, 'starts_at' => '2026-11-01', 'due_day' => 31, 'first_competence' => '2027-02', 'generate_first_charge' => 1]);

        $response->assertRedirect('/contratos');
        $this->assertDatabaseHas('contracts', ['tenant_id' => 1, 'customer_id' => $customer->id]);
        $charge = Charge::where('tenant_id', 1)->firstOrFail();
        $this->assertSame('2027-02-28', $charge->due_date->toDateString());
        $this->assertSame(15990, $charge->amount_cents);
    }

    public function test_manual_charge_form_rejects_duplicate_competence(): void
    {
        Tenant::create(['name' => 'Instituto Horizonte']);
        $customer = Customer::create(['tenant_id' => 1, 'name' => 'Cliente A']);
        $plan = Plan::create(['tenant_id' => 1, 'name' => 'Plano A', 'amount_cents' => 10000]);
        $contract = Contract::create(['tenant_id' => 1, 'customer_id' => $customer->id, 'payer_id' => $customer->id, 'plan_id' => $plan->id, 'starts_at' => '2026-01-01', 'due_day' => 10]);
        Charge::create(['tenant_id' => 1, 'contract_id' => $contract->id, 'customer_id' => $customer->id, 'competence' => '2026-11-01', 'due_date' => '2026-11-10', 'description' => 'Mensalidade', 'amount_cents' => 10000]);

        $response = $this->from('/cobrancas/nova')->post('/cobrancas', ['contract_id' => $contract->id, 'competence' => '2026-11', 'description' => 'Outra', 'amount' => '100.00']);

        $response->assertRedirect('/cobrancas/nova')->assertSessionHasErrors('competence');
        $this->assertDatabaseCount('charges', 1);
    }

    private function fixtures(): array
    {
        Tenant::firstOrCreate(['id' => 1], ['name' => 'Instituto Horizonte']);
        $tenant = Tenant::create(['name' => 'Outra Empresa']);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Cliente B']);
        $plan = Plan::create(['tenant_id' => $tenant->id, 'name' => 'Plano', 'amount_cents' => 10000]);
        $contract = Contract::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'payer_id' => $customer->id, 'plan_id' => $plan->id, 'starts_at' => '2026-01-01', 'due_day' => 10]);

        return [$tenant, $contract, $customer];
    }
}
