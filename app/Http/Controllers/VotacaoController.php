<?php

namespace App\Http\Controllers;

use App\Models\Votacao;
use Illuminate\Http\Request;

class VotacaoController extends Controller
{
    public function index()
    {
        // Retorna todas as votações
        return Votacao::orderBy('data_inicio', 'desc')->get();
    }

    public function ativa()
    {
        // Retorna a votação ativa atual
        $hoje = now()->toDateString();
        return Votacao::where('data_inicio', '<=', $hoje)
                      ->where('data_fim', '>=', $hoje)
                      ->where('ativa', true)
                      ->first();
    }

    public function store(Request $request)
    {
        $request->validate([
            'data_inicio' => 'required|date',
            'data_fim' => 'required|date|after_or_equal:data_inicio',
        ]);

        $votacao = Votacao::create([
            'data_inicio' => $request->data_inicio,
            'data_fim' => $request->data_fim,
            'ativa' => true,
        ]);

        return response()->json($votacao, 201);
    }

        public function medias($id)
    {
        $votacao = \App\Models\Votacao::find($id);

        if (!$votacao) {
            return response()->json(['error' => 'Votação não encontrada'], 404);
        }

        $medias = \App\Models\Voto::select(
                'jogador_destino_id',
                \DB::raw('ROUND(AVG(tecnica), 2) as tecnica'),
                \DB::raw('ROUND(AVG(inteligencia), 2) as inteligencia'),
                \DB::raw('ROUND(AVG(velocidade_preparo), 2) as velocidade_preparo'),
                \DB::raw('ROUND(AVG(disciplina_tatica), 2) as disciplina_tatica'),
                \DB::raw('ROUND(AVG(poder_ofensivo), 2) as poder_ofensivo'),
                \DB::raw('ROUND(AVG(poder_defensivo), 2) as poder_defensivo'),
                \DB::raw('ROUND(AVG(fundamentos_basicos), 2) as fundamentos_basicos'),
                \DB::raw('ROUND((
                    AVG(tecnica) +
                    AVG(inteligencia) +
                    AVG(velocidade_preparo) +
                    AVG(disciplina_tatica) +
                    AVG(poder_ofensivo) +
                    AVG(poder_defensivo) +
                    AVG(fundamentos_basicos)
                ) / 7, 2) as media_geral'),
                \DB::raw('COUNT(*) as total_votos')
            )
            ->where('votacao_id', $id)
            ->groupBy('jogador_destino_id')
            ->with('jogadorDestino:id,apelido,nome,posicao')
            ->get()
            ->sortBy(fn ($item) => $item->jogadorDestino->apelido)
            ->values(); // reindexa após sort

        return response()->json($medias);
    }


}
