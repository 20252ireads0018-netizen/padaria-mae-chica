<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    /** Uso: ->middleware('role:caixa,producao') — a gerência sempre passa. */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->active) {
            auth()->logout();

            return redirect()->route('login');
        }

        abort_unless($user->hasRole(...$roles), 403, 'Seu perfil não tem acesso a esta área.');

        return $next($request);
    }
}
