<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // De eenmalige device-claim-codes zijn vervangen door `login_sessions` (cross-device
    // login). De wegwerp-handshake is identiek; de durable device-identiteit zit in de
    // Sanctum-tokens (personal_access_tokens), niet hier.
    public function up(): void
    {
        Schema::dropIfExists('device_claims');
    }

    public function down(): void
    {
        Schema::create('device_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }
};
