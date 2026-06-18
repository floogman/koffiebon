<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Cross-device login: de PWA start een sessie en houdt het geheim (`secret`)
        // bij zich; de server kent alleen de hash. De e-maillink bevestigt de sessie,
        // waarna de server een publiek event op `login.{secret_hash}` uitzendt. De PWA
        // wisselt vervolgens het geheim in voor een device-token. Beide hashes at rest.
        Schema::create('login_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('secret_hash', 64)->unique();       // sha256(secret) — ook de kanaalnaam
            $table->string('email_token_hash', 64)->unique();  // sha256(email-token uit de link)
            $table->string('status', 16)->default('pending');  // pending | confirmed | consumed
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_sessions');
    }
};
