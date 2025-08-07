<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id(); // lokalne ID
            $table->string('lead_id')->unique(); // np. "recxVyBlB1V6T1LXP" z n8n
            $table->string('contract_id')->nullable(); // np. GET/2025/01/sder23
            $table->string('case_type_code')->nullable(); // np. GET, WAL, KZO

            $table->date('loan_date'); // data
            $table->string('loan_currency'); // PLN, CHF, ...
            $table->decimal('loan_amount', 15, 2); // kwota w PLN
            $table->decimal('loan_amount_currency', 15, 2)->nullable(); // kwota w walucie
            $table->string('paid_in_currency')->nullable(); // PLN, CHF, EUR

            $table->date('loan_currency_change_date')->nullable(); // zmiana waluty

            $table->integer('loan_term_month'); // ilość miesięcy
            $table->integer('grace_period')->nullable(); // karencja

            $table->decimal('bank_margin', 10, 6); // marża banku
            $table->decimal('spreed', 10, 6)->nullable(); // spread

            $table->string('loan_indexes'); // LIBOR 3M, WIBOR 6M
            $table->enum('loan_installments', ['stale', 'zmienne']); // typ rat

            $table->boolean('loan_paid_option')->nullable(); // czy spłacony
            $table->date('loan_paid')->nullable(); // data spłaty

            $table->boolean('loan_overpayment_option')->nullable(); // nadpłata?
            $table->string('loan_overpayment_result')->nullable(); // np. obniżenie rat
            $table->decimal('loan_overpayment_amount', 15, 2)->nullable(); // wartość nadpłaty
            $table->string('loan_overpayment_currency')->nullable(); // waluta nadpłaty

            $table->boolean('loan_repayment_option')->nullable(); // wcześniejsza spłata?
            $table->date('loan_repayment_date')->nullable(); // data spłaty

            $table->json('payload'); // cały oryginalny rekord JSON z n8n

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};