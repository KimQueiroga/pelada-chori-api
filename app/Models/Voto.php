<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Voto extends Model
{
    protected $fillable = [
        'votacao_id', 'jogador_origem_id', 'jogador_destino_id',
        'tecnica', 'inteligencia', 'velocidade_preparo',
        'disciplina_tatica', 'poder_ofensivo', 'poder_defensivo', 'fundamentos_basicos'
    ];

    public function votacao()
    {
        return $this->belongsTo(Votacao::class);
    }

    public function jogadorOrigem()
    {
        return $this->belongsTo(Jogador::class, 'jogador_origem_id');
    }

    public function jogadorDestino()
    {
        return $this->belongsTo(Jogador::class, 'jogador_destino_id');
    }
}

