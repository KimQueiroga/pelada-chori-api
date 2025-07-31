<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
     public function up(): void
    {
        // Ajuste a precisão/escala conforme seu schema atual
        Schema::table('sorteio_times', function (Blueprint $table) {
            $table->decimal('media', 5, 2)->nullable()->change();
        });

        // Opcional, mas recomendado: permitir NULL também em media dos jogadores do time
        if (Schema::hasTable('sorteio_time_jogadores')) {
            Schema::table('sorteio_time_jogadores', function (Blueprint $table) {
                $table->decimal('media', 5, 2)->nullable()->change();
            });
        }
    }


    /**
     * Reverse the migrations.
     */
      public function down(): void
    {
        Schema::table('sorteio_times', function (Blueprint $table) {
            $table->decimal('media', 5, 2)->default(0)->nullable(false)->change();
        });

        if (Schema::hasTable('sorteio_time_jogadores')) {
            Schema::table('sorteio_time_jogadores', function (Blueprint $table) {
                $table->decimal('media', 5, 2)->default(0)->nullable(false)->change();
            });
        }
    }
};

