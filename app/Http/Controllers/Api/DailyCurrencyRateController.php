<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use App\Models\Currency;
use App\Models\CurrencyRate;
use Carbon\Carbon;

class DailyCurrencyRateController extends Controller
{
    public function updateTodayRates()
    {
        $today = Carbon::today()->toDateString();
        $table = 'A';

        $currencies = Currency::pluck('currency')->toArray();
        $inserted = [];

        foreach ($currencies as $code) {
            $url = "https://api.nbp.pl/api/exchangerates/rates/{$table}/{$code}/{$today}/?format=json";

            try {
                $response = Http::get($url);

                if ($response->failed()) {
                    continue;
                }

                $data = $response->json();
                $rate = $data['rates'][0];

                CurrencyRate::updateOrCreate(
                    [
                        'effective_date' => $rate['effectiveDate'],
                        'currency' => $code,
                    ],
                    [
                        'value' => $rate['mid'],
                    ]
                );

                $inserted[] = [
                    'currency' => $code,
                    'date' => $rate['effectiveDate'],
                    'value' => $rate['mid']
                ];
            } catch (\Throwable $e) {
                continue;
            }
        }

        return response()->json([
            'success' => true,
            'inserted' => $inserted,
        ]);
    }
}