<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\WhatsAppIntegration;
use App\Services\AutomaWhatsAppProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutomaWhatsAppProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_documented_form_fields_and_does_not_claim_delivery(): void
    {
        Http::fake(['automa.botusist.net.br/*' => Http::response(['success' => true], 200)]);
        $provider = new AutomaWhatsAppProvider($this->integration());
        $result = $provider->sendText('5511999999999', 'Olá');
        $this->assertSame('accepted_unconfirmed', $result->status);
        Http::assertSent(fn ($r) => $r->isForm() && $r['account'] === 'conta-1' && $r['recipient'] === '5511999999999' && $r['type'] === 'text' && $r['message'] === 'Olá' && $r['secret'] === 'segredo');
    }

    public function test_http_200_with_failure_body_is_failed(): void
    {
        Http::fake(['automa.botusist.net.br/*' => Http::response(['success' => false, 'error' => 'invalid account'], 200)]);
        $result = (new AutomaWhatsAppProvider($this->integration()))->sendText('5511999999999', 'Teste');
        $this->assertSame('failed', $result->status);
    }

    private function integration(): WhatsAppIntegration
    {
        $tenant = Tenant::create(['name' => 'Empresa']);

        return WhatsAppIntegration::create(['tenant_id' => $tenant->id, 'base_url' => 'https://automa.botusist.net.br', 'secret' => 'segredo', 'account' => 'conta-1', 'enabled' => true]);
    }
}
