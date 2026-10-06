<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Docs\ApiDocsController;

Route::get('/', function () {
    return redirect('/admin/login');
})->name('index');

/*
 * Direct Access API Documentation (Internal Developer Reference)
 */
Route::get('/docs/api.json', [ApiDocsController::class, 'json'])->name('docs.api.json');
Route::get('/docs/api', [ApiDocsController::class, 'ui'])->name('docs.api.ui');
Route::get('/docs', [ApiDocsController::class, 'ui'])->name('docs.ui');
