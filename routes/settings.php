<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    // Datos fiscales del freelancer (emisor de las facturas) y su logo.
    Route::livewire('settings/fiscal', 'pages::settings.fiscal')->name('fiscal.edit');

    Route::livewire('settings/security', 'pages::settings.security')
        ->name('security.edit');
});
