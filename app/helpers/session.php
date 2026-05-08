<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user']);
}

function user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function requireLogin() {
    if (empty($_SESSION['user'])) {
        header('Location: /helpdesk-php/login.php');
        exit;
    }
}

function requireRole(string $role): void
{
    requireLogin();

    if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== $role) {
        header('Location: index.php');
        exit;
    }
}