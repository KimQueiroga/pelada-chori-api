<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jogador_estatisticas_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jogador_id')->constrained('jogadores')->cascadeOnDelete();
            $table->date('data_referencia');
            $table->unsignedInteger('gols')->default(0);
            $table->unsignedInteger('assistencias')->default(0);
            $table->unsignedInteger('vitorias')->default(0);
            $table->string('origem', 40)->default('manual');
            $table->string('observacao', 255)->nullable();
            $table->timestamps();

            $table->index(['jogador_id', 'data_referencia']);
            $table->index('data_referencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jogador_estatisticas_extras');
    }
};
