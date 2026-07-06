<?php

declare(strict_types=1);

/**
 * Copia este archivo como mail.local.php y reemplaza los valores.
 * No subas mail.local.php a Git ni lo compartas.
 */
return [
    'enabled' => true,
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
    'auth' => true,
    'username' => 'tu-cuenta@gmail.com',
    'password' => 'CONTRASENA_DE_APLICACION_DE_16_CARACTERES',
    'from_email' => 'tu-cuenta@gmail.com',
    'from_name' => 'Mesa de Ayuda Pronet System',
    'reply_to' => 'tu-cuenta@gmail.com',

    // Para XAMPP local:
    'app_url' => 'http://localhost/helpdesk-php',

    // Para Cloudflare Tunnel, reemplaza la línea anterior por algo como:
    // 'app_url' => 'https://tu-subdominio.trycloudflare.com/helpdesk-php',

    'token_ttl_minutes' => 30,
];
