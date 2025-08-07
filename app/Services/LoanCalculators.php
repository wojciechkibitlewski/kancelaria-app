<?php

namespace App\Services;

use App\Models\Lead;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LoanCalculators
{
    ///////////////////
    // 
    // Kalkulator GET
    //
    ///////////////////

    public static function calculatorGet(Lead $lead): array
    {
        return match ($lead->loan_installments) {
            'stale' => self::calculateFixedInstallments($lead),
            // 'malejace' => self::calculateDecreasingInstallments($lead),
            default => ['error' => 'Nieobsługiwany typ rat: ' . $lead->loan_installments],
        };
    }

    // raty stałe
    private static function calculateFixedInstallments(Lead $lead): array
    {
            $results = [];

            $startDate = Carbon::parse($lead->loan_date)->addMonth();
            $endDate = $lead->loan_repayment_date 
                ? Carbon::parse($lead->loan_repayment_date) 
                : $startDate->copy()->addMonths($lead->loan_term_month - 1);
            
                // Salda początkowe
            $saldoPLN = $lead->loan_amount;
            $saldoCHF = $lead->loan_amount_currency;
            $saldoNaleznePLN = $lead->loan_amount;
            
            $currentDate = $startDate->copy();
            $ratNumber = 1;

            while ($currentDate->lte($endDate)) {
                
                $effectiveDate = self::adjustWeekend($currentDate);

                // 1. Notowanie waluty
                $rate = self::getCurrencyRate($effectiveDate, $lead->loan_currency);

                $results[] = [
                    'test' => 'test'
                ];

            }           

            return $results;

    }
    
    //raty malejące
    // private static function calculateDecreasingInstallments(Lead $lead): array
    // {
        
    // }

    // korekta weekendów i dni wolnych
     private static function adjustWeekend(Carbon $date): Carbon
    {
        return match ($date->dayOfWeek) {
            Carbon::SATURDAY => $date->copy()->addDays(2),
            Carbon::SUNDAY => $date->copy()->addDay(),
            default => $date->copy()
        };
        
    }

    // pobieranie kursu waluty
    private static function getCurrencyRate(Carbon $date, string $currency): float
    {
        return DB::table('currency_rates')
        ->where('currency', $currency)
        ->where('date', '<=', $date->toDateString())
        ->orderByDesc('date')
        ->value('value') ?? throw new \Exception("Brak kursu waluty $currency dla $date");
        
    }

    // pobieranie stawki indeksu
    private static function getIndexValue(string $indexName, Carbon $date): float
    {
        $monthDate = $date->copy()->startOfMonth()->toDateString();

        return DB::table('loan_indexes')
            ->where('index_name', $indexName)
            ->where('date', '<=', $monthDate)
            ->orderByDesc('date')
            ->value('value') ?? throw new \Exception("Brak stawki indeksu $indexName dla $monthDate");
        
    }

     // rata annuitetowa
    private static function calculateMonthlyInstallment(float $P, float $rAnnual, int $n): float
    {
        $r = $rAnnual / 12;
        $numerator = pow(1 + $r, $n) * $r;
        $denominator = pow(1 + $r, $n) - 1;

        return $P * ($numerator / $denominator);
       
    }
}