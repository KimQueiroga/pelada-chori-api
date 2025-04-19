<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\JogadorController;
use App\Http\Controllers\VotacaoController;
use App\Http\Controllers\VotoController;


// ROTAS PÚBLICAS
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// ROTAS PROTEGIDAS
Route::middleware(['auth:api'])->group(function () {
    Route::post('/jogador', [JogadorController::class, 'store']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/jogadores', [JogadorController::class, 'index']);
    Route::get('/meus-dados', [JogadorController::class, 'meusDados']);

    // Votações
    Route::get('/votacoes', [VotacaoController::class, 'index']);
    Route::get('/votacao-ativa', [VotacaoController::class, 'ativa']);
    Route::post('/votacoes', [VotacaoController::class, 'store']);
    // Votos
    Route::post('/votos', [VotoController::class, 'store']);
    Route::get('/votacoes/{id}/medias', [VotacaoController::class, 'medias']);
    Route::get('/votos/me', [App\Http\Controllers\VotoController::class, 'meusVotos']);

});

// EXEMPLO DE ROTA AUTH PADRÃO
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');


