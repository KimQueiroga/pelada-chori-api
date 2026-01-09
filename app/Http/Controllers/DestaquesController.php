<?php

namespace App\Http\Controllers;

use App\Models\JogadorVitoria;
use App\Models\PartidaGol;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DestaquesController extends Controller
{
    public function mes(Request $request)
    {
        $dataStr = $request->query('data');

        try {
            $ref = $dataStr ? Carbon::parse($dataStr) : now();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Data invalida. Use YYYY-MM-DD.'], 422);
        }

        $inicio = $ref->copy()->startOfMonth()->startOfDay();
        $fim = $ref->copy()->endOfMonth()->endOfDay();

        $limite = (int) $request->query('limite', 5);
        if ($limite < 1) {
            $limite = 5;
        }
        if ($limite > 20) {
            $limite = 20;
        }

        $vitorias = $this->topVitorias($inicio, $fim, $limite);
        $gols = $this->topGols($inicio, $fim, $limite);
        $assistencias = $this->topAssistencias($inicio, $fim, $limite);

        return response()->json([
            'mes_referencia' => $ref->format('Y-m'),
            'periodo' => [
                'inicio' => $inicio->toDateString(),
                'fim' => $fim->toDateString(),
            ],
            'top5' => [
                'vitorias' => $vitorias,
                'gols' => $gols,
                'assistencias' => $assistencias,
            ],
        ]);
    }

    private function topVitorias(Carbon $inicio, Carbon $fim, int $limite)
    {
        $rows = JogadorVitoria::query()
            ->join('partidas', 'partidas.id', '=', 'jogador_vitorias.partida_id')
            ->join('jogadores', 'jogadores.id', '=', 'jogador_vitorias.jogador_id')
            ->whereNotNull('partidas.encerrada_em')
            ->whereBetween('partidas.encerrada_em', [$inicio, $fim])
            ->groupBy('jogador_vitorias.jogador_id', 'jogadores.nome', 'jogadores.apelido')
            ->orderByDesc('total')
            ->orderBy('jogadores.nome')
            ->limit($limite)
            ->get([
                'jogador_vitorias.jogador_id as jogador_id',
                'jogadores.nome',
                'jogadores.apelido',
                DB::raw('COUNT(*) as total'),
            ]);

        return $this->mapTop($rows);
    }

    private function topGols(Carbon $inicio, Carbon $fim, int $limite)
    {
        $rows = PartidaGol::query()
            ->join('jogadores', 'jogadores.id', '=', 'partida_gols.jogador_id')
            ->whereBetween('partida_gols.ocorreu_em', [$inicio, $fim])
            ->groupBy('partida_gols.jogador_id', 'jogadores.nome', 'jogadores.apelido')
            ->orderByDesc('total')
            ->orderBy('jogadores.nome')
            ->limit($limite)
            ->get([
                'partida_gols.jogador_id as jogador_id',
                'jogadores.nome',
                'jogadores.apelido',
                DB::raw('COUNT(*) as total'),
            ]);

        return $this->mapTop($rows);
    }

    private function topAssistencias(Carbon $inicio, Carbon $fim, int $limite)
    {
        $rows = PartidaGol::query()
            ->join('jogadores', 'jogadores.id', '=', 'partida_gols.assist_jogador_id')
            ->whereNotNull('partida_gols.assist_jogador_id')
            ->whereBetween('partida_gols.ocorreu_em', [$inicio, $fim])
            ->groupBy('partida_gols.assist_jogador_id', 'jogadores.nome', 'jogadores.apelido')
            ->orderByDesc('total')
            ->orderBy('jogadores.nome')
            ->limit($limite)
            ->get([
                'partida_gols.assist_jogador_id as jogador_id',
                'jogadores.nome',
                'jogadores.apelido',
                DB::raw('COUNT(*) as total'),
            ]);

        return $this->mapTop($rows);
    }

    private function mapTop($rows)
    {
        return $rows->map(function ($row) {
            $apelido = is_string($row->apelido ?? null) ? trim($row->apelido) : '';
            $nome = $apelido !== '' ? $apelido : ($row->nome ?? ('Jogador '.$row->jogador_id));

            return [
                'jogador_id' => (int) $row->jogador_id,
                'nome' => $nome,
                'valor' => (int) $row->total,
            ];
        })->values();
    }
}
