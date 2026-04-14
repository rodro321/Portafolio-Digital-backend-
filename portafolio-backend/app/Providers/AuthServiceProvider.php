<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use App\Auth\UsuarioUserProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [];

    public function boot()
    {
        $this->registerPolicies();

        // Registrar el provider personalizado para el modelo Usuario
        Auth::provider('usuario_provider', function ($app, array $config) {
            return new UsuarioUserProvider($app['hash'], $config['model']);
        });
    }
}