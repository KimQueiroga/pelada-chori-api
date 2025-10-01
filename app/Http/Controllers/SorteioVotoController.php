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
    /**
     * POST /sorteios/{sorteio}/votos
     * Regras:
     * - Apenas jogador participante pode votar.
     * - Um voto por jogador em APENAS UM dos sorteios em votação no mesmo dia.
     */
    public function store(Request $request, Sorteio $sorteio)
    {
        $request->validate([
            'jogador_id' => 'required|exists:jogadores,id',
        ]);

        $user = Auth::user();

        // (opcional, mas recomendado) jogador do request deve ser o do usuário logado
        if (!$user || !$user->jogador || (int)$user->jogador->id !== (int)$request->jogador_id) {
            return response()->json([
                'message' => 'Você só pode votar como o seu próprio jogador.'
            ], 403);
        }

        // 1) Confirma se o jogador participa do sorteio (em algum time deste sorteio)
        $participa = SorteioTimeJogador::whereIn('sorteio_time_id', function ($q) use ($sorteio) {
                $q->select('id')->from('sorteio_times')->where('sorteio_id', $sorteio->id);
            })
            ->where('jogador_id', $request->jogador_id)
            ->exists();

        if (!$participa) {
            return response()->json([
                'message' => 'Somente jogadores participantes podem votar neste sorteio.'
            ], 403);
        }

        // 2) Bloqueia voto duplo na DUPLA do dia: se já votou em QUALQUER sorteio da dupla do mesmo dia, não pode votar de novo.
        $idsDuplaDoDia = Sorteio::whereDate('data', $sorteio->data)
            ->where('em_votacao', true)
            ->pluck('id');

        if ($idsDuplaDoDia->isNotEmpty()) {
            $jaVotouNaDupla = SorteioVoto::whereIn('sorteio_id', $idsDuplaDoDia)
                ->where('jogador_id', $request->jogador_id)
                ->exists();

            if ($jaVotouNaDupla) {
                return response()->json([
                    'message' => 'Você já votou em um dos sorteios em votação hoje.'
                ], 422);
            }
        }

        // 3) Registra o voto (há também o unique(sorteio_id, jogador_id) via migration)
        try {
            $voto = SorteioVoto::create([
                'sorteio_id' => $sorteio->id,
                'jogador_id' => $request->jogador_id,
                'user_id'    => $user->id,
            ]);

            return response()->json([
                'message' => 'Voto computado com sucesso.',
                'voto'    => $voto,
            ], 201);
        } catch (\Throwable $e) {
            // Em caso de violação de unique no banco, retorna erro amigável
            return response()->json([
                'message' => 'Você já votou neste sorteio.',
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /sorteios/{sorteio}/votos
     * Retorna a contagem e (opcional) a listagem de votos do sorteio.
     */
    public function index(Sorteio $sorteio)
    {
        $total = SorteioVoto::where('sorteio_id', $sorteio->id)->count();

        // Se quiser, envie também os votos (ou nomes dos votantes)
        // $votos = SorteioVoto::with('jogador.user:id,name')->where('sorteio_id', $sorteio->id)->get();

        return response()->json([
            'sorteio_id' => $sorteio->id,
            'numero'     => $sorteio->numero,
            'data'       => $sorteio->data,
            'total_votos'=> $total,
            // 'votos'    => $votos,
        ]);
    }

    /**
     * GET /sorteios/votacao-ativa/resumo
     * Retorna a contagem dos votos para os sorteios que estão em votação HOJE.
     * Útil para mostrar o placar da dupla.
     */
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
