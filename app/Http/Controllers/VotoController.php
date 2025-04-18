<?php

namespace App\Http\Controllers;

use App\Models\Voto;
use App\Models\Votacao;
use App\Models\Jogador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VotoController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'jogador_destino_id' => 'required|exists:jogadores,id',
            'notas.tecnica' => 'required|numeric|between:1,5',
            'notas.inteligencia' => 'required|numeric|between:1,5',
            'notas.velocidade_preparo' => 'required|numeric|between:1,5',
            'notas.disciplina_tatica' => 'required|numeric|between:1,5',
            'notas.poder_ofensivo' => 'required|numeric|between:1,5',
            'notas.poder_defensivo' => 'required|numeric|between:1,5',
            'notas.fundamentos_basicos' => 'required|numeric|between:1,5',
        ]);

        $votacao = Votacao::where('ativa', true)
            ->where('data_inicio', '<=', now())
            ->where('data_fim', '>=', now())
            ->first();

        if (!$votacao) {
            return response()->json(['error' => 'Nenhuma votação ativa'], 400);
        }

        $user = Auth::user();
        $jogadorOrigem = $user->jogador;

        if (!$jogadorOrigem || $jogadorOrigem->id == $request->jogador_destino_id) {
            return response()->json(['error' => 'Voto inválido'], 403);
        }

        $jaVotou = Voto::where('votacao_id', $votacao->id)
            ->where('jogador_origem_id', $jogadorOrigem->id)
            ->where('jogador_destino_id', $request->jogador_destino_id)
            ->exists();

        if ($jaVotou) {
            return response()->json(['error' => 'Já votou neste jogador'], 409);
        }

        $voto = Voto::create([
            'votacao_id' => $votacao->id,
            'jogador_origem_id' => $jogadorOrigem->id,
            'jogador_destino_id' => $request->jogador_destino_id,
            ...$request->notas,
        ]);

        return response()->json($voto, 201);
    }

    public function meusVotos()
    {
        $user = Auth::user();
        $jogador = $user->jogador;

        if (!$jogador) {
            return response()->json(['error' => 'Jogador não encontrado'], 404);
        }

        $votacao = \App\Models\Votacao::where('ativa', true)
            ->where('data_inicio', '<=', now())
            ->where('data_fim', '>=', now())
            ->first();

        if (!$votacao) {
            return response()->json(['error' => 'Nenhuma votação ativa'], 404);
        }

        $votados = \App\Models\Voto::where('votacao_id', $votacao->id)
            ->where('jogador_origem_id', $jogador->id)
            ->pluck('jogador_destino_id');

        return response()->json($votados);
    }

}
