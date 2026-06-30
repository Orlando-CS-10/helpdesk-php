<?php

require_once __DIR__ . '/system_maintenance.php';

if (!function_exists('systemToolsTableExists')) {
    function systemToolsTableExists(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
            $stmt->execute(['table_name' => $table]);
            return (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('systemToolsModuleReady')) {
    function systemToolsModuleReady(PDO $pdo): bool
    {
        foreach (['system_maintenance_settings', 'system_backup_records', 'system_maintenance_logs'] as $table) {
            if (!systemToolsTableExists($pdo, $table)) {
                return false;
            }
        }
        return true;
    }
}

if (!function_exists('systemToolsCsrfToken')) {
    function systemToolsCsrfToken(): string
    {
        if (empty($_SESSION['system_tools_csrf'])) {
            $_SESSION['system_tools_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['system_tools_csrf'];
    }
}

if (!function_exists('systemToolsVerifyCsrf')) {
    function systemToolsVerifyCsrf(?string $token): bool
    {
        $stored = (string) ($_SESSION['system_tools_csrf'] ?? '');
        $token = (string) $token;
        return $stored !== '' && $token !== '' && hash_equals($stored, $token);
    }
}

if (!function_exists('systemToolsClientIp')) {
    function systemToolsClientIp(): ?string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return $ip !== '' ? substr($ip, 0, 45) : null;
    }
}

if (!function_exists('systemToolsLog')) {
    function systemToolsLog(PDO $pdo, string $actionType, string $description, ?int $actorUserId = null, string $severity = 'info', array $metadata = []): void
    {
        if (!systemToolsTableExists($pdo, 'system_maintenance_logs')) {
            return;
        }

        $severity = in_array($severity, ['info', 'warning', 'critical'], true) ? $severity : 'info';

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO system_maintenance_logs
                    (action_type, severity, description, metadata_json, ip_address, actor_user_id)
                 VALUES
                    (:action_type, :severity, :description, :metadata_json, :ip_address, :actor_user_id)'
            );
            $stmt->execute([
                'action_type' => substr($actionType, 0, 80),
                'severity' => $severity,
                'description' => substr($description, 0, 500),
                'metadata_json' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                'ip_address' => systemToolsClientIp(),
                'actor_user_id' => $actorUserId ?: null,
            ]);
        } catch (Throwable $e) {
            // El registro de mantenimiento no debe interrumpir la acción principal.
        }
    }
}

if (!function_exists('systemToolsProjectRoot')) {
    function systemToolsProjectRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}

if (!function_exists('systemToolsBackupsDirectory')) {
    function systemToolsBackupsDirectory(): string
    {
        return systemToolsProjectRoot() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'system-backups';
    }
}

if (!function_exists('systemToolsTempDirectory')) {
    function systemToolsTempDirectory(): string
    {
        return systemToolsProjectRoot() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'system-temp';
    }
}

if (!function_exists('systemToolsEnsureDirectory')) {
    function systemToolsEnsureDirectory(string $directory): bool
    {
        return is_dir($directory) || (@mkdir($directory, 0775, true) && is_dir($directory));
    }
}

if (!function_exists('systemToolsFormatBytes')) {
    function systemToolsFormatBytes(int|float $bytes, int $precision = 1): string
    {
        $bytes = max(0, (float) $bytes);
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? min((int) floor(log($bytes, 1024)), count($units) - 1) : 0;
        $value = $bytes / (1024 ** $power);
        return number_format($value, $precision, '.', '') . ' ' . $units[$power];
    }
}

if (!function_exists('systemToolsDirectorySize')) {
    function systemToolsDirectorySize(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $size = 0;
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += (int) $file->getSize();
                }
            }
        } catch (Throwable $e) {
            return $size;
        }
        return $size;
    }
}

