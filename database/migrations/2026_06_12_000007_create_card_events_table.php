<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Onveranderlijk grootboek: rijen worden nooit gewijzigd of verwijderd.
        Schema::create('card_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // issue|activate|redeem|void|adjust
            $table->integer('cups_delta')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->index(['card_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_events');
    }
};
