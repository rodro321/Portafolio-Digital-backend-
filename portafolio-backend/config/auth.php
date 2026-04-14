<?php

return [

    'defaults' => [
        'guard'     => 'api',
        'passwords' => 'usuarios',
    ],

    'guards' => [
        'web' => [
            'driver'   => 'session',
            'provider' => 'usuarios',
        ],
        'api' => [
            'driver'   => 'sanctum',
            'provider' => 'usuarios',
        ],
    ],

    'providers' => [
        'usuarios' => [
            'driver' => 'usuario_provider',   // registrado en AuthServiceProvider
            'model'  => App\Models\Usuario::class,
        ],
    ],

    'passwords' => [
        'usuarios' => [
            'provider' => 'usuarios',
            'table'    => 'password_resets',
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 10800,
];