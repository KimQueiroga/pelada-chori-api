<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SorteioTime extends Model
{
    use HasFactory;

    protected $fillable = [
        'sorteio_id', 'nome', 'media'
    ];

    public function sorteio()
    {
        return $this->belongsTo(Sorteio::class);
    }

    public function jogadores()
    {
        return $this->hasMany(SorteioTimeJogador::class);
    }
}
