<?php
// php artisan currency:fetch-chf
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\CurrencyRate;
use Carbon\Carbon;

class FetchChfRates extends Command
{
    protected $signature = 'currency:fetch-chf';
    protected $description = 'Pobiera kurs CHF od konkretnej daty do dziś i zapisuje do bazy danych';

    public function handle()
    {
        $this->info('📥 Start pobierania danych CHF...');

        $startYear = 2017; // <- TUTAJ USTAW STARTOWY ROK
        $currentYear = Carbon::now()->year;

        for ($year = $startYear; $year <= $currentYear; $year++) {
            $this->line("📅 Rok: $year");

            $startDate = Carbon::create($year, 1, 1);
            $endDate = Carbon::create($year, 12, 31)->min(Carbon::now());

            $current = $startDate->copy();

            while ($current->lessThanOrEqualTo($endDate)) {
                $chunkStart = $current->copy()->startOfMonth()->toDateString();
                $chunkEnd = $current->copy()->endOfMonth()->min($endDate)->toDateString();

                $url = "https://api.nbp.pl/api/exchangerates/rates/A/CHF/{$chunkStart}/{$chunkEnd}/?format=json";

                $this->line("🔄 Pobieranie: $chunkStart → $chunkEnd");

                try {
                    $response = Http::timeout(10)->get($url);

                    if ($response->failed()) {
                        $this->warn("⚠️ Błąd: $chunkStart → $chunkEnd");
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
                sleep(1); // oddech między miesiącami
            }

            $this->line("✅ Zakończono rok $year — czekam chwilę...");
            sleep(2); // oddech między latami
        }

        $this->info('🎉 Gotowe!');
    }
}