<?php

declare(strict_types=1);

require_once __DIR__ . '/password_reset.php';

if (!function_exists('loginTwoFactorConfig')) {
    function loginTwoFactorConfig(): array
    {
        return passwordResetConfig();
    }
}

if (!function_exists('loginTwoFactorTableReady')) {
    function loginTwoFactorTableReady(PDO $pdo): bool
    {
        try {
            $statement = $pdo->prepare('SHOW TABLES LIKE :table_name');
            $statement->execute(['table_name' => 'login_two_factor_codes']);
            return (bool) $statement->fetch(PDO::FETCH_NUM);
        } catch (Throwable $exception) {
            return false;
        }
    }
}

if (!function_exists('loginTwoFactorMailConfigured')) {
    function loginTwoFactorMailConfigured(?array $config = null): bool
    {
        return passwordResetMailConfigured($config ?? loginTwoFactorConfig());
    }
}

if (!function_exists('loginTwoFactorActive')) {
    function loginTwoFactorActive(PDO $pdo): bool
    {
        return loginTwoFactorTableReady($pdo)
            && loginTwoFactorMailConfigured(loginTwoFactorConfig());
    }
}

if (!function_exists('loginTwoFactorTtlMinutes')) {
    function loginTwoFactorTtlMinutes(?array $config = null): int
    {
        $config ??= loginTwoFactorConfig();
        return max(3, min(15, (int) ($config['two_factor_ttl_minutes'] ?? 5)));
    }
}

if (!function_exists('loginTwoFactorResendSeconds')) {
    function loginTwoFactorResendSeconds(?array $config = null): int
    {
        $config ??= loginTwoFactorConfig();
        return max(30, min(180, (int) ($config['two_factor_resend_seconds'] ?? 60)));
    }
}

if (!function_exists('loginTwoFactorMaxAttempts')) {
    function loginTwoFactorMaxAttempts(?array $config = null): int
    {
        $config ??= loginTwoFactorConfig();
        return max(3, min(8, (int) ($config['two_factor_max_attempts'] ?? 5)));
    }
}

if (!function_exists('loginTwoFactorMaskEmail')) {
    function loginTwoFactorMaskEmail(string $email): string
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'tu correo registrado';
        }

        [$local, $domain] = explode('@', $email, 2);
        $localLength = strlen($local);
        $first = substr($local, 0, 1);
        $last = $localLength > 2 ? substr($local, -1) : '';
        $hidden = str_repeat('•', max(3, min(7, $localLength)));

        return $first . $hidden . $last . '@' . $domain;
    }
}

if (!function_exists('loginTwoFactorLogMailError')) {
    function loginTwoFactorLogMailError(int $userId, string $error): void
    {
        $directory = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $message = sprintf(
            "[%s] user_id=%d two-factor mail error: %s%s",
            date('Y-m-d H:i:s'),
            $userId,
            preg_replace('/[\r\n]+/', ' ', substr($error, 0, 800)),
            PHP_EOL
        );

        if (is_dir($directory) && is_writable($directory)) {
            @file_put_contents($directory . '/two-factor-mail.log', $message, FILE_APPEND | LOCK_EX);
            return;
        }

        error_log(trim($message));
    }
}

