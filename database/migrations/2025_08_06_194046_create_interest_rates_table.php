<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('interest_rates', function (Blueprint $table) {
            $table->id();
            $table->date('effective_date');
            $table->string('index_name'); // np. WIBOR 3M
            $table->decimal('value', 10, 6); // np. 0.023000
            $table->timestamps();

            $table->unique(['effective_date', 'index_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interest_rates');
    }
};