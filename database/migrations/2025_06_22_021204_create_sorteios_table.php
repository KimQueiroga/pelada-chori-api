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
        Schema::create('sorteios', function (Blueprint $table) {
            $table->id();
            $table->string('descricao')->nullable();
            $table->date('data');
            $table->integer('numero')->comment('1 ou 2 para indicar o sorteio do dia');
            $table->timestamps();
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sorteios');
    }
};
