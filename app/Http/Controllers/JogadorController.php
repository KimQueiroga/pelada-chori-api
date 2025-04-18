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


}

