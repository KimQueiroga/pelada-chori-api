<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SorteioTimeJogador;

class SorteioTimeJogadorController extends Controller
{
        public function store(Request $request, $sorteioId, $timeId)
    {
        $request->validate([
            'jogador_id' => 'required|exists:jogadores,id',
            'media' => 'nullable|numeric',
        ]);

        return SorteioTimeJogador::create([
            'sorteio_time_id' => $timeId,
            'jogador_id' => $request->jogador_id,
            'media' => $request->media ?? 0
        ]);
    }
}
