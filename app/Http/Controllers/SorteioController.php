<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sorteio;

class SorteioController extends Controller
{
    public function index()
    {
        return Sorteio::with('times.jogadores')->get();
    }

    public function store(Request $request)
    {
        $request->validate([
            'data' => 'required|date',
            'descricao' => 'nullable|string',
            'numero' => 'required|integer|min:1',
            'quantidade_times' => 'required|integer|min:1',
            'quantidade_jogadores_time' => 'required|integer|min:1'
        ]);

        return Sorteio::create($request->only([
            'data',
            'descricao',
            'numero',
            'quantidade_times',
            'quantidade_jogadores_time'
        ]));
    }

    // SorteioController.php
    public function ativos()
    {
        $hoje = now()->toDateString();
        return Sorteio::whereDate('data', '>=', $hoje)->with('times.jogadores')->get();
    }

}
