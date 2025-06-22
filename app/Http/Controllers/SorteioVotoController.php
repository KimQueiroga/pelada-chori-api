<?php

// SorteioVotoController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SorteioVoto;
use Illuminate\Support\Facades\Auth;

class SorteioVotoController extends Controller
    {
            public function store(Request $request, $sorteio)
    {
        $request->validate([
            'jogador_id' => 'required|exists:jogadores,id',
        ]);

        return SorteioVoto::create([
            'sorteio_id' => $sorteio, // <- PEGO DA URL
            'jogador_id' => $request->jogador_id,
            'user_id' => auth()->id(),
        ]);
    }


}