if (!function_exists('systemToolsDatabaseSize')) {
    function systemToolsDatabaseSize(PDO $pdo): int
    {
        try {
            $stmt = $pdo->query(
                "SELECT COALESCE(SUM(data_length + index_length), 0)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()"
            );
            return (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return 0;
        }
    }
}

if (!function_exists('systemToolsDatabaseStats')) {
    function systemToolsDatabaseStats(PDO $pdo): array
    {
        $stats = ['tables' => 0, 'size_bytes' => 0, 'database_name' => ''];
        try {
            $stats['database_name'] = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            $stmt = $pdo->query(
                "SELECT COUNT(*), COALESCE(SUM(data_length + index_length), 0)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()"
            );
            $row = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
            $stats['tables'] = (int) $row[0];
            $stats['size_bytes'] = (int) $row[1];
        } catch (Throwable $e) {
        }
        return $stats;
    }
}

if (!function_exists('systemToolsDiagnostics')) {
    function systemToolsDiagnostics(PDO $pdo): array
    {
        $checks = [];
        $add = static function (string $key, string $label, string $status, string $detail, string $group = 'Sistema') use (&$checks): void {
            $checks[] = compact('key', 'label', 'status', 'detail', 'group');
        };

        $start = microtime(true);
        try {
            $pdo->query('SELECT 1')->fetchColumn();
            $elapsed = (int) round((microtime(true) - $start) * 1000);
            $add('database', 'Conexión con MySQL', 'ok', "Operativa · {$elapsed} ms", 'Base de datos');
        } catch (Throwable $e) {
            $add('database', 'Conexión con MySQL', 'error', 'No disponible', 'Base de datos');
        }

        try {
            $mysqlVersion = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
            $add('mysql_version', 'Versión de MySQL', 'ok', $mysqlVersion ?: 'No identificada', 'Base de datos');
        } catch (Throwable $e) {
            $add('mysql_version', 'Versión de MySQL', 'warning', 'No identificada', 'Base de datos');
        }

        try {
            $mysqlTimezone = (string) $pdo->query("SELECT @@session.time_zone")->fetchColumn();
            $add('mysql_timezone', 'Zona horaria de MySQL', $mysqlTimezone === '-05:00' ? 'ok' : 'warning', $mysqlTimezone ?: 'No identificada', 'Base de datos');
        } catch (Throwable $e) {
            $add('mysql_timezone', 'Zona horaria de MySQL', 'warning', 'No identificada', 'Base de datos');
        }

        $add('php_version', 'Versión de PHP', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'warning', PHP_VERSION, 'Servidor');
        $add('php_timezone', 'Zona horaria de PHP', date_default_timezone_get() === 'America/Lima' ? 'ok' : 'warning', date_default_timezone_get(), 'Servidor');

        $requiredExtensions = [
            'pdo_mysql' => 'PDO MySQL',
            'fileinfo' => 'Fileinfo',
            'mbstring' => 'Mbstring',
            'dom' => 'DOM/XML',
            'json' => 'JSON',
            'zip' => 'ZIP',
        ];
        foreach ($requiredExtensions as $extension => $label) {
            $loaded = extension_loaded($extension);
            $status = $loaded ? 'ok' : ($extension === 'zip' ? 'warning' : 'error');
            $detail = $loaded ? 'Instalada' : ($extension === 'zip' ? 'No instalada · limita respaldos de archivos' : 'No instalada');
            $add('ext_' . $extension, 'Extensión ' . $label, $status, $detail, 'Extensiones PHP');
        }

        $root = systemToolsProjectRoot();
        $directories = [
            'Adjuntos de tickets' => $root . '/storage/ticket-message-attachments',
            'Respaldos del sistema' => systemToolsBackupsDirectory(),
            'Temporales del sistema' => systemToolsTempDirectory(),
            'Logo institucional' => $root . '/public/uploads/system',
            'Logos de empresas' => $root . '/public/uploads/companies',
            'Fotos de usuarios' => $root . '/public/uploads/users',
        ];

        foreach ($directories as $label => $path) {
            systemToolsEnsureDirectory($path);
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $status = $writable ? 'ok' : ($exists ? 'warning' : 'error');
            $detail = !$exists ? 'No existe' : ($writable ? 'Escritura permitida' : 'Sin permiso de escritura');
            $add('dir_' . md5($path), $label, $status, $detail, 'Directorios');
        }

        $free = @disk_free_space($root);
        if ($free !== false) {
            $status = $free >= 500 * 1024 * 1024 ? 'ok' : 'warning';
            $add('disk_space', 'Espacio libre en disco', $status, systemToolsFormatBytes((int) $free), 'Servidor');
        }

        $autoload = $root . '/vendor/autoload.php';
        $add('composer', 'Dependencias de Composer', is_file($autoload) ? 'ok' : 'warning', is_file($autoload) ? 'Disponibles' : 'No se encontró vendor/autoload.php', 'Aplicación');

        $databaseStats = systemToolsDatabaseStats($pdo);
        $add('database_size', 'Tamaño de la base de datos', 'ok', systemToolsFormatBytes($databaseStats['size_bytes']) . ' · ' . $databaseStats['tables'] . ' tablas', 'Base de datos');

        $summary = ['ok' => 0, 'warning' => 0, 'error' => 0, 'total' => count($checks)];
        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        return ['checks' => $checks, 'summary' => $summary, 'ran_at' => date('Y-m-d H:i:s')];
    }
}

if (!function_exists('systemToolsSqlValue')) {
    function systemToolsSqlValue(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        return $pdo->quote((string) $value);
    }
}

if (!function_exists('systemToolsCreateDatabaseDump')) {
    function systemToolsCreateDatabaseDump(PDO $pdo, string $destination): array
    {
        if (!systemToolsEnsureDirectory(dirname($destination))) {
            throw new RuntimeException('No se pudo preparar la carpeta de respaldos.');
        }

        $handle = fopen($destination, 'wb');
        if (!$handle) {
            throw new RuntimeException('No se pudo crear el archivo de respaldo.');
        }

        try {
            $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
            fwrite($handle, "-- Respaldo generado por HelpDesk\n");
            fwrite($handle, '-- Fecha: ' . date('Y-m-d H:i:s') . "\n");
            fwrite($handle, '-- Base de datos: ' . $database . "\n\n");
            fwrite($handle, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");

            $tables = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                $quotedTable = '`' . str_replace('`', '``', (string) $table) . '`';
                $createRow = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_ASSOC);
                $createSql = $createRow['Create Table'] ?? array_values($createRow ?: [])[1] ?? '';

                fwrite($handle, "-- ---------------------------------------------\n");
                fwrite($handle, '-- Tabla: ' . $table . "\n");
                fwrite($handle, 'DROP TABLE IF EXISTS ' . $quotedTable . ";\n");
                fwrite($handle, $createSql . ";\n\n");

                $stmt = $pdo->query('SELECT * FROM ' . $quotedTable);
                $batch = [];
                $columns = null;

                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    if ($columns === null) {
                        $columns = array_keys($row);
                    }
                    $values = array_map(static fn ($value) => systemToolsSqlValue($pdo, $value), array_values($row));
                    $batch[] = '(' . implode(', ', $values) . ')';

                    if (count($batch) >= 100) {
                        $columnSql = implode(', ', array_map(static fn ($column) => '`' . str_replace('`', '``', $column) . '`', $columns));
                        fwrite($handle, 'INSERT INTO ' . $quotedTable . ' (' . $columnSql . ") VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }

                if ($batch && $columns) {
                    $columnSql = implode(', ', array_map(static fn ($column) => '`' . str_replace('`', '``', $column) . '`', $columns));
                    fwrite($handle, 'INSERT INTO ' . $quotedTable . ' (' . $columnSql . ") VALUES\n" . implode(",\n", $batch) . ";\n");
                }

                fwrite($handle, "\n");
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } catch (Throwable $e) {
            fclose($handle);
            @unlink($destination);
            throw $e;
        }

        fclose($handle);
        return ['size_bytes' => is_file($destination) ? (int) filesize($destination) : 0];
    }
}

if (!function_exists('systemToolsAddDirectoryToZip')) {
    function systemToolsAddDirectoryToZip(ZipArchive $zip, string $source, string $zipPrefix): int
    {
        if (!is_dir($source)) {
            return 0;
        }

        $count = 0;
        $sourceReal = realpath($source);
        if (!$sourceReal) {
            return 0;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceReal, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relative = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($sourceReal))), '/');
            $localName = trim($zipPrefix, '/') . ($relative !== '' ? '/' . $relative : '');

            if ($item->isDir()) {
                $zip->addEmptyDir($localName);
            } else {
                $zip->addFile($item->getPathname(), $localName);
                $count++;
            }
        }

        return $count;
    }
}

