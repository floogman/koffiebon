<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type'); // customer|card
            $table->unsignedBigInteger('subject_id');
            // Alleen de hash van de nonce wordt opgeslagen; de platte nonce verlaat de server één keer.
            $table->string('nonce_hash', 64)->unique();
            $table->string('purpose'); // identify|redeem
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_tokens');
    }
};
