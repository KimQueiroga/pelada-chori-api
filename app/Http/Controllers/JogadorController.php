<?php

namespace App\Http\Controllers;

use App\Models\Jogador;
use App\Models\NotaJogador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\Votacao;
use App\Models\Voto;



class JogadorController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nome'                 => 'required|string|max:255',
            'apelido'              => 'required|string|max:255',
            'numero_camisa'        => 'required|string|max:3',
            'foto'                 => 'nullable|url',
            'posicao'              => 'nullable|in:Defesa,Meio,Ataque',
            'notas.tecnica'             => 'nullable|numeric|between:1,5',
            'notas.inteligencia'        => 'nullable|numeric|between:1,5',
            'notas.velocidade_preparo'  => 'nullable|numeric|between:1,5',
            'notas.disciplina_tatica'   => 'nullable|numeric|between:1,5',
            'notas.poder_ofensivo'      => 'nullable|numeric|between:1,5',
            'notas.poder_defensivo'     => 'nullable|numeric|between:1,5',
            'notas.fundamentos_basicos' => 'nullable|numeric|between:1,5',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = Auth::user();

        // Evitar múltiplos cadastros por usuário
        if ($user->jogador) {
            return response()->json(['error' => 'Jogador já cadastrado para este usuário.'], 409);
        }

        // Cria jogador
        $jogador = Jogador::create([
            'user_id'        => $user->id,
            'nome'           => $request->nome,
            'apelido'        => $request->apelido,
            'numero_camisa'  => $request->numero_camisa,
            'foto'           => $request->foto,
            'posicao'        => $request->posicao,
        ]);

        // Cria notas
        if ($request->has('notas')) {
            $notas = new NotaJogador($request->notas);
            $jogador->notas()->save($notas);
        }
        

        return response()->json(['message' => 'Jogador cadastrado com sucesso!', 'jogador' => $jogador->load('nota')], 201);
    }

        public function index()
    {
        $user = Auth::user();
        $jogadorOrigem = $user->jogador;

        $votacaoAtiva = Votacao::where('ativa', true)
            ->where('data_inicio', '<=', now())
            ->where('data_fim', '>=', now())
            ->first();

        if (!$votacaoAtiva || !$jogadorOrigem) {
            return response()->json([], 200);
        }

        // IDs dos jogadores que já receberam voto deste jogador na votação ativa
        $votados = Voto::where('votacao_id', $votacaoAtiva->id)
            ->where('jogador_origem_id', $jogadorOrigem->id)
            ->pluck('jogador_destino_id');

        // Retorna apenas jogadores que ainda não foram votados e que não é ele mesmo
        $jogadoresParaVotar = Jogador::where('id', '!=', $jogadorOrigem->id)
            ->whereNotIn('id', $votados)
            ->orderBy('apelido')
            ->get();

        return response()->json($jogadoresParaVotar);
    }

    public function meusDados()
    {
        $user = Auth::user();
        $jogador = $user->jogador;

        if (!$jogador) {
            return response()->json(['error' => 'Jogador não encontrado'], 404);
        }

        // Agrupa e calcula a média das notas em Votos onde o jogador foi o destino
        $notas = Voto::where('jogador_destino_id', $jogador->id)
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

        return response()->json([
            'jogador' => $jogador,
            'notas' => $notasArray,
            'soma' => round($soma, 1),
            'media' => round($media, 2),
        ]);
    }

    public function updateMeusDados(Request $request)
    {
        $user = auth()->user();

        $jogador = \App\Models\Jogador::where('user_id', $user->id)->firstOrFail();

        $data = $request->validate([
            'nome'          => 'required|string|max:255',
            'apelido'       => 'nullable|string|max:255',
            'posicao'       => 'required|string|in:Defesa,Meio,Ataque',
            'numero_camisa' => 'nullable|integer|min:0|max:99',
            'foto'          => 'nullable|url',
        ]);

        // Atualiza o jogador
        $jogador->fill([
            'nome'          => $data['nome'],
            'apelido'       => $data['apelido'] ?? null,
            'posicao'       => $data['posicao'],
            'numero_camisa' => $data['numero_camisa'] ?? null,
            'foto'          => $data['foto'] ?? null,
        ])->save();

        // Opcional: manter o User->name alinhado ao nome do jogador
        if ($user->name !== $data['nome']) {
            $user->name = $data['nome'];
            $user->save();
        }

        // Retorna o mesmo shape que você já usa em /meus-dados
        return response()->json([
            'jogador' => $jogador->toArray(),
            'notas'   => [], // se quiser, pode recomputar/retornar como no endpoint atual
            'media'   => 0,
            'soma'    => 0,
        ]);
    }
    public function todos()
    {
        return response()->json(Jogador::orderBy('apelido')->get());
    }
}

