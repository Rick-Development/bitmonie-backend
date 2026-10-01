<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Frontend\IndexController;

Route::name('frontend.')->group(function() {
    // return abort(404);
    Route::controller(IndexController::class)->group(function() {
        Route::get('/','index')->name('index');
        Route::post("subscribe","subscribe")->name("subscribe");
        Route::get('contact','contact')->name('contact');
        Route::post("contact/message/send","contactMessageSend")->name("contact.message.send");
        Route::get('link/{slug}','usefulLink')->name('useful.links');
        Route::post('languages/switch','languageSwitch')->name('languages.switch');

        //pages
        Route::get('about','about')->name('about');
        Route::get('services','services')->name('services');
        Route::get('web-journals','webJournals')->name('web.journals');
        Route::get('journal-details/{slug}','journalDetails')->name('journal.details');
        Route::get('journal-category/{id}','journalCategory')->name('journal.category');
        Route::get('contact','contact')->name('contact');
    });
});