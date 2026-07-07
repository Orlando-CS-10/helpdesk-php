<?php

declare(strict_types=1);

require_once __DIR__ . '/password_reset.php';

if (!function_exists('ticketMailConfig')) {
    function ticketMailConfig(): array
    {
        return function_exists('passwordResetConfig') ? passwordResetConfig() : [];
    }
}

if (!function_exists('ticketMailConfigured')) {
    function ticketMailConfigured(?array $config = null): bool
    {
        $config ??= ticketMailConfig();

        if (function_exists('passwordResetMailConfigured')) {
            return passwordResetMailConfigured($config);
        }

        $required = ['enabled', 'host', 'username', 'password', 'from_email', 'app_url'];
        foreach ($required as $key) {
            if (trim((string)($config[$key] ?? '')) === '') {
                return false;
            }
        }

        return filter_var((string)($config['from_email'] ?? ''), FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('ticketMailBaseUrl')) {
    function ticketMailBaseUrl(array $config): string
    {
        $base = trim((string)($config['app_url'] ?? ''));
        return $base !== '' ? rtrim($base, '/') : 'http://localhost/helpdesk-php';
    }
}

if (!function_exists('ticketMailSafe')) {
    function ticketMailSafe(mixed $value): string
    {
        return htmlspecialchars(trim((string)$value), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ticketMailLimitText')) {
    function ticketMailLimitText(string $value, int $limit = 1200): string
    {
        $value = trim(preg_replace('/\s+/', ' ', strip_tags($value)) ?? '');
        if (mb_strlen($value, 'UTF-8') <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit - 3, 'UTF-8') . '...';
    }
}

if (!function_exists('ticketMailFormatDate')) {
    function ticketMailFormatDate(?string $date): string
    {
        $date = trim((string)$date);
        if ($date === '') {
            return '-';
        }

        try {
            $dt = new DateTime($date, new DateTimeZone('America/Lima'));
            return $dt->format('d/m/Y H:i');
        } catch (Throwable) {
            return $date;
        }
    }
}

if (!function_exists('ticketMailLabel')) {
    function ticketMailLabel(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '-';
        }

        return str_replace('_', ' ', mb_convert_case($value, MB_CASE_TITLE, 'UTF-8'));
    }
}

if (!function_exists('ticketMailLogError')) {
    function ticketMailLogError(string $context, int $ticketId, string $error): void
    {
        $directory = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        $message = sprintf(
            "[%s] context=%s ticket_id=%d ticket-mail error: %s%s",
            date('Y-m-d H:i:s'),
            preg_replace('/[^a-z0-9_\-]/i', '', $context),
            $ticketId,
            preg_replace('/[\r\n]+/', ' ', substr($error, 0, 900)),
            PHP_EOL
        );

        if (is_dir($directory) && is_writable($directory)) {
            @file_put_contents($directory . '/ticket-mail.log', $message, FILE_APPEND | LOCK_EX);
            return;
        }

        error_log(trim($message));
    }
}

if (!function_exists('ticketMailFetchUserById')) {
    function ticketMailFetchUserById(PDO $pdo, int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT id, name, email, role, tech_level, status FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            return $user ?: null;
        } catch (Throwable) {
            return null;
        }
    }
}

if (!function_exists('ticketMailFetchActiveAdmins')) {
    function ticketMailFetchActiveAdmins(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT id, name, email, role, status FROM users WHERE role = 'ADMIN' AND status = 1 AND email IS NOT NULL AND email <> '' ORDER BY id ASC");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }
}

if (!function_exists('ticketMailSendHtml')) {
    function ticketMailSendHtml(array $recipient, string $subject, string $html, string $altBody, ?array $config = null): array
    {
        $config ??= ticketMailConfig();

        if (!ticketMailConfigured($config)) {
            return ['ok' => false, 'error' => 'La configuración de correo no está completa.'];
        }

        $autoloadPath = dirname(__DIR__, 2) . '/vendor/autoload.php';
        if (!is_file($autoloadPath)) {
            return ['ok' => false, 'error' => 'No se encontró vendor/autoload.php.'];
        }

        require_once $autoloadPath;

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            return ['ok' => false, 'error' => 'PHPMailer no está disponible.'];
        }

        $recipientEmail = trim((string)($recipient['email'] ?? ''));
        $recipientName = trim((string)($recipient['name'] ?? 'Usuario'));

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'El correo del destinatario no es válido.'];
        }

        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = trim((string)($config['host'] ?? ''));
            $mail->Port = (int)($config['port'] ?? 587);
            $mail->SMTPAuth = !empty($config['auth']);
            $mail->Username = trim((string)($config['username'] ?? ''));
            $mail->Password = (string)($config['password'] ?? '');
            $mail->CharSet = 'UTF-8';
            $mail->Timeout = 20;

            $encryption = strtolower(trim((string)($config['encryption'] ?? 'tls')));
            if ($encryption === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            } elseif (in_array($encryption, ['ssl', 'smtps'], true)) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = '';
                $mail->SMTPAutoTLS = false;
            }

            $fromEmail = trim((string)($config['from_email'] ?? ''));
            $fromName = trim((string)($config['from_name'] ?? 'Mesa de Ayuda Pronet System'));
            $mail->setFrom($fromEmail, $fromName);
            $mail->addAddress($recipientEmail, $recipientName !== '' ? $recipientName : $recipientEmail);

            $replyTo = trim((string)($config['reply_to'] ?? ''));
            if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo, $fromName);
            }

            $logoPath = dirname(__DIR__, 2) . '/public/assets/img/pronet-system-logo.png';
            if (is_file($logoPath)) {
                $mail->addEmbeddedImage($logoPath, 'pronetLogo', 'pronet-system-logo.png');
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $altBody;
            $mail->send();

            return ['ok' => true, 'error' => ''];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }
}

