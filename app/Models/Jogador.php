<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jogador extends Model
{
    use HasFactory;

    protected $table = 'jogadores'; // <- adiciona isso

    protected $fillable = [
        'user_id',
        'nome',
        'apelido',
        'numero_camisa',
        'foto_url',
        'posicao_preferencia',
        'nota_tecnica',
        'nota_inteligencia',
        'nota_velocidade',
        'nota_tatica',
        'nota_ofensiva',
        'nota_defensiva',
        'nota_fundamentos',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // App\Models\Jogador.php

    public function nota()
    {
        return $this->hasOne(NotaJogador::class);
    }


}
