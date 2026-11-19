<?php

namespace App\Services;

use App\Contracts\WhatsAppProvider;
use App\Data\WhatsAppResult;
use App\Models\WhatsAppIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class AutomaWhatsAppProvider implements WhatsAppProvider
{
    public function __construct(private readonly WhatsAppIntegration $integration) {}

    public function sendText(string $recipient, string $message): WhatsAppResult
    {
        if (! $this->integration->enabled) {
            return new WhatsAppResult('disabled', 0, error: 'A integração está desativada.');
        }
        try {
            $response = Http::asForm()->acceptJson()->timeout(15)->connectTimeout(5)->post(rtrim($this->integration->base_url, '/').'/api/send/whatsapp', ['secret' => $this->integration->secret, 'account' => $this->integration->account, 'recipient' => $recipient, 'type' => 'text', 'message' => $message]);
        } catch (ConnectionException $e) {
            return new WhatsAppResult('unknown', 0, error: 'Timeout ou falha de conexão. O envio pode ter ocorrido; não reenvie automaticamente.');
        }
        $body = mb_substr($response->body(), 0, 4000);
        if (! $response->successful()) {
            return new WhatsAppResult('failed', $response->status(), error: "Automa respondeu HTTP {$response->status()}.", rawResponse: $body);
        }
        $json = $response->json();
        if (is_array($json) && (($json['success'] ?? null) === false || ($json['ok'] ?? null) === false || ! empty($json['error']))) {
            return new WhatsAppResult('failed', $response->status(), error: 'O Automa respondeu HTTP 200, mas informou falha no corpo.', rawResponse: $body);
        }

        return new WhatsAppResult('accepted_unconfirmed', $response->status(), rawResponse: $body);
    }
}
