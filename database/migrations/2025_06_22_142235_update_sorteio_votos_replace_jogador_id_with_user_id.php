<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sorteio_votos', function (Blueprint $table) {
            // Apenas adiciona user_id e novo índice
            $table->foreignId('user_id')->after('sorteio_id')->constrained()->onDelete('cascade');

            // Garante 1 voto por user por sorteio
            $table->unique(['sorteio_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sorteio_votos', function (Blueprint $table) {
            $table->dropUnique(['sorteio_id', 'user_id']);
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });
    }
};

