<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql')->create('refresh_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_id')->index();
            $table->unsignedBigInteger('id_usuario')->index();
            $table->char('token_hash', 64)->unique();
            $table->char('device_hash', 64);
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->uuid('replaced_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('refresh_tokens');
    }
};
