<?php

namespace App\Http\Controllers;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SuperAdminController extends Controller
{
    public function index()
    {
        $companies = Tenant::with('platformPlan')->withCount(['users', 'customers'])->latest()->get();
        $plans = PlatformPlan::withCount('tenants')->orderBy('price_cents')->get();

        return view('admin.index', compact('companies', 'plans'));
    }

    public function createCompany()
    {
        $plans = PlatformPlan::where('active', true)->orderBy('price_cents')->get();

        return view('admin.company-create', compact('plans'));
    }

    public function storeCompany(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'max:150'], 'document' => ['nullable', 'max:30'], 'contact_email' => ['required', 'email'], 'platform_plan_id' => ['nullable', 'exists:platform_plans,id'], 'admin_name' => ['required', 'max:150'], 'admin_email' => ['required', 'email', 'unique:users,email'], 'password' => ['required', 'min:8', 'confirmed']]);
        $tenant = DB::transaction(function () use ($data) {
            $tenant = Tenant::create(['name' => $data['name'], 'document' => $data['document'] ?? null, 'contact_email' => $data['contact_email'], 'platform_plan_id' => $data['platform_plan_id'] ?? null, 'status' => 'active', 'timezone' => 'America/Sao_Paulo']);
            User::create(['tenant_id' => $tenant->id, 'name' => $data['admin_name'], 'email' => $data['admin_email'], 'password' => Hash::make($data['password']), 'role' => 'admin']);

            return $tenant;
        });

        return redirect()->route('admin.index')->with('success', "Empresa {$tenant->name} cadastrada com seu administrador.");
    }

    public function showCompany(Tenant $company)
    {
        $company->load(['platformPlan', 'users'])->loadCount(['customers', 'users']);
        $financial = [
            'contracts' => DB::table('contracts')->where('tenant_id', $company->id)->count(),
            'charges' => DB::table('charges')->where('tenant_id', $company->id)->count(),
            'open_cents' => DB::table('charges')->where('tenant_id', $company->id)->where('status', 'open')->sum('amount_cents'),
        ];

        return view('admin.company-show', compact('company', 'financial'));
    }

    public function editCompany(Tenant $company)
    {
        $plans = PlatformPlan::where('active', true)->orderBy('price_cents')->get();

        return view('admin.company-edit', compact('company', 'plans'));
    }

    public function updateCompany(Request $request, Tenant $company)
    {
        $data = $request->validate([
            'name' => ['required', 'max:150'],
            'document' => ['nullable', 'max:30'],
            'contact_email' => ['required', 'email'],
            'platform_plan_id' => ['nullable', 'exists:platform_plans,id'],
            'status' => ['required', 'in:active,suspended,cancelled'],
            'timezone' => ['required', 'timezone'],
        ]);
        $company->update($data);
        $this->audit($company, 'company.updated');

        return redirect()->route('admin.companies.show', $company)->with('success', 'Empresa atualizada com sucesso.');
    }

    public function destroyCompany(Tenant $company)
    {
        $company->update(['status' => 'suspended']);
        $this->audit($company, 'company.suspended');

        return redirect()->route('admin.index')->with('success', 'Empresa suspensa. Todos os dados foram preservados.');
    }

    public function createPlan()
    {
        return view('admin.plan-create');
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'max:100'], 'price' => ['required', 'numeric', 'min:0'], 'customer_limit' => ['nullable', 'integer', 'min:1'], 'user_limit' => ['nullable', 'integer', 'min:1']]);
        PlatformPlan::create(['name' => $data['name'], 'price_cents' => (int) round(((float) $data['price']) * 100), 'customer_limit' => $data['customer_limit'] ?? null, 'user_limit' => $data['user_limit'] ?? null, 'active' => true]);

        return redirect()->route('admin.index')->with('success', 'Plano comercial criado com sucesso.');
    }

    private function audit(Tenant $company, string $action): void
    {
        DB::table('audit_logs')->insert(['tenant_id' => $company->id, 'action' => $action, 'subject_type' => Tenant::class, 'subject_id' => $company->id, 'metadata' => json_encode(['changes' => $company->getChanges()]), 'created_at' => now()]);
    }
}
