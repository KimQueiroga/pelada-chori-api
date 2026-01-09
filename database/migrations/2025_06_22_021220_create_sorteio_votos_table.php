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
        Schema::create('sorteio_votos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sorteio_id')->constrained()->onDelete('cascade');
            $table->foreignId('jogador_id')->nullable()->constrained('jogadores')->onDelete('set null');
            $table->timestamps();
            $table->unique(['sorteio_id', 'jogador_id']);
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sorteio_votos');
    }
};
