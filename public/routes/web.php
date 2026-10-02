<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    // return 404
    return abort(404);
    // return view('frontend.index');
})->name('index');
