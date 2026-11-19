<?php

namespace App\Http\Controllers;

use App\Models\Charge;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\MessageOutbox;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\ReminderRule;
use App\Models\User;
use App\Models\WhatsAppIntegration;
use App\Services\AutomaWhatsAppProvider;
use App\Services\DueDateCalculator;
use App\Services\ReminderGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AppController extends Controller
{
    private int $tenant = 1;

    public function dashboard()
    {
        $charges = Charge::where('tenant_id', $this->tenant)->with('payments')->get();
        $received = $charges->sum(fn ($c) => $c->paid_cents);
        $open = $charges->sum(fn ($c) => $c->balance_cents);
        $overdue = $charges->filter(fn ($c) => $c->due_date->isPast())->sum(fn ($c) => $c->balance_cents);

        return view('dashboard', compact('charges', 'received', 'open', 'overdue'));
    }

    public function customers(Request $r)
    {
        $customers = Customer::where('tenant_id', $this->tenant)->when($r->q, fn ($q, $v) => $q->where('name', 'like', "%$v%"))->withCount('contracts')->latest()->paginate(12)->withQueryString();

        return view('customers', compact('customers'));
    }

    public function storeCustomer(Request $r)
    {
        $data = $r->validate(['name' => 'required|max:150', 'document' => 'nullable|max:30', 'email' => 'nullable|email', 'phone' => 'nullable|max:30', 'category' => 'required|max:50']);
        Customer::create($data + ['tenant_id' => $this->tenant]);

        return back()->with('success', 'Cliente cadastrado com sucesso.');
    }

    public function createCustomer()
    {
        return view('customers-form', ['customer' => new Customer]);
    }

    public function showCustomer(Customer $customer)
    {
        $this->assertTenant($customer);
        $customer->load(['contracts.plan', 'charges.payments']);

        return view('customer-show', compact('customer'));
    }

    public function createCustomerAccess(Request $request, Customer $customer)
    {
        $this->assertTenant($customer);
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'min:8', 'confirmed']]);
        $existing = User::where('email', $data['email'])->where('customer_id', '!=', $customer->id)->exists();
        if ($existing) {
            return back()->withErrors(['access' => 'Este e-mail já pertence a outro acesso.']);
        }
        User::updateOrCreate(['customer_id' => $customer->id], ['tenant_id' => $this->tenant, 'name' => $customer->name, 'email' => $data['email'], 'password' => Hash::make($data['password']), 'role' => 'customer']);
        $this->audit('customer.portal_access_saved', $customer);

        return back()->with('success', 'Acesso do portal criado. Envie as credenciais ao cliente por um canal seguro.');
    }

    public function customerAccessForm(Customer $customer)
    {
        $this->assertTenant($customer);
        $access = User::where('customer_id', $customer->id)->first();

        return view('customer-access', compact('customer', 'access'));
    }

    public function editCustomer(Customer $customer)
    {
        $this->assertTenant($customer);

        return view('customers-form', compact('customer'));
    }

    public function updateCustomer(Request $request, Customer $customer)
    {
        $this->assertTenant($customer);
        $data = $request->validate(['name' => ['required', 'max:150'], 'document' => ['nullable', 'max:30'], 'email' => ['nullable', 'email'], 'phone' => ['nullable', 'max:30'], 'category' => ['required', 'max:50'], 'status' => ['required', 'in:active,inactive'], 'contact_opt_in' => ['nullable', 'boolean']]);
        $customer->update($data + ['contact_opt_in' => (bool) ($data['contact_opt_in'] ?? false)]);
        $this->audit('customer.updated', $customer);

        return redirect()->route('customers.show', $customer)->with('success', 'Cliente atualizado.');
    }

    public function destroyCustomer(Customer $customer)
    {
        $this->assertTenant($customer);
        $customer->update(['status' => 'inactive', 'contact_opt_in' => false]);
        $this->audit('customer.deactivated', $customer);

        return redirect()->route('customers.index')->with('success', 'Cliente inativado. O histórico foi preservado.');
    }

    public function charges(Request $r)
    {
        $charges = Charge::where('tenant_id', $this->tenant)->with(['customer', 'payments'])->when($r->status === 'overdue', fn ($q) => $q->whereDate('due_date', '<', today()))->orderBy('due_date')->paginate(15)->withQueryString();

        return view('charges', compact('charges'));
    }

    public function showCharge(Charge $charge)
    {
        abort_unless($charge->tenant_id === $this->tenant, 404);
        $charge->load(['customer', 'contract.plan', 'payments']);

        return view('charge', compact('charge'));
    }

    public function editCharge(Charge $charge)
    {
        $this->assertTenant($charge);
        $charge->load(['contract.customer', 'payments']);

        return view('charges-edit', compact('charge'));
    }

    public function updateCharge(Request $request, Charge $charge)
    {
        $this->assertTenant($charge);
        abort_if($charge->status === 'cancelled', 422, 'Cobrança cancelada não pode ser editada.');
        $data = $request->validate(['description' => ['required', 'max:180'], 'due_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'min:0.01']]);
        $amount = (int) round(((float) $data['amount']) * 100);
        if ($amount < $charge->paid_cents) {
            return back()->withInput()->withErrors(['amount' => 'O valor não pode ser menor que o total já recebido.']);
        }
        $charge->update(['description' => $data['description'], 'due_date' => $data['due_date'], 'amount_cents' => $amount]);
        $this->audit('charge.updated', $charge);

        return redirect()->route('charges.show', $charge)->with('success', 'Cobrança atualizada.');
    }

    public function destroyCharge(Charge $charge)
    {
        $this->assertTenant($charge);
        if ($charge->paid_cents > 0) {
            return back()->withErrors(['charge' => 'Cobrança com recebimentos não pode ser cancelada; faça um estorno auditado.']);
        }
        $charge->update(['status' => 'cancelled']);
        MessageOutbox::where('tenant_id', $this->tenant)->where('charge_id', $charge->id)->where('status', 'pending')->update(['status' => 'cancelled', 'last_error' => 'Cobrança cancelada.']);
        $this->audit('charge.cancelled', $charge);

        return redirect()->route('charges.index')->with('success', 'Cobrança cancelada sem apagar o histórico.');
    }

    public function createCharge()
    {
        $contracts = Contract::where('tenant_id', $this->tenant)->where('status', 'active')->with(['customer', 'plan'])->get();

        return view('charges-create', compact('contracts'));
    }

    public function storeCharge(Request $request, DueDateCalculator $dueDates)
    {
        $data = $request->validate(['contract_id' => ['required', 'integer'], 'competence' => ['required', 'date_format:Y-m'], 'description' => ['required', 'string', 'max:180'], 'amount' => ['required', 'numeric', 'min:0.01'], 'due_date' => ['nullable', 'date']]);
        $contract = Contract::where('tenant_id', $this->tenant)->with('plan')->findOrFail($data['contract_id']);
        $competence = CarbonImmutable::createFromFormat('!Y-m', $data['competence']);
        if (Charge::where('tenant_id', $this->tenant)->where('contract_id', $contract->id)->whereDate('competence', $competence)->exists()) {
            return back()->withInput()->withErrors(['competence' => 'Já existe uma cobrança deste contrato para essa competência.']);
        }
        $charge = Charge::create(['tenant_id' => $this->tenant, 'contract_id' => $contract->id, 'customer_id' => $contract->payer_id, 'competence' => $competence, 'due_date' => $data['due_date'] ?: $dueDates->forMonth($competence->year, $competence->month, $contract->due_day)->toDateString(), 'description' => $data['description'], 'amount_cents' => (int) round(((float) $data['amount']) * 100), 'status' => 'open']);

        return redirect()->route('charges.show', $charge)->with('success', 'Cobrança criada com sucesso.');
    }

    public function receive(Request $r, Charge $charge)
    {
        abort_unless($charge->tenant_id === $this->tenant, 404);
        $data = $r->validate(['amount' => 'required|numeric|min:0.01', 'method' => 'required|in:pix,cash,transfer,card,other', 'notes' => 'nullable|max:500']);
        $cents = (int) round(((float) $data['amount']) * 100);
        if ($cents > $charge->balance_cents) {
            return back()->withErrors(['amount' => 'O valor não pode superar o saldo atual.']);
        }DB::transaction(function () use ($charge, $data, $cents) {
            Payment::create(['tenant_id' => $this->tenant, 'charge_id' => $charge->id, 'amount_cents' => $cents, 'method' => $data['method'], 'status' => 'confirmed', 'confirmed_at' => now(), 'notes' => $data['notes'] ?? null]);
            DB::table('audit_logs')->insert(['tenant_id' => $this->tenant, 'action' => 'payment.confirmed', 'subject_type' => Charge::class, 'subject_id' => $charge->id, 'metadata' => json_encode(['amount_cents' => $cents]), 'created_at' => now()]);
        });

        return back()->with('success', 'Recebimento registrado e auditado.');
    }

    public function contracts()
    {
        $contracts = Contract::where('tenant_id', $this->tenant)->with(['customer', 'plan'])->paginate(15);

        return view('contracts', compact('contracts'));
    }

    public function showContract(Contract $contract)
    {
        $this->assertTenant($contract);
        $contract->load(['customer', 'plan', 'charges.payments']);

        return view('contract-show', compact('contract'));
    }

    public function editContract(Contract $contract)
    {
        $this->assertTenant($contract);
        $customers = Customer::where('tenant_id', $this->tenant)->orderBy('name')->get();
        $plans = Plan::where('tenant_id', $this->tenant)->where('active', true)->orderBy('name')->get();

        return view('contracts-edit', compact('contract', 'customers', 'plans'));
    }

    public function updateContract(Request $request, Contract $contract)
    {
        $this->assertTenant($contract);
        $data = $request->validate(['customer_id' => ['required', 'integer'], 'payer_id' => ['required', 'integer'], 'plan_id' => ['required', 'integer'], 'due_day' => ['required', 'integer', 'between:1,31'], 'status' => ['required', 'in:active,paused,closed,cancelled']]);
        Customer::where('tenant_id', $this->tenant)->findOrFail($data['customer_id']);
        Customer::where('tenant_id', $this->tenant)->findOrFail($data['payer_id']);
        Plan::where('tenant_id', $this->tenant)->findOrFail($data['plan_id']);
        $contract->update($data);
        $this->audit('contract.updated', $contract);

        return redirect()->route('contracts.show', $contract)->with('success', 'Contrato atualizado. Cobranças emitidas não foram alteradas.');
    }

    public function destroyContract(Contract $contract)
    {
        $this->assertTenant($contract);
        $contract->update(['status' => 'cancelled']);
        $this->audit('contract.cancelled', $contract);

        return redirect()->route('contracts.index')->with('success', 'Contrato cancelado. As cobranças já emitidas foram preservadas.');
    }

    public function createContract()
    {
        $customers = Customer::where('tenant_id', $this->tenant)->where('status', 'active')->orderBy('name')->get();
        $plans = Plan::where('tenant_id', $this->tenant)->where('active', true)->orderBy('name')->get();

        return view('contracts-create', compact('customers', 'plans'));
    }

    public function storeContract(Request $request, DueDateCalculator $dueDates)
    {
        $data = $request->validate(['customer_id' => ['required', 'integer'], 'payer_id' => ['required', 'integer'], 'plan_id' => ['required', 'integer'], 'starts_at' => ['required', 'date'], 'due_day' => ['required', 'integer', 'between:1,31'], 'first_competence' => ['required', 'date_format:Y-m'], 'generate_first_charge' => ['nullable', 'boolean']]);
        $customer = Customer::where('tenant_id', $this->tenant)->findOrFail($data['customer_id']);
        $payer = Customer::where('tenant_id', $this->tenant)->findOrFail($data['payer_id']);
        $plan = Plan::where('tenant_id', $this->tenant)->where('active', true)->findOrFail($data['plan_id']);
        DB::transaction(function () use ($data, $customer, $payer, $plan, $dueDates) {
            $contract = Contract::create(['tenant_id' => $this->tenant, 'customer_id' => $customer->id, 'payer_id' => $payer->id, 'plan_id' => $plan->id, 'starts_at' => $data['starts_at'], 'due_day' => $data['due_day'], 'status' => 'active']);
            if (! empty($data['generate_first_charge'])) {
                $competence = CarbonImmutable::createFromFormat('!Y-m', $data['first_competence']);
                Charge::create(['tenant_id' => $this->tenant, 'contract_id' => $contract->id, 'customer_id' => $payer->id, 'competence' => $competence, 'due_date' => $dueDates->forMonth($competence->year, $competence->month, (int) $data['due_day'])->toDateString(), 'description' => 'Mensalidade · '.$competence->translatedFormat('F/Y'), 'amount_cents' => $plan->amount_cents, 'status' => 'open']);
            }
        });

        return redirect()->route('contracts.index')->with('success', 'Contrato criado com sucesso.');
    }

    public function automations()
    {
        $defaults = [-5 => 'Lembrete antecipado', -2 => 'Vencimento próximo', 0 => 'Vence hoje', 3 => 'Primeiro aviso de atraso', 7 => 'Segundo aviso de atraso', 15 => 'Acompanhamento de atraso'];
        foreach ($defaults as $offset => $name) {
            ReminderRule::firstOrCreate(['tenant_id' => $this->tenant, 'days_offset' => $offset], ['name' => $name, 'send_time' => '09:00', 'daily_limit' => 100, 'enabled' => false, 'template' => $this->defaultReminderTemplate($offset)]);
        }
        $rules = ReminderRule::where('tenant_id', $this->tenant)->orderBy('days_offset')->get();
        $recent = MessageOutbox::where('tenant_id', $this->tenant)->with('charge.customer')->latest()->limit(15)->get();
        $stats = MessageOutbox::where('tenant_id', $this->tenant)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('automations', compact('rules', 'recent', 'stats'));
    }

    public function saveAutomations(Request $request)
    {
        $data = $request->validate(['rules' => ['required', 'array'], 'rules.*.send_time' => ['required', 'date_format:H:i'], 'rules.*.daily_limit' => ['required', 'integer', 'between:1,1000'], 'rules.*.template' => ['required', 'string', 'max:1500'], 'rules.*.enabled' => ['nullable', 'boolean']]);
        foreach ($data['rules'] as $id => $values) {
            ReminderRule::where('tenant_id', $this->tenant)->whereKey($id)->update(['send_time' => $values['send_time'], 'daily_limit' => $values['daily_limit'], 'template' => $values['template'], 'enabled' => (bool) ($values['enabled'] ?? false)]);
        }

        return back()->with('success', 'Régua de lembretes atualizada.');
    }

    public function generateReminders(ReminderGenerator $generator)
    {
        $count = $generator->generate($this->tenant);

        return back()->with('success', "{$count} nova(s) ocorrência(s) agendada(s), sem duplicar lembretes existentes.");
    }

    private function defaultReminderTemplate(int $offset): string
    {
        if ($offset < 0) {
            return 'Olá, {responsavel}. Lembramos que {descricao}, no valor de {saldo}, vence em {vencimento}. Em caso de dúvida, fale com {empresa}.';
        }
        if ($offset === 0) {
            return 'Olá, {responsavel}. A cobrança {descricao}, no valor de {saldo}, vence hoje ({vencimento}). Se já pagou, desconsidere.';
        }

        return 'Olá, {responsavel}. Identificamos saldo de {saldo} referente a {descricao}, vencido em {vencimento}. Se já regularizou, desconsidere; caso precise, fale conosco.';
    }

    private function assertTenant($model): void
    {
        abort_unless((int) $model->tenant_id === $this->tenant, 404);
    }

    private function audit(string $action, $model): void
    {
        DB::table('audit_logs')->insert(['tenant_id' => $this->tenant, 'action' => $action, 'subject_type' => $model::class, 'subject_id' => $model->id, 'metadata' => json_encode(['changes' => $model->getChanges()]), 'created_at' => now()]);
    }

    public function reports()
    {
        return view('placeholder', ['title' => 'Relatórios', 'description' => 'Visões por competência, vencimento e recebimento, sempre limitadas à empresa ativa.', 'kind' => 'reports']);
    }

    public function settings()
    {
        $integration = WhatsAppIntegration::firstOrNew(['tenant_id' => $this->tenant], ['base_url' => 'https://automa.botusist.net.br', 'enabled' => false]);

        return view('settings', compact('integration'));
    }

    public function saveWhatsApp(Request $request)
    {
        $data = $request->validate(['account' => ['required', 'string', 'max:190'], 'secret' => ['nullable', 'string', 'max:1000'], 'enabled' => ['nullable', 'boolean']]);
        $integration = WhatsAppIntegration::firstOrNew(['tenant_id' => $this->tenant]);
        $integration->base_url = 'https://automa.botusist.net.br';
        $integration->provider = 'automa';
        $integration->account = $data['account'];
        $integration->enabled = (bool) ($data['enabled'] ?? false);
        if (! empty($data['secret'])) {
            $integration->secret = $data['secret'];
        }
        if (! $integration->secret) {
            return back()->withInput()->withErrors(['secret' => 'Informe o segredo da API na primeira configuração.']);
        }
        $integration->save();

        return back()->with('success', 'Configuração do Automa salva. Nenhuma mensagem foi enviada.');
    }

    public function testWhatsApp(Request $request)
    {
        $data = $request->validate(['recipient' => ['required', 'regex:/^\+?[0-9]{10,15}$/'], 'message' => ['required', 'string', 'max:1000']]);
        $integration = WhatsAppIntegration::where('tenant_id', $this->tenant)->firstOrFail();
        $result = (new AutomaWhatsAppProvider($integration))->sendText($data['recipient'], $data['message']);
        $integration->update(['last_tested_at' => now(), 'last_status' => $result->status, 'last_error' => $result->error]);
        DB::table('audit_logs')->insert(['tenant_id' => $this->tenant, 'action' => 'whatsapp.test', 'subject_type' => WhatsAppIntegration::class, 'subject_id' => $integration->id, 'metadata' => json_encode(['recipient_tail' => substr($data['recipient'], -4), 'status' => $result->status, 'http_status' => $result->httpStatus]), 'created_at' => now()]);

        if ($result->accepted()) {
            return back()->with('success', 'O Automa aceitou a requisição (HTTP 200). A API fornecida não permite confirmar entrega ou leitura.');
        }

        return back()->withErrors(['test' => $result->error ?? 'Não foi possível concluir o teste.']);
    }
}
