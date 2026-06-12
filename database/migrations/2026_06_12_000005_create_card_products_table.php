<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // "12 voor de prijs van 10" => cups_total=12, cups_paid=10.
            $table->unsignedInteger('cups_total');
            $table->unsignedInteger('cups_paid');
            $table->unsignedInteger('price_per_cup_cents');
            // Kostprijs per kop t.b.v. marge-berekening (optioneel, default 0).
            $table->unsignedInteger('cost_per_cup_cents')->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedInteger('validity_days')->default(730); // ~24 maanden
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_products');
    }
};
