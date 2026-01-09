<?php

// database/migrations/2025_10_26_000002_create_partida_gols_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('partida_gols', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partida_id')->constrained('partidas')->cascadeOnDelete();
            $table->foreignId('time_id')->constrained('sorteio_times')->cascadeOnDelete();
            $table->foreignId('jogador_id')->constrained('jogadores')->cascadeOnDelete(); // autor do gol
            $table->foreignId('assist_jogador_id')->nullable()->constrained('jogadores')->nullOnDelete(); // opcional

            // quando aconteceu (servidor) + segundo relativo (opcional, vindo do cliente)
            $table->timestamp('ocorreu_em')->useCurrent();
            $table->unsignedInteger('segundo_relativo')->nullable();

            $table->timestamps();

            $table->index(['partida_id','time_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('partida_gols');
    }
};
