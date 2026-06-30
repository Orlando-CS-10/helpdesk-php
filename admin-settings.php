<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';

$settingsModules = [
    [
        'title' => 'Perfil del sistema',
        'description' => 'Administra la identidad institucional, los datos corporativos y el logo de la plataforma.',
        'href' => '/helpdesk-php/admin-system-profile.php',
        'icon' => 'fa-solid fa-building',
        'status' => 'Disponible',
        'available' => true,
    ],
    [
        'title' => 'Personalización del sistema',
        'description' => 'Configura colores, tema visual y estado inicial del menú administrativo.',
        'href' => '/helpdesk-php/admin-system-customization.php',
        'icon' => 'fa-solid fa-palette',
        'status' => 'Disponible',
        'available' => true,
    ],
    [
        'title' => 'Configuración de reportes PDF',
        'description' => 'Define contenido, límites y presentación predeterminada de los reportes.',
        'href' => '#',
        'icon' => 'fa-solid fa-file-pdf',
        'status' => 'Próximamente',
        'available' => false,
    ],
    [
        'title' => 'SLA y reglas del sistema',
        'description' => 'Gestiona perfiles, tiempos TTA/TTR, horarios, alertas y pausas operativas.',
        'href' => '/helpdesk-php/admin-system-sla.php',
        'icon' => 'fa-solid fa-clock',
        'status' => 'Disponible',
        'available' => true,
    ],
    [
        'title' => 'Notificaciones',
        'description' => 'Controla los eventos que generan avisos dentro del sistema.',
        'href' => '#',
        'icon' => 'fa-solid fa-bell',
        'status' => 'Próximamente',
        'available' => false,
    ],
    [
        'title' => 'Seguridad del sistema',
        'description' => 'Configura contraseñas, bloqueos, sesiones activas y auditoría de accesos.',
        'href' => '/helpdesk-php/admin-system-security.php',
        'icon' => 'fa-solid fa-shield-halved',
        'status' => 'Disponible',
        'available' => true,
    ],
    [
        'title' => 'Herramientas del sistema',
        'description' => 'Centraliza respaldos, diagnóstico, registros y tareas de mantenimiento.',
        'href' => '/helpdesk-php/admin-system-tools.php',
        'icon' => 'fa-solid fa-screwdriver-wrench',
        'status' => 'Disponible',
        'available' => true,
    ],
];

require __DIR__ . '/app/views/admin/settings.php';
