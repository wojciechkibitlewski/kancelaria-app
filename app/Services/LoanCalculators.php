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
    // public static function calculatorGet(Lead $lead): array
    // {
    //     return match ($lead->loan_installments) {
    //         'stale' => self::summarizeInstallments(
    //             self::calculateFixedInstallments($lead),
    //             $lead
    //         ),
            
    //         // 'malejace' => self::summarizeInstallments(
    //         //     self::calculateDecreasingInstallments($lead),
    //         //     $lead
    //         // ),
            
    //         default => [
    //             'error' => 'Nieobsługiwany typ rat: ' . $lead->loan_installments
    //         ],
    //     };
    // }

    //podsumowanie kredytu GET
    private static function summarizeInstallments(array $installments, Lead $lead): array
    {
        $today = Carbon::today();
        $threeYearsLater = $today->copy()->addYears(3);

        $sumaRatyPLN_do_dzisiaj = 0;
        $sumaRatyPLN_po_dzisiaj = 0;
        $sumaRatyPLN_lacznie = 0;
        $sumaRatyPLN_do_3lat = 0;
        $sumaRatyNaleznaPLN_do_dzisiaj = 0;

        foreach ($installments as $rata) {
            $paymentDate = Carbon::parse($rata['paymentDate']);

            $rataPLN = $rata['rataPLN'] ?? 0;
            $rataNaleznaPLN = $rata['rataNaleznaPLN'] ?? 0;

            $sumaRatyPLN_lacznie += $rataPLN;

            if ($paymentDate->lte($today)) {
                $sumaRatyPLN_do_dzisiaj += $rataPLN;
                $sumaRatyNaleznaPLN_do_dzisiaj += $rataNaleznaPLN;
            }

            if ($paymentDate->gt($today)) {
                $sumaRatyPLN_po_dzisiaj += $rataPLN;
            }

            if ($paymentDate->gt($today) && $paymentDate->lte($threeYearsLater)) {
                $sumaRatyPLN_do_3lat += $rataPLN;
            }
        }

        $kwotaNadplaconychRatDoDzisiaj = $sumaRatyPLN_do_dzisiaj - $sumaRatyNaleznaPLN_do_dzisiaj;

        // Obliczenie salda zadłużenia
        $saldoCHF = end($installments)['saldoCHF'] ?? 0;
        $kursOstatni = end($installments)['kursWaluty'] ?? 1;
        $saldoZadluzeniaPLN = $saldoCHF * $kursOstatni;

        $saldoNaleznePLN = end($installments)['saldoNaleznePLN'] ?? 0;
        $kwotaDoZwrotu = $saldoZadluzeniaPLN - $saldoNaleznePLN;

        return [
            "sumaPobranychRatDoDzisiajPLN" => round($sumaRatyPLN_do_dzisiaj, 2),
            "sumaPobranychRatDoKoniecUmowyPLN" => round($sumaRatyPLN_lacznie, 2),
            "sumaRatDoSplaceniaPLN" => round($sumaRatyPLN_po_dzisiaj, 2),
            "sumaRatDoTrzyLataPLN" => round($sumaRatyPLN_do_3lat, 2),
            "sumaNaleznychRatDoDzisiajPLN" => round($sumaRatyNaleznaPLN_do_dzisiaj, 2),
            "kwotaNadplaconychRatDoDzisiaj" => round($kwotaNadplaconychRatDoDzisiaj, 2),
            "saldoZadluzeniaPLN" => round($saldoZadluzeniaPLN, 2),
            "sumaNaleznegoZadluzeniaPLN" => round($saldoNaleznePLN, 2),
            "kwotaDoZwrotu" => round($kwotaDoZwrotu, 2),
            "LeadID" => $lead->lead_id,
            "DataWyliczenia" => $today->toDateString()
        ];
    }

    // raty stałe
    private static function calculateFixedInstallments(Lead $lead): array
    {
        $schedule = [];

        $startDate = Carbon::parse($lead->loan_date)->addMonth();
        $endDate = $lead->loan_repayment_date
            ? Carbon::parse($lead->loan_repayment_date)
            : $startDate->copy()->addMonths($lead->loan_term_month - 1);

        $saldoPLN = $lead->loan_amount;
        $saldoCHF = $lead->loan_amount_currency;
        $saldoNaleznePLN = $lead->loan_amount;

        $currentDate = $startDate->copy();
        $ratNumber = 1;

        while ($currentDate->lte($endDate)) {
            $effectiveDate = self::adjustWeekend($currentDate);

            // --- TUTAJ w przyszłości podstawimy rzeczywiste wartości ---
            $rate = 2.08; // Kurs waluty
            $indexValue = 0.0145; // LIBOR / indeks
            $oprocentowanie = $indexValue + $lead->bank_margin;

            // Raty
            $rataCHF = self::calculateMonthlyInstallment($saldoCHF, $oprocentowanie, $lead->loan_term_month);
            $rataNaleznaPLN = self::calculateMonthlyInstallment($saldoNaleznePLN, $oprocentowanie, $lead->loan_term_month);
            $rataPLN = $rataCHF * $rate * (1 + $lead->spreed);

            // Odsetki
            $odsetkiCHF = ($saldoCHF * $oprocentowanie) / 12;
            $odsetkiPLN = $odsetkiCHF * $rate * (1 + $lead->spreed);
            $odsetkiNaleznePLN = ($saldoNaleznePLN * $oprocentowanie) / 12;

            // Kapitał
            $kapitalCHF = $rataCHF - $odsetkiCHF;
            $kapitalPLN = $rataPLN - $odsetkiPLN;
            $kapitalNaleznePLN = $rataNaleznaPLN - $odsetkiNaleznePLN;

            // Saldo
            $saldoCHF -= $kapitalCHF;
            $saldoPLN -= $kapitalPLN;
            $saldoNaleznePLN -= $kapitalNaleznePLN;

            $schedule[] = [
                'installmentNumber' => $ratNumber,
                'paymentDate' => $currentDate->toDateString(),
                'effectiveDate' => $effectiveDate->toDateString(),

                'kursWaluty' => round($rate, 4),
                'index' => round($indexValue, 5),
                'oprocentowanie' => round($oprocentowanie, 5),

                'saldoCHF' => round($saldoCHF, 2),
                'saldoPLN' => round($saldoPLN, 2),

                'rataCHF' => round($rataCHF, 2),
                'rataPLN' => round($rataPLN, 2),
                'rataNaleznaPLN' => round($rataNaleznaPLN, 2),
                'rataOdsetkowaCHF' => round($odsetkiCHF, 2),
                'rataOdsetkowaPLN' => round($odsetkiPLN, 2),
                'rataOdsetkowaNaleznaPLN' => round($odsetkiNaleznePLN, 2),
                'rataKapitalowaCHF' => round($kapitalCHF, 2),
                'rataKapitalowaPLN' => round($kapitalPLN, 2),
                'rataKapitalowaNaleznaPLN' => round($kapitalNaleznePLN, 2),
                'roznicaPLN' => round($rataPLN - $rataNaleznaPLN, 2),

                'abuse' => 'tak', // stała wartość dla tego scenariusza
            ];

            $ratNumber++;
            $currentDate->addMonth();
        }

        return [
            'lead_id' => $lead->lead_id,
            'case_type_code' => $lead->case_type_code,
            'loan_installments' => $lead->loan_installments,
            'loan_amount' => $lead->loan_amount,
            'loan_amount_currency' => $lead->loan_amount_currency,
            'loan_term_month' => $lead->loan_term_month,
            'loan_date' => $lead->loan_date->toDateString(),
            'loan_currency_change_date' => optional($lead->loan_currency_change_date)->toDateString(),
            'loan_paid' => optional($lead->loan_paid)->toDateString(),
            'spreed' => $lead->spreed,

            'schedule' => $schedule
        ];
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