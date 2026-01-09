<?php

// database/migrations/2025_10_26_000003_create_jogador_vitorias_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('jogador_vitorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')->constrained('partidas')->cascadeOnDelete();
            $table->foreignId('jogador_id')->constrained('jogadores')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['partida_id','jogador_id']);
            $table->index('jogador_id');
        });
    }
    public function down(): void {
        Schema::dropIfExists('jogador_vitorias');
    }
};
