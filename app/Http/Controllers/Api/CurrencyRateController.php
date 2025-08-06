<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CurrencyRate;

class CurrencyRateController extends Controller
{
    public function getRates(Request $request)
    {
        $data = $request->all();
        $results = [];

        foreach ($data as $entry) {
            $rate = CurrencyRate::where('effective_date', $entry['effective_date'])
                ->where('currency', strtoupper($entry['currency']))
                ->first();

            $results[] = [
                'effective_date' => $entry['effective_date'],
                'currency' => strtoupper($entry['currency']),
                'value' => $rate?->value ?? null
            ];
        }

        return response()->json($results);
    }
}
