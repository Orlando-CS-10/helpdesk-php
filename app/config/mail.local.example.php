<?php

return [
    'enabled' => true,
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'auth' => true,
    'username' => 'tu-correo@gmail.com',
    'password' => 'tu-contraseña-de-aplicacion',
    'from_email' => 'tu-correo@gmail.com',
    'from_name' => 'Mesa de Ayuda Pronet System',
    'reply_to' => 'tu-correo@gmail.com',
    'app_url' => 'http://localhost/helpdesk-php',
    'token_ttl_minutes' => 30,
    'two_factor_ttl_minutes' => 5,
    'two_factor_resend_seconds' => 60,
    'two_factor_max_attempts' => 5,
];
