<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Guarda en la sesión el idioma elegido. El middleware SetLocale lo aplica en cada petición.
 */
class CambiarIdiomaController extends Controller
{
    public function __invoke(Request $request, string $idioma): RedirectResponse
    {
        $request->session()->put('idioma', $idioma);

        return back();
    }
}
