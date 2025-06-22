<?php

// SorteioTimeController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SorteioTime;

class SorteioTimeController extends Controller
{
        public function store(Request $request, $sorteioId)
    {
        $request->validate([
            'nome' => 'required|string',
            'media' => 'nullable|numeric'
        ]);

        return SorteioTime::create([
            'sorteio_id' => $sorteioId,
            'nome' => $request->input('nome'),
            'media' => $request->input('media')
        ]);
    }
}
