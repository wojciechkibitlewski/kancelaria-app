<?php
// php artisan currency:fetch-chf-2002
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\CurrencyRate;
use Carbon\Carbon;

class FetchChfRates2002 extends Command
{
    protected $signature = 'currency:fetch-chf-2002';
    protected $description = 'Pobiera kurs CHF z 2002 roku i zapisuje do bazy danych';

    public function handle()
    {
        $this->info('Pobieranie danych CHF z 2002 roku...');

        $startDate = Carbon::create('2011-01-02');
        $endDate = Carbon::create('2011-12-31');

        $current = $startDate->copy();

        while ($current->lessThanOrEqualTo($endDate)) {        
            $chunkStart = $current->copy()->startOfMonth()->toDateString();
            $chunkEnd = $current->copy()->endOfMonth()->min($endDate)->toDateString();

            $url = "https://api.nbp.pl/api/exchangerates/rates/A/CHF/{$chunkStart}/{$chunkEnd}/?format=json";

            $this->line("Pobieranie danych dla okresu: $chunkStart → $chunkEnd");

            try {
                $response = Http::timeout(10)->get($url);

                if ($response->failed()) {
                    $this->warn("Błąd: $chunkStart → $chunkEnd");
                    $current->addMonth();
                    continue;
                }

                $data = $response->json();
                $counter = 0;

                foreach ($data['rates'] as $rate) {
                    CurrencyRate::updateOrCreate(
                        [
                            'effective_date' => $rate['effectiveDate'],
                            'currency' => 'CHF',
                        ],
                        [
                            'value' => $rate['mid'],
                        ]
                    );
                    $counter++;
                }

                $this->line("✔️  Zapisano $counter rekordów ($chunkStart → $chunkEnd)");

            } catch (\Throwable $e) {
                $this->error("❌ Wyjątek: " . $e->getMessage());
            }

            $current->addMonth();
            sleep(1);
        }
    }
}