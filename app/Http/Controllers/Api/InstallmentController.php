<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lead;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class InstallmentController extends Controller
{
    public function index(Request $request)
    {
        try {
            // 1. Przyjmij i zapisz dane
            $lead = $this->acceptData($request);

            // 2. Sprawdź typ sprawy i uruchom odpowiedni kalkulator
            $caseType = $lead->case_type_code;

            $result = match ($caseType) {
                'GET'   => \App\Services\LoanCalculators::calculatorGet($lead),
                //'WAL'   => \App\Services\LoanCalculators::calculatorWal($lead),
                //'KZO'   => \App\Services\LoanCalculators::calculatorKzo($lead),
               // 'SKD'   => \App\Services\LoanCalculators::calculatorSkd($lead),
                //'WIBOR' => \App\Services\LoanCalculators::calculatorWibor($lead),
                default => ['error' => 'Nieobsługiwany Case Type Code']
            };

            return response()->json([
                'lead_id' => $lead->lead_id,
                'calculation' => $result,
            ]);

        } catch (\Throwable $e) {
            Log::error('InstallmentController@index error: ' . $e->getMessage());
            return response()->json([
                'error' => 'Wystąpił błąd przy przetwarzaniu danych.',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    private function acceptData(Request $request): Lead
    {
        $data = $request->json()->all();

        // Walidacja minimalna
        $validator = Validator::make($data[0], [
            'Lead ID' => 'required|string',
            'LoanDate' => 'required|date',
            'LoanAmount' => 'required|numeric',
            'LoanCurrency' => 'required|string',
            'LoanTermMonth' => 'required|integer',
            'BankMargin' => 'required|numeric',
            'LoanIndexes' => 'required|string',
            'LoanInstallments' => 'required|string',
            'Case Type Code' => 'required|string',
        ]);

        if ($validator->fails()) {
            throw new \Exception('Błąd walidacji danych: ' . $validator->errors()->first());
        }

        $leadData = $data[0];

        // Mapowanie danych z n8n do modelu Lead
        return Lead::updateOrCreate(
            ['lead_id' => $leadData['Lead ID']],
            [
                'contract_id' => $leadData['Contract ID'] ?? null,
                'case_type_code' => $leadData['Case Type Code'][0] ?? null,
                'loan_date' => $leadData['LoanDate'],
                'loan_currency' => $leadData['LoanCurrency'],
                'loan_amount' => $leadData['LoanAmount'],
                'loan_amount_currency' => $leadData['LoanAmountInCurrency'] ?? null,
                'paid_in_currency' => $leadData['PaidInCurrency'] ?? null,
                'loan_currency_change_date' => $leadData['LoanCurrencyChangeDate'] ?? null,
                'loan_term_month' => $leadData['LoanTermMonth'],
                'grace_period' => $leadData['GracePeriod'] ?? null,
                'bank_margin' => $leadData['BankMargin'],
                'spreed' => $leadData['Spreed'] ?? null,
                'loan_indexes' => $leadData['LoanIndexes'],
                'loan_installments' => $leadData['LoanInstallments'],
                'loan_paid_option' => $leadData['LoanPaidOption'] ?? null,
                'loan_paid' => $leadData['LoanPaid'] ?? null,
                'loan_overpayment_option' => $leadData['LoanOverpaymentOption'] ?? null,
                'loan_overpayment_result' => $leadData['LoanOverpaymentResult'] ?? null,
                'loan_overpayment_amount' => $leadData['LoanOverpaymentAmount'] ?? null,
                'loan_overpayment_currency' => $leadData['LoanOverpaymentCurrency'] ?? null,
                'loan_repayment_option' => $leadData['LoanRepaymentOption'] ?? null,
                'loan_repayment_date' => $leadData['LoanRepaymentDate'] ?? null,
                'payload' => $leadData,
            ]
        );
    }
}