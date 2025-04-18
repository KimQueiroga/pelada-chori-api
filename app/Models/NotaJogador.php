<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotaJogador extends Model
{
    use HasFactory;

    protected $table = 'notas_jogadores';

    protected $fillable = [
        'jogador_id',
        'tecnica',
        'inteligencia',
        'velocidade_preparo',
        'disciplina_tatica',
        'poder_ofensivo',
        'poder_defensivo',
        'fundamentos_basicos',
    ];

    public function jogador()
    {
        return $this->belongsTo(Jogador::class);
    }
}

