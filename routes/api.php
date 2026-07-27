<?php

use App\Http\Controllers\Api\AccountController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::post('/login', function (Request $request) {
    $credentials = $request->validate([
        'email' => ['required', 'email'],
        'password' => ['required'],
    ]);

    if (! Auth::attempt($credentials)) {
        throw \Illuminate\Validation\ValidationException::withMessages([
            'email' => ['These credentials do not match our records.'],
        ]);
    }

    $request->session()->regenerate();

    return response()->json(['user' => Auth::user()]);
});

Route::post('/logout', function (Request $request) {
    Auth::guard('web')->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return response()->noContent();
})->middleware('auth:sanctum');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::middleware(['auth:sanctum', 'trial.active'])->group(function () {
    Route::get('/accounts', [AccountController::class, 'index']);
    Route::post('/accounts', [AccountController::class, 'store']);
    Route::get('/accounts/{account}', [AccountController::class, 'show']);
    Route::patch('/accounts/{account}', [AccountController::class, 'update']);
    Route::delete('/accounts/{account}', [AccountController::class, 'destroy']);
});

Route::get('/branding', function (Request $request) {
    $reseller = $request->attributes->get('reseller_account');

    return $reseller
        ? $reseller->branding
        : ['product_name' => 'CRM Platform', 'primary_color' => '#1976D2', 'support_email' => null];
});
