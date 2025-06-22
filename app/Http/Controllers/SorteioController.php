<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Sorteio;
use App\Models\Voto;


class SorteioController extends Controller
{
    public function show($id)
    {
        $sorteio = Sorteio::with([
            'times.jogadores.jogador.user'
        ])->findOrFail($id);

        // Para cada time do sorteio
        foreach ($sorteio->times as $time) {
            // Para cada jogador no time
            foreach ($time->jogadores as $jogadorTime) {
                $jogadorId = $jogadorTime->jogador_id;

                // Calcula a média do jogador com base nos votos
                $notas = Voto::where('jogador_destino_id', $jogadorId)
                    ->selectRaw('
                        AVG(tecnica) as tecnica,
                        AVG(inteligencia) as inteligencia,
                        AVG(velocidade_preparo) as velocidade_preparo,
                        AVG(disciplina_tatica) as disciplina_tatica,
                        AVG(poder_ofensivo) as poder_ofensivo,
                        AVG(poder_defensivo) as poder_defensivo,
                        AVG(fundamentos_basicos) as fundamentos_basicos
                    ')
                    ->first();

                $notasArray = collect($notas)->filter(fn($n) => !is_null($n))->map(function ($value, $key) {
                    return ['nome' => ucfirst(str_replace('_', ' ', $key)), 'nota' => round($value, 1)];
                })->values();

                $soma = $notasArray->sum('nota');
                $media = $notasArray->count() ? $soma / $notasArray->count() : 0;

                // Adiciona a média no jogador do time
                $jogadorTime->media = round($media, 2);
            }

            // Recalcula a média do time com base nas médias dos jogadores
            $mediaTime = $time->jogadores->pluck('media')->filter()->avg();
            $time->media_calculada = round($mediaTime, 2);
        }

        return response()->json($sorteio);
    }

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
