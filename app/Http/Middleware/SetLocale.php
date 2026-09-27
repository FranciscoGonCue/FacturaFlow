<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aplica el idioma elegido por el usuario (guardado en la sesión) en cada petición.
 */
class SetLocale
{
    /** Idiomas disponibles: código => nombre visible. */
    public const array IDIOMAS = ['es' => 'Español', 'en' => 'English'];

    public function handle(Request $request, Closure $next): Response
    {
        $idioma = $request->session()->get('idioma', config('app.locale'));

        if (array_key_exists($idioma, self::IDIOMAS)) {
            App::setLocale($idioma);
        }

        return $next($request);
    }
}
