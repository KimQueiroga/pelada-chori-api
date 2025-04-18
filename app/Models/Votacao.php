<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Votacao extends Model
{
    protected $table = 'votacoes';

    protected $fillable = [
        'data_inicio',
        'data_fim',
        'ativa',
    ];
}
