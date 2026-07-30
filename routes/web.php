<?php

use App\Http\Controllers\Whatsapp\ShortLinkRedirectController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Public click-to-chat short link (screenshot 79's "Generated Link") — plain
// Laravel redirect + click counter, no auth, no bridge dependency.
Route::get('/wa/{slug}', ShortLinkRedirectController::class)->name('whatsapp.short-link-redirect');