if (!function_exists('systemToolsCreateBackup')) {
    function systemToolsCreateBackup(PDO $pdo, string $type, int $actorUserId): array
    {
        $type = strtoupper($type);
        if (!in_array($type, ['DATABASE', 'FILES', 'FULL'], true)) {
            throw new RuntimeException('Tipo de respaldo no válido.');
        }

        $backupDir = systemToolsBackupsDirectory();
        $tempDir = systemToolsTempDirectory();
        if (!systemToolsEnsureDirectory($backupDir) || !systemToolsEnsureDirectory($tempDir)) {
            throw new RuntimeException('No se pudieron preparar las carpetas del módulo.');
        }

        $stamp = date('Ymd_His');
        $token = bin2hex(random_bytes(4));
        $baseName = 'helpdesk_' . strtolower($type) . '_' . $stamp . '_' . $token;
        $finalPath = '';
        $notes = '';

        if ($type === 'DATABASE') {
            $finalPath = $backupDir . DIRECTORY_SEPARATOR . $baseName . '.sql';
            systemToolsCreateDatabaseDump($pdo, $finalPath);
            $notes = 'Respaldo SQL de la base de datos.';
        } else {
            if (!class_exists('ZipArchive')) {
                throw new RuntimeException('La extensión ZIP de PHP no está instalada. Solo está disponible el respaldo de base de datos.');
            }

            $finalPath = $backupDir . DIRECTORY_SEPARATOR . $baseName . '.zip';
            $zip = new ZipArchive();
            if ($zip->open($finalPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No se pudo crear el archivo ZIP.');
            }

            $root = systemToolsProjectRoot();
            $filesCount = 0;

            if ($type === 'FULL') {
                $tempSql = $tempDir . DIRECTORY_SEPARATOR . $baseName . '.sql';
                systemToolsCreateDatabaseDump($pdo, $tempSql);
                $zip->addFile($tempSql, 'database/' . basename($tempSql));
            }

            $directories = [
                $root . '/storage/ticket-message-attachments' => 'storage/ticket-message-attachments',
                $root . '/public/uploads/system' => 'public/uploads/system',
                $root . '/public/uploads/companies' => 'public/uploads/companies',
                $root . '/public/uploads/users' => 'public/uploads/users',
            ];

            foreach ($directories as $source => $prefix) {
                $filesCount += systemToolsAddDirectoryToZip($zip, $source, $prefix);
            }

            $zip->close();

            if (isset($tempSql) && is_file($tempSql)) {
                @unlink($tempSql);
            }

            $notes = $type === 'FULL'
                ? "Respaldo completo con base de datos y {$filesCount} archivos."
                : "Respaldo de {$filesCount} archivos cargados.";
        }

        $relativePath = 'storage/system-backups/' . basename($finalPath);
        $size = is_file($finalPath) ? (int) filesize($finalPath) : 0;

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO system_backup_records
                    (backup_type, file_name, storage_path, file_size_bytes, status, notes, created_by)
                 VALUES
                    (:backup_type, :file_name, :storage_path, :file_size_bytes, :status, :notes, :created_by)'
            );
            $stmt->execute([
                'backup_type' => $type,
                'file_name' => basename($finalPath),
                'storage_path' => $relativePath,
                'file_size_bytes' => $size,
                'status' => 'COMPLETED',
                'notes' => $notes,
                'created_by' => $actorUserId ?: null,
            ]);
        } catch (Throwable $e) {
            if (is_file($finalPath)) {
                @unlink($finalPath);
            }
            throw $e;
        }

        systemToolsLog($pdo, 'BACKUP_CREATED', 'Se creó un respaldo del sistema.', $actorUserId, 'info', [
            'type' => $type,
            'file_name' => basename($finalPath),
            'size_bytes' => $size,
        ]);

        return ['id' => (int) $pdo->lastInsertId(), 'file_name' => basename($finalPath), 'size_bytes' => $size, 'notes' => $notes];
    }
}

if (!function_exists('systemToolsBackupRecords')) {
    function systemToolsBackupRecords(PDO $pdo, int $limit = 20, int $offset = 0): array
    {
        if (!systemToolsTableExists($pdo, 'system_backup_records')) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $sql = "SELECT b.*, u.name AS created_by_name
                FROM system_backup_records b
                LEFT JOIN users u ON u.id = b.created_by
                ORDER BY b.created_at DESC, b.id DESC
                LIMIT {$limit} OFFSET {$offset}";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('systemToolsCleanupAnalysis')) {
    function systemToolsCleanupAnalysis(PDO $pdo): array
    {
        $root = systemToolsProjectRoot();
        $analysis = [
            'expired_sessions' => ['count' => 0, 'bytes' => 0, 'label' => 'Sesiones vencidas o revocadas'],
            'read_notifications' => ['count' => 0, 'bytes' => 0, 'label' => 'Notificaciones leídas antiguas'],
            'temp_files' => ['count' => 0, 'bytes' => 0, 'label' => 'Archivos temporales'],
            'orphan_attachments' => ['count' => 0, 'bytes' => 0, 'label' => 'Adjuntos huérfanos'],
            'empty_directories' => ['count' => 0, 'bytes' => 0, 'label' => 'Carpetas vacías de adjuntos'],
        ];

        if (systemToolsTableExists($pdo, 'user_sessions')) {
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM user_sessions
                 WHERE (revoked_at IS NOT NULL OR (expires_at IS NOT NULL AND expires_at < NOW()))
                   AND last_activity_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            $analysis['expired_sessions']['count'] = (int) $stmt->fetchColumn();
        }

        if (systemToolsTableExists($pdo, 'notifications')) {
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM notifications
                 WHERE is_read = 1
                   AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
            $analysis['read_notifications']['count'] = (int) $stmt->fetchColumn();
        }

        $tempDir = systemToolsTempDirectory();
        if (is_dir($tempDir)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tempDir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getMTime() < time() - 86400) {
                    $analysis['temp_files']['count']++;
                    $analysis['temp_files']['bytes'] += (int) $file->getSize();
                }
            }
        }

        $attachmentsDir = $root . '/storage/ticket-message-attachments';
        $hasAttachmentRegistry = systemToolsTableExists($pdo, 'ticket_message_attachments');
        $registered = [];
        if ($hasAttachmentRegistry) {
            foreach ($pdo->query('SELECT storage_path FROM ticket_message_attachments')->fetchAll(PDO::FETCH_COLUMN) as $path) {
                $registered[str_replace('\\', '/', ltrim((string) $path, '/'))] = true;
            }
        }

        if (is_dir($attachmentsDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($attachmentsDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isFile() && $hasAttachmentRegistry) {
                    $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
                    if (!isset($registered[$relative])) {
                        $analysis['orphan_attachments']['count']++;
                        $analysis['orphan_attachments']['bytes'] += (int) $item->getSize();
                    }
                } elseif ($item->isDir()) {
                    $children = @scandir($item->getPathname());
                    if (is_array($children) && count($children) === 2) {
                        $analysis['empty_directories']['count']++;
                    }
                }
            }
        }

        return $analysis;
    }
}

