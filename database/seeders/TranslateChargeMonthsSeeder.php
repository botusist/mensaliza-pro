<?php

namespace Database\Seeders;

use App\Models\Charge;
use Illuminate\Database\Seeder;

class TranslateChargeMonthsSeeder extends Seeder
{
    public function run(): void
    {
        $englishMonths = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        Charge::query()->where(function ($query) use ($englishMonths) {
            foreach ($englishMonths as $month) {
                $query->orWhere('description', 'like', "Mensalidade · {$month}/%");
            }
        })->each(function (Charge $charge) {
            $charge->update(['description' => 'Mensalidade · '.$charge->competence->locale('pt_BR')->translatedFormat('F/Y')]);
        });
    }
}
