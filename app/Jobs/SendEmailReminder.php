<?php

namespace App\Jobs;

use App\Models\EmailReminderOutbox;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

final class SendEmailReminder implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public readonly int $outboxId) {}

    public function backoff(): array
    {
        return [60, 300, 1800];
    }

    public function handle(): void
    {
        $outbox = EmailReminderOutbox::with(['charge.customer', 'charge.payments'])->findOrFail($this->outboxId);
        if ($outbox->status !== 'pending') {
            return;
        }$charge = $outbox->charge;
        if (! $charge || $charge->balance_cents <= 0 || $charge->status !== 'open' || ! $charge->customer->contact_opt_in) {
            $outbox->update(['status' => 'cancelled', 'last_error' => 'Cobrança quitada, cancelada ou contato sem consentimento.']);

            return;
        }if (! $charge->customer->email) {
            $outbox->update(['status' => 'failed', 'last_error' => 'Cliente sem e-mail.']);

            return;
        }$outbox->increment('attempts');
        try {
            Mail::raw($outbox->message, function ($mail) use ($outbox) {
                $mail->to($outbox->recipient)->subject($outbox->subject);
            });
            $outbox->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
        } catch (\Throwable $e) {
            $outbox->update(['status' => 'failed', 'last_error' => 'Falha no transporte SMTP.']);
            throw $e;
        }
    }
}
