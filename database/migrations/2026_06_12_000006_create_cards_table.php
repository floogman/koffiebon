<?php

use App\Enums\CardStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('card_product_id')->constrained();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default(CardStatus::Pending->value);
            // Bevroren snapshot van het product bij uitgifte.
            $table->unsignedInteger('cups_total');
            // Gecachte afgeleide van het grootboek; card_events is leidend.
            $table->unsignedInteger('cups_remaining');
            $table->unsignedInteger('price_paid_cents')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
