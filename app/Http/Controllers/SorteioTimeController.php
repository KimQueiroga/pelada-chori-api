<?php

// app/Http/Controllers/SorteioTimeController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SorteioTime;

class SorteioTimeController extends Controller
{
    public function store(Request $request, $sorteioId)
    {
        $request->validate([
            'nome'  => 'required|string',
            'media' => 'nullable|numeric'
        ]);

        return SorteioTime::create([
            'sorteio_id' => $sorteioId,
            'nome'       => $request->input('nome'),
            'media'      => $request->input('media')
        ]);
    }

    // NOVO: necessário para a tela montar partidas
    public function index($sorteioId)
    {
        return SorteioTime::with([
                'jogadores.jogador.user', // traz jogador + user p/ foto/nome
            ])
            ->where('sorteio_id', $sorteioId)
            ->orderBy('id')
            ->get();
    }
}

