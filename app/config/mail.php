<?php

declare(strict_types=1);

/**
 * Configuración base del correo saliente.
 *
 * Las credenciales reales deben guardarse en mail.local.php, que no debe
 * subirse a repositorios ni compartirse públicamente.
 */
$config = [
    'enabled' => false,
    'host' => '',
    'port' => 587,
    'encryption' => 'tls', // tls, ssl o none
    'auth' => true,
    'username' => '',
    'password' => '',
    'from_email' => '',
    'from_name' => 'Mesa de Ayuda Pronet System',
    'reply_to' => '',
    'app_url' => 'http://localhost/helpdesk-php',
    'token_ttl_minutes' => 30,
];

$localConfigPath = __DIR__ . '/mail.local.php';
if (is_file($localConfigPath)) {
    $localConfig = require $localConfigPath;

    if (is_array($localConfig)) {
        $config = array_replace($config, $localConfig);
    }
}

return $config;