if (!function_exists('systemToolsRunCleanup')) {
    function systemToolsRunCleanup(PDO $pdo, array $categories, int $actorUserId): array
    {
        $allowed = ['expired_sessions', 'read_notifications', 'temp_files', 'orphan_attachments', 'empty_directories'];
        $categories = array_values(array_intersect($allowed, $categories));
        if (!$categories) {
            throw new RuntimeException('Selecciona al menos una categoría para limpiar.');
        }

        $root = systemToolsProjectRoot();
        $result = ['deleted' => 0, 'bytes' => 0, 'categories' => $categories];

        if (in_array('expired_sessions', $categories, true) && systemToolsTableExists($pdo, 'user_sessions')) {
            $stmt = $pdo->prepare(
                "DELETE FROM user_sessions
                 WHERE (revoked_at IS NOT NULL OR (expires_at IS NOT NULL AND expires_at < NOW()))
                   AND last_activity_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
            );
            $stmt->execute();
            $result['deleted'] += $stmt->rowCount();
        }

        if (in_array('read_notifications', $categories, true) && systemToolsTableExists($pdo, 'notifications')) {
            $stmt = $pdo->prepare(
                "DELETE FROM notifications
                 WHERE is_read = 1
                   AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)"
            );
            $stmt->execute();
            $result['deleted'] += $stmt->rowCount();
        }

        if (in_array('temp_files', $categories, true)) {
            $tempDir = systemToolsTempDirectory();
            if (is_dir($tempDir)) {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tempDir, FilesystemIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $item) {
                    if ($item->isFile() && $item->getMTime() < time() - 86400) {
                        $result['bytes'] += (int) $item->getSize();
                        if (@unlink($item->getPathname())) {
                            $result['deleted']++;
                        }
                    } elseif ($item->isDir()) {
                        @rmdir($item->getPathname());
                    }
                }
            }
        }

        $attachmentsDir = $root . '/storage/ticket-message-attachments';
        if (in_array('orphan_attachments', $categories, true) && is_dir($attachmentsDir)) {
            if (!systemToolsTableExists($pdo, 'ticket_message_attachments')) {
                throw new RuntimeException('No se puede limpiar adjuntos huérfanos porque falta la tabla de registro de archivos.');
            }

            $registered = [];
            foreach ($pdo->query('SELECT storage_path FROM ticket_message_attachments')->fetchAll(PDO::FETCH_COLUMN) as $path) {
                $registered[str_replace('\\', '/', ltrim((string) $path, '/'))] = true;
            }

            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($attachmentsDir, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $item) {
                if (!$item->isFile()) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($item->getPathname(), strlen($root) + 1));
                if (!isset($registered[$relative])) {
                    $result['bytes'] += (int) $item->getSize();
                    if (@unlink($item->getPathname())) {
                        $result['deleted']++;
                    }
                }
            }
        }

        if (in_array('empty_directories', $categories, true) && is_dir($attachmentsDir)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($attachmentsDir, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    $children = @scandir($item->getPathname());
                    if (is_array($children) && count($children) === 2 && @rmdir($item->getPathname())) {
                        $result['deleted']++;
                    }
                }
            }
        }

        systemToolsLog($pdo, 'CLEANUP_EXECUTED', 'Se ejecutó una limpieza del sistema.', $actorUserId, 'warning', $result);
        return $result;
    }
}

if (!function_exists('systemToolsHistory')) {
    function systemToolsHistory(PDO $pdo, int $limit = 15, int $offset = 0): array
    {
        if (!systemToolsTableExists($pdo, 'system_maintenance_logs')) {
            return [];
        }
        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);
        $sql = "SELECT l.*, u.name AS actor_name
                FROM system_maintenance_logs l
                LEFT JOIN users u ON u.id = l.actor_user_id
                ORDER BY l.created_at DESC, l.id DESC
                LIMIT {$limit} OFFSET {$offset}";
        return $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }
}

/* =========================================================
   INFORMACIÓN TÉCNICA DEL SISTEMA
   ========================================================= */
if (!function_exists('systemToolsScalar')) {
    function systemToolsScalar(PDO $pdo, string $sql, mixed $default = 0): mixed
    {
        try {
            $value = $pdo->query($sql)->fetchColumn();
            return $value !== false ? $value : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('systemToolsEnvironmentName')) {
    function systemToolsEnvironmentName(): string
    {
        $configured = trim((string) (getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? '')));
        if ($configured !== '') {
            return ucfirst(strtolower($configured));
        }

        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? ''));
        if ($host === '' || str_contains($host, 'localhost') || str_contains($host, '127.0.0.1') || str_contains($host, '::1')) {
            return 'Desarrollo local';
        }

        return 'Producción';
    }
}

if (!function_exists('systemToolsApplicationVersion')) {
    function systemToolsApplicationVersion(): string
    {
        if (defined('APP_VERSION')) {
            return trim((string) constant('APP_VERSION')) ?: 'No configurada';
        }

        $environmentVersion = trim((string) (getenv('APP_VERSION') ?: ''));
        return $environmentVersion !== '' ? $environmentVersion : 'No configurada';
    }
}

