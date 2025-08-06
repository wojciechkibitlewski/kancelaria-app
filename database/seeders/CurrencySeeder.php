<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Currency;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencies = [
            'CHF',
            'USD',
            'EUR',
            'GBP',
            'JPY'
        ];

        foreach ($currencies as $code) {
            Currency::updateOrCreate(
                ['currency' => $code]
            );
        }
    }
}
