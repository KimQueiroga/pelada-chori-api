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

    public function analitico(Request $request)
    {
        $estatistica = $request->query('estatistica', 'vitorias');
        $permitidas = ['vitorias', 'gols', 'assistencias'];
        if (!in_array($estatistica, $permitidas, true)) {
            return response()->json(['error' => 'Estatistica invalida.'], 422);
        }

        $ano = $request->query('ano');
        $mes = $request->query('mes');
        $jogadorId = $request->query('jogador_id');

        $limite = (int) $request->query('limite', 50);
        if ($limite < 1) {
            $limite = 1;
        }
        if ($limite > 100) {
            $limite = 100;
        }

        $inicio = null;
        $fim = null;

        if ($ano || $mes) {
            $ano = $ano ? (int) $ano : (int) now()->year;
            if ($mes !== null && $mes !== '') {
                $mes = (int) $mes;
                if ($mes < 1 || $mes > 12) {
                    return response()->json(['error' => 'Mes invalido.'], 422);
                }
                $ref = Carbon::create($ano, $mes, 1);
                $inicio = $ref->copy()->startOfMonth()->startOfDay();
                $fim = $ref->copy()->endOfMonth()->endOfDay();
            } else {
                $ref = Carbon::create($ano, 1, 1);
                $inicio = $ref->copy()->startOfYear()->startOfDay();
                $fim = $ref->copy()->endOfYear()->endOfDay();
            }
        }

        $jogadorId = $jogadorId ? (int) $jogadorId : null;

        if ($estatistica === 'vitorias') {
            $items = $this->rankingVitorias($inicio, $fim, $jogadorId, $limite);
        } elseif ($estatistica === 'gols') {
            $items = $this->rankingGols($inicio, $fim, $jogadorId, $limite);
        } else {
            $items = $this->rankingAssistencias($inicio, $fim, $jogadorId, $limite);
        }

        return response()->json([
            'filtros' => [
                'ano' => $ano ? (int) $ano : null,
                'mes' => $mes !== null && $mes !== '' ? (int) $mes : null,
                'jogador_id' => $jogadorId,
                'estatistica' => $estatistica,
            ],
            'items' => $items,
        ]);
    }

    private function topVitorias(Carbon $inicio, Carbon $fim, int $limite)
    {
        $base = JogadorVitoria::query()
            ->join('partidas', 'partidas.id', '=', 'jogador_vitorias.partida_id')
            ->whereNotNull('partidas.encerrada_em')
            ->whereBetween('partidas.encerrada_em', [$inicio, $fim])
            ->groupBy('jogador_vitorias.jogador_id')
            ->select([
                'jogador_vitorias.jogador_id as jogador_id',
                DB::raw('COUNT(*) as total'),
            ]);

        $extra = $this->extraStatsQuery('vitorias', $inicio, $fim);
        $rows = $this->mergeStats($base, $extra, null, $limite);

        return $this->mapTop($rows);
    }

    private function rankingVitorias($inicio, $fim, ?int $jogadorId, int $limite)
    {
        $base = JogadorVitoria::query()
            ->join('partidas', 'partidas.id', '=', 'jogador_vitorias.partida_id')
            ->whereNotNull('partidas.encerrada_em')
            ->groupBy('jogador_vitorias.jogador_id')
            ->select([
                'jogador_vitorias.jogador_id as jogador_id',
                DB::raw('COUNT(*) as total'),
            ]);

        if ($inicio && $fim) {
            $base->whereBetween('partidas.encerrada_em', [$inicio, $fim]);
        }

        $extra = $this->extraStatsQuery('vitorias', $inicio, $fim);
        $rows = $this->mergeStats($base, $extra, $jogadorId, $limite);

        return $this->mapTop($rows);
    }

    private function topGols(Carbon $inicio, Carbon $fim, int $limite)
    {
        $base = PartidaGol::query()
            ->whereBetween('partida_gols.ocorreu_em', [$inicio, $fim])
            ->groupBy('partida_gols.jogador_id')
            ->select([
                'partida_gols.jogador_id as jogador_id',
                DB::raw('COUNT(*) as total'),
            ]);

        $extra = $this->extraStatsQuery('gols', $inicio, $fim);
        $rows = $this->mergeStats($base, $extra, null, $limite);

        return $this->mapTop($rows);
    }

    private function rankingGols($inicio, $fim, ?int $jogadorId, int $limite)
    {
        $base = PartidaGol::query()
            ->groupBy('partida_gols.jogador_id')
            ->select([
                'partida_gols.jogador_id as jogador_id',
                DB::raw('COUNT(*) as total'),
            ]);

        if ($inicio && $fim) {
            $base->whereBetween('partida_gols.ocorreu_em', [$inicio, $fim]);
        }

        $extra = $this->extraStatsQuery('gols', $inicio, $fim);
        $rows = $this->mergeStats($base, $extra, $jogadorId, $limite);

        return $this->mapTop($rows);
    }

    private function topAssistencias(Carbon $inicio, Carbon $fim, int $limite)
    {
        $base = PartidaGol::query()
            ->whereNotNull('partida_gols.assist_jogador_id')
            ->whereBetween('partida_gols.ocorreu_em', [$inicio, $fim])
            ->groupBy('partida_gols.assist_jogador_id')
            ->select([
                'partida_gols.assist_jogador_id as jogador_id',
                DB::raw('COUNT(*) as total'),
            ]);

        $extra = $this->extraStatsQuery('assistencias', $inicio, $fim);
        $rows = $this->mergeStats($base, $extra, null, $limite);

        return $this->mapTop($rows);
    }

    private function rankingAssistencias($inicio, $fim, ?int $jogadorId, int $limite)
    {
        $base = PartidaGol::query()
            ->whereNotNull('partida_gols.assist_jogador_id')
            ->groupBy('partida_gols.assist_jogador_id')
            ->select([
                'partida_gols.assist_jogador_id as jogador_id',
                DB::raw('COUNT(*) as total'),
            ]);

        if ($inicio && $fim) {
            $base->whereBetween('partida_gols.ocorreu_em', [$inicio, $fim]);
        }

        $extra = $this->extraStatsQuery('assistencias', $inicio, $fim);
        $rows = $this->mergeStats($base, $extra, $jogadorId, $limite);

        return $this->mapTop($rows);
    }

    private function extraStatsQuery(string $coluna, ?Carbon $inicio, ?Carbon $fim)
    {
        $permitidas = ['gols', 'assistencias', 'vitorias'];
        if (!in_array($coluna, $permitidas, true)) {
            throw new \InvalidArgumentException('Coluna invalida.');
        }

        $q = DB::table('jogador_estatisticas_extras')
            ->select('jogador_id', DB::raw('SUM('.$coluna.') as total'))
            ->groupBy('jogador_id');

        if ($inicio && $fim) {
            $q->whereBetween('data_referencia', [
                $inicio->toDateString(),
                $fim->toDateString(),
            ]);
        }

        return $q;
    }

    private function mergeStats($baseQuery, $extraQuery, ?int $jogadorId, int $limite)
    {
        $union = $baseQuery->unionAll($extraQuery);

        $q = DB::query()
            ->fromSub($union, 'stats')
            ->join('jogadores', 'jogadores.id', '=', 'stats.jogador_id')
            ->groupBy('stats.jogador_id', 'jogadores.nome', 'jogadores.apelido')
            ->orderByDesc('total')
            ->orderBy('jogadores.nome')
            ->limit($limite)
            ->select([
                'stats.jogador_id as jogador_id',
                'jogadores.nome',
                'jogadores.apelido',
                DB::raw('SUM(stats.total) as total'),
            ]);

        if ($jogadorId) {
            $q->where('stats.jogador_id', $jogadorId);
        }

        return $q->get();
    }

    private function mapTop($rows)
    {
        $items = [];
        $posicaoAtual = 0;
        $ultimoValor = null;

        foreach ($rows as $index => $row) {
            $valor = (int) $row->total;
            if ($ultimoValor === null || $valor < $ultimoValor) {
                $posicaoAtual = $index + 1;
            }

            $apelido = is_string($row->apelido ?? null) ? trim($row->apelido) : '';
            $nome = $apelido !== '' ? $apelido : ($row->nome ?? ('Jogador '.$row->jogador_id));

            $items[] = [
                'jogador_id' => (int) $row->jogador_id,
                'nome' => $nome,
                'valor' => $valor,
                'posicao' => $posicaoAtual,
            ];

            $ultimoValor = $valor;
        }

        return collect($items);
    }
}
