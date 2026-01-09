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
        Schema::create('sorteio_time_jogadores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sorteio_time_id')->constrained()->onDelete('cascade');
            $table->foreignId('jogador_id')->nullable()->constrained('jogadores')->onDelete('set null');
            $table->string('nome')->nullable()->comment('Usado para jogadores externos');
            $table->decimal('media', 4, 2)->default(0);
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sorteio_time_jogadores');
    }
};
