<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // De koffiekaart-menukaart: koffiesoort × maat, met kostprijs per drankje.
        // Eén verzilvering blijft één kop; type/maat dienen voor keuze + analytics.
        Schema::create('drinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('type');  // regular|cappuccino|flat_white|espresso
            $table->string('size');  // small|medium|large
            $table->unsignedInteger('cost_cents')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['merchant_id', 'type', 'size']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drinks');
    }
};
