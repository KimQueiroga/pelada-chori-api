<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sorteios', function (Blueprint $table) {
            $table->unsignedInteger('tentativa')->default(1)->after('numero');
            $table->string('status', 20)->default('rascunho')->after('tentativa'); // rascunho|em_votacao|encerrado|descartado
            $table->boolean('em_votacao')->default(false)->after('status');

            // Índices úteis
            $table->index(['data', 'tentativa']);
            $table->index(['data', 'em_votacao']);
        });
    }

    public function down(): void
    {
        Schema::table('sorteios', function (Blueprint $table) {
            $table->dropIndex(['data', 'tentativa']);
            $table->dropIndex(['data', 'em_votacao']);
            $table->dropColumn(['tentativa', 'status', 'em_votacao']);
        });
    }
};
