<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * Orígenes permitidos.
     * En desarrollo: solo el frontend local.
     * En producción: cambiar a la URL exacta del frontend desplegado.
     */
    'allowed_origins' => [
        env('FRONTEND_URL', 'http://localhost:3000'),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    /*
     * Necesario para que axios envíe el token Bearer
     * y para que el browser acepte la respuesta CORS.
     */
    'supports_credentials' => true,
];