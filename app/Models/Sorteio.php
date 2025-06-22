<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sorteio extends Model
{
    use HasFactory;

    protected $fillable = [
        'data', 'quantidade_times', 'quantidade_jogadores_time', 'numero', 'descricao'
    ];

    protected $dates = ['data'];

    public function times()
    {
        return $this->hasMany(SorteioTime::class);
    }

    public function votos()
    {
        return $this->hasMany(SorteioVoto::class);
    }
}
