<?php

// database/migrations/xxxx_xx_xx_xxxxxx_create_password_reset_codes_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('password_reset_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('code_hash');       // Hash do código (nunca salve o código em texto puro)
            $table->string('reset_token')->nullable(); // token temporário pós-verificação
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'expires_at']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('password_reset_codes');
    }
};
