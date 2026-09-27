<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Filtro de las rutas /admin: si no eres administrador, 403 Prohibido.
 * Se registra con el alias "admin" en bootstrap/app.php.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->esAdministrador(), 403);

        return $next($request);
    }
}
