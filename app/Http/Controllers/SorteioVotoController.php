<?php

namespace App\Http\Controllers;

use App\Models\Sorteio;
use App\Models\SorteioVoto;
use App\Models\SorteioTimeJogador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SorteioVotoController extends Controller
{
    public function store(Request $request, Sorteio $sorteio)
    {
        $user    = Auth::user();
        $jogador = optional($user)->jogador;

        if (!$user || !$jogador) {
            return response()->json(['message' => 'Usuário não possui jogador vinculado.'], 403);
        }

        if (!$sorteio->em_votacao) {
            return response()->json(['message' => 'Este sorteio não está em votação.'], 422);
        }

        $participa = SorteioTimeJogador::whereIn('sorteio_time_id', function ($q) use ($sorteio) {
                $q->select('id')->from('sorteio_times')->where('sorteio_id', $sorteio->id);
            })
            ->where('jogador_id', $jogador->id)
            ->exists();

        if (!$participa) {
            return response()->json([
                'message' => 'Somente jogadores participantes podem votar neste sorteio.'
            ], 403);
        }

        $idsDuplaDoDia = Sorteio::whereDate('data', $sorteio->data)
            ->where('em_votacao', true)
            ->pluck('id');

        if ($idsDuplaDoDia->isNotEmpty()) {
            $jaVotouNaDupla = SorteioVoto::whereIn('sorteio_id', $idsDuplaDoDia)
                ->where('user_id', $user->id)
                ->exists();

            if ($jaVotouNaDupla) {
                return response()->json([
                    'message' => 'Você já votou em um dos sorteios em votação hoje.'
                ], 422);
            }
        }

        try {
            $voto = SorteioVoto::create([
                'sorteio_id' => $sorteio->id,
                'user_id'    => $user->id,
                'jogador_id' => $jogador->id,
            ]);

            return response()->json([
                'message' => 'Voto computado com sucesso.',
                'voto'    => $voto,
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Você já votou neste sorteio.',
            ], 422);
        }
    }

    /**
     * GET /sorteios/{sorteio}/votos
     * ?detalhe=1  -> inclui a lista de votos (com foto do jogador)
     */
    public function index(Request $request, Sorteio $sorteio)
    {
        $total = SorteioVoto::where('sorteio_id', $sorteio->id)->count();

        $payload = [
            'sorteio_id'  => $sorteio->id,
            'numero'      => $sorteio->numero,
            'data'        => $sorteio->data,
            'total_votos' => $total,
        ];

        if ($request->boolean('detalhe')) {
            $user = Auth::user();

            // <- AQUI: carregamos também a FOTO do jogador
            $votos = SorteioVoto::with([
                    'usuario:id,name',
                    'jogador:id,apelido,nome,foto',
                ])
                ->where('sorteio_id', $sorteio->id)
                ->orderByDesc('id')
                ->get()
                ->map(function ($v) {
                    return [
                        'id'            => $v->id,
                        'user_id'       => $v->user_id,
                        'user_name'     => optional($v->usuario)->name,
                        'jogador_id'    => $v->jogador_id,
                        'jogador_nome'  => optional($v->jogador)->apelido ?: optional($v->jogador)->nome,
                        'jogador_foto'  => optional($v->jogador)->foto, // <- FOTO para a UI
                        'created_at'    => $v->created_at,
                    ];
                })
                ->values();

            $meuVotoNaDupla = null;
            if ($user) {
                $idsDupla = Sorteio::whereDate('data', $sorteio->data)
                    ->where('em_votacao', true)
                    ->pluck('id');

                if ($idsDupla->isNotEmpty()) {
                    $meuVotoNaDupla = SorteioVoto::whereIn('sorteio_id', $idsDupla)
                        ->where('user_id', $user->id)
                        ->value('sorteio_id');
                }
            }

            $payload['detalhes'] = [
                'votos' => $votos,
                'me' => [
                    'votou'             => !is_null($meuVotoNaDupla),
                    'sorteio_votado_id' => $meuVotoNaDupla,
                ],
            ];
        }

        return response()->json($payload);
    }

    public function resumoDiaAtual()
    {
        $hoje = now()->toDateString();

        $ids = Sorteio::whereDate('data', $hoje)
            ->where('em_votacao', true)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return response()->json([], 200);
        }

        $totais = SorteioVoto::select('sorteio_id', DB::raw('COUNT(*) as total'))
            ->whereIn('sorteio_id', $ids)
            ->groupBy('sorteio_id')
            ->get()
            ->keyBy('sorteio_id');

        $sorteios = Sorteio::whereIn('id', $ids)->get()
            ->map(function ($s) use ($totais) {
                return [
                    'sorteio_id'  => $s->id,
                    'numero'      => $s->numero,
                    'data'        => $s->data,
                    'total_votos' => (int)($totais[$s->id]->total ?? 0),
                ];
            })
            ->values();

        return response()->json($sorteios, 200);
    }
}
