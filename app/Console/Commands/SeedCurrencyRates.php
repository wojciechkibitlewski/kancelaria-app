<?php
// Uruchamianie: php artisan currency:seed
// Zwróć uwagę na daty - pobieramy partiami

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\CurrencyRate;
use Carbon\Carbon;

class SeedCurrencyRates extends Command
{
    protected $signature = 'currency:seed';
    protected $description = 'Pobiera archiwalne kursy jednej waluty z NBP i zapisuje do bazy';

    public function handle()
    {
        $this->info('Pobieranie archiwalnych danych z NBP...');

        $table = 'A';
        $currency = 'CHF'; // 👈 Wpisz ręcznie walutę tutaj
        $startDate = Carbon::create('2002-01-02');
        $endDate = Carbon::now();

        $this->line("Pobieranie waluty: $currency");

        $current = $startDate->copy();
        while ($current->lessThanOrEqualTo($endDate)) {
            $chunkStart = $current->toDateString();
            $chunkEnd = $current->copy()->addDays(92)->min($endDate)->toDateString();

            $url = "https://api.nbp.pl/api/exchangerates/rates/{$table}/{$currency}/{$chunkStart}/{$chunkEnd}/";

            try {
                $response = Http::timeout(10)->get($url);

                if ($response->failed()) {
                    $this->warn("Błąd pobierania danych dla: $currency od $chunkStart do $chunkEnd");
                    $current->addDays(93);
                    continue;
                }

                $data = $response->json();
                $counter = 0;

                foreach ($data['rates'] as $rate) {
                    CurrencyRate::updateOrCreate(
                        [
                            'effective_date' => $rate['effectiveDate'],
                            'currency' => $currency,
                        ],
                        [
                            'value' => $rate['mid'],
                        ]
                    );
                    sleep(5);
                    $counter++;
                }

                $this->line("✔️  $currency: $counter rekordów dodanych ($chunkStart → $chunkEnd)");

            } catch (\Throwable $e) {
                $this->error("❌ Wyjątek dla $currency od $chunkStart do $chunkEnd: " . $e->getMessage());
            }

            $current->addDays(93);
            sleep(1); // ⏳ oddech dla NBP
        }

        $this->info('✅ Zakończono pobieranie danych.');
    }
}