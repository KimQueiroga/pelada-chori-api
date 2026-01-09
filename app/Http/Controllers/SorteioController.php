<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;      // se estiver usando DB::transaction
use Carbon\Carbon;                      // <-- ADICIONE ESTE
use App\Models\Sorteio;
use App\Models\SorteioTime;
use App\Models\SorteioTimeJogador;
use App\Models\Voto;
use App\Models\SorteioVoto;
use App\Services\DrawService;
use App\DTOs\Player;


use App\Models\Jogador;


class SorteioController extends Controller
{
        public function show($id)
    {
        $sorteio = Sorteio::with([
            'times.jogadores.jogador.user'
        ])->findOrFail($id);

        foreach ($sorteio->times as $time) {
            // se pivot.media já veio do sorteio, usa; senão calcula on-demand pelos votos
            foreach ($time->jogadores as $jogadorTime) {
                if ($jogadorTime->media !== null) {
                    // já persistido no sorteio
                    $jogadorTime->media = round((float)$jogadorTime->media, 2);
                    continue;
                }

                // cálculo legacy pelos votos
                $jogadorId = $jogadorTime->jogador_id;

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

                $valores = collect([
                    $notas->tecnica,
                    $notas->inteligencia,
                    $notas->velocidade_preparo,
                    $notas->disciplina_tatica,
                    $notas->poder_ofensivo,
                    $notas->poder_defensivo,
                    $notas->fundamentos_basicos,
                ])->filter(fn($n) => !is_null($n));

                $media = $valores->count() ? ($valores->avg()) : 0.0;
                $jogadorTime->media = round($media, 2);
            }

            // média do time = média das médias persistidas / calculadas dos jogadores
            $mediaTime = $time->jogadores->pluck('media')->filter()->avg();
            $time->media_calculada = round((float)$mediaTime, 2);
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
        return Sorteio::whereDate('data', '>=', $hoje)
            ->where('em_votacao', true)
            ->with('times.jogadores')
            ->orderBy('data')
            ->orderBy('numero')
            ->get();
    }


        // ... seus métodos show, index, store, ativos

    /**
     * Rota única que:
     * 1) cria dois sorteios na mesma data (nº 1 e nº 2)
     * 2) cria os times em cada sorteio
     * 3) distribui automaticamente os jogadores
     *
     * Body esperado (JSON):
     * {
     *   "data": "2025-06-25",
     *   "descricao": "Sorteio semanal",
     *   "quantidade_times": 3,
     *   "quantidade_jogadores_time": 5,
     *   "jogadores_ids": [1,2,3,4,5,6,7,8,9,10,11,...],
     *   "estrategia": "balanceado" | "random"    // opcional, default "balanceado"
     * }
     */
    public function storeDuploCompleto(Request $request, DrawService $draw)
    {
        $request->validate([
            'data'                         => 'required|date',
            'descricao'                    => 'nullable|string',
            'quantidade_times'             => 'required|integer|min:2',
            'quantidade_jogadores_time'    => 'required|integer|min:1',

            // NOVO formato (preferível)
            'jogadores'                    => 'nullable|array|min:1',
            'jogadores.*.id'               => 'required_with:jogadores|integer|exists:jogadores,id',
            'jogadores.*.media'            => 'nullable|numeric|min:0|max:5',

            // Formato antigo (retrocompat.)
            'jogadores_ids'                => 'nullable|array|min:1',
            'jogadores_ids.*'              => 'integer|exists:jogadores,id',

            // Opcional: exigir média manual se não houver votos
            'require_media_for_unrated'    => 'sometimes|boolean',

            // opcional: controle fino do DrawService
            'limite'                       => 'nullable|numeric',
        ]);

        $data      = Carbon::parse($request->input('data'))->toDateString();
        $qtTimes   = (int) $request->input('quantidade_times');
        $qtPorTime = (int) $request->input('quantidade_jogadores_time');
        $limit     = (float) $request->input('limite', 0.10);
        $strict    = (bool)  $request->input('require_media_for_unrated', false);

        // Normaliza entrada para um array de [{id, media?}]
        $entrada = collect($request->input('jogadores', []))
            ->map(fn($j) => ['id' => (int)$j['id'], 'media' => array_key_exists('media',$j) ? (float)$j['media'] : null])
            ->values()
            ->all();

        if (empty($entrada)) {
            // retrocompat: veio jogadores_ids
            $ids = (array) $request->input('jogadores_ids', []);
            $entrada = collect($ids)->map(fn($id) => ['id' => (int)$id, 'media' => null])->all();
        }

        if (empty($entrada)) {
            return response()->json(['error' => 'Informe os jogadores.'], 422);
        }

        // Capacidade exata (mesmo comportamento que você tinha)
        $cap = $qtTimes * $qtPorTime;
        if (count($entrada) !== $cap) {
            return response()->json([
                'error' =>
                    "Quantidade de jogadores (".count($entrada).") deve ser exatamente igual à capacidade ($cap)."
            ], 422);
        }

        $ids       = array_map(fn($j) => $j['id'], $entrada);
        $overrides = collect($entrada)->mapWithKeys(fn($j) => [$j['id'] => $j['media']])->all();

        // 1) médias vindas dos votos (apenas para quem tem votos)
        $ratingsFromVotes = $this->calcularNotasJogadoresNullable($ids); // <— NOVO helper
        $default = 3.00;

        // 2) resolve a média a ser USADA no sorteio (override > votos > default)
        $mediaUsada = [];
        $semMedia   = [];

        foreach ($ids as $id) {
            if (array_key_exists($id, $overrides) && $overrides[$id] !== null) {
                $mediaUsada[$id] = round((float)$overrides[$id], 2);
            } elseif (array_key_exists($id, $ratingsFromVotes)) {
                $mediaUsada[$id] = round((float)$ratingsFromVotes[$id], 2);
            } else {
                if ($strict) {
                    $semMedia[] = $id;
                } else {
                    $mediaUsada[$id] = $default;
                }
            }
        }

        if ($strict && !empty($semMedia)) {
            $nomes = Jogador::whereIn('id', $semMedia)->pluck('nome')->implode(', ');
            return response()->json([
                'error' => 'Jogadores sem média (forneça "media" no request ou registre votos): '.$nomes,
                'ids'   => $semMedia
            ], 422);
        }

        // 3) carrega dados básicos para montar os DTOs Player
        $dadosJog = Jogador::whereIn('id', $ids)
            ->select('id','nome','apelido','posicao')
            ->get()
            ->keyBy('id');

        $players = [];
        foreach ($ids as $id) {
            $j    = $dadosJog[$id];
            $nome = trim($j->apelido ?: $j->nome ?: ('Jogador '.$id));
            $pos  = strtoupper($j->posicao ?? 'MEIO');
            if (!in_array($pos, ['DEFESA','MEIO','ATAQUE'])) $pos = 'MEIO';

            $players[] = new Player($id, $nome, $mediaUsada[$id], $pos);
        }

        // Próxima tentativa do dia
        $nextTentativa = (Sorteio::whereDate('data', $data)->max('tentativa') ?? 0) + 1;

        // Gera os dois sorteios via DrawService
        try {
            $sorteio1Teams = $draw->makeSorteio1($players, $qtTimes, $qtPorTime, $limit);
            $sorteio2Teams = $draw->makeSorteio2($players, $qtTimes, $qtPorTime, $limit);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        DB::beginTransaction();
        try {
            // nº 1
            $s1 = Sorteio::create([
                'data'                         => $data,
                'descricao'                    => $request->input('descricao'),
                'numero'                       => 1,
                'quantidade_times'             => $qtTimes,
                'quantidade_jogadores_time'    => $qtPorTime,
                'tentativa'                    => $nextTentativa,
                'status'                       => 'rascunho',
                'em_votacao'                   => false,
            ]);
            $this->persistTeamsFromDTO($s1, $sorteio1Teams); // grava pivot.media = Player->media

            // nº 2
            $s2 = Sorteio::create([
                'data'                         => $data,
                'descricao'                    => $request->input('descricao'),
                'numero'                       => 2,
                'quantidade_times'             => $qtTimes,
                'quantidade_jogadores_time'    => $qtPorTime,
                'tentativa'                    => $nextTentativa,
                'status'                       => 'rascunho',
                'em_votacao'                   => false,
            ]);
            $this->persistTeamsFromDTO($s2, $sorteio2Teams);

            DB::commit();

            return response()->json([
                'message'   => 'Par de sorteios criado como rascunho.',
                'tentativa' => $nextTentativa,
                'sorteio_1' => $s1->load('times.jogadores'),
                'sorteio_2' => $s2->load('times.jogadores'),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    /**
     * Calcula a média global do jogador com base nos votos.
     * Retorna array [jogador_id => media_float]
     */
    private function mediasJogadores(array $jogadorIds): array
    {
        if (empty($jogadorIds)) return [];

        $aggs = Voto::whereIn('jogador_destino_id', $jogadorIds)
            ->selectRaw('
                jogador_destino_id,
                AVG(tecnica) as tecnica,
                AVG(inteligencia) as inteligencia,
                AVG(velocidade_preparo) as velocidade_preparo,
                AVG(disciplina_tatica) as disciplina_tatica,
                AVG(poder_ofensivo) as poder_ofensivo,
                AVG(poder_defensivo) as poder_defensivo,
                AVG(fundamentos_basicos) as fundamentos_basicos
            ')
            ->groupBy('jogador_destino_id')
            ->get()
            ->keyBy('jogador_destino_id');

        $out = [];
        foreach ($jogadorIds as $id) {
            $n = $aggs->get($id);
            if (!$n) { $out[$id] = 0.0; continue; }

            $valores = collect([
                $n->tecnica, $n->inteligencia, $n->velocidade_preparo, $n->disciplina_tatica,
                $n->poder_ofensivo, $n->poder_defensivo, $n->fundamentos_basicos,
            ])->filter(fn($v) => !is_null($v));

            $media = $valores->count() ? $valores->avg() : 0.0;
            $out[$id] = round($media, 2);
        }
        return $out;
        // (se quiser inclusive buscar jogadores sem votos e setar 0, isso já cobre)
    }

    /**
     * Distribuição por serpentina balanceada.
     * Retorna: [numTime => [ ['id'=>, 'media'=>], ... ], ...]
     */
    private function distribuirBalanceadoSerpentina(array $jogadoresOrdenados, int $qtTimes, int $qtPorTime): array
    {
        $times = [];
        for ($i = 1; $i <= $qtTimes; $i++) $times[$i] = [];

        $idx = 0;
        $totalCap = $qtTimes * $qtPorTime;
        $players = array_slice($jogadoresOrdenados, 0, $totalCap);

        // serpentina: ida e volta
        while (!empty($players)) {
            // ida
            for ($t = 1; $t <= $qtTimes; $t++) {
                if (empty($players)) break;
                if (count($times[$t]) < $qtPorTime) {
                    $times[$t][] = array_shift($players);
                }
            }
            // volta
            for ($t = $qtTimes; $t >= 1; $t--) {
                if (empty($players)) break;
                if (count($times[$t]) < $qtPorTime) {
                    $times[$t][] = array_shift($players);
                }
            }
        }

        return $times;
    }

    /**
     * Distribuição aleatória com semente fixa.
     */
    private function distribuirRandom(array $jogadoresOrdenados, int $qtTimes, int $qtPorTime, int $seed): array
    {
        $times = [];
        for ($i = 1; $i <= $qtTimes; $i++) $times[$i] = [];

        $cap = $qtTimes * $qtPorTime;
        $pool = array_slice($jogadoresOrdenados, 0, $cap);

        // embaralha determinístico
        mt_srand($seed);
        usort($pool, function($a, $b) { return mt_rand(-1,1); });

        $ptr = 0;
        for ($i = 0; $i < $qtPorTime; $i++) {
            for ($t = 1; $t <= $qtTimes; $t++) {
                if ($ptr >= count($pool)) break 2;
                $times[$t][] = $pool[$ptr++];
            }
        }

        return $times;
    }

    // SorteioController.php
    public function publicarPar(Request $request)
    {
        $request->validate([
            'sorteio_id_1' => 'required|integer|exists:sorteios,id',
            'sorteio_id_2' => 'required|integer|exists:sorteios,id',
        ]);

        $s1 = Sorteio::with('times')->findOrFail($request->sorteio_id_1);
        $s2 = Sorteio::with('times')->findOrFail($request->sorteio_id_2);

        if ($s1->data->toDateString() !== $s2->data->toDateString()) {
            return response()->json(['error' => 'Os dois sorteios devem ser do mesmo dia.'], 422);
        }
        if ($s1->tentativa !== $s2->tentativa) {
            return response()->json(['error' => 'Os dois sorteios devem ser da mesma tentativa.'], 422);
        }
        if (!in_array($s1->numero,[1,2]) || !in_array($s2->numero,[1,2]) || $s1->numero === $s2->numero) {
            return response()->json(['error' => 'Deve haver um nº1 e um nº2.'], 422);
        }

        DB::beginTransaction();
        try {
            $data = $s1->data->toDateString();

            // despublica qualquer votação do mesmo dia
            Sorteio::whereDate('data', $data)->update([
                'em_votacao' => false,
                'status'     => DB::raw("CASE WHEN status='em_votacao' THEN 'encerrado' ELSE status END"),
            ]);

            // publica o par
            $s1->update(['em_votacao' => true, 'status' => 'em_votacao']);
            $s2->update(['em_votacao' => true, 'status' => 'em_votacao']);

            DB::commit();

            return response()->json([
                'message'    => 'Par publicado para votação com sucesso.',
                'data'       => $data,
                'tentativa'  => $s1->tentativa,
                'publicados' => [$s1->id, $s2->id],
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function porData(Request $request)
    {
        $dataStr = $request->query('data', now()->toDateString());
        $somenteVotacao = filter_var($request->query('somente_votacao', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $data = Carbon::parse($dataStr)->toDateString();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Data inválida. Use YYYY-MM-DD.'], 422);
        }

        $q = Sorteio::whereDate('data', $data)->with('times.jogadores')->orderBy('numero');
        if ($somenteVotacao) {
            $q->where('em_votacao', true);
        }
        return response()->json($q->get());
    }

    /**
 * Calcula a nota média de cada jogador (média das 7 métricas de Voto).
 * Para quem não tem votos, usa 3.0 como padrão.
 *
 * @param  int[] $ids
 * @return array<int,float> mapa [jogador_id => nota_media]
 */
    private function calcularNotasJogadores(array $ids): array
    {
        if (empty($ids)) return [];

        // Default 3.0 para todos
        $notaPorId = array_fill_keys($ids, 3.0);

        // Agrupa e calcula AVG de cada métrica por jogador_destino_id
        $registros = Voto::select(
                'jogador_destino_id',
                DB::raw('AVG(tecnica) as tecnica'),
                DB::raw('AVG(inteligencia) as inteligencia'),
                DB::raw('AVG(velocidade_preparo) as velocidade_preparo'),
                DB::raw('AVG(disciplina_tatica) as disciplina_tatica'),
                DB::raw('AVG(poder_ofensivo) as poder_ofensivo'),
                DB::raw('AVG(poder_defensivo) as poder_defensivo'),
                DB::raw('AVG(fundamentos_basicos) as fundamentos_basicos')
            )
            ->whereIn('jogador_destino_id', $ids)
            ->groupBy('jogador_destino_id')
            ->get();

        foreach ($registros as $r) {
            $valores = array_filter([
                $r->tecnica,
                $r->inteligencia,
                $r->velocidade_preparo,
                $r->disciplina_tatica,
                $r->poder_ofensivo,
                $r->poder_defensivo,
                $r->fundamentos_basicos,
            ], fn($v) => !is_null($v));

            if (count($valores) > 0) {
                $notaPorId[$r->jogador_destino_id] = array_sum($valores) / count($valores);
            }
        }

        return $notaPorId;
    }

    /**
 * Retorna mapa [jogador_id => média] SOMENTE para quem tem votos.
 * Quem não tem votos não entra no array (permite detectar convidados).
 */
    private function calcularNotasJogadoresNullable(array $ids): array
    {
        if (empty($ids)) return [];

        $registros = Voto::select(
                'jogador_destino_id',
                DB::raw('AVG(tecnica) as tecnica'),
                DB::raw('AVG(inteligencia) as inteligencia'),
                DB::raw('AVG(velocidade_preparo) as velocidade_preparo'),
                DB::raw('AVG(disciplina_tatica) as disciplina_tatica'),
                DB::raw('AVG(poder_ofensivo) as poder_ofensivo'),
                DB::raw('AVG(poder_defensivo) as poder_defensivo'),
                DB::raw('AVG(fundamentos_basicos) as fundamentos_basicos')
            )
            ->whereIn('jogador_destino_id', $ids)
            ->groupBy('jogador_destino_id')
            ->get()
            ->keyBy('jogador_destino_id');

        $out = [];
        foreach ($registros as $id => $r) {
            $vals = array_filter([
                $r->tecnica,
                $r->inteligencia,
                $r->velocidade_preparo,
                $r->disciplina_tatica,
                $r->poder_ofensivo,
                $r->poder_defensivo,
                $r->fundamentos_basicos,
            ], fn($v) => !is_null($v));

            if (count($vals) > 0) {
                $out[$id] = round(array_sum($vals)/count($vals), 2);
            }
        }
        return $out;
    }

    /**
 * Monta times equilibrados via snake-draft com leve aleatoriedade por "tier".
 *
 * @param  int[]              $ids
 * @param  array<int,float>   $ratings  mapa [jogador_id => nota_media]
 * @return array<int,array{nome:string,jogadores:int[]}>
 */
    /**
 * Monta times equilibrados por posição (Defesa/Meio/Ataque) + snake-draft com leve aleatoriedade.
 *
 * Regras:
 *  - Se houver jogadores suficientes por posição, cada time recebe ao menos 1 Defesa, 1 Meio e 1 Ataque.
 *  - Em seguida, preenche as vagas restantes com snake-draft por tiers, preservando equilíbrio por rating.
 *  - Jogadores sem posição caem em "Outros" e são distribuídos no preenchimento geral.
 *
 * @param  int[]              $ids                 Lista de IDs de jogadores selecionados
 * @param  array<int,float>   $ratings            mapa [jogador_id => nota_media]
 * @param  int                $qtdTimes
 * @param  int                $qtdPorTime
 * @param  int|null           $seed               Semente opcional para variação reprodutível
 * @return array<int,array{nome:string,jogadores:int[]}>
 */
    private function montarTimes(array $ids, array $ratings, int $qtdTimes, int $qtdPorTime, ?int $seed = null): array
    {
        if ($seed !== null) {
            mt_srand($seed);
        }

        // Capacidade total
        $cap = $qtdTimes * $qtdPorTime;
        if (count($ids) > $cap) {
            $ids = array_slice($ids, 0, $cap);
        }

        // Mapa de posições (id => 'Defesa'|'Meio'|'Ataque'|null)
        $posicoes = Jogador::whereIn('id', $ids)->pluck('posicao', 'id')->toArray();

        // Buckets de times
        // - ids: jogadores já alocados
        // - pos: contagem por posição (para debug/garantia)
        $buckets = [];
        for ($i = 0; $i < $qtdTimes; $i++) {
            $buckets[$i] = [
                'ids' => [],
                'pos' => ['Defesa' => 0, 'Meio' => 0, 'Ataque' => 0],
            ];
        }

        // Grupos por posição
        $grupos = [
            'Defesa' => [],
            'Meio'   => [],
            'Ataque' => [],
            'Outros' => [], // nulos / qualquer outro valor
        ];

        foreach ($ids as $id) {
            $p = $posicoes[$id] ?? null;
            if ($p === 'Defesa' || $p === 'Meio' || $p === 'Ataque') {
                $grupos[$p][] = $id;
            } else {
                $grupos['Outros'][] = $id;
            }
        }

        // Ordena cada grupo por rating desc (empate: aleatório leve)
        $ordenador = function (&$arr) use ($ratings) {
            usort($arr, function ($a, $b) use ($ratings) {
                $cmp = ($ratings[$b] ?? 3.0) <=> ($ratings[$a] ?? 3.0);
                if ($cmp !== 0) return $cmp;
                return (mt_rand(0, 1) === 0) ? -1 : 1;
            });
        };
        foreach (['Defesa', 'Meio', 'Ataque', 'Outros'] as $g) {
            $ordenador($grupos[$g]);
        }

        // 1) PASSO DE OBRIGATÓRIOS POR POSIÇÃO (se houver lastro suficiente)
        //    Tenta alocar 1 Defesa, 1 Meio e 1 Ataque por time, em serpentina.
        $sentido = 1; // 1 = 0..N-1 / -1 = N-1..0
        foreach (['Defesa', 'Meio', 'Ataque'] as $pos) {
            if (empty($grupos[$pos])) {
                continue; // não há ninguém nessa posição; pula
            }

            $indices = range(0, $qtdTimes - 1);
            if ($sentido === -1) {
                $indices = array_reverse($indices);
            }

            foreach ($indices as $idxTime) {
                if (empty($grupos[$pos])) {
                    break; // acabou o estoque da posição
                }
                if (count($buckets[$idxTime]['ids']) >= $qtdPorTime) {
                    continue; // time já está cheio
                }

                // Aloca o melhor (topo) disponível da posição
                $playerId = array_shift($grupos[$pos]);
                // Evita duplicidade por segurança
                if (in_array($playerId, $buckets[$idxTime]['ids'], true)) {
                    continue;
                }

                $buckets[$idxTime]['ids'][] = $playerId;
                $buckets[$idxTime]['pos'][$pos] += 1;
            }

            $sentido *= -1; // serpentina
        }

        // 2) PREENCHIMENTO GERAL (snake-draft por tiers, respeitando capacidade)
        // Junta todos os remanescentes (ordem por rating já aplicada)
        $restantes = array_merge($grupos['Defesa'], $grupos['Meio'], $grupos['Ataque'], $grupos['Outros']);
        $ordenador($restantes); // reordena geral para manter o critério de força

        // Snake por tiers do tamanho do nº de times
        $tiers = array_chunk($restantes, $qtdTimes);
        $sentido = 1;
        foreach ($tiers as $tier) {
            shuffle($tier); // aleatoriza dentro do tier

            $ordem = range(0, $qtdTimes - 1);
            if ($sentido === -1) $ordem = array_reverse($ordem);

            $i = 0;
            foreach ($ordem as $idxTime) {
                if (!isset($tier[$i])) break;
                if (count($buckets[$idxTime]['ids']) < $qtdPorTime) {
                    $buckets[$idxTime]['ids'][] = $tier[$i];
                } else {
                    // tenta colocar em outro time que ainda tenha vaga
                    $alocado = false;
                    foreach ($ordem as $altIdx) {
                        if (count($buckets[$altIdx]['ids']) < $qtdPorTime) {
                            $buckets[$altIdx]['ids'][] = $tier[$i];
                            $alocado = true;
                            break;
                        }
                    }
                    // se não conseguiu alocar, descarta (capacidade total atingido
                }
                $i++;
            }

            $sentido *= -1;
        }

        // 3) Monta retorno
        $result = [];
        for ($i = 0; $i < $qtdTimes; $i++) {
            $result[] = [
                'nome'      => 'Time ' . ($i + 1),
                'jogadores' => $buckets[$i]['ids'],
            ];
        }

        return $result;
    }


    /**
 * Gera duas distribuições distintas (para nº1 e nº2) usando seeds diferentes.
 *
 * @return array{0:array,1:array} [timesSorteio1, timesSorteio2]
 */
    private function distribuirDuplo(array $jogadoresIds, int $qtdTimes, int $qtdPorTime): array
    {
        $ratings = $this->calcularNotasJogadores($jogadoresIds);

        $seed1 = random_int(1, PHP_INT_MAX);
        $seed2 = random_int(1, PHP_INT_MAX);
        while ($seed2 === $seed1) {
            $seed2 = random_int(1, PHP_INT_MAX);
        }

        $times1 = $this->montarTimes($jogadoresIds, $ratings, $qtdTimes, $qtdPorTime, $seed1);
        $times2 = $this->montarTimes($jogadoresIds, $ratings, $qtdTimes, $qtdPorTime, $seed2);

        return [$times1, $times2];
    }

        /**
     * Persiste os times e jogadores no banco.
     *
     * @param  \App\Models\Sorteio $sorteio
     * @param  array<int,array{nome:string,jogadores:int[]}> $timesDistribuidos
     */
    private function persistirTimesEJogadores(Sorteio $sorteio, array $timesDistribuidos): void
    {
        foreach ($timesDistribuidos as $t) {
            $time = SorteioTime::create([
                'sorteio_id' => $sorteio->id,
                'nome'       => $t['nome'],
                'media'      => null, // calculada depois/na exibição
            ]);

            foreach ($t['jogadores'] as $jogadorId) {
                SorteioTimeJogador::create([
                    'sorteio_time_id' => $time->id,
                    'jogador_id'      => $jogadorId,
                    'media'           => null, // opcional; calculamos on-demand
                ]);
            }
        }
    }

    /**
 * Persiste times/jogadores a partir dos DTOs Team/Player
 * @param \App\Models\Sorteio $sorteio
 * @param \App\DTOs\Team[] $teams
 */
    private function persistTeamsFromDTO(\App\Models\Sorteio $sorteio, array $teams): void
    {
        foreach ($teams as $i => $team) {
            $time = \App\Models\SorteioTime::create([
                'sorteio_id' => $sorteio->id,
                'nome'       => 'Time '.($i + 1),
                'media'      => round($team->avg(), 2),
            ]);

            foreach ($team->players as $p) {
                \App\Models\SorteioTimeJogador::create([
                    'sorteio_time_id' => $time->id,
                    'jogador_id'      => $p->id,
                    'media'           => $p->media, // opcional
                ]);
            }
        }
    }




    public function rascunhosDoDia(Request $request)
    {
        $data = $request->query('data', Carbon::today()->toDateString());

        $rascunhos = Sorteio::with(['times.jogadores.jogador.user'])
            ->whereDate('data', $data)
            ->where('status', 'rascunho')
            ->orderBy('tentativa')          // 1 e 2
            ->get();

        return response()->json($rascunhos);
    }
    public function publicarDupla(Request $request)
    {
        $request->validate([
            'data' => 'required|date',
        ]);

        $data = Carbon::parse($request->input('data'))->toDateString();

        // Busca as últimas 2 tentativas em rascunho desse dia
        $dupla = Sorteio::whereDate('data', $data)
            ->where('status', 'rascunho')
            ->orderByDesc('tentativa')      // caso haja mais de uma dupla, pega a mais recente
            ->take(2)
            ->get();

        if ($dupla->count() !== 2) {
            return response()->json(['message' => 'Não há duas tentativas em rascunho para publicar.'], 422);
        }

        DB::transaction(function () use ($data, $dupla) {
            // Desativa qualquer votação ativa do mesmo dia
            Sorteio::whereDate('data', $data)
                ->where('em_votacao', 1)
                ->update(['em_votacao' => 0, 'status' => 'ativo']); // mantém ativos, apenas tira de votação

            // Publica a dupla
            Sorteio::whereIn('id', $dupla->pluck('id'))
                ->update(['status' => 'ativo', 'em_votacao' => 1]);
        });

        return response()->json([
            'message' => 'Dupla publicada para votação.',
            'ids_publicados' => $dupla->pluck('id'),
            'data' => $data,
        ], 200);
    }

    public function votacaoAtivaDoDia(Request $request)
    {
        $data = $request->query('data', Carbon::today()->toDateString());

        $sorteios = Sorteio::with(['times.jogadores.jogador.user'])
            ->whereDate('data', $data)
            ->where('em_votacao', 1)
            ->withCount('votos')    // precisa da relação votos() no model
            ->orderBy('tentativa')
            ->get();

        return response()->json($sorteios);
    }

    public function votos()
    {
        return $this->hasMany(SorteioVoto::class, 'sorteio_id');
    }

    // SorteioController.php
    public function fecharVotacao(Request $request)
    {
        $request->validate([
            'data'         => 'required|date',
            'vencedor_id'  => 'nullable|integer|exists:sorteios,id', // para desempate manual
        ]);

        $data = \Carbon\Carbon::parse($request->input('data'))->toDateString();

        $em_votacao = Sorteio::whereDate('data', $data)
            ->where('em_votacao', true)   // <- padronizado
            ->withCount('votos')
            ->orderBy('numero')
            ->get();

        if ($em_votacao->count() !== 2) {
            return response()->json(['message' => 'Não há exatamente dois sorteios em votação para encerrar.'], 422);
        }

        $a = $em_votacao[0];
        $b = $em_votacao[1];

        // regra normal
        $vencedor = null; $perdedor = null;

        if ($a->votos_count > $b->votos_count) {
            $vencedor = $a; $perdedor = $b;
        } elseif ($b->votos_count > $a->votos_count) {
            $vencedor = $b; $perdedor = $a;
        } else {
            // EMPATE
            if ($request->filled('vencedor_id')) {
                $vencedor = [$a, $b][ (int)($request->vencedor_id === $b->id) ];
                $perdedor = $vencedor->id === $a->id ? $b : $a;
            } else {
                return response()->json([
                    'message'  => 'Empate detectado. Envie "vencedor_id" para desempatar.',
                    'empate'   => [
                        ['id' => $a->id, 'votos' => $a->votos_count],
                        ['id' => $b->id, 'votos' => $b->votos_count],
                    ],
                ], 409);
            }
        }

        DB::transaction(function () use ($vencedor, $perdedor) {
            Sorteio::whereIn('id', [$vencedor->id, $perdedor->id])
                ->update(['em_votacao' => false]);

            $vencedor->update(['status' => 'confirmado']);
            $perdedor->update(['status' => 'descartado']);
        });

        return response()->json([
            'message'  => 'Votação encerrada.',
            'vencedor' => [
                'id'        => $vencedor->id,
                'numero'    => $vencedor->numero,
                'tentativa' => $vencedor->tentativa,
                'votos'     => $vencedor->votos_count,
            ],
            'descartado' => [
                'id'        => $perdedor->id,
                'numero'    => $perdedor->numero,
                'tentativa' => $perdedor->tentativa,
                'votos'     => $perdedor->votos_count,
            ],
        ], 200);
    }

    // app/Http/Controllers/SorteioController.php

    public function exibirDoDia(Request $request)
    {
        $data = Carbon::parse($request->query('data', now()->toDateString()))
            ->toDateString();

        // 1) Tenta trazer os que estão em votação
        $emVotacao = Sorteio::whereDate('data', $data)
            ->where('em_votacao', true)
            ->withCount('votos')
            ->with(['times.jogadores.jogador.user'])
            ->orderBy('numero')
            ->get();

        if ($emVotacao->isNotEmpty()) {
            return response()->json([
                'modo'     => 'votacao',      // para a UI saber o contexto
                'data'     => $data,
                'sorteios' => $emVotacao,     // contém votos_count, times, jogadores
            ]);
        }

        // 2) Sem votação: traz os confirmados do dia (normalmente 1 – o vencedor)
        $confirmados = Sorteio::whereDate('data', $data)
            ->where('status', 'confirmado')
            ->withCount('votos')
            ->with(['times.jogadores.jogador.user'])
            ->orderBy('numero')
            ->get();

        if ($confirmados->isNotEmpty()) {
            return response()->json([
                'modo'     => 'confirmado',
                'data'     => $data,
                'sorteios' => $confirmados,   // idem: votos_count, times, jogadores
            ]);
        }

        // 3) Nada a exibir hoje
        return response()->json([
            'modo'     => 'vazio',
            'data'     => $data,
            'sorteios' => [],
        ], 200);
    }



}





