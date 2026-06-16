<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_tokens', function (Blueprint $table) {
            // Identify-token (kaart kopen) draagt de in de PWA gekozen drank mee, zodat de
            // balie hem op de nieuwe kaart kan vastleggen. Redeem-tokens laten dit leeg.
            $table->string('preferred_coffee_type')->nullable()->after('purpose');
            $table->string('preferred_cup_size')->nullable()->after('preferred_coffee_type');
        });
    }

    public function down(): void
    {
        Schema::table('qr_tokens', function (Blueprint $table) {
            $table->dropColumn(['preferred_coffee_type', 'preferred_cup_size']);
        });
    }
};
