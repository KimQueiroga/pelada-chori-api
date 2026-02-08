<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('partida_substituicoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')->constrained('partidas')->cascadeOnDelete();
            $table->foreignId('time_id')->constrained('sorteio_times')->cascadeOnDelete();
            $table->foreignId('jogador_sai_id')->constrained('jogadores')->cascadeOnDelete();
            $table->foreignId('jogador_entra_id')->constrained('jogadores')->cascadeOnDelete();
            $table->timestamp('revertida_em')->nullable();
            $table->timestamps();

            $table->index(['partida_id', 'time_id']);
            $table->index(['partida_id', 'revertida_em']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('partida_substituicoes');
    }
};
