<?php

namespace App\Services;

use App\Models\Lead;

class LoanCalculators
{
    public static function calculatorGet(Lead $lead): array
    {
        // Dla przykładu: proste obliczenie na bazie danych z rekordu
        $amount = $lead->loan_amount;
        $margin = $lead->bank_margin;
        $months = $lead->loan_term_month;

        // Przykład prostego obliczenia raty miesięcznej
        $baseRate = 0.02; // przykładowy LIBOR 3M = 2%
        $totalRate = $baseRate + $margin;

        // Procent miesięczny
        $monthlyRate = $totalRate / 12;

        // Rata równa (stała)
        $rata = ($amount * $monthlyRate) / (1 - pow(1 + $monthlyRate, -$months));
        $rata = round($rata, 2);

        // Przykładowe zwracane wartości
        return [
            'Typ kalkulatora' => 'GET',
            'WartoscWplaconychRat' => round($rata * 100, 2), // fikcyjna wartość
            'WartoscRatDoZaplaty' => round($rata * ($months - 100), 2),
            'RataMiesieczna' => $rata,
            'OprocentowanieRoczne' => round($totalRate * 100, 2) . '%',
        ];
    }

    // Kolejne metody: calculatorWal, calculatorKzo, itd...
}