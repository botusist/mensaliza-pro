<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_login_and_only_open_own_portal(): void
    {
        $tenant = Tenant::create(['name' => 'Empresa']);
        $customer = Customer::create(['tenant_id' => $tenant->id, 'name' => 'Cliente', 'email' => 'cliente@example.com']);
        $user = User::create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'role' => 'customer', 'name' => 'Cliente', 'email' => 'cliente@example.com', 'password' => Hash::make('senha-segura')]);
        $this->post('/portal/login', ['email' => $user->email, 'password' => 'senha-segura'])->assertRedirect('/portal');
        $this->get('/portal')->assertOk()->assertSee('Cliente');
    }

    public function test_password_recovery_does_not_reveal_unknown_email(): void
    {
        Notification::fake();
        $response = $this->post('/portal/esqueci-senha', ['email' => 'desconhecido@example.com']);
        $response->assertSessionHas('status');
        Notification::assertNothingSent();
    }
}
