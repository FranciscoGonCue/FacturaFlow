<?php

use App\Http\Controllers\CambiarIdiomaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas web de FacturaFlow
|--------------------------------------------------------------------------
| Casi todas las páginas son componentes Livewire "full-page":
|   Route::livewire('url', 'pages::carpeta.componente')
| apunta a resources/views/pages/carpeta/⚡componente.blade.php
|
| Las rutas de login, registro y recuperación de contraseña las crea Fortify
| (starter kit). Las de ajustes están en routes/settings.php.
*/

Route::view('/', 'welcome')->name('home');

// Cambiar de idioma (español / inglés). Disponible también sin iniciar sesión.
Route::post('idioma/{idioma}', CambiarIdiomaController::class)
    ->whereIn('idioma', ['es', 'en'])
    ->name('idioma');

// ── Zona privada: solo usuarios con sesión iniciada ─────────────────────────
Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::livewire('dashboard', 'pages::panel')->name('dashboard');

    // Clientes
    Route::prefix('clientes')->name('clientes.')->group(function (): void {
        Route::livewire('/', 'pages::clientes.index')->name('index');
        Route::livewire('crear', 'pages::clientes.form')->name('create');
        Route::livewire('{cliente}', 'pages::clientes.show')->name('show');
        Route::livewire('{cliente}/editar', 'pages::clientes.form')->name('edit');
    });

    // Proyectos (+ tablero Kanban con arrastrar y soltar)
    Route::prefix('proyectos')->name('proyectos.')->group(function (): void {
        Route::livewire('/', 'pages::proyectos.index')->name('index');
        Route::livewire('tablero', 'pages::proyectos.tablero')->name('tablero');
        Route::livewire('crear', 'pages::proyectos.form')->name('create');
        Route::livewire('{proyecto}', 'pages::proyectos.show')->name('show');
        Route::livewire('{proyecto}/editar', 'pages::proyectos.form')->name('edit');
    });

    // Facturas
    Route::prefix('facturas')->name('facturas.')->group(function (): void {
        Route::livewire('/', 'pages::facturas.index')->name('index');
        Route::livewire('crear', 'pages::facturas.form')->name('create');
        Route::livewire('{factura}', 'pages::facturas.show')->name('show');
        Route::livewire('{factura}/editar', 'pages::facturas.form')->name('edit');
    });

    // Etiquetas (relación muchos a muchos con proyectos)
    Route::livewire('etiquetas', 'pages::etiquetas.index')->name('etiquetas.index');

    // ── Zona de administración: además hay que ser admin (middleware "admin") ──
    Route::prefix('admin')->name('admin.')->middleware('admin')->group(function (): void {
        Route::livewire('/', 'pages::admin.panel')->name('panel');
        Route::livewire('usuarios', 'pages::admin.usuarios')->name('usuarios');
    });
});

require __DIR__.'/settings.php';
