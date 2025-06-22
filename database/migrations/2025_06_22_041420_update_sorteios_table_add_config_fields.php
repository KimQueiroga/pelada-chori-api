<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sorteios', function (Blueprint $table) {
            $table->unsignedInteger('quantidade_times')->default(3)->after('descricao');
            $table->unsignedInteger('quantidade_jogadores_time')->default(6)->after('quantidade_times');
            $table->integer('numero')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('sorteios', function (Blueprint $table) {
            $table->dropColumn(['quantidade_times', 'quantidade_jogadores_time']);
            $table->integer('numero')->nullable()->change();
        });
    }
};
