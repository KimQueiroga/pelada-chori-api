<?php

namespace App\Http\Controllers;

use App\Models\Partida;
use App\Models\PartidaSubstituicao;
use App\Models\SorteioTimeJogador;
use App\Services\PartidaElencoService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PartidaSubstituicaoController extends Controller
{
    public function elenco(Partida $partida)
    {
        return response()->json(PartidaElencoService::elencoAtivo($partida));
    }

    public function store(Request $request, Partida $partida)
    {
        $request->validate([
            'time_id' => ['required','integer', Rule::exists('sorteio_times','id')],
            'jogador_sai_id' => ['required','integer', Rule::exists('jogadores','id')],
            'jogador_entra_id' => ['required','integer', Rule::exists('jogadores','id'), 'different:jogador_sai_id'],
        ]);

        if (!$partida->isAndamento()) {
            return response()->json(['message' => 'So e possivel substituir com a partida em andamento.'], 422);
        }

        $timeId = (int)$request->time_id;
        if (!in_array($timeId, [$partida->time_a_id, $partida->time_b_id], true)) {
            return response()->json(['message' => 'Substituicao deve ser de um dos times da partida.'], 422);
        }

        $jogadorSai = (int)$request->jogador_sai_id;
        $jogadorEntra = (int)$request->jogador_entra_id;

        $pertenceSai = SorteioTimeJogador::where('sorteio_time_id', $timeId)
            ->where('jogador_id', $jogadorSai)
            ->exists();
        if (!$pertenceSai) {
            return response()->json(['message' => 'Jogador substituido nao pertence a este time.'], 422);
        }

        $timesDoSorteio = \App\Models\SorteioTime::where('sorteio_id', $partida->sorteio_id)->pluck('id');
        $pertenceSorteio = SorteioTimeJogador::whereIn('sorteio_time_id', $timesDoSorteio)
            ->where('jogador_id', $jogadorEntra)
            ->exists();
        if (!$pertenceSorteio) {
            return response()->json(['message' => 'Jogador substituto nao pertence a este sorteio.'], 422);
        }

        $elenco = PartidaElencoService::elencoAtivo($partida);
        $idsAtivos = PartidaElencoService::idsAtivos($elenco);
        $idsTime = ($timeId === $partida->time_a_id) ? $idsAtivos['time_a'] : $idsAtivos['time_b'];

        if (!in_array($jogadorSai, $idsTime, true)) {
            return response()->json(['message' => 'Jogador substituido nao esta ativo neste time.'], 422);
        }

        if (in_array($jogadorEntra, $idsAtivos['all'], true)) {
            return response()->json(['message' => 'Jogador substituto ja esta ativo em uma das equipes.'], 422);
        }

        PartidaSubstituicao::create([
            'partida_id' => $partida->id,
            'time_id' => $timeId,
            'jogador_sai_id' => $jogadorSai,
            'jogador_entra_id' => $jogadorEntra,
        ]);

        return response()->json(PartidaElencoService::elencoAtivo($partida));
    }

    public function desfazer(Partida $partida, PartidaSubstituicao $substituicao)
    {
        if ($substituicao->partida_id !== $partida->id) {
            return response()->json(['message' => 'Substituicao nao pertence a partida.'], 422);
        }

        if (!$partida->isAndamento()) {
            return response()->json(['message' => 'So e possivel desfazer com a partida em andamento.'], 422);
        }

        if ($substituicao->revertida_em === null) {
            $substituicao->update(['revertida_em' => now()]);
        }

        return response()->json(PartidaElencoService::elencoAtivo($partida));
    }
}
