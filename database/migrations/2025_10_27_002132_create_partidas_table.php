<?php

// database/migrations/2025_10_26_000001_create_partidas_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('partidas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sorteio_id')->constrained('sorteios')->cascadeOnDelete();
            $table->foreignId('time_a_id')->constrained('sorteio_times')->cascadeOnDelete();
            $table->foreignId('time_b_id')->constrained('sorteio_times')->cascadeOnDelete();

            // status: agendada | em_andamento | encerrada
            $table->string('status', 20)->default('agendada');

            // relógio (manual para encerrar)
            $table->unsignedInteger('duracao_prevista_segundos')->default(420); // 7 min
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('encerrada_em')->nullable();

            // placar espelhado (também validado pelos eventos ao encerrar)
            $table->unsignedInteger('placar_a')->default(0);
            $table->unsignedInteger('placar_b')->default(0);

            // resultado
            $table->foreignId('vencedor_time_id')->nullable()->constrained('sorteio_times')->nullOnDelete();
            $table->boolean('empate')->default(false);

            $table->timestamps();

            $table->index(['sorteio_id','status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('partidas');
    }
};