if (!function_exists('systemToolsLatestMaintenanceEvent')) {
    function systemToolsLatestMaintenanceEvent(PDO $pdo, array $types = []): ?array
    {
        if (!systemToolsTableExists($pdo, 'system_maintenance_logs')) {
            return null;
        }

        try {
            $parameters = [];
            $where = '';

            if ($types) {
                $placeholders = [];
                foreach (array_values($types) as $index => $type) {
                    $key = ':type_' . $index;
                    $placeholders[] = $key;
                    $parameters[$key] = (string) $type;
                }
                $where = 'WHERE l.action_type IN (' . implode(', ', $placeholders) . ')';
            }

            $stmt = $pdo->prepare(
                "SELECT l.action_type, l.description, l.created_at, u.name AS actor_name
                 FROM system_maintenance_logs l
                 LEFT JOIN users u ON u.id = l.actor_user_id
                 {$where}
                 ORDER BY l.created_at DESC, l.id DESC
                 LIMIT 1"
            );
            $stmt->execute($parameters);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('systemToolsInformation')) {
    function systemToolsInformation(PDO $pdo, array $profile = []): array
    {
        $root = systemToolsProjectRoot();
        $databaseStats = systemToolsDatabaseStats($pdo);

        $mysqlVersion = (string) systemToolsScalar($pdo, 'SELECT VERSION()', 'No identificada');
        $mysqlTimezone = (string) systemToolsScalar($pdo, 'SELECT @@session.time_zone', 'No identificada');
        $databaseCharset = (string) systemToolsScalar($pdo, 'SELECT @@character_set_database', 'No identificado');
        $databaseCollation = (string) systemToolsScalar($pdo, 'SELECT @@collation_database', 'No identificada');

        $companyCount = systemToolsTableExists($pdo, 'client_companies')
            ? (int) systemToolsScalar($pdo, 'SELECT COUNT(*) FROM client_companies', 0)
            : 0;

        $activeCompanyCount = systemToolsTableExists($pdo, 'client_companies')
            ? (int) systemToolsScalar($pdo, 'SELECT COUNT(*) FROM client_companies WHERE status = 1', 0)
            : 0;

        $userCount = systemToolsTableExists($pdo, 'users')
            ? (int) systemToolsScalar($pdo, 'SELECT COUNT(*) FROM users', 0)
            : 0;

        $activeUserCount = systemToolsTableExists($pdo, 'users')
            ? (int) systemToolsScalar($pdo, 'SELECT COUNT(*) FROM users WHERE status = 1', 0)
            : 0;

        $ticketCount = systemToolsTableExists($pdo, 'tickets')
            ? (int) systemToolsScalar($pdo, 'SELECT COUNT(*) FROM tickets', 0)
            : 0;

        $openTicketCount = systemToolsTableExists($pdo, 'tickets')
            ? (int) systemToolsScalar($pdo, "SELECT COUNT(*) FROM tickets WHERE status <> 'CERRADO'", 0)
            : 0;

        $closedTicketCount = systemToolsTableExists($pdo, 'tickets')
            ? (int) systemToolsScalar($pdo, "SELECT COUNT(*) FROM tickets WHERE status = 'CERRADO'", 0)
            : 0;

        $storage = [
            'attachments' => [
                'label' => 'Adjuntos de tickets',
                'path' => $root . '/storage/ticket-message-attachments',
            ],
            'system_logo' => [
                'label' => 'Logo institucional',
                'path' => $root . '/public/uploads/system',
            ],
            'company_logos' => [
                'label' => 'Logos de empresas',
                'path' => $root . '/public/uploads/companies',
            ],
            'user_photos' => [
                'label' => 'Fotografías de usuarios',
                'path' => $root . '/public/uploads/users',
            ],
            'backups' => [
                'label' => 'Copias de seguridad',
                'path' => systemToolsBackupsDirectory(),
            ],
            'temporary' => [
                'label' => 'Archivos temporales',
                'path' => systemToolsTempDirectory(),
            ],
        ];

        $storageTotal = 0;
        foreach ($storage as $key => $item) {
            $bytes = systemToolsDirectorySize($item['path']);
            $storage[$key]['bytes'] = $bytes;
            $storage[$key]['exists'] = is_dir($item['path']);
            $storage[$key]['writable'] = is_dir($item['path']) && is_writable($item['path']);
            $storageTotal += $bytes;
        }

        $freeSpace = @disk_free_space($root);
        $totalSpace = @disk_total_space($root);

        return [
            'platform' => [
                'system_name' => trim((string) ($profile['system_name'] ?? '')) ?: 'Mesa de Ayuda',
                'company_name' => trim((string) ($profile['company_name'] ?? '')) ?: 'No configurada',
                'commercial_name' => trim((string) ($profile['commercial_name'] ?? '')) ?: 'No configurado',
                'version' => systemToolsApplicationVersion(),
                'environment' => systemToolsEnvironmentName(),
                'server_datetime' => date('d/m/Y H:i:s'),
                'timezone' => date_default_timezone_get(),
                'project_root' => $root,
            ],
            'technology' => [
                'php_version' => PHP_VERSION,
                'php_sapi' => PHP_SAPI,
                'server_software' => trim((string) ($_SERVER['SERVER_SOFTWARE'] ?? 'No identificado')),
                'operating_system' => (defined('PHP_OS_FAMILY') ? PHP_OS_FAMILY : PHP_OS) . ' · ' . PHP_OS,
                'memory_limit' => (string) ini_get('memory_limit'),
                'upload_limit' => (string) ini_get('upload_max_filesize'),
                'post_limit' => (string) ini_get('post_max_size'),
                'max_execution_time' => (string) ini_get('max_execution_time') . ' s',
            ],
            'database' => [
                'name' => $databaseStats['database_name'] ?: 'No identificada',
                'version' => $mysqlVersion ?: 'No identificada',
                'tables' => (int) $databaseStats['tables'],
                'size_bytes' => (int) $databaseStats['size_bytes'],
                'charset' => $databaseCharset ?: 'No identificado',
                'collation' => $databaseCollation ?: 'No identificada',
                'timezone' => $mysqlTimezone ?: 'No identificada',
            ],
            'statistics' => [
                'companies' => $companyCount,
                'active_companies' => $activeCompanyCount,
                'users' => $userCount,
                'active_users' => $activeUserCount,
                'tickets' => $ticketCount,
                'open_tickets' => $openTicketCount,
                'closed_tickets' => $closedTicketCount,
            ],
            'storage' => $storage,
            'storage_total_bytes' => $storageTotal,
            'disk_free_bytes' => $freeSpace !== false ? (int) $freeSpace : null,
            'disk_total_bytes' => $totalSpace !== false ? (int) $totalSpace : null,
            'activity' => [
                'last_diagnostic' => systemToolsLatestMaintenanceEvent($pdo, ['DIAGNOSTIC_RUN']),
                'last_backup' => systemToolsLatestMaintenanceEvent($pdo, ['BACKUP_CREATED']),
                'last_cleanup' => systemToolsLatestMaintenanceEvent($pdo, ['CLEANUP_EXECUTED']),
                'last_maintenance_change' => systemToolsLatestMaintenanceEvent($pdo, ['MAINTENANCE_ENABLED', 'MAINTENANCE_DISABLED']),
                'last_action' => systemToolsLatestMaintenanceEvent($pdo),
            ],
        ];
    }
}

/* =========================================================
   PRUEBAS CONTROLADAS DEL SISTEMA
   ========================================================= */
if (!function_exists('systemToolsTestDefinitions')) {
    function systemToolsTestDefinitions(): array
    {
        return [
            'mysql_connection' => [
                'key' => 'mysql_connection',
                'group' => 'Base de datos',
                'label' => 'Conexión con MySQL',
                'description' => 'Comprueba que la conexión PDO responda correctamente.',
                'icon' => 'fa-database',
            ],
            'mysql_read' => [
                'key' => 'mysql_read',
                'group' => 'Base de datos',
                'label' => 'Consulta de lectura',
                'description' => 'Ejecuta una lectura segura y confirma la base de datos activa.',
                'icon' => 'fa-magnifying-glass-chart',
            ],
            'temporary_storage' => [
                'key' => 'temporary_storage',
                'group' => 'Archivos y almacenamiento',
                'label' => 'Escritura temporal',
                'description' => 'Crea, lee y elimina un archivo temporal de comprobación.',
                'icon' => 'fa-file-pen',
            ],
            'directory_permissions' => [
                'key' => 'directory_permissions',
                'group' => 'Archivos y almacenamiento',
                'label' => 'Permisos de carpetas',
                'description' => 'Revisa escritura en adjuntos, logos, fotos, respaldos y temporales.',
                'icon' => 'fa-folder-open',
            ],
            'pdf_generation' => [
                'key' => 'pdf_generation',
                'group' => 'Documentos y multimedia',
                'label' => 'Generación de PDF',
                'description' => 'Genera un PDF mínimo en memoria mediante Dompdf.',
                'icon' => 'fa-file-pdf',
            ],
            'image_processing' => [
                'key' => 'image_processing',
                'group' => 'Documentos y multimedia',
                'label' => 'Lectura de imágenes',
                'description' => 'Valida Fileinfo, lectura de PNG y compatibilidad del servidor.',
                'icon' => 'fa-image',
            ],
            'zip_generation' => [
                'key' => 'zip_generation',
                'group' => 'Servicios internos',
                'label' => 'Creación de archivos ZIP',
                'description' => 'Crea, abre y elimina un ZIP temporal sin tocar respaldos reales.',
                'icon' => 'fa-file-zipper',
            ],
            'mail_configuration' => [
                'key' => 'mail_configuration',
                'group' => 'Servicios internos',
                'label' => 'Configuración de correo',
                'description' => 'Detecta si existe una configuración de correo para pruebas de envío.',
                'icon' => 'fa-envelope-circle-check',
            ],
        ];
    }
}

if (!function_exists('systemToolsTestResult')) {
    function systemToolsTestResult(
        string $key,
        string $status,
        string $detail,
        int $durationMs = 0,
        array $metadata = []
    ): array {
        $allowed = ['ok', 'warning', 'error'];
        $status = in_array($status, $allowed, true) ? $status : 'error';

        return [
            'key' => $key,
            'status' => $status,
            'detail' => $detail,
            'duration_ms' => max(0, $durationMs),
            'metadata' => $metadata,
            'ran_at' => date('Y-m-d H:i:s'),
        ];
    }
}

if (!function_exists('systemToolsRunSingleTest')) {
    function systemToolsRunSingleTest(PDO $pdo, string $key): array
    {
        $definitions = systemToolsTestDefinitions();
        if (!isset($definitions[$key])) {
            throw new InvalidArgumentException('La prueba solicitada no existe.');
        }

        $startedAt = microtime(true);
        $duration = static fn (): int => (int) round((microtime(true) - $startedAt) * 1000);
        $root = systemToolsProjectRoot();

        try {
            switch ($key) {
                case 'mysql_connection':
                    $pdo->query('SELECT 1')->fetchColumn();
                    return systemToolsTestResult($key, 'ok', 'MySQL respondió correctamente.', $duration());

                case 'mysql_read':
                    $stmt = $pdo->query('SELECT DATABASE() AS database_name, NOW() AS server_time');
                    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    $database = trim((string) ($row['database_name'] ?? '')) ?: 'No identificada';
                    $serverTime = trim((string) ($row['server_time'] ?? '')) ?: 'No identificada';
                    return systemToolsTestResult(
                        $key,
                        'ok',
                        'Lectura correcta en ' . $database . ' · hora MySQL ' . $serverTime . '.',
                        $duration(),
                        ['database' => $database]
                    );

                case 'temporary_storage':
                    $tempDir = systemToolsTempDirectory();
                    if (!systemToolsEnsureDirectory($tempDir) || !is_writable($tempDir)) {
                        return systemToolsTestResult($key, 'error', 'La carpeta temporal no permite escritura.', $duration());
                    }

                    $file = $tempDir . DIRECTORY_SEPARATOR . 'system_test_' . bin2hex(random_bytes(6)) . '.txt';
                    $payload = 'HelpDesk system test ' . date('c');
                    $written = @file_put_contents($file, $payload, LOCK_EX);
                    $readBack = is_file($file) ? @file_get_contents($file) : false;
                    $deleted = is_file($file) ? @unlink($file) : true;

                    if ($written === false || $readBack !== $payload || !$deleted) {
                        if (is_file($file)) {
                            @unlink($file);
                        }
                        return systemToolsTestResult($key, 'error', 'No se completó el ciclo de escritura, lectura y eliminación.', $duration());
                    }

                    return systemToolsTestResult($key, 'ok', 'Escritura, lectura y eliminación completadas.', $duration());

                case 'directory_permissions':
                    $directories = [
                        'Adjuntos' => $root . '/storage/ticket-message-attachments',
                        'Respaldos' => systemToolsBackupsDirectory(),
                        'Temporales' => systemToolsTempDirectory(),
                        'Logo institucional' => $root . '/public/uploads/system',
                        'Logos de empresas' => $root . '/public/uploads/companies',
                        'Fotos de usuarios' => $root . '/public/uploads/users',
                    ];

                    $ready = 0;
                    $issues = [];
                    foreach ($directories as $label => $path) {
                        systemToolsEnsureDirectory($path);
                        if (is_dir($path) && is_writable($path)) {
                            $ready++;
                        } else {
                            $issues[] = $label;
                        }
                    }

                    if (!$issues) {
                        return systemToolsTestResult($key, 'ok', $ready . '/' . count($directories) . ' carpetas permiten escritura.', $duration());
                    }

                    return systemToolsTestResult(
                        $key,
                        $ready > 0 ? 'warning' : 'error',
                        $ready . '/' . count($directories) . ' disponibles. Revisar: ' . implode(', ', $issues) . '.',
                        $duration(),
                        ['issues' => $issues]
                    );

                case 'pdf_generation':
                    $autoload = $root . '/vendor/autoload.php';
                    if (!class_exists('Dompdf\\Dompdf') && is_file($autoload)) {
                        require_once $autoload;
                    }
                    if (!class_exists('Dompdf\\Dompdf')) {
                        return systemToolsTestResult($key, 'warning', 'Dompdf no está disponible en vendor/.', $duration());
                    }

                    $dompdf = new Dompdf\Dompdf();
                    $dompdf->loadHtml('<!doctype html><html><body><h1>Prueba HelpDesk</h1><p>Documento temporal.</p></body></html>', 'UTF-8');
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    $binary = $dompdf->output();
                    $valid = is_string($binary) && strlen($binary) > 100 && str_starts_with($binary, '%PDF-');
                    unset($binary, $dompdf);

                    return systemToolsTestResult(
                        $key,
                        $valid ? 'ok' : 'error',
                        $valid ? 'Dompdf generó un PDF válido en memoria.' : 'Dompdf respondió, pero el documento generado no fue válido.',
                        $duration()
                    );

                case 'image_processing':
                    if (!extension_loaded('fileinfo')) {
                        return systemToolsTestResult($key, 'error', 'La extensión Fileinfo no está instalada.', $duration());
                    }

                    $tempDir = systemToolsTempDirectory();
                    if (!systemToolsEnsureDirectory($tempDir) || !is_writable($tempDir)) {
                        return systemToolsTestResult($key, 'error', 'No se pudo preparar la carpeta temporal.', $duration());
                    }

                    $file = $tempDir . DIRECTORY_SEPARATOR . 'image_test_' . bin2hex(random_bytes(6)) . '.png';
                    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl1z6AAAAAASUVORK5CYII=', true);
                    if ($png === false || @file_put_contents($file, $png, LOCK_EX) === false) {
                        return systemToolsTestResult($key, 'error', 'No se pudo crear la imagen temporal.', $duration());
                    }

                    $finfo = new finfo(FILEINFO_MIME_TYPE);
                    $mime = (string) $finfo->file($file);
                    $dimensions = @getimagesize($file);
                    @unlink($file);

                    $valid = $mime === 'image/png' && is_array($dimensions) && (int) ($dimensions[0] ?? 0) === 1;
                    $gdLoaded = extension_loaded('gd');
                    $detail = $valid
                        ? 'PNG leído correctamente' . ($gdLoaded ? ' · GD instalada.' : ' · GD no instalada; la validación básica sí funciona.')
                        : 'El servidor no identificó correctamente la imagen temporal.';

                    return systemToolsTestResult($key, $valid ? ($gdLoaded ? 'ok' : 'warning') : 'error', $detail, $duration());

                case 'zip_generation':
                    if (!class_exists('ZipArchive')) {
                        return systemToolsTestResult($key, 'warning', 'La extensión ZIP no está instalada.', $duration());
                    }

                    $tempDir = systemToolsTempDirectory();
                    if (!systemToolsEnsureDirectory($tempDir) || !is_writable($tempDir)) {
                        return systemToolsTestResult($key, 'error', 'No se pudo preparar la carpeta temporal.', $duration());
                    }

                    $file = $tempDir . DIRECTORY_SEPARATOR . 'zip_test_' . bin2hex(random_bytes(6)) . '.zip';
                    $zip = new ZipArchive();
                    $opened = $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                    if ($opened !== true) {
                        return systemToolsTestResult($key, 'error', 'ZipArchive no pudo crear el archivo temporal.', $duration());
                    }
                    $zip->addFromString('health-check.txt', 'HelpDesk ZIP test');
                    $zip->close();

                    $reader = new ZipArchive();
                    $readOpened = $reader->open($file);
                    $content = $readOpened === true ? $reader->getFromName('health-check.txt') : false;
                    if ($readOpened === true) {
                        $reader->close();
                    }
                    @unlink($file);

                    $valid = $content === 'HelpDesk ZIP test';
                    return systemToolsTestResult(
                        $key,
                        $valid ? 'ok' : 'error',
                        $valid ? 'ZIP temporal creado, leído y eliminado.' : 'El ZIP temporal no pudo verificarse.',
                        $duration()
                    );

                case 'mail_configuration':
                    $host = trim((string) (getenv('SMTP_HOST') ?: getenv('MAIL_HOST') ?: ($_SERVER['SMTP_HOST'] ?? '')));
                    $dsn = trim((string) (getenv('MAILER_DSN') ?: ($_SERVER['MAILER_DSN'] ?? '')));
                    $mailerAvailable = class_exists('PHPMailer\\PHPMailer\\PHPMailer') || class_exists('Symfony\\Component\\Mailer\\Mailer');

                    if ($host === '' && $dsn === '' && !$mailerAvailable) {
                        return systemToolsTestResult(
                            $key,
                            'warning',
                            'No se detectó una configuración SMTP. No se envió ningún correo.',
                            $duration()
                        );
                    }

                    return systemToolsTestResult(
                        $key,
                        'warning',
                        'Se detectó una configuración o librería de correo. El envío real requiere un destinatario de prueba y no se ejecutó.',
                        $duration(),
                        ['configured' => true]
                    );
            }
        } catch (Throwable $e) {
            return systemToolsTestResult(
                $key,
                'error',
                'La prueba no pudo completarse: ' . substr($e->getMessage(), 0, 180),
                $duration()
            );
        }

        return systemToolsTestResult($key, 'error', 'La prueba no produjo un resultado.', $duration());
    }
}

if (!function_exists('systemToolsRunTests')) {
    function systemToolsRunTests(PDO $pdo, array $keys, int $actorUserId): array
    {
        $definitions = systemToolsTestDefinitions();
        $keys = array_values(array_unique(array_filter(
            $keys,
            static fn ($key): bool => is_string($key) && isset($definitions[$key])
        )));

        if (!$keys) {
            throw new InvalidArgumentException('Selecciona al menos una prueba válida.');
        }

        $results = [];
        $summary = ['ok' => 0, 'warning' => 0, 'error' => 0, 'total' => 0];
        $startedAt = microtime(true);

        foreach ($keys as $key) {
            $result = systemToolsRunSingleTest($pdo, $key);
            $results[$key] = $result;
            $summary[$result['status']]++;
            $summary['total']++;

            if (in_array($result['status'], ['warning', 'error'], true)) {
                $definition = $definitions[$key] ?? [];
                systemToolsTechnicalLog(
                    $pdo,
                    $result['status'],
                    'system-tests',
                    (string) ($definition['label'] ?? $key) . ': ' . (string) ($result['detail'] ?? 'Sin detalle.'),
                    [
                        'test_key' => $key,
                        'duration_ms' => (int) ($result['duration_ms'] ?? 0),
                        'metadata' => (array) ($result['metadata'] ?? []),
                    ],
                    $actorUserId
                );
            }
        }

        $run = [
            'results' => $results,
            'summary' => $summary,
            'ran_at' => date('Y-m-d H:i:s'),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ];

        systemToolsLog(
            $pdo,
            'SYSTEM_TESTS_RUN',
            'Se ejecutaron pruebas controladas del sistema.',
            $actorUserId,
            $summary['error'] > 0 ? 'warning' : 'info',
            [
                'tests' => array_keys($results),
                'summary' => $summary,
                'duration_ms' => $run['duration_ms'],
            ]
        );

        return $run;
    }
}

/* =========================================================
   REGISTROS TÉCNICOS
   ========================================================= */
if (!function_exists('systemToolsTechnicalTextSlice')) {
    function systemToolsTechnicalTextSlice(string $value, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, 0, $length, 'UTF-8')
            : substr($value, 0, $length);
    }
}

if (!function_exists('systemToolsRedactTechnicalContext')) {
    function systemToolsRedactTechnicalContext(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 8) {
            return '[PROFUNDIDAD LIMITADA]';
        }

        if (is_array($value)) {
            $clean = [];
            foreach ($value as $key => $item) {
                $keyText = (string) $key;
                if (preg_match('/password|passwd|secret|token|csrf|authorization|cookie|session_id|api[_-]?key|private[_-]?key/i', $keyText)) {
                    $clean[$key] = '[OCULTO]';
                    continue;
                }
                $clean[$key] = systemToolsRedactTechnicalContext($item, $depth + 1);
            }
            return $clean;
        }

        if (is_object($value)) {
            return systemToolsRedactTechnicalContext((array) $value, $depth + 1);
        }

        if (is_string($value)) {
            return systemToolsTechnicalTextSlice($value, 2000);
        }

        return $value;
    }
}

