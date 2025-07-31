<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JogadorController;
use App\Http\Controllers\VotacaoController;
use App\Http\Controllers\VotoController;
use App\Http\Controllers\SorteioController;
use App\Http\Controllers\SorteioTimeController;
use App\Http\Controllers\SorteioTimeJogadorController;
use App\Http\Controllers\SorteioVotoController;



// ROTAS PÚBLICAS
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ROTAS PROTEGIDAS
Route::middleware(['auth:api'])->group(function () {
    Route::post('/jogador', [JogadorController::class, 'store']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/jogadores', [JogadorController::class, 'index']);
    Route::get('/meus-dados', [JogadorController::class, 'meusDados']);
    Route::get('/jogadores/todos', [JogadorController::class, 'todos']);

    // Votações
    Route::get('/votacoes', [VotacaoController::class, 'index']);
    Route::get('/votacao-ativa', [VotacaoController::class, 'ativa']);
    Route::post('/votacoes', [VotacaoController::class, 'store']);
    // Votos
    Route::post('/votos', [VotoController::class, 'store']);
    Route::get('/votacoes/{id}/medias', [VotacaoController::class, 'medias']);
    Route::get('/votos/me', [App\Http\Controllers\VotoController::class, 'meusVotos']);
    // sorteio
    Route::prefix('sorteios')->group(function () {
        //ROTAS DE CRIAÇÃO
        Route::post('/', [SorteioController::class, 'store']); // ✅ ADICIONE ESTA LINHA
        Route::post('/duplo-completo', [SorteioController::class, 'storeDuploCompleto']); 
        Route::post('/publicar', [SorteioController::class, 'publicarPar']);
        Route::post('/fechar-votacao', [SorteioController::class, 'fecharVotacao']);  
                   // publica um par e despublica os demais do dia
        // NOVA//LISTAGENS
        Route::get('/', [SorteioController::class, 'index']);
        Route::get('/rascunhos-dia', [SorteioController::class, 'rascunhosDoDia']);
        Route::get('/votacao-ativa', [SorteioController::class, 'votacaoAtivaDoDia']); // ?data=YYYY-MM-DD (opcional)
        Route::get('/ativos', [SorteioController::class, 'ativos']);
        Route::get('/por-data', [SorteioController::class, 'porData']);                  // lista por data (opcional)
        Route::get('/{id}', [SorteioController::class, 'show']);
        Route::delete('/{id}', [SorteioController::class, 'destroy']);
    //  TIMES E JOGADORES
    Route::prefix('/{sorteio}/times')->group(function () {
        Route::post('/', [SorteioTimeController::class, 'store']);
        Route::get('/', [SorteioTimeController::class, 'index']);
            
    Route::prefix('/{time}/jogadores')->group(function () {
        Route::post('/', [SorteioTimeJogadorController::class, 'store']);
        Route::get('/', [SorteioTimeJogadorController::class, 'index']);
       });
    });

        Route::prefix('/{sorteio}/votos')->group(function () {
            Route::post('/', [SorteioVotoController::class, 'store']);
            Route::get('/', [SorteioVotoController::class, 'index']);
        });
    });
});

// EXEMPLO DE ROTA AUTH PADRÃO
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');


