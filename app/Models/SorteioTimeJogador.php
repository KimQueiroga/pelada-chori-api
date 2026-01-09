<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SorteioTimeJogador extends Model
{
    use HasFactory;
    protected $table = 'sorteio_time_jogadores'; // <- Adicione isso!

    protected $fillable = [
        'sorteio_time_id', 'jogador_id', 'nome_externo', 'media'
    ];

    public function time()
    {
        return $this->belongsTo(SorteioTime::class, 'sorteio_time_id');
    }

    public function jogador()
    {
        return $this->belongsTo(Jogador::class);
    }
}
