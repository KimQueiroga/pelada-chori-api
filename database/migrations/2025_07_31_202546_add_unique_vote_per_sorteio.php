
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sorteio_votos', function (Blueprint $table) {
            $table->unique(['sorteio_id', 'jogador_id'], 'uniq_voto_sorteio_jogador');
        });
    }

    public function down(): void
    {
        Schema::table('sorteio_votos', function (Blueprint $table) {
            $table->dropUnique('uniq_voto_sorteio_jogador');
        });
    }
};
