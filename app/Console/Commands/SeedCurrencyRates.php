<?php
// Uruchamianie: php artisan currency:seed
// Zwróć uwagę na daty - pobieramy partiami

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Carbon\Carbon;

class SeedCurrencyRates extends Command
{
    protected $signature = 'currency:seed';
    protected $description = 'Pobiera archiwalne kursy walut z NBP i zapisuje do bazy';

    public function handle()
{
    $this->info('Pobieranie archiwalnych danych z NBP...');

    $table = 'A';
    $startDate = Carbon::create('2002-01-02');
    $endDate = Carbon::now();
    $currencies = Currency::pluck('currency')->toArray();

    foreach ($currencies as $code) {
        $this->line("Pobieranie: $code");

        $current = $startDate->copy();
        while ($current->lessThanOrEqualTo($endDate)) {
            $chunkStart = $current->toDateString();
            $chunkEnd = $current->copy()->addDays(92)->min($endDate)->toDateString();

            $url = "https://api.nbp.pl/api/exchangerates/rates/{$table}/{$code}/{$chunkStart}/{$chunkEnd}/?format=json";

            try {
                //$response = Http::get($url);
                $response = Http::timeout(10)->get($url);

                if ($response->failed()) {
                    $this->warn("Błąd pobierania danych dla: $code od $chunkStart do $chunkEnd");
                    $current->addDays(93);
                    continue;
                }

                $data = $response->json();
                $counter = 0;

                foreach ($data['rates'] as $rate) {
                    CurrencyRate::updateOrCreate(
                        [
                            'effective_date' => $rate['effectiveDate'],
                            'currency' => $code,
                        ],
                        [
                            'value' => $rate['mid'],
                        ]
                    );
                    $counter++;
                }

            } catch (\Throwable $e) {
                $this->error("Wyjątek dla $code od $chunkStart do $chunkEnd: " . $e->getMessage());
            }

            $current->addDays(93);
        }
    }

    $this->line("Dodano $counter rekordów dla $code ($chunkStart → $chunkEnd)");
}
}