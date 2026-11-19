<?php

namespace App\Console\Commands;

use App\Services\ReminderGenerator;
use Illuminate\Console\Command;

class GenerateReminders extends Command
{
    protected $signature = 'reminders:generate {--tenant=}';

    protected $description = 'Gera ocorrências idempotentes de lembretes';

    public function handle(ReminderGenerator $generator): int
    {
        $count = $generator->generate($this->option('tenant') ? (int) $this->option('tenant') : null);
        $this->info("{$count} lembrete(s) agendado(s).");

        return self::SUCCESS;
    }
}
