<?php

/**
 * Funciones compartidas del Perfil del sistema.
 * El módulo utiliza un único registro con id = 1.
 */

if (!function_exists('systemProfileTableExists')) {
    function systemProfileTableExists(PDO $pdo): bool
    {
        try {
            $statement = $pdo->prepare("SHOW TABLES LIKE :table_name");
            $statement->execute(['table_name' => 'system_profile']);

            return (bool) $statement->fetch(PDO::FETCH_NUM);
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('systemProfileDefaults')) {
    function systemProfileDefaults(): array
    {
        return [
            'id' => 1,
            'company_name' => 'PRONET SYSTEM S.A.C.',
            'commercial_name' => 'Pronet System',
            'system_name' => 'Mesa de Ayuda',
            'ruc' => '',
            'corporate_email' => '',
            'phone' => '',
            'address' => '',
            'website' => '',
            'description' => '',
            'slogan' => '',
            'logo_path' => 'public/assets/img/logo.png',
            'updated_by' => null,
            'updated_by_name' => '',
            'created_at' => null,
            'updated_at' => null,
        ];
    }
}

if (!function_exists('getSystemProfile')) {
    function getSystemProfile(PDO $pdo): array
    {
        $defaults = systemProfileDefaults();

        if (!systemProfileTableExists($pdo)) {
            return $defaults;
        }

        try {
            $statement = $pdo->query(
                "SELECT
                    sp.*,
                    u.name AS updated_by_name
                 FROM system_profile sp
                 LEFT JOIN users u ON u.id = sp.updated_by
                 WHERE sp.id = 1
                 LIMIT 1"
            );

            $profile = $statement ? $statement->fetch(PDO::FETCH_ASSOC) : false;

            if (!$profile) {
                return $defaults;
            }

            return array_merge($defaults, $profile);
        } catch (Throwable $exception) {
            return $defaults;
        }
    }
}

if (!function_exists('systemProfileLogoUrl')) {
    function systemProfileLogoUrl(?string $logoPath): ?string
    {
        $logoPath = trim((string) $logoPath);

        if ($logoPath === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $logoPath)) {
            return $logoPath;
        }

        if (str_starts_with($logoPath, '/')) {
            return $logoPath;
        }

        return '/helpdesk-php/' . ltrim($logoPath, '/');
    }
}

if (!function_exists('systemProfileCsrfToken')) {
    function systemProfileCsrfToken(): string
    {
        if (empty($_SESSION['system_profile_csrf'])) {
            $_SESSION['system_profile_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['system_profile_csrf'];
    }
}

if (!function_exists('systemProfileVerifyCsrfToken')) {
    function systemProfileVerifyCsrfToken(?string $token): bool
    {
        $sessionToken = (string) ($_SESSION['system_profile_csrf'] ?? '');
        $token = (string) $token;

        return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
    }
}

if (!function_exists('systemProfileIsManagedUpload')) {
    function systemProfileIsManagedUpload(?string $logoPath): bool
    {
        $logoPath = str_replace('\\', '/', trim((string) $logoPath));

        return str_starts_with($logoPath, 'public/uploads/system/');
    }
}
