<?php

namespace Tests\Feature;

use App\Models\PlatformPlan;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_create_view_update_and_suspend_company(): void
    {
        $plan = PlatformPlan::create(['name' => 'Profissional', 'price_cents' => 14990, 'active' => true]);
        $create = $this->post('/admin/empresas', ['name' => 'Empresa Teste', 'document' => '123', 'contact_email' => 'contato@empresa.test', 'platform_plan_id' => $plan->id, 'admin_name' => 'Admin Empresa', 'admin_email' => 'admin@empresa.test', 'password' => 'senha-segura', 'password_confirmation' => 'senha-segura']);
        $create->assertRedirect('/admin');
        $company = Tenant::where('name', 'Empresa Teste')->firstOrFail();
        $this->get('/admin/empresas/'.$company->id)->assertOk()->assertSee('Empresa Teste');
        $this->put('/admin/empresas/'.$company->id, ['name' => 'Empresa Atualizada', 'document' => '456', 'contact_email' => 'novo@empresa.test', 'platform_plan_id' => $plan->id, 'status' => 'active', 'timezone' => 'America/Sao_Paulo'])->assertRedirect('/admin/empresas/'.$company->id);
        $this->assertDatabaseHas('tenants', ['id' => $company->id, 'name' => 'Empresa Atualizada']);
        $this->delete('/admin/empresas/'.$company->id)->assertRedirect('/admin');
        $this->assertDatabaseHas('tenants', ['id' => $company->id, 'status' => 'suspended']);
    }
}