if (!function_exists('systemToolsTechnicalLog')) {
    function systemToolsTechnicalLog(
        PDO $pdo,
        string $level,
        string $module,
        string $message,
        array $context = [],
        ?int $userId = null
    ): void {
        if (!systemToolsTableExists($pdo, 'system_technical_logs')) {
            return;
        }

        $level = in_array($level, ['info', 'warning', 'error', 'critical'], true) ? $level : 'info';
        $module = trim($module) !== '' ? trim($module) : 'system';
        $message = trim($message) !== '' ? trim($message) : 'Evento técnico sin descripción.';

        $context['request'] = array_filter([
            'ip' => systemToolsClientIp(),
            'method' => $_SERVER['REQUEST_METHOD'] ?? null,
            'uri' => isset($_SERVER['REQUEST_URI']) ? substr((string) $_SERVER['REQUEST_URI'], 0, 500) : null,
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 500) : null,
        ], static fn ($value): bool => $value !== null && $value !== '');

        $context = systemToolsRedactTechnicalContext($context);

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO system_technical_logs
                    (level, module, message, context_json, user_id)
                 VALUES
                    (:level, :module, :message, :context_json, :user_id)'
            );
            $stmt->execute([
                'level' => $level,
                'module' => systemToolsTechnicalTextSlice($module, 100),
                'message' => systemToolsTechnicalTextSlice($message, 500),
                'context_json' => $context
                    ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                    : null,
                'user_id' => $userId ?: null,
            ]);
        } catch (Throwable $e) {
            // Un fallo al registrar no debe derribar la operación principal.
        }
    }
}

