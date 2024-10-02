<?php

use Illuminate\Support\Facades\Route;
use Hoangnh283\Fantom\Http\controllers\FantomController;
Route::get('/test', function (){
    return 'fantom test package';
});
Route::get('/fantom/test', [FantomController::class, 'test']);
Route::get('/fantom/create_wallet', [FantomController::class, 'createWallet']);

