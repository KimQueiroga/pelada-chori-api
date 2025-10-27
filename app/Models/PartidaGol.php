<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PartidaGol extends Model
{
    use HasFactory;

    protected $fillable = [
        'partida_id','time_id','jogador_id','assist_jogador_id',
        'ocorreu_em','segundo_relativo',
    ];

    protected $casts = [
        'ocorreu_em' => 'datetime',
    ];

    public function partida()     { return $this->belongsTo(Partida::class); }
    public function time()        { return $this->belongsTo(SorteioTime::class, 'time_id'); }
    public function autor()       { return $this->belongsTo(Jogador::class, 'jogador_id'); }
    public function assistente()  { return $this->belongsTo(Jogador::class, 'assist_jogador_id'); }
}
