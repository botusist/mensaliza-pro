<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppReminder;
use App\Models\Charge;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\MessageOutbox;
use App\Models\Plan;
use App\Models\ReminderRule;
use App\Models\Tenant;
use App\Services\ReminderGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReminderAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_generations_create_one_occurrence(): void
    {
        Queue::fake();
        [$charge,$rule] = $this->fixtures();
        $generator = app(ReminderGenerator::class);
        $this->assertSame(1, $generator->generate(1));
        $this->assertSame(0, $generator->generate(1));
        $this->assertDatabaseCount('message_outbox', 1);
        Queue::assertPushed(SendWhatsAppReminder::class, 1);
    }

    public function test_payment_before_send_cancels_pending_message(): void
    {
        [$charge,$rule] = $this->fixtures();
        $charge->payments()->create(['tenant_id' => 1, 'amount_cents' => 10000, 'method' => 'pix', 'status' => 'confirmed', 'confirmed_at' => now()]);
        $outbox = MessageOutbox::create(['tenant_id' => 1, 'charge_id' => $charge->id, 'reminder_rule_id' => $rule->id, 'stage' => 'offset:0', 'scheduled_at' => now(), 'status' => 'pending']);
        (new SendWhatsAppReminder($outbox->id))->handle();
        $this->assertSame('cancelled', $outbox->fresh()->status);
    }

    private function fixtures(): array
    {
        $tenant = Tenant::create(['name' => 'Empresa']);
        $customer = Customer::create(['tenant_id' => 1, 'name' => 'Cliente', 'phone' => '5511999999999', 'contact_opt_in' => true]);
        $plan = Plan::create(['tenant_id' => 1, 'name' => 'Plano', 'amount_cents' => 10000]);
        $contract = Contract::create(['tenant_id' => 1, 'customer_id' => $customer->id, 'payer_id' => $customer->id, 'plan_id' => $plan->id, 'starts_at' => today(), 'due_day' => today()->day]);
        $charge = Charge::create(['tenant_id' => 1, 'contract_id' => $contract->id, 'customer_id' => $customer->id, 'competence' => today()->startOfMonth(), 'due_date' => today(), 'description' => 'Mensalidade', 'amount_cents' => 10000, 'status' => 'open']);
        $rule = ReminderRule::create(['tenant_id' => 1, 'name' => 'Vence hoje', 'days_offset' => 0, 'send_time' => now()->format('H:i'), 'template' => 'Olá {responsavel}, saldo {saldo}', 'enabled' => true, 'daily_limit' => 100]);

        return [$charge, $rule];
    }
}
