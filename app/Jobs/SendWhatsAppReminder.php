<?php

namespace App\Jobs;

use App\Models\MessageOutbox;
use App\Models\WhatsAppIntegration;
use App\Services\AutomaWhatsAppProvider;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class SendWhatsAppReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 30;

    public function __construct(public readonly int $outboxId) {}

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(): void
    {
        $outbox = MessageOutbox::with(['charge.customer', 'charge.payments', 'rule'])->findOrFail($this->outboxId);
        if ($outbox->status !== 'pending') {
            return;
        }
        $charge = $outbox->charge;
        if (! $charge || $charge->balance_cents <= 0 || $charge->status !== 'open' || ! $charge->customer->contact_opt_in) {
            $outbox->update(['status' => 'cancelled', 'last_error' => 'Cobrança quitada, cancelada ou contato sem consentimento.']);

            return;
        }
        $recipient = preg_replace('/\D+/', '', $charge->customer->phone ?? '');
        if (strlen($recipient) < 10) {
            $outbox->update(['status' => 'failed', 'last_error' => 'Telefone ausente ou inválido.']);

            return;
        }
        $integration = WhatsAppIntegration::where('tenant_id', $outbox->tenant_id)->where('enabled', true)->first();
        if (! $integration) {
            $outbox->update(['status' => 'failed', 'last_error' => 'Integração Automa desativada.']);

            return;
        }
        $message = $this->render($outbox->rule->template, $charge);
        $outbox->update(['status' => 'processing', 'recipient' => $recipient, 'message' => $message, 'attempts' => DB::raw('attempts + 1')]);
        $result = (new AutomaWhatsAppProvider($integration))->sendText($recipient, $message);
        if ($result->status === 'accepted_unconfirmed') {
            $outbox->update(['status' => 'accepted_unconfirmed', 'sent_at' => now(), 'last_error' => 'Requisição aceita pelo endpoint; entrega não confirmada pela API.']);

            return;
        }
        if ($result->status === 'unknown') {
            $outbox->update(['status' => 'unknown', 'last_error' => $result->error]);

            return;
        }
        $outbox->update(['status' => 'failed', 'last_error' => $result->error]);
    }

    private function render(string $template, $charge): string
    {
        $values = ['{responsavel}' => $charge->customer->name, '{empresa}' => 'Instituto Horizonte', '{descricao}' => $charge->description, '{competencia}' => $charge->competence->format('m/Y'), '{vencimento}' => $charge->due_date->format('d/m/Y'), '{saldo}' => 'R$ '.number_format($charge->balance_cents / 100, 2, ',', '.'), '{dias_atraso}' => (string) max(0, $charge->due_date->diffInDays(today(), false))];

        return strtr($template, $values);
    }
}
