<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PartidaSubstituicao extends Model
{
    use HasFactory;

    protected $table = 'partida_substituicoes';

    protected $fillable = [
        'partida_id',
        'time_id',
        'jogador_sai_id',
        'jogador_entra_id',
        'revertida_em',
    ];

    protected $casts = [
        'revertida_em' => 'datetime',
    ];

    public function partida() { return $this->belongsTo(Partida::class); }
    public function time() { return $this->belongsTo(SorteioTime::class, 'time_id'); }
    public function jogadorSai() { return $this->belongsTo(Jogador::class, 'jogador_sai_id'); }
    public function jogadorEntra() { return $this->belongsTo(Jogador::class, 'jogador_entra_id'); }
}
