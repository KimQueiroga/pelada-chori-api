<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Partida extends Model
{
    use HasFactory;

    protected $fillable = [
        'sorteio_id','time_a_id','time_b_id',
        'status','duracao_prevista_segundos','iniciada_em','encerrada_em',
        'placar_a','placar_b','vencedor_time_id','empate',
    ];

    protected $casts = [
        'iniciada_em' => 'datetime',
        'encerrada_em'=> 'datetime',
        'empate'      => 'boolean',
    ];

    public function sorteio()      { return $this->belongsTo(Sorteio::class); }
    public function timeA()        { return $this->belongsTo(SorteioTime::class, 'time_a_id'); }
    public function timeB()        { return $this->belongsTo(SorteioTime::class, 'time_b_id'); }
    public function vencedorTime() { return $this->belongsTo(SorteioTime::class, 'vencedor_time_id'); }

    public function gols()         { return $this->hasMany(PartidaGol::class); }

    // helpers
    public function isAndamento(): bool { return $this->status === 'em_andamento'; }
    public function isEncerrada(): bool { return $this->status === 'encerrada'; }
}
