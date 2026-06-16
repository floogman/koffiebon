<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            // Voorkeursdrankje van de kaart, als tekst (type + maat). Bewust géén FK naar
            // drinks: de drankenkaart kan wijzigen, maar de keuze van de kaart blijft leesbaar.
            $table->string('preferred_coffee_type')->nullable()->after('status');
            $table->string('preferred_cup_size')->nullable()->after('preferred_coffee_type');
        });
    }

    public function down(): void
    {
        Schema::table('cards', function (Blueprint $table) {
            $table->dropColumn(['preferred_coffee_type', 'preferred_cup_size']);
        });
    }
};