if (!function_exists('ticketMailBuildCreatedTemplate')) {
    function ticketMailBuildCreatedTemplate(array $ticket, array $requester, ?array $assignedTech, string $recipientRole, string $detailUrl): array
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        $subject = ticketMailSafe($ticket['subject'] ?? 'Ticket sin asunto');
        $description = nl2br(ticketMailSafe(ticketMailLimitText((string)($ticket['description'] ?? ''), 1400)));
        $priority = ticketMailSafe(ticketMailLabel((string)($ticket['priority'] ?? '')));
        $category = ticketMailSafe(ticketMailLabel((string)($ticket['category'] ?? '')));
        $status = ticketMailSafe(ticketMailLabel((string)($ticket['status'] ?? '')));
        $createdAt = ticketMailSafe(ticketMailFormatDate((string)($ticket['created_at'] ?? '')));
        $slaHours = (int)($ticket['sla_hours'] ?? 0);
        $slaText = $slaHours > 0 ? $slaHours . ' hora' . ($slaHours === 1 ? '' : 's') : '-';
        $profileName = trim((string)($ticket['sla_profile_name'] ?? ''));
        $ttaDue = ticketMailSafe(ticketMailFormatDate($ticket['sla_tta_due_at'] ?? null));
        $ttrDue = ticketMailSafe(ticketMailFormatDate($ticket['sla_ttr_due_at'] ?? null));
        $requesterName = ticketMailSafe($requester['name'] ?? 'Cliente');
        $requesterEmail = ticketMailSafe($requester['email'] ?? '');
        $assignedName = $assignedTech ? ticketMailSafe($assignedTech['name'] ?? 'Técnico asignado') : 'Pendiente de asignación';
        $assignedLevel = $assignedTech ? 'Nivel ' . (int)($assignedTech['tech_level'] ?? 1) : '-';
        $safeUrl = ticketMailSafe($detailUrl);

        $headline = match ($recipientRole) {
            'client' => 'Tu ticket fue registrado',
            'tech' => 'Nuevo ticket asignado',
            'admin' => 'Nuevo ticket creado',
            default => 'Actualización de ticket',
        };

        $intro = match ($recipientRole) {
            'client' => 'Hemos recibido tu solicitud. El equipo técnico revisará la incidencia y podrás hacer seguimiento desde la Mesa de Ayuda.',
            'tech' => 'Se te asignó un nuevo ticket. Revisa el resumen y atiende la incidencia según prioridad y SLA.',
            'admin' => 'Se registró un nuevo ticket en la Mesa de Ayuda. Este es el resumen operativo del caso.',
            default => 'Se generó una actualización en la Mesa de Ayuda.',
        };

        $mailSubject = match ($recipientRole) {
            'client' => "Ticket #{$ticketId} registrado - Pronet System",
            'tech' => "Nuevo ticket asignado #{$ticketId} - Pronet System",
            'admin' => "Nuevo ticket creado #{$ticketId} - Pronet System",
            default => "Ticket #{$ticketId} - Pronet System",
        };

        $logoHtml = '<img src="cid:pronetLogo" alt="Pronet System" style="display:block;max-width:210px;width:210px;height:auto;">';

        $html = <<<HTML
<!doctype html>
<html lang="es">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:24px;background:#f4f7f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
  <div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:22px;overflow:hidden;box-shadow:0 18px 44px rgba(15,23,42,.10);">
    <div style="padding:24px 28px;background:#0b1714;color:#ffffff;border-bottom:5px solid #ff7a00;">
      {$logoHtml}
      <div style="margin-top:18px;font-size:12px;font-weight:bold;letter-spacing:.08em;text-transform:uppercase;color:#ffbf80;">Mesa de Ayuda</div>
      <h1 style="margin:8px 0 0;font-size:28px;line-height:1.18;color:#ffffff;">{$headline}</h1>
      <p style="margin:10px 0 0;color:#d7e2dd;font-size:14px;line-height:1.6;">{$intro}</p>
    </div>

    <div style="padding:28px;line-height:1.6;">
      <div style="display:inline-block;padding:8px 12px;border-radius:999px;background:#fff7ed;color:#c45c0b;font-size:12px;font-weight:bold;">Ticket #{$ticketId}</div>
      <h2 style="margin:16px 0 8px;color:#111827;font-size:22px;line-height:1.3;">{$subject}</h2>

      <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin:20px 0;border-collapse:separate;border-spacing:0 10px;">
        <tr>
          <td style="width:50%;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;vertical-align:top;"><div style="font-size:11px;color:#64748b;font-weight:bold;text-transform:uppercase;">Prioridad</div><div style="margin-top:5px;font-size:15px;font-weight:bold;color:#0f172a;">{$priority}</div></td>
          <td style="width:50%;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;vertical-align:top;"><div style="font-size:11px;color:#64748b;font-weight:bold;text-transform:uppercase;">Categoría</div><div style="margin-top:5px;font-size:15px;font-weight:bold;color:#0f172a;">{$category}</div></td>
        </tr>
        <tr>
          <td style="width:50%;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;vertical-align:top;"><div style="font-size:11px;color:#64748b;font-weight:bold;text-transform:uppercase;">Estado</div><div style="margin-top:5px;font-size:15px;font-weight:bold;color:#0f172a;">{$status}</div></td>
          <td style="width:50%;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;vertical-align:top;"><div style="font-size:11px;color:#64748b;font-weight:bold;text-transform:uppercase;">SLA</div><div style="margin-top:5px;font-size:15px;font-weight:bold;color:#0f172a;">{$slaText}</div></td>
        </tr>
        <tr>
          <td style="width:50%;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;vertical-align:top;"><div style="font-size:11px;color:#64748b;font-weight:bold;text-transform:uppercase;">Cliente</div><div style="margin-top:5px;font-size:15px;font-weight:bold;color:#0f172a;">{$requesterName}</div><div style="font-size:12px;color:#64748b;word-break:break-all;">{$requesterEmail}</div></td>
          <td style="width:50%;padding:14px;border:1px solid #e2e8f0;border-radius:14px;background:#f8fafc;vertical-align:top;"><div style="font-size:11px;color:#64748b;font-weight:bold;text-transform:uppercase;">Técnico</div><div style="margin-top:5px;font-size:15px;font-weight:bold;color:#0f172a;">{$assignedName}</div><div style="font-size:12px;color:#64748b;">{$assignedLevel}</div></td>
        </tr>
      </table>

      <div style="padding:16px;border:1px solid #e2e8f0;border-radius:16px;background:#ffffff;">
        <div style="font-size:12px;color:#64748b;font-weight:bold;text-transform:uppercase;">Descripción reportada</div>
        <p style="margin:8px 0 0;color:#334155;font-size:14px;line-height:1.7;">{$description}</p>
      </div>

      <div style="margin-top:18px;padding:14px;border-radius:14px;background:#eef6f2;border:1px solid #cfe4da;color:#0f5132;font-size:13px;line-height:1.55;">
        <strong>Fechas clave:</strong><br>
        Registro: {$createdAt}<br>
        Primera atención esperada: {$ttaDue}<br>
        Resolución esperada: {$ttrDue}
      </div>

      <p style="margin:26px 0 6px;text-align:center;">
        <a href="{$safeUrl}" style="display:inline-block;padding:14px 22px;border-radius:13px;background:#ff7a00;color:#ffffff;text-decoration:none;font-weight:bold;box-shadow:0 12px 24px rgba(255,122,0,.24);">Ver ticket</a>
      </p>

      <p style="margin:18px 0 0;color:#64748b;font-size:12px;text-align:center;">Este correo fue generado automáticamente por la Mesa de Ayuda de Pronet System.</p>
      <div style="margin-top:18px;padding-top:16px;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;word-break:break-all;">
        Si el botón no funciona, copia esta dirección en tu navegador:<br>{$safeUrl}
      </div>
    </div>
  </div>
</body>
</html>
HTML;

        $alt = "{$headline}\n\n"
            . "Ticket #{$ticketId}: " . strip_tags((string)($ticket['subject'] ?? '')) . "\n"
            . "Cliente: " . (string)($requester['name'] ?? '') . "\n"
            . "Prioridad: " . ticketMailLabel((string)($ticket['priority'] ?? '')) . "\n"
            . "Categoría: " . ticketMailLabel((string)($ticket['category'] ?? '')) . "\n"
            . "Estado: " . ticketMailLabel((string)($ticket['status'] ?? '')) . "\n"
            . "Técnico: " . ($assignedTech['name'] ?? 'Pendiente de asignación') . "\n"
            . "Creado: " . ticketMailFormatDate((string)($ticket['created_at'] ?? '')) . "\n\n"
            . "Descripción: " . ticketMailLimitText((string)($ticket['description'] ?? ''), 900) . "\n\n"
            . "Ver ticket: {$detailUrl}";

        return [
            'subject' => $mailSubject,
            'html' => $html,
            'alt' => $alt,
        ];
    }
}

