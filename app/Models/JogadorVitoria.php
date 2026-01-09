<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class JogadorVitoria extends Model
{
    use HasFactory;

    protected $fillable = ['partida_id','jogador_id'];

    public function partida() { return $this->belongsTo(Partida::class); }
    public function jogador() { return $this->belongsTo(Jogador::class); }
}
