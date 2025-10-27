<?php

namespace App\Http\Controllers;

use App\Models\Sorteio;
use App\Models\SorteioTime;
use App\Models\SorteioTimeJogador;
use App\Models\Partida;
use App\Models\PartidaGol;
use App\Models\JogadorVitoria;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class PartidaController extends Controller
{
    // LISTAR partidas de um sorteio (com times, gols e autores/assistências)
    public function indexPorSorteio(Sorteio $sorteio)
    {
        return Partida::with([
                'timeA.jogadores.jogador.user',
                'timeB.jogadores.jogador.user',
                'gols.autor','gols.assistente',
            ])
            ->where('sorteio_id', $sorteio->id)
            ->orderByDesc('id')
            ->get();
    }

    // CRIAR partida (default status=agendada; o app já pode mandar iniciar em seguida)
    public function store(Request $request, Sorteio $sorteio)
    {
        $request->validate([
            'time_a_id' => ['required','integer', Rule::exists('sorteio_times','id')],
            'time_b_id' => ['required','integer', Rule::exists('sorteio_times','id'), 'different:time_a_id'],
            'duracao_segundos' => ['nullable','integer','min:60','max:3600'],
        ]);

        // regra de negócio: só criar partidas para sorteio confirmado
        if ($sorteio->status !== 'confirmado') {
            return response()->json(['message' => 'Só é permitido registrar partidas de sorteios confirmados.'], 422);
        }

        // garantir que os times pertencem ao sorteio
        foreach (['time_a_id','time_b_id'] as $k) {
            $time = SorteioTime::findOrFail((int)$request->input($k));
            if ($time->sorteio_id !== $sorteio->id) {
                return response()->json(['message' => 'Times devem pertencer ao sorteio informado.'], 422);
            }
        }

        $p = Partida::create([
            'sorteio_id' => $sorteio->id,
            'time_a_id'  => (int)$request->time_a_id,
            'time_b_id'  => (int)$request->time_b_id,
            'duracao_prevista_segundos' => (int)$request->input('duracao_segundos', 420),
            'status'     => 'agendada',
        ]);

        return response()->json($p->load('timeA','timeB'), 201);
    }

    // INICIAR partida (status -> em_andamento)
    public function iniciar(Partida $partida)
    {
        if ($partida->isEncerrada()) {
            return response()->json(['message' => 'Partida já encerrada.'], 422);
        }
        if ($partida->status === 'em_andamento') {
            return response()->json($partida);
        }

        $partida->update([
            'status'      => 'em_andamento',
            'iniciada_em' => now(),
        ]);

        return response()->json($partida->fresh());
    }

    // REGISTRAR GOL (assistência opcional)
    public function registrarGol(Request $request, Partida $partida)
    {
        $request->validate([
            'time_id'            => ['required','integer', Rule::exists('sorteio_times','id')],
            'jogador_id'         => ['required','integer', Rule::exists('jogadores','id')],
            'assist_jogador_id'  => ['nullable','integer', Rule::exists('jogadores','id'), 'different:jogador_id'],
            'segundo_relativo'   => ['nullable','integer','min:0','max:7200'],
        ]);

        if (!$partida->isAndamento()) {
            return response()->json(['message' => 'Só é possível registrar gols com a partida em andamento.'], 422);
        }

        // time deve pertencer à partida
        $timeId = (int)$request->time_id;
        if (!in_array($timeId, [$partida->time_a_id, $partida->time_b_id])) {
            return response()->json(['message' => 'Gol deve ser de um dos times da partida.'], 422);
        }

        // autor (e assistente, se houver) precisam estar nesse time
        $jogadorId = (int)$request->jogador_id;
        $assistId  = $request->filled('assist_jogador_id') ? (int)$request->assist_jogador_id : null;

        $pertenceAutor = SorteioTimeJogador::where('sorteio_time_id', $timeId)
            ->where('jogador_id', $jogadorId)
            ->exists();

        if (!$pertenceAutor) {
            return response()->json(['message' => 'Jogador autor do gol não pertence a este time.'], 422);
        }

        if ($assistId) {
            $pertenceAssist = SorteioTimeJogador::where('sorteio_time_id', $timeId)
                ->where('jogador_id', $assistId)
                ->exists();
            if (!$pertenceAssist) {
                return response()->json(['message' => 'Assistente não pertence a este time.'], 422);
            }
        }

        DB::transaction(function () use ($partida, $timeId, $jogadorId, $assistId, $request) {
            PartidaGol::create([
                'partida_id'       => $partida->id,
                'time_id'          => $timeId,
                'jogador_id'       => $jogadorId,
                'assist_jogador_id'=> $assistId,
                'segundo_relativo' => $request->input('segundo_relativo'),
            ]);

            // atualiza placar espelhado
            if ($timeId === $partida->time_a_id) {
                $partida->increment('placar_a');
            } else {
                $partida->increment('placar_b');
            }
        });

        return response()->json($partida->fresh()->load('gols.autor','gols.assistente'));
    }

    // REMOVER gol (caso precise desfazer)
    public function removerGol(Partida $partida, PartidaGol $gol)
    {
        if ($gol->partida_id !== $partida->id) {
            return response()->json(['message' => 'Gol não pertence à partida.'], 422);
        }

        DB::transaction(function () use ($partida, $gol) {
            // ajusta placar espelhado
            if ($gol->time_id === $partida->time_a_id && $partida->placar_a > 0) {
                $partida->decrement('placar_a');
            } elseif ($gol->time_id === $partida->time_b_id && $partida->placar_b > 0) {
                $partida->decrement('placar_b');
            }
            $gol->delete();
        });

        return response()->json($partida->fresh());
    }

    // ENCERRAR partida (manual) — define vencedor/empate e registra vitórias por jogador
    public function encerrar(Partida $partida)
    {
        if ($partida->isEncerrada()) {
            return response()->json($partida);
        }

        // recalcula placar a partir dos eventos para estabilidade
        $placares = PartidaGol::select('time_id', DB::raw('COUNT(*) as gols'))
            ->where('partida_id', $partida->id)
            ->groupBy('time_id')
            ->get()
            ->keyBy('time_id');

        $placarA = (int)($placares[$partida->time_a_id]->gols ?? 0);
        $placarB = (int)($placares[$partida->time_b_id]->gols ?? 0);

        $empate = ($placarA === $placarB);
        $vencedorTimeId = null;
        if (!$empate) {
            $vencedorTimeId = ($placarA > $placarB) ? $partida->time_a_id : $partida->time_b_id;
        }

        DB::transaction(function () use ($partida, $placarA, $placarB, $empate, $vencedorTimeId) {
            $partida->update([
                'status'          => 'encerrada',
                'encerrada_em'    => now(),
                'placar_a'        => $placarA,
                'placar_b'        => $placarB,
                'empate'          => $empate,
                'vencedor_time_id'=> $vencedorTimeId,
            ]);

            // registra vitórias para cada jogador do time vencedor (se houver)
            if (!$empate && $vencedorTimeId) {
                $idsJogadores = SorteioTimeJogador::where('sorteio_time_id', $vencedorTimeId)
                    ->pluck('jogador_id');

                foreach ($idsJogadores as $jid) {
                    JogadorVitoria::firstOrCreate([
                        'partida_id' => $partida->id,
                        'jogador_id' => $jid,
                    ]);
                }
            }
        });

        return response()->json($partida->fresh()->load([
            'timeA.jogadores.jogador','timeB.jogadores.jogador','gols.autor','gols.assistente'
        ]));
    }

    // SHOW (detalhe da partida)
    public function show(Partida $partida)
    {
        return $partida->load([
            'sorteio','timeA.jogadores.jogador.user','timeB.jogadores.jogador.user',
            'gols.autor','gols.assistente'
        ]);
    }
}