if (!function_exists('systemToolsTechnicalLogNormalizeFilters')) {
    function systemToolsTechnicalLogNormalizeFilters(array $source): array
    {
        $level = strtolower(trim((string) ($source['level'] ?? '')));
        if (!in_array($level, ['', 'info', 'warning', 'error', 'critical'], true)) {
            $level = '';
        }

        $dateFrom = trim((string) ($source['date_from'] ?? ''));
        $dateTo = trim((string) ($source['date_to'] ?? ''));
        $isDate = static fn (string $value): bool => $value === '' || (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);

        return [
            'q' => systemToolsTechnicalTextSlice(trim((string) ($source['q'] ?? '')), 120),
            'level' => $level,
            'module' => systemToolsTechnicalTextSlice(trim((string) ($source['module'] ?? '')), 100),
            'user_id' => max(0, (int) ($source['user_id'] ?? 0)),
            'date_from' => $isDate($dateFrom) ? $dateFrom : '',
            'date_to' => $isDate($dateTo) ? $dateTo : '',
        ];
    }
}

if (!function_exists('systemToolsTechnicalLogWhere')) {
    function systemToolsTechnicalLogWhere(array $filters): array
    {
        $clauses = ['1 = 1'];
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $clauses[] = '(l.message LIKE :search OR l.module LIKE :search OR u.name LIKE :search OR l.context_json LIKE :search)';
            $params['search'] = '%' . $filters['q'] . '%';
        }
        if (($filters['level'] ?? '') !== '') {
            $clauses[] = 'l.level = :level';
            $params['level'] = $filters['level'];
        }
        if (($filters['module'] ?? '') !== '') {
            $clauses[] = 'l.module = :module';
            $params['module'] = $filters['module'];
        }
        if ((int) ($filters['user_id'] ?? 0) > 0) {
            $clauses[] = 'l.user_id = :user_id';
            $params['user_id'] = (int) $filters['user_id'];
        }
        if (($filters['date_from'] ?? '') !== '') {
            $clauses[] = 'l.created_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (($filters['date_to'] ?? '') !== '') {
            $clauses[] = 'l.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)';
            $params['date_to'] = $filters['date_to'] . ' 00:00:00';
        }

        return [implode(' AND ', $clauses), $params];
    }
}