if (!function_exists('ticketMailNotifyTicketCreated')) {
    function ticketMailNotifyTicketCreated(PDO $pdo, array $ticket, array $requester, ?int $assignedTo, ?array $assignedTech = null): void
    {
        $ticketId = (int)($ticket['id'] ?? 0);
        if ($ticketId <= 0) {
            return;
        }

        $config = ticketMailConfig();
        if (!ticketMailConfigured($config)) {
            ticketMailLogError('ticket_created', $ticketId, 'Correo no configurado. Se omitió el envío.');
            return;
        }

        if ($assignedTo !== null && (!$assignedTech || empty($assignedTech['email']))) {
            $assignedTech = ticketMailFetchUserById($pdo, $assignedTo) ?: $assignedTech;
        }

        $detailUrl = ticketMailBaseUrl($config) . '/ticket-detail.php?id=' . $ticketId;

        $recipients = [];

        if (!empty($requester['email']) && filter_var((string)$requester['email'], FILTER_VALIDATE_EMAIL)) {
            $recipients[] = ['role' => 'client', 'user' => $requester];
        }

        if ($assignedTech && !empty($assignedTech['email']) && filter_var((string)$assignedTech['email'], FILTER_VALIDATE_EMAIL)) {
            $recipients[] = ['role' => 'tech', 'user' => $assignedTech];
        }

        foreach (ticketMailFetchActiveAdmins($pdo) as $admin) {
            $adminEmail = trim((string)($admin['email'] ?? ''));
            if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $alreadyAdded = false;
            foreach ($recipients as $recipient) {
                if (strcasecmp((string)($recipient['user']['email'] ?? ''), $adminEmail) === 0) {
                    $alreadyAdded = true;
                    break;
                }
            }

            if (!$alreadyAdded) {
                $recipients[] = ['role' => 'admin', 'user' => $admin];
            }
        }

        foreach ($recipients as $recipient) {
            $template = ticketMailBuildCreatedTemplate($ticket, $requester, $assignedTech, $recipient['role'], $detailUrl);
            $result = ticketMailSendHtml($recipient['user'], $template['subject'], $template['html'], $template['alt'], $config);
            if (!$result['ok']) {
                ticketMailLogError('ticket_created_' . $recipient['role'], $ticketId, $result['error'] ?? 'Error desconocido al enviar correo.');
            }
        }
    }
}
