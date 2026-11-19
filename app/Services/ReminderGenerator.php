<?php

namespace App\Services;

use App\Jobs\SendEmailReminder;
use App\Jobs\SendWhatsAppReminder;
use App\Models\Charge;
use App\Models\EmailReminderOutbox;
use App\Models\MessageOutbox;
use App\Models\ReminderRule;
use Carbon\CarbonImmutable;

final class ReminderGenerator
{
    public function generate(?int $tenantId = null): int
    {
        $count = 0;
        $today = CarbonImmutable::today('America/Sao_Paulo');
        ReminderRule::query()->where('enabled', true)->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->each(function (ReminderRule $rule) use (&$count, $today) {
            $dueDate = $today->subDays($rule->days_offset)->toDateString();
            $already = MessageOutbox::where('tenant_id', $rule->tenant_id)->where('reminder_rule_id', $rule->id)->whereDate('scheduled_at', $today)->count();
            $remaining = max(0, $rule->daily_limit - $already);
            if (! $remaining) {
                return;
            }
            Charge::where('tenant_id', $rule->tenant_id)->whereDate('due_date', $dueDate)->where('status', 'open')->with(['customer', 'payments'])->limit($remaining)->get()->each(function (Charge $charge) use ($rule, $today, &$count) {
                if ($charge->balance_cents <= 0 || ! $charge->customer->contact_opt_in) {
                    return;
                }$scheduled = $today->setTimeFromTimeString($rule->send_time);
                $stage = 'offset:'.$rule->days_offset;
                $outbox = MessageOutbox::firstOrCreate(['tenant_id' => $rule->tenant_id, 'charge_id' => $charge->id, 'stage' => $stage, 'scheduled_at' => $scheduled], ['reminder_rule_id' => $rule->id, 'status' => 'pending']);
                if ($outbox->wasRecentlyCreated) {
                    SendWhatsAppReminder::dispatch($outbox->id)->onQueue('notifications')->delay($scheduled);
                    $count++;
                }
                if ($charge->customer->email) {
                    $email = EmailReminderOutbox::firstOrCreate(['tenant_id' => $rule->tenant_id, 'charge_id' => $charge->id, 'reminder_rule_id' => $rule->id, 'scheduled_at' => $scheduled], ['recipient' => $charge->customer->email, 'subject' => $this->emailSubject($rule->days_offset), 'message' => $this->renderEmail($rule->template, $charge), 'status' => 'pending']);
                    if ($email->wasRecentlyCreated) {
                        SendEmailReminder::dispatch($email->id)->onQueue('notifications')->delay($scheduled);
                    }
                }
            });
        });

        return $count;
    }

    private function emailSubject(int $offset): string
    {
        return $offset < 0 ? 'Lembrete de vencimento' : ($offset === 0 ? 'Sua mensalidade vence hoje' : 'Lembrete de mensalidade em aberto');
    }

    private function renderEmail(string $template, Charge $charge): string
    {
        return strtr($template, ['{responsavel}' => $charge->customer->name, '{empresa}' => 'Instituto Horizonte', '{descricao}' => $charge->description, '{competencia}' => $charge->competence->format('m/Y'), '{vencimento}' => $charge->due_date->format('d/m/Y'), '{saldo}' => 'R$ '.number_format($charge->balance_cents / 100, 2, ',', '.'), '{dias_atraso}' => (string) max(0, $charge->due_date->diffInDays(today(), false))]);
    }
}
