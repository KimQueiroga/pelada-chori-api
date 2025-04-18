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
        Schema::create('notas_jogadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jogador_id')->constrained('jogadores')->onDelete('cascade');

            $table->decimal('tecnica', 3, 1);
            $table->decimal('inteligencia', 3, 1);
            $table->decimal('velocidade_preparo', 3, 1);
            $table->decimal('disciplina_tatica', 3, 1);
            $table->decimal('poder_ofensivo', 3, 1);
            $table->decimal('poder_defensivo', 3, 1);
            $table->decimal('fundamentos_basicos', 3, 1);

            $table->timestamps();
        });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notas_jogadores');
    }
};
