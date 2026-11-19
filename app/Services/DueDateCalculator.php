<?php

namespace App\Services;

use Carbon\CarbonImmutable;

final class DueDateCalculator
{
    public function forMonth(int $year, int $month, int $contractedDay): CarbonImmutable
    {
        $last = CarbonImmutable::create($year, $month, 1)->endOfMonth()->day;

        return CarbonImmutable::create($year, $month, min($contractedDay, $last));
    }
}
