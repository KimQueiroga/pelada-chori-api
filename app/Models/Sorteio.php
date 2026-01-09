<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sorteio extends Model
{
    use HasFactory;

    protected $fillable = [
        'data', 
        'quantidade_times', 
        'quantidade_jogadores_time', 
        'numero', 
        'descricao',
         // novos:
        'tentativa',
        'status',
        'em_votacao',                     
    ];

    protected $casts = [
        'data' => 'date',
        'em_votacao' => 'boolean',
    ];

    protected $dates = ['data'];

    public function times()
    {
        return $this->hasMany(SorteioTime::class);
    }

     public function votos()
    {
        return $this->hasMany(\App\Models\SorteioVoto::class, 'sorteio_id');
    }

        public function votosCount(): int
    {
        return $this->votos()->count();
    }

}