if (!function_exists('systemToolsTechnicalLogCount')) {
    function systemToolsTechnicalLogCount(PDO $pdo, array $filters = []): int
    {
        if (!systemToolsTableExists($pdo, 'system_technical_logs')) {
            return 0;
        }

        [$where, $params] = systemToolsTechnicalLogWhere(systemToolsTechnicalLogNormalizeFilters($filters));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM system_technical_logs l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE {$where}"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}

if (!function_exists('systemToolsTechnicalLogSummary')) {
    function systemToolsTechnicalLogSummary(PDO $pdo, array $filters = []): array
    {
        $empty = ['total' => 0, 'info' => 0, 'warning' => 0, 'error' => 0, 'critical' => 0];
        if (!systemToolsTableExists($pdo, 'system_technical_logs')) {
            return $empty;
        }

        $filters = systemToolsTechnicalLogNormalizeFilters($filters);
        $filters['level'] = '';
        [$where, $params] = systemToolsTechnicalLogWhere($filters);
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(l.level = 'info') AS info,
                SUM(l.level = 'warning') AS warning,
                SUM(l.level = 'error') AS error,
                SUM(l.level = 'critical') AS critical
             FROM system_technical_logs l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE {$where}"
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        foreach ($empty as $key => $value) {
            $empty[$key] = (int) ($row[$key] ?? 0);
        }
        return $empty;
    }
}

if (!function_exists('systemToolsTechnicalLogs')) {
    function systemToolsTechnicalLogs(PDO $pdo, array $filters = [], int $limit = 15, int $offset = 0): array
    {
        if (!systemToolsTableExists($pdo, 'system_technical_logs')) {
            return [];
        }

        $limit = max(1, min(5000, $limit));
        $offset = max(0, $offset);
        [$where, $params] = systemToolsTechnicalLogWhere(systemToolsTechnicalLogNormalizeFilters($filters));

        $stmt = $pdo->prepare(
            "SELECT l.*, u.name AS user_name, u.email AS user_email
             FROM system_technical_logs l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE {$where}
             ORDER BY l.created_at DESC, l.id DESC
             LIMIT {$limit} OFFSET {$offset}"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $decoded = [];
            if (!empty($row['context_json'])) {
                $value = json_decode((string) $row['context_json'], true);
                $decoded = is_array($value) ? $value : ['raw' => systemToolsTechnicalTextSlice((string) $row['context_json'], 2000)];
            }
            $row['context'] = systemToolsRedactTechnicalContext($decoded);
        }
        unset($row);

        return $rows;
    }
}

if (!function_exists('systemToolsTechnicalLogModules')) {
    function systemToolsTechnicalLogModules(PDO $pdo): array
    {
        if (!systemToolsTableExists($pdo, 'system_technical_logs')) {
            return [];
        }
        return $pdo->query(
            "SELECT DISTINCT module
             FROM system_technical_logs
             WHERE module <> ''
             ORDER BY module ASC"
        )->fetchAll(PDO::FETCH_COLUMN);
    }
}

if (!function_exists('systemToolsTechnicalLogUsers')) {
    function systemToolsTechnicalLogUsers(PDO $pdo): array
    {
        if (!systemToolsTableExists($pdo, 'system_technical_logs')) {
            return [];
        }
        return $pdo->query(
            "SELECT DISTINCT u.id, u.name, u.email
             FROM system_technical_logs l
             INNER JOIN users u ON u.id = l.user_id
             ORDER BY u.name ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('systemToolsDeleteOldTechnicalLogs')) {
    function systemToolsDeleteOldTechnicalLogs(PDO $pdo, int $days, int $actorUserId): int
    {
        if (!systemToolsTableExists($pdo, 'system_technical_logs')) {
            return 0;
        }

        $allowed = [30, 90, 180, 365];
        if (!in_array($days, $allowed, true)) {
            throw new InvalidArgumentException('La antigüedad seleccionada no es válida.');
        }

        $stmt = $pdo->prepare(
            "DELETE FROM system_technical_logs
             WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)"
        );
        $stmt->execute();
        $deleted = $stmt->rowCount();

        systemToolsLog(
            $pdo,
            'TECHNICAL_LOGS_CLEANED',
            'Se eliminaron registros técnicos antiguos.',
            $actorUserId,
            'warning',
            ['days' => $days, 'deleted' => $deleted]
        );

        return $deleted;
    }
}
