<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SorteioVoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'sorteio_id',
        'user_id',        // ID do usuário logado
        'jogador_id'      // (opcional) pode ser útil para consulta futura
    ];

    public function sorteio()
    {
        return $this->belongsTo(Sorteio::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function jogador()
    {
        return $this->belongsTo(Jogador::class, 'jogador_id');
    }
}
