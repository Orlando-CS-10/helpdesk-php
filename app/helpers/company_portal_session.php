<?php

/**
 * Sesión exclusiva del Portal corporativo.
 * Usa una cookie diferente a la autenticación de usuarios, técnicos y administradores.
 */
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

    session_name('PRONET_COMPANY_PORTAL');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_secure', $isHttps ? '1' : '0');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/helpdesk-php/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}
