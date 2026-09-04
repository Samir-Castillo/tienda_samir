<?php

use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('/ventas', [VentaController::class, 'create'])->name('ventas.create');
    Route::get('/ventas/{invoice}/document', [VentaController::class, 'document'])->name('ventas.document');
});

Route::prefix('api')->middleware('auth')->group(function () {
    Route::post('/ventas', [VentaController::class, 'store'])->name('api.ventas.store');
    Route::post('/ventas/{invoice}/factus', [VentaController::class, 'sendToFactus'])->name('api.ventas.factus');
});

require __DIR__.'/settings.php';
