<?php

/**
 * Funciones compartidas de Personalización del sistema.
 * El módulo utiliza un único registro con id = 1.
 */

if (!function_exists('systemCustomizationTableExists')) {
    function systemCustomizationTableExists(PDO $pdo): bool
    {
        try {
            $statement = $pdo->prepare("SHOW TABLES LIKE :table_name");
            $statement->execute(['table_name' => 'system_customization']);

            return (bool) $statement->fetch(PDO::FETCH_NUM);
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('systemCustomizationDefaults')) {
    function systemCustomizationDefaults(): array
    {
        return [
            'id' => 1,
            'primary_color' => '#0f3d2e',
            'secondary_color' => '#ff7a00',
            'accent_color' => '#1f7a5a',
            'theme' => 'light',
            'sidebar_default' => 'expanded',
            'updated_by' => null,
            'updated_by_name' => '',
            'created_at' => null,
            'updated_at' => null,
        ];
    }
}

if (!function_exists('getSystemCustomization')) {
    function getSystemCustomization(PDO $pdo): array
    {
        $defaults = systemCustomizationDefaults();

        if (!systemCustomizationTableExists($pdo)) {
            return $defaults;
        }

        try {
            $statement = $pdo->query(
                "SELECT
                    sc.*,
                    u.name AS updated_by_name
                 FROM system_customization sc
                 LEFT JOIN users u ON u.id = sc.updated_by
                 WHERE sc.id = 1
                 LIMIT 1"
            );

            $customization = $statement ? $statement->fetch(PDO::FETCH_ASSOC) : false;

            if (!$customization) {
                return $defaults;
            }

            return array_merge($defaults, $customization);
        } catch (Throwable $exception) {
            return $defaults;
        }
    }
}

if (!function_exists('systemCustomizationNormalizeHex')) {
    function systemCustomizationNormalizeHex(?string $value, string $fallback): string
    {
        $value = strtolower(trim((string) $value));
        $fallback = strtolower(trim($fallback));

        if (preg_match('/^#[0-9a-f]{6}$/', $value)) {
            return $value;
        }

        return preg_match('/^#[0-9a-f]{6}$/', $fallback) ? $fallback : '#000000';
    }
}

if (!function_exists('systemCustomizationHexToRgb')) {
    function systemCustomizationHexToRgb(string $hex): array
    {
        $hex = ltrim(systemCustomizationNormalizeHex($hex, '#000000'), '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}

if (!function_exists('systemCustomizationRgbToHex')) {
    function systemCustomizationRgbToHex(int $red, int $green, int $blue): string
    {
        $red = max(0, min(255, $red));
        $green = max(0, min(255, $green));
        $blue = max(0, min(255, $blue));

        return sprintf('#%02x%02x%02x', $red, $green, $blue);
    }
}

if (!function_exists('systemCustomizationMixColors')) {
    function systemCustomizationMixColors(string $base, string $mix, float $mixWeight): string
    {
        [$baseRed, $baseGreen, $baseBlue] = systemCustomizationHexToRgb($base);
        [$mixRed, $mixGreen, $mixBlue] = systemCustomizationHexToRgb($mix);
        $mixWeight = max(0, min(1, $mixWeight));
        $baseWeight = 1 - $mixWeight;

        return systemCustomizationRgbToHex(
            (int) round(($baseRed * $baseWeight) + ($mixRed * $mixWeight)),
            (int) round(($baseGreen * $baseWeight) + ($mixGreen * $mixWeight)),
            (int) round(($baseBlue * $baseWeight) + ($mixBlue * $mixWeight))
        );
    }
}

if (!function_exists('systemCustomizationContrastColor')) {
    function systemCustomizationContrastColor(string $hex): string
    {
        [$red, $green, $blue] = systemCustomizationHexToRgb($hex);
        $luminance = (($red * 299) + ($green * 587) + ($blue * 114)) / 1000;

        return $luminance >= 155 ? '#0f172a' : '#ffffff';
    }
}

if (!function_exists('systemCustomizationCssVariables')) {
    function systemCustomizationCssVariables(array $customization): string
    {
        $defaults = systemCustomizationDefaults();
        $primary = systemCustomizationNormalizeHex(
            (string) ($customization['primary_color'] ?? ''),
            $defaults['primary_color']
        );
        $secondary = systemCustomizationNormalizeHex(
            (string) ($customization['secondary_color'] ?? ''),
            $defaults['secondary_color']
        );
        $accent = systemCustomizationNormalizeHex(
            (string) ($customization['accent_color'] ?? ''),
            $defaults['accent_color']
        );

        [$primaryRed, $primaryGreen, $primaryBlue] = systemCustomizationHexToRgb($primary);
        [$secondaryRed, $secondaryGreen, $secondaryBlue] = systemCustomizationHexToRgb($secondary);
        [$accentRed, $accentGreen, $accentBlue] = systemCustomizationHexToRgb($accent);

        $variables = [
            '--color-primary' => $primary,
            '--color-primary-rgb' => "$primaryRed, $primaryGreen, $primaryBlue",
            '--color-primary-hover' => systemCustomizationMixColors($primary, '#000000', 0.16),
            '--color-primary-soft' => systemCustomizationMixColors($primary, '#ffffff', 0.88),
            '--color-primary-contrast' => systemCustomizationContrastColor($primary),
            '--color-secondary' => $secondary,
            '--color-secondary-rgb' => "$secondaryRed, $secondaryGreen, $secondaryBlue",
            '--color-secondary-hover' => systemCustomizationMixColors($secondary, '#000000', 0.12),
            '--color-secondary-soft' => systemCustomizationMixColors($secondary, '#ffffff', 0.88),
            '--color-secondary-contrast' => systemCustomizationContrastColor($secondary),
            '--color-accent' => $accent,
            '--color-accent-rgb' => "$accentRed, $accentGreen, $accentBlue",
            '--color-accent-hover' => systemCustomizationMixColors($accent, '#000000', 0.14),
            '--color-accent-soft' => systemCustomizationMixColors($accent, '#ffffff', 0.88),
            '--color-accent-contrast' => systemCustomizationContrastColor($accent),
            '--admin-sidebar-start' => systemCustomizationMixColors($primary, '#07111a', 0.56),
            '--admin-sidebar-end' => systemCustomizationMixColors($primary, '#102331', 0.38),
        ];

        $lines = [':root {'];

        foreach ($variables as $name => $value) {
            $lines[] = "    {$name}: {$value};";
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }
}

if (!function_exists('systemCustomizationCsrfToken')) {
    function systemCustomizationCsrfToken(): string
    {
        if (empty($_SESSION['system_customization_csrf'])) {
            $_SESSION['system_customization_csrf'] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION['system_customization_csrf'];
    }
}

if (!function_exists('systemCustomizationVerifyCsrfToken')) {
    function systemCustomizationVerifyCsrfToken(?string $token): bool
    {
        $sessionToken = (string) ($_SESSION['system_customization_csrf'] ?? '');
        $token = (string) $token;

        return $sessionToken !== '' && $token !== '' && hash_equals($sessionToken, $token);
    }
}
