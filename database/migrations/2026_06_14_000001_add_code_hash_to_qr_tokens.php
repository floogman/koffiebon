<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_tokens', function (Blueprint $table) {
            // SHA256 van de 6-cijferige baliecode. Alleen de hash wordt opgeslagen;
            // de platte code verlaat de server precies één keer (bij uitgifte).
            $table->string('code_hash', 64)->nullable()->after('nonce_hash');
            $table->index('code_hash');
        });
    }

    public function down(): void
    {
        Schema::table('qr_tokens', function (Blueprint $table) {
            $table->dropIndex(['code_hash']);
            $table->dropColumn('code_hash');
        });
    }
};