if (!function_exists('loginTwoFactorRecentlyRequested')) {
    function loginTwoFactorRecentlyRequested(PDO $pdo, int $userId, int $seconds = 60): bool
    {
        if ($userId <= 0 || !loginTwoFactorTableReady($pdo)) {
            return false;
        }

        $seconds = max(30, min(300, $seconds));
        $statement = $pdo->prepare(
            "SELECT id
             FROM login_two_factor_codes
             WHERE user_id = :user_id
               AND created_at >= DATE_SUB(NOW(), INTERVAL {$seconds} SECOND)
             ORDER BY id DESC
             LIMIT 1"
        );
        $statement->execute(['user_id' => $userId]);

        return (bool) $statement->fetch(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('loginTwoFactorIssueChallenge')) {
    function loginTwoFactorIssueChallenge(PDO $pdo, array $user, ?int $ttlMinutes = null, ?int $maxAttempts = null): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $email = trim((string) ($user['email'] ?? ''));

        if ($userId <= 0 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('La cuenta no es válida para autenticación de dos pasos.');
        }

        if (!loginTwoFactorTableReady($pdo)) {
            throw new RuntimeException('La tabla de autenticación de dos pasos no está instalada.');
        }

        $config = loginTwoFactorConfig();
        $ttlMinutes = $ttlMinutes ?? loginTwoFactorTtlMinutes($config);
        $maxAttempts = $maxAttempts ?? loginTwoFactorMaxAttempts($config);
        $ttlMinutes = max(3, min(15, $ttlMinutes));
        $maxAttempts = max(3, min(8, $maxAttempts));

        $code = (string) random_int(100000, 999999);
        $codeHash = password_hash($code, PASSWORD_DEFAULT);

        $pdo->beginTransaction();

        try {
            $invalidate = $pdo->prepare(
                "UPDATE login_two_factor_codes
                 SET invalidated_at = NOW()
                 WHERE user_id = :user_id
                   AND used_at IS NULL
                   AND invalidated_at IS NULL"
            );
            $invalidate->execute(['user_id' => $userId]);

            $insert = $pdo->prepare(
                "INSERT INTO login_two_factor_codes
                    (user_id, email, code_hash, expires_at, max_attempts, delivery_status, request_ip, user_agent, created_at)
                 VALUES
                    (:user_id, :email, :code_hash, DATE_ADD(NOW(), INTERVAL {$ttlMinutes} MINUTE), :max_attempts,
                     'PENDING', :request_ip, :user_agent, NOW())"
            );
            $insert->execute([
                'user_id' => $userId,
                'email' => $email,
                'code_hash' => $codeHash,
                'max_attempts' => $maxAttempts,
                'request_ip' => function_exists('systemSecurityClientIp')
                    ? (systemSecurityClientIp() ?: null)
                    : null,
                'user_agent' => function_exists('systemSecurityUserAgent')
                    ? (systemSecurityUserAgent() ?: null)
                    : null,
            ]);

            $challengeId = (int) $pdo->lastInsertId();
            $pdo->commit();

            return [
                'id' => $challengeId,
                'code' => $code,
                'expires_in_minutes' => $ttlMinutes,
                'expires_at_ts' => time() + ($ttlMinutes * 60),
                'max_attempts' => $maxAttempts,
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }
}

if (!function_exists('loginTwoFactorMarkDelivery')) {
    function loginTwoFactorMarkDelivery(PDO $pdo, int $challengeId, bool $sent, string $error = ''): void
    {
        if ($challengeId <= 0 || !loginTwoFactorTableReady($pdo)) {
            return;
        }

        $sql = $sent
            ? "UPDATE login_two_factor_codes
               SET delivery_status = 'SENT',
                   sent_at = NOW(),
                   last_error = NULL
               WHERE id = :id"
            : "UPDATE login_two_factor_codes
               SET delivery_status = 'FAILED',
                   invalidated_at = NOW(),
                   last_error = :last_error
               WHERE id = :id";

        $statement = $pdo->prepare($sql);
        $params = ['id' => $challengeId];
        if (!$sent) {
            $params['last_error'] = substr($error, 0, 500);
        }
        $statement->execute($params);
    }
}

if (!function_exists('loginTwoFactorSendEmail')) {
    function loginTwoFactorSendEmail(array $config, array $user, string $code, int $ttlMinutes): array
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

        if (!preg_match('/^[0-9]{6}$/', $code)) {
            return ['ok' => false, 'error' => 'El código de verificación no es válido.'];
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

            $logoPath = dirname(__DIR__, 2) . '/public/assets/img/pronet-system-logo.png';
            $hasEmbeddedLogo = is_file($logoPath);
            if ($hasEmbeddedLogo) {
                $mail->addEmbeddedImage($logoPath, 'pronetLogo', 'pronet-system-logo.png');
            }

            $safeName = htmlspecialchars($recipientName !== '' ? $recipientName : 'Usuario', ENT_QUOTES, 'UTF-8');
            $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
            $safeTtl = max(3, min(15, $ttlMinutes));

            $mail->isHTML(true);
            $mail->Subject = 'Código de verificación de Mesa de Ayuda';
            $logoHtml = $hasEmbeddedLogo
                ? '<img src="cid:pronetLogo" alt="Pronet System" style="display:block;width:210px;max-width:80%;height:auto;margin:0 auto 18px;">'
                : '<div style="margin:0 auto 18px;text-align:center;color:#ffffff;font-size:24px;font-weight:900;letter-spacing:.02em;">Pronet <span style="color:#ff7a00;">System</span></div>';

            $mail->Body = <<<HTML
<!doctype html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:24px;background:#f4f7f9;font-family:Arial,sans-serif;color:#0f172a;">
  <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:22px;overflow:hidden;box-shadow:0 18px 44px rgba(15,23,42,.08);">
    <div style="padding:26px 28px 25px;background:#0f3d2e;color:#ffffff;border-bottom:4px solid #ff7a00;text-align:center;">
      {$logoHtml}
      <div style="font-size:12px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;color:#ffd6ad;">Verificación de acceso</div>
      <h1 style="margin:8px 0 0;font-size:26px;line-height:1.2;">Código de seguridad</h1>
    </div>
    <div style="padding:30px 30px 28px;line-height:1.65;">
      <p style="margin:0 0 16px;">Hola, <strong>{$safeName}</strong>.</p>
      <p style="margin:0 0 20px;">Para completar tu inicio de sesión en la Mesa de Ayuda, ingresa este código:</p>
      <div style="margin:26px 0;text-align:center;">
        <div style="display:inline-block;padding:16px 24px;border-radius:17px;background:#fff7ed;border:1px solid #fed7aa;color:#0f172a;font-size:34px;font-weight:900;letter-spacing:8px;">{$safeCode}</div>
      </div>
      <p style="margin:0 0 14px;">Este código vencerá en <strong>{$safeTtl} minutos</strong> y solo podrá utilizarse una vez.</p>
      <p style="margin:0;padding:13px 14px;border-radius:13px;background:#f8fafc;border:1px solid #eef2f7;font-size:13px;color:#64748b;">Si no intentaste iniciar sesión, cambia tu contraseña o informa al administrador.</p>
    </div>
  </div>
</body>
</html>
HTML;
            $mail->AltBody = "Hola, {$recipientName}.\n\n"
                . "Tu código de verificación para Mesa de Ayuda es: {$code}\n\n"
                . "El código vencerá en {$safeTtl} minutos y solo puede utilizarse una vez.\n"
                . "Si no intentaste iniciar sesión, informa al administrador.";

            $mail->send();
            return ['ok' => true, 'error' => ''];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }
}

if (!function_exists('loginTwoFactorFindChallenge')) {
    function loginTwoFactorFindChallenge(PDO $pdo, int $challengeId, int $userId, bool $forUpdate = false): ?array
    {
        if ($challengeId <= 0 || $userId <= 0 || !loginTwoFactorTableReady($pdo)) {
            return null;
        }

        $sql = "SELECT
                    tf.id AS challenge_id,
                    tf.user_id,
                    tf.email,
                    tf.code_hash,
                    tf.expires_at,
                    tf.attempts,
                    tf.max_attempts,
                    tf.delivery_status,
                    u.name,
                    u.email AS current_email,
                    u.role,
                    u.status
                FROM login_two_factor_codes tf
                INNER JOIN users u ON u.id = tf.user_id
                WHERE tf.id = :id
                  AND tf.user_id = :user_id
                  AND tf.used_at IS NULL
                  AND tf.invalidated_at IS NULL
                  AND tf.delivery_status = 'SENT'
                  AND u.status = 1
                LIMIT 1";

        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $challengeId, 'user_id' => $userId]);
        $record = $statement->fetch(PDO::FETCH_ASSOC);

        return $record ?: null;
    }
}

if (!function_exists('loginTwoFactorValidateCode')) {
    function loginTwoFactorValidateCode(PDO $pdo, int $challengeId, int $userId, string $code): array
    {
        $code = preg_replace('/\D+/', '', $code) ?? '';

        if (!preg_match('/^[0-9]{6}$/', $code)) {
            return ['ok' => false, 'message' => 'Ingresa el código de 6 dígitos.'];
        }

        if (!loginTwoFactorTableReady($pdo)) {
            return ['ok' => false, 'message' => 'La verificación de dos pasos no está instalada.'];
        }

        $pdo->beginTransaction();

        try {
            $challenge = loginTwoFactorFindChallenge($pdo, $challengeId, $userId, true);

            if (!$challenge) {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'El código ya no está disponible. Inicia sesión otra vez.'];
            }

            $expiresAt = strtotime((string) ($challenge['expires_at'] ?? ''));
            if ($expiresAt === false || $expiresAt < time()) {
                $expire = $pdo->prepare('UPDATE login_two_factor_codes SET invalidated_at = NOW() WHERE id = :id');
                $expire->execute(['id' => $challengeId]);
                $pdo->commit();

                return ['ok' => false, 'expired' => true, 'message' => 'El código venció. Inicia sesión nuevamente.'];
            }

            $attempts = (int) ($challenge['attempts'] ?? 0);
            $maxAttempts = max(3, (int) ($challenge['max_attempts'] ?? 5));

            if ($attempts >= $maxAttempts) {
                $block = $pdo->prepare('UPDATE login_two_factor_codes SET invalidated_at = NOW() WHERE id = :id');
                $block->execute(['id' => $challengeId]);
                $pdo->commit();

                return ['ok' => false, 'expired' => true, 'message' => 'Se agotaron los intentos del código. Inicia sesión nuevamente.'];
            }

            if (!password_verify($code, (string) $challenge['code_hash'])) {
                $attempts++;
                $remaining = max(0, $maxAttempts - $attempts);

                $sql = $remaining <= 0
                    ? 'UPDATE login_two_factor_codes SET attempts = :attempts, invalidated_at = NOW() WHERE id = :id'
                    : 'UPDATE login_two_factor_codes SET attempts = :attempts WHERE id = :id';

                $fail = $pdo->prepare($sql);
                $fail->execute(['attempts' => $attempts, 'id' => $challengeId]);
                $pdo->commit();

                $remainingMessage = $remaining <= 0
                    ? 'Se agotaron los intentos del código. Inicia sesión nuevamente.'
                    : 'Código incorrecto. Te queda' . ($remaining === 1 ? '' : 'n') . " {$remaining} intento" . ($remaining === 1 ? '' : 's') . '.';

                return [
                    'ok' => false,
                    'expired' => $remaining <= 0,
                    'message' => $remainingMessage,
                ];
            }

            $consume = $pdo->prepare('UPDATE login_two_factor_codes SET used_at = NOW() WHERE id = :id AND used_at IS NULL');
            $consume->execute(['id' => $challengeId]);

            if ($consume->rowCount() !== 1) {
                throw new RuntimeException('El código ya fue utilizado.');
            }

            $invalidate = $pdo->prepare(
                "UPDATE login_two_factor_codes
                 SET invalidated_at = NOW()
                 WHERE user_id = :user_id
                   AND id <> :id
                   AND used_at IS NULL
                   AND invalidated_at IS NULL"
            );
            $invalidate->execute(['user_id' => $userId, 'id' => $challengeId]);

            $pdo->commit();
            return ['ok' => true, 'message' => 'Código verificado correctamente.'];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            return ['ok' => false, 'message' => 'No se pudo validar el código. Inténtalo nuevamente.'];
        }
    }
}
