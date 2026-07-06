<?php

declare(strict_types=1);

if (!function_exists('passwordResetConfig')) {
    function passwordResetConfig(): array
    {
        static $config = null;

        if (is_array($config)) {
            return $config;
        }

        $path = dirname(__DIR__) . '/config/mail.php';
        $loaded = is_file($path) ? require $path : [];
        $config = is_array($loaded) ? $loaded : [];

        return $config;
    }
}

if (!function_exists('passwordResetTableReady')) {
    function passwordResetTableReady(PDO $pdo): bool
    {
        try {
            $statement = $pdo->prepare('SHOW TABLES LIKE :table_name');
            $statement->execute(['table_name' => 'password_reset_tokens']);
            return (bool) $statement->fetch(PDO::FETCH_NUM);
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('passwordResetMailConfigured')) {
    function passwordResetMailConfigured(?array $config = null): bool
    {
        $config ??= passwordResetConfig();

        if (empty($config['enabled'])) {
            return false;
        }

        $required = ['host', 'username', 'password', 'from_email', 'app_url'];
        foreach ($required as $key) {
            $value = trim((string) ($config[$key] ?? ''));
            if ($value === '' || str_contains($value, 'TU_') || str_contains($value, 'tu-cuenta')) {
                return false;
            }
        }

        return filter_var((string) $config['from_email'], FILTER_VALIDATE_EMAIL) !== false
            && filter_var((string) $config['app_url'], FILTER_VALIDATE_URL) !== false;
    }
}

if (!function_exists('passwordResetBaseUrl')) {
    function passwordResetBaseUrl(array $config): string
    {
        return rtrim(trim((string) ($config['app_url'] ?? '')), '/');
    }
}

if (!function_exists('passwordResetTokenTtl')) {
    function passwordResetTokenTtl(array $config): int
    {
        return max(10, min(120, (int) ($config['token_ttl_minutes'] ?? 30)));
    }
}

if (!function_exists('passwordResetRecentlyRequested')) {
    function passwordResetRecentlyRequested(PDO $pdo, int $userId, int $seconds = 60): bool
    {
        if ($userId <= 0 || !passwordResetTableReady($pdo)) {
            return false;
        }

        $seconds = max(30, min(900, $seconds));
        $statement = $pdo->prepare(
            "SELECT id
             FROM password_reset_tokens
             WHERE user_id = :user_id
               AND created_at >= DATE_SUB(NOW(), INTERVAL {$seconds} SECOND)
             ORDER BY id DESC
             LIMIT 1"
        );
        $statement->execute(['user_id' => $userId]);

        return (bool) $statement->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('passwordResetIssueToken')) {
    function passwordResetIssueToken(PDO $pdo, array $user, int $ttlMinutes): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $email = trim((string) ($user['email'] ?? ''));

        if ($userId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('La cuenta no es válida para recuperación.');
        }

        $ttlMinutes = max(10, min(120, $ttlMinutes));
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $pdo->beginTransaction();

        try {
            $invalidate = $pdo->prepare(
                "UPDATE password_reset_tokens
                 SET invalidated_at = NOW()
                 WHERE user_id = :user_id
                   AND used_at IS NULL
                   AND invalidated_at IS NULL"
            );
            $invalidate->execute(['user_id' => $userId]);

            $insert = $pdo->prepare(
                "INSERT INTO password_reset_tokens
                    (user_id, email, token_hash, expires_at, delivery_status, request_ip, user_agent, created_at)
                 VALUES
                    (:user_id, :email, :token_hash, DATE_ADD(NOW(), INTERVAL {$ttlMinutes} MINUTE),
                     'PENDING', :request_ip, :user_agent, NOW())"
            );
            $insert->execute([
                'user_id' => $userId,
                'email' => $email,
                'token_hash' => $tokenHash,
                'request_ip' => function_exists('systemSecurityClientIp')
                    ? (systemSecurityClientIp() ?: null)
                    : null,
                'user_agent' => function_exists('systemSecurityUserAgent')
                    ? (systemSecurityUserAgent() ?: null)
                    : null,
            ]);

            $tokenId = (int) $pdo->lastInsertId();
            $pdo->commit();

            return [
                'id' => $tokenId,
                'token' => $rawToken,
                'expires_in_minutes' => $ttlMinutes,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

if (!function_exists('passwordResetMarkDelivery')) {
    function passwordResetMarkDelivery(PDO $pdo, int $tokenId, bool $sent, string $error = ''): void
    {
        if ($tokenId <= 0 || !passwordResetTableReady($pdo)) {
            return;
        }

        $sql = $sent
            ? "UPDATE password_reset_tokens
               SET delivery_status = 'SENT',
                   sent_at = NOW(),
                   last_error = NULL
               WHERE id = :id"
            : "UPDATE password_reset_tokens
               SET delivery_status = 'FAILED',
                   invalidated_at = NOW(),
                   last_error = :last_error
               WHERE id = :id";

        $statement = $pdo->prepare($sql);
        $params = ['id' => $tokenId];
        if (!$sent) {
            $params['last_error'] = substr($error, 0, 500);
        }
        $statement->execute($params);
    }
}

if (!function_exists('passwordResetLogMailError')) {
    function passwordResetLogMailError(int $userId, string $error): void
    {
        $directory = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $message = sprintf(
            "[%s] user_id=%d password-reset mail error: %s%s",
            date('Y-m-d H:i:s'),
            $userId,
            preg_replace('/[\r\n]+/', ' ', substr($error, 0, 800)),
            PHP_EOL
        );

        if (is_dir($directory) && is_writable($directory)) {
            @file_put_contents($directory . '/password-reset-mail.log', $message, FILE_APPEND | LOCK_EX);
            return;
        }

        error_log(trim($message));
    }
}

if (!function_exists('passwordResetSendEmail')) {
    function passwordResetSendEmail(array $config, array $user, string $resetUrl, int $ttlMinutes): array
    {
        $autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoloadPath)) {
            return ['ok' => false, 'error' => 'No se encontró vendor/autoload.php.'];
        }

        require_once $autoloadPath;

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return ['ok' => false, 'error' => 'PHPMailer no está disponible.'];
        }

        $recipientEmail = trim((string) ($user['email'] ?? ''));
        $recipientName = trim((string) ($user['name'] ?? 'Usuario'));

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'El correo del destinatario no es válido.'];
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = trim((string) ($config['host'] ?? ''));
            $mail->Port = (int) ($config['port'] ?? 587);
            $mail->SMTPAuth = !empty($config['auth']);
            $mail->Username = trim((string) ($config['username'] ?? ''));
            $mail->Password = (string) ($config['password'] ?? '');
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 20;

            $encryption = strtolower(trim((string) ($config['encryption'] ?? 'tls')));
            if ($encryption === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (in_array($encryption, ['ssl', 'smtps'], true)) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $fromEmail = trim((string) ($config['from_email'] ?? ''));
            $fromName = trim((string) ($config['from_name'] ?? 'Mesa de Ayuda'));
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($recipientEmail, $recipientName);

            $replyTo = trim((string) ($config['reply_to'] ?? ''));
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo, $fromName);
            }

            $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Usuario', ENT_QUOTES, 'UTF-8');
            $safeUrl = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
            $safeTtl = max(10, min(120, $ttlMinutes));

            $mail->isHTML(true);
            $mail->Subject = 'Restablece tu contraseña de Mesa de Ayuda';
            $mail->Body = <<<HTML
<!doctype html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:24px;background:#f4f7f9;font-family:Arial,sans-serif;color:#0f172a;">
  <div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:20px;overflow:hidden;">
    <div style="padding:24px 28px;background:#0f3d2e;color:#ffffff;border-bottom:4px solid #ff7a00;">
      <div style="font-size:12px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;color:#ffd6ad;">Seguridad de la cuenta</div>
      <h1 style="margin:8px 0 0;font-size:26px;">Restablece tu contraseña</h1>
    </div>
    <div style="padding:28px;line-height:1.65;">
      <p>Hola, <strong>{$safeName}</strong>.</p>
      <p>Recibimos una solicitud para cambiar la contraseña de tu cuenta en la Mesa de Ayuda.</p>
      <p style="margin:26px 0;text-align:center;">
        <a href="{$safeUrl}" style="display:inline-block;padding:14px 22px;border-radius:12px;background:#ff7a00;color:#ffffff;text-decoration:none;font-weight:bold;">Crear una nueva contraseña</a>
      </p>
      <p>Este enlace vencerá en <strong>{$safeTtl} minutos</strong> y solo podrá utilizarse una vez.</p>
      <p style="font-size:13px;color:#64748b;">Si no solicitaste el cambio, ignora este mensaje. Tu contraseña actual seguirá funcionando.</p>
      <div style="margin-top:24px;padding-top:18px;border-top:1px solid #e2e8f0;font-size:12px;color:#64748b;word-break:break-all;">
        Si el botón no funciona, copia esta dirección en tu navegador:<br>{$safeUrl}
      </div>
    </div>
  </div>
</body>
</html>
HTML;
            $mail->AltBody = "Hola, {$recipientName}.\n\n"
                . "Usa el siguiente enlace para restablecer tu contraseña:\n{$resetUrl}\n\n"
                . "El enlace vencerá en {$safeTtl} minutos y solo puede utilizarse una vez.\n"
                . "Si no solicitaste este cambio, ignora el mensaje.";

            $mail->send();
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }
}

if (!function_exists('passwordResetFindValidToken')) {
    function passwordResetFindValidToken(PDO $pdo, string $rawToken, bool $forUpdate = false): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $rawToken) || !passwordResetTableReady($pdo)) {
            return null;
        }

        $sql = "SELECT
                    pr.id AS token_id,
                    pr.user_id,
                    pr.email AS requested_email,
                    pr.expires_at,
                    u.name,
                    u.email,
                    u.status,
                    u.created_at
                FROM password_reset_tokens pr
                INNER JOIN users u ON u.id = pr.user_id
                WHERE pr.token_hash = :token_hash
                  AND pr.delivery_status = 'SENT'
                  AND pr.used_at IS NULL
                  AND pr.invalidated_at IS NULL
                  AND pr.expires_at >= NOW()
                  AND u.status = 1
                LIMIT 1";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $pdo->prepare($sql);
        $statement->execute(['token_hash' => hash('sha256', $rawToken)]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record ?: null;
    }
}

if (!function_exists('passwordResetConsumeToken')) {
    function passwordResetConsumeToken(PDO $pdo, int $tokenId, int $userId): void
    {
        $consume = $pdo->prepare(
            "UPDATE password_reset_tokens
             SET used_at = NOW()
             WHERE id = :id
               AND user_id = :user_id
               AND used_at IS NULL
               AND invalidated_at IS NULL"
        );
        $consume->execute(['id' => $tokenId, 'user_id' => $userId]);

        if ($consume->rowCount() !== 1) {
            throw new RuntimeException('El enlace ya fue utilizado o dejó de ser válido.');
        }

        $invalidate = $pdo->prepare(
            "UPDATE password_reset_tokens
             SET invalidated_at = NOW()
             WHERE user_id = :user_id
               AND id <> :id
               AND used_at IS NULL
               AND invalidated_at IS NULL"
        );
        $invalidate->execute(['user_id' => $userId, 'id' => $tokenId]);
    }
}
