<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\JogadorController;
use App\Http\Controllers\VotacaoController;
use App\Http\Controllers\VotoController;
use App\Http\Controllers\SorteioController;
use App\Http\Controllers\SorteioTimeController;
use App\Http\Controllers\SorteioTimeJogadorController;
use App\Http\Controllers\SorteioVotoController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PartidaController;



// ROTAS PÚBLICAS (sem autenticação)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);
// Reset de senha
Route::post('/password/forgot', [PasswordResetController::class, 'sendCode'])->middleware('throttle:5,1');   // 5 req / min
Route::post('/password/verify', [PasswordResetController::class, 'verifyCode'])->middleware('throttle:10,1');
Route::post('/password/reset',  [PasswordResetController::class, 'reset'])->middleware('throttle:10,1');


// Refresh NÃO usa auth:api (permite token expirado, ainda dentro do refresh_ttl)
Route::post('/auth/refresh', [AuthController::class, 'refresh']);

// ROTAS PROTEGIDAS
Route::middleware(['auth:api'])->group(function () {

    // Auth util
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/me',           [AuthController::class, 'me']);

    

    // Jogadores
    Route::post('/jogador', [JogadorController::class, 'store']);
    Route::get('/jogadores', [JogadorController::class, 'index']);
    Route::get('/meus-dados', [JogadorController::class, 'meusDados']);
    Route::put('/meus-dados', [JogadorController::class, 'updateMeusDados']);
    Route::get('/jogadores/todos', [JogadorController::class, 'todos']);

    // Votações & Votos
    Route::get('/votacoes', [VotacaoController::class, 'index']);
    Route::get('/votacao-ativa', [VotacaoController::class, 'ativa']);
    Route::post('/votacoes', [VotacaoController::class, 'store']);

    Route::post('/votos', [VotoController::class, 'store']);
    Route::get('/votacoes/{id}/medias', [VotacaoController::class, 'medias']);
    Route::get('/votos/me', [VotoController::class, 'meusVotos']);

    // Sorteios
    Route::prefix('sorteios')->group(function () {
        // criação
        Route::post('/', [SorteioController::class, 'store']);
        Route::post('/duplo-completo', [SorteioController::class, 'storeDuploCompleto']); 
        Route::post('/publicar', [SorteioController::class, 'publicarPar']);
        Route::post('/fechar-votacao', [SorteioController::class, 'fecharVotacao']);
        
        // PARTIDAS de um sorteio confirmado
        Route::get('/{sorteio}/partidas', [PartidaController::class, 'indexPorSorteio'])->whereNumber('sorteio');
        Route::post('/{sorteio}/partidas', [PartidaController::class, 'store'])->whereNumber('sorteio');

        // listagens
        Route::get('/', [SorteioController::class, 'index']);
        Route::get('/exibir-do-dia', [SorteioController::class, 'exibirDoDia']); // NOVA
        Route::get('/rascunhos-dia', [SorteioController::class, 'rascunhosDoDia']);
        Route::get('/votacao-ativa', [SorteioController::class, 'votacaoAtivaDoDia']); // ?data=YYYY-MM-DD (opcional)
        Route::get('/ativos', [SorteioController::class, 'ativos']);
        Route::get('/por-data', [SorteioController::class, 'porData']);
        Route::get('/{id}', [SorteioController::class, 'show']);
        Route::delete('/{id}', [SorteioController::class, 'destroy']);

        // votos por sorteio
        Route::prefix('/{sorteio}/votos')->group(function () {
            Route::post('/', [SorteioVotoController::class, 'store']); // votar neste sorteio
            Route::get('/', [SorteioVotoController::class, 'index']);  // contagem/listagem de votos
        });

        // resumo fora do escopo {sorteio}/votos
        Route::get('/votacao-ativa/resumo', [SorteioVotoController::class, 'resumoDiaAtual']);
        // times e jogadores
        Route::prefix('/{sorteio}/times')->group(function () {
            Route::post('/', [SorteioTimeController::class, 'store']);
            Route::get('/', [SorteioTimeController::class, 'index']);
            Route::prefix('/{time}/jogadores')->group(function () {
                Route::post('/', [SorteioTimeJogadorController::class, 'store']);
                Route::get('/', [SorteioTimeJogadorController::class, 'index']);
            });
        });
    });

    Route::prefix('partidas')->group(function () {
        Route::get('/{partida}', [PartidaController::class, 'show'])->whereNumber('partida');
        Route::post('/{partida}/iniciar', [PartidaController::class, 'iniciar'])->whereNumber('partida');
        Route::post('/{partida}/encerrar', [PartidaController::class, 'encerrar'])->whereNumber('partida');

        Route::post('/{partida}/gols', [PartidaController::class, 'registrarGol'])->whereNumber('partida');
        Route::delete('/{partida}/gols/{gol}', [PartidaController::class, 'removerGol'])
            ->whereNumber('partida')->whereNumber('gol');
  });
});

// Exemplo padrão
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');
