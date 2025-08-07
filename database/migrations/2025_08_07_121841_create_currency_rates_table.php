<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->date('effective_date');
            $table->string('currency', 3); // np. CHF, USD
            $table->decimal('value', 10, 4); // np. 2.3456
            $table->timestamps();

            $table->unique(['effective_date', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rates');
    }
};
