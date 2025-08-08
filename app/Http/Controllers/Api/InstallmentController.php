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
            $leadData = $this->acceptData($request);

            
            // 2. Sprawdź typ sprawy i uruchom odpowiedni kalkulator
            $caseType = $leadData->case_type_code;

            $result = match ($caseType) {
                'GET'   => \App\Services\LoanCalculators::calculatorGet($leadData),
                // 'WAL'   => \App\Services\LoanCalculators::calculatorWal($leadData),
                // 'KZO'   => \App\Services\LoanCalculators::calculatorKzo($leadData),
                // 'SKD'   => \App\Services\LoanCalculators::calculatorSkd($leadData),
                // 'WIBOR' => \App\Services\LoanCalculators::calculatorWibor($leadData),
                default => ['error' => 'Nieobsługiwany Case Type Code']
            };
            
            return response()->json([
                'lead_id' => $leadData->lead_id,
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

    private function acceptData(Request $request)
    {
        try {
            $leadData = $request->input('data');

            // Na tym etapie data powinno być tablicą
            if (!is_array($leadData) || empty($leadData)) {
                throw new \Exception('Brak danych w polu "data".');
            }

            
            // $lead = $data[0];

            // Walidacja minimalna
            $validator = Validator::make($leadData[0], [
                'lead_id' => 'required|string',
                'contract_id' => 'string|nullable',
                'case_type_code' => 'required|string',
                'loan_date' => 'required|date',
                'loan_currency' => 'required|string',
                'loan_amount' => 'required|numeric',
                'loan_amount_currency' => 'required|numeric',
                'paid_in_currency' => 'required|string',
                'loan_currency_change_date' => 'date|nullable',
                'loan_term_month' => 'required|numeric',
                'grace_period' => 'numeric',
                'bank_margin' => 'required|numeric',
                'spreed' => 'numeric|nullable',
                'loan_indexes' => 'required|string',
                'loan_installments' => 'required|string',
                'loan_paid_option' => 'string|nullable',
                'loan_paid' => 'string|nullable',
                'loan_overpayment_option' => 'string|nullable',
                'loan_overpayment_result' => 'string|nullable',
                'loan_overpayment_amount' => 'numeric|nullable',
                'loan_overpayment_currency' => 'string|nullable',
                'loan_repayment_option' => 'string|nullable',
                'loan_repayment_date' => 'date|nullable',                
            ]);

            if ($validator->fails()) {
                throw new \Exception('Błąd walidacji danych: ' . $validator->errors()->first());
            }

            $savedLead = Lead::updateOrCreate(
                ['lead_id' => $leadData[0]['lead_id']], // warunki wyszukiwania
                [
                    'contract_id' => $leadData[0]['contract_id'] ?? null,
                    'case_type_code' => $leadData[0]['case_type_code'],
                    'loan_date' => $leadData[0]['loan_date'],
                    'loan_currency' => $leadData[0]['loan_currency'],
                    'loan_amount' => $leadData[0]['loan_amount'],
                    'loan_amount_currency' => $leadData[0]['loan_amount_currency'],
                    'paid_in_currency' => $leadData[0]['paid_in_currency'],
                    'loan_currency_change_date' => $leadData[0]['loan_currency_change_date'] ?? null,
                    'loan_term_month' => $leadData[0]['loan_term_month'],
                    'grace_period' => $leadData[0]['grace_period'] ?? null,
                    'bank_margin' => $leadData[0]['bank_margin'],
                    'spreed' => $leadData[0]['spreed'] ?? null,
                    'loan_indexes' => $leadData[0]['loan_indexes'],
                    'loan_installments' => $leadData[0]['loan_installments'],
                    'loan_paid_option' => $leadData[0]['loan_paid_option'] ?? null,
                    'loan_paid' => $leadData[0]['loan_paid'] ?? null,
                    'loan_overpayment_option' => $leadData[0]['loan_overpayment_option'] ?? null,
                    'loan_overpayment_result' => $leadData[0]['loan_overpayment_result'] ?? null,
                    'loan_overpayment_amount' => $leadData[0]['loan_overpayment_amount'] ?? null,
                    'loan_overpayment_currency' => $leadData[0]['loan_overpayment_currency'] ?? null,
                    'loan_repayment_option' => $leadData[0]['loan_repayment_option'] ?? null,
                    'loan_repayment_date' => $leadData[0]['loan_repayment_date'] ?? null,
                    'payload' => $leadData[0], // opcjonalnie – cały rekord oryginalny
                ]
            );

            // Zwracamy do testu
            return $savedLead;

        } catch (\Throwable $e) {
            Log::error('acceptData error: ' . $e->getMessage());
            throw $e; // przekazujemy do index()
        }
        
    }

}