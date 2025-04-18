<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
        public function up()
    {
        Schema::create('votos', function (Blueprint $table) {
            $table->id();
        
            $table->unsignedBigInteger('votacao_id');
            $table->unsignedBigInteger('jogador_origem_id');
            $table->unsignedBigInteger('jogador_destino_id');
        
            $table->decimal('tecnica', 3, 1);
            $table->decimal('inteligencia', 3, 1);
            $table->decimal('velocidade_preparo', 3, 1);
            $table->decimal('disciplina_tatica', 3, 1);
            $table->decimal('poder_ofensivo', 3, 1);
            $table->decimal('poder_defensivo', 3, 1);
            $table->decimal('fundamentos_basicos', 3, 1);
        
            $table->timestamps();
        
            $table->foreign('votacao_id')->references('id')->on('votacoes')->onDelete('cascade');
            $table->foreign('jogador_origem_id')->references('id')->on('jogadores')->onDelete('cascade');
            $table->foreign('jogador_destino_id')->references('id')->on('jogadores')->onDelete('cascade');
        });
    }                


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votos');
    }
};
