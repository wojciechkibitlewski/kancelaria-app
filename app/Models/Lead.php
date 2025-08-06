<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'lead_id',
        'contract_id',
        'case_type_code',
        'loan_date',
        'loan_currency',
        'loan_amount',
        'loan_amount_currency',
        'paid_in_currency',
        'loan_currency_change_date',
        'loan_term_month',
        'grace_period',
        'bank_margin',
        'spreed',
        'loan_indexes',
        'loan_installments',
        'loan_paid_option',
        'loan_paid',
        'loan_overpayment_option',
        'loan_overpayment_result',
        'loan_overpayment_amount',
        'loan_overpayment_currency',
        'loan_repayment_option',
        'loan_repayment_date',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'loan_date' => 'date',
        'loan_currency_change_date' => 'date',
        'loan_paid' => 'date',
        'loan_repayment_date' => 'date',
        'loan_paid_option' => 'boolean',
        'loan_overpayment_option' => 'boolean',
        'loan_repayment_option' => 'boolean',
    ];
}