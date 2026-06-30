<?php

if (!function_exists('systemMaintenanceTableExists')) {
    function systemMaintenanceTableExists(PDO $pdo): bool
    {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'system_maintenance_settings'");
            return (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('systemMaintenanceDefaults')) {
    function systemMaintenanceDefaults(): array
    {
        return [
            'id' => 1,
            'is_enabled' => 0,
            'message' => 'El sistema se encuentra temporalmente en mantenimiento.',
            'estimated_return_at' => null,
            'allow_admin' => 1,
            'block_tech' => 1,
            'block_client' => 1,
            'updated_by' => null,
            'updated_at' => null,
            'updated_by_name' => null,
        ];
    }
}

if (!function_exists('getSystemMaintenanceSettings')) {
    function getSystemMaintenanceSettings(PDO $pdo): array
    {
        $defaults = systemMaintenanceDefaults();

        if (!systemMaintenanceTableExists($pdo)) {
            return $defaults;
        }

        try {
            $stmt = $pdo->query(
                "SELECT s.*, u.name AS updated_by_name
                 FROM system_maintenance_settings s
                 LEFT JOIN users u ON u.id = s.updated_by
                 WHERE s.id = 1
                 LIMIT 1"
            );
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? array_merge($defaults, $row) : $defaults;
        } catch (Throwable $e) {
            return $defaults;
        }
    }
}

if (!function_exists('systemMaintenanceShouldBlock')) {
    function systemMaintenanceShouldBlock(PDO $pdo, ?array $currentUser): bool
    {
        $settings = getSystemMaintenanceSettings($pdo);

        if (empty($settings['is_enabled'])) {
            return false;
        }

        $role = strtoupper((string) ($currentUser['role'] ?? ''));

        if ($role === 'ADMIN' && !empty($settings['allow_admin'])) {
            return false;
        }

        if ($role === 'TECH') {
            return !empty($settings['block_tech']);
        }

        if ($role === 'CLIENT') {
            return !empty($settings['block_client']);
        }

        return true;
    }
}
