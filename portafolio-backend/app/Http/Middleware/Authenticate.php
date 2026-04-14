<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * Para rutas API siempre retorna null → Laravel lanza AuthenticationException
     * que se convierte en JSON {"message":"Unauthenticated."} con status 401.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    protected function redirectTo($request)
    {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return route('login');
        }
        // Retorna null → 401 JSON automáticamente
    }
}
