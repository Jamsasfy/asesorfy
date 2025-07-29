<?php

use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;
use App\Http\Controllers\FacturaPdfController; // <-- Asegúrate de que esta importación esté
use App\Http\Controllers\FileViewController;



Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

Route::get('/facturas/generar-pdf/{factura}', [FacturaPdfController::class, 'generarPdf'])
    ->name('facturas.generar-pdf')
    ->middleware('auth');

    Route::get('/view-storage-file/{path}', [FileViewController::class, 'show'])
    ->where('path', '.*')
    ->name('file.view')
    ->middleware('auth'); // <-- AÑADIR ESTA LÍNEA



require __DIR__.'/auth.php';
