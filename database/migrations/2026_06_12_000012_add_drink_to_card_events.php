<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Snapshot van het geschonken drankje op het redeem-event (grootboek blijft leidend).
        Schema::table('card_events', function (Blueprint $table) {
            $table->foreignId('drink_id')->nullable()->after('staff_user_id')->constrained()->nullOnDelete();
            $table->string('coffee_type')->nullable()->after('drink_id');
            $table->string('cup_size')->nullable()->after('coffee_type');
            $table->unsignedInteger('cost_cents')->nullable()->after('cup_size');
        });
    }

    public function down(): void
    {
        Schema::table('card_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('drink_id');
            $table->dropColumn(['coffee_type', 'cup_size', 'cost_cents']);
        });
    }
};
