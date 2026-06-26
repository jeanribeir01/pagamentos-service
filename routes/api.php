<?php

use App\Http\Controllers\PagamentoController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    
    Route::get('/pagamentos', [PagamentoController::class, 'index']);
    Route::get('/pagamentos/historico', [PagamentoController::class, 'historico']);
    Route::get('/pagamentos/{id}', [PagamentoController::class, 'show']);
    Route::post('/pagamentos', [PagamentoController::class, 'store']);
    Route::post('/pagamentos/{id}/recibo/reenviar', [PagamentoController::class, 'reenviarRecibo']);
});