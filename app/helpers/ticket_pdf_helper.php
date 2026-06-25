<?php

/**
 * Utilidades de presentación para la exportación PDF de tickets.
 * Mantiene la lógica visual fuera de export-ticket-pdf.php.
 */

if (!function_exists('ticketPdfEscape')) {
    function ticketPdfEscape(mixed $value): string
    {
        return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('ticketPdfStringLength')) {
    function ticketPdfStringLength(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}

if (!function_exists('ticketPdfStringSlice')) {
    function ticketPdfStringSlice(string $value, int $start, int $length): string
    {
        return function_exists('mb_substr')
            ? mb_substr($value, $start, $length, 'UTF-8')
            : substr($value, $start, $length);
    }
}

if (!function_exists('ticketPdfDate')) {
    function ticketPdfDate(?string $value, string $fallback = 'No disponible'): string
    {
        if (empty($value)) {
            return $fallback;
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('d/m/Y H:i', $timestamp) : $fallback;
    }
}

if (!function_exists('ticketPdfDateOnly')) {
    function ticketPdfDateOnly(?string $value, string $fallback = 'No disponible'): string
    {
        if (empty($value)) {
            return $fallback;
        }

        $timestamp = strtotime($value);

        return $timestamp ? date('d/m/Y', $timestamp) : $fallback;
    }
}

if (!function_exists('ticketPdfRoleLabel')) {
    function ticketPdfRoleLabel(?string $role): string
    {
        return match (strtoupper(trim((string)$role))) {
            'ADMIN' => 'Administrador',
            'TECH' => 'Técnico',
            'CLIENT' => 'Cliente',
            'SYSTEM' => 'Sistema',
            default => trim((string)$role) !== '' ? (string)$role : 'No definido',
        };
    }
}

if (!function_exists('ticketPdfStatusLabel')) {
    function ticketPdfStatusLabel(?string $status): string
    {
        $status = trim((string)$status);

        if ($status === '') {
            return 'No definido';
        }

        return ucfirst(strtolower(str_replace('_', ' ', $status)));
    }
}

if (!function_exists('ticketPdfInitials')) {
    function ticketPdfInitials(?string $name): string
    {
        $name = trim((string)$name);

        if ($name === '') {
            return 'U';
        }

        $parts = preg_split('/\s+/u', $name) ?: [];
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $letter = ticketPdfStringSlice($part, 0, 1);
            $initials .= function_exists('mb_strtoupper')
                ? mb_strtoupper($letter, 'UTF-8')
                : strtoupper($letter);

            if (ticketPdfStringLength($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : 'U';
    }
}

if (!function_exists('ticketPdfPlainText')) {
    function ticketPdfPlainText(?string $value): string
    {
        $value = html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }
}

if (!function_exists('ticketPdfExcerpt')) {
    function ticketPdfExcerpt(?string $value, int $limit = 240): string
    {
        $plain = ticketPdfPlainText($value);

        if ($plain === '' || ticketPdfStringLength($plain) <= $limit) {
            return $plain;
        }

        return rtrim(ticketPdfStringSlice($plain, 0, max(1, $limit - 1))) . '…';
    }
}

if (!function_exists('ticketPdfLastItems')) {
    function ticketPdfLastItems(array $items, int $limit): array
    {
        if ($limit <= 0 || count($items) <= $limit) {
            return $items;
        }

        return array_values(array_slice($items, -$limit));
    }
}

if (!function_exists('ticketPdfAttachmentAbsolutePath')) {
    function ticketPdfAttachmentAbsolutePath(array $attachment): ?string
    {
        $relativePath = ltrim((string)($attachment['storage_path'] ?? ''), '/');

        if ($relativePath === '') {
            return null;
        }

        $path = ticketStorageBasePath() . '/' . $relativePath;

        return is_file($path) ? $path : null;
    }
}

if (!function_exists('ticketPdfFitImageDimensions')) {
    /**
     * Calcula dimensiones físicas seguras para Dompdf.
     * Se devuelven valores explícitos porque max-width, max-height y object-fit
     * no siempre son respetados por el motor PDF.
     */
    function ticketPdfFitImageDimensions(
        int $sourceWidth,
        int $sourceHeight,
        int $maxWidth,
        int $maxHeight
    ): array {
        $sourceWidth = max(1, $sourceWidth);
        $sourceHeight = max(1, $sourceHeight);
        $maxWidth = max(1, $maxWidth);
        $maxHeight = max(1, $maxHeight);

        $scale = min(
            1,
            $maxWidth / $sourceWidth,
            $maxHeight / $sourceHeight
        );

        return [
            'width' => max(1, (int)round($sourceWidth * $scale)),
            'height' => max(1, (int)round($sourceHeight * $scale)),
        ];
    }
}

if (!function_exists('ticketPdfLocalImagePayload')) {
    /**
     * Convierte una imagen local en data URI y adjunta dimensiones explícitas.
     * La optimización es opcional y funciona incluso si GD no está disponible:
     * en ese caso se conserva el archivo original, pero Dompdf recibe width y
     * height definidos para evitar páginas vacías o imágenes gigantes.
     */
    function ticketPdfLocalImagePayload(
        string $path,
        bool $optimize = false,
        int $maxWidth = 520,
        int $maxHeight = 260,
        ?string $declaredMimeType = null
    ): ?array {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $imageInfo = @getimagesize($path);
        $sourceWidth = is_array($imageInfo) ? (int)($imageInfo[0] ?? 0) : 0;
        $sourceHeight = is_array($imageInfo) ? (int)($imageInfo[1] ?? 0) : 0;
        $mimeType = strtolower(trim((string)(
            (is_array($imageInfo) ? ($imageInfo['mime'] ?? null) : null)
            ?: $declaredMimeType
            ?: 'application/octet-stream'
        )));

        if (!str_starts_with($mimeType, 'image/')) {
            return null;
        }

        if ($sourceWidth <= 0 || $sourceHeight <= 0) {
            $sourceWidth = $maxWidth;
            $sourceHeight = $maxHeight;
        }

        $dimensions = ticketPdfFitImageDimensions(
            $sourceWidth,
            $sourceHeight,
            $maxWidth,
            $maxHeight
        );

        $content = @file_get_contents($path);

        if ($content === false || $content === '') {
            return null;
        }

        $outputContent = $content;
        $outputMimeType = $mimeType;

        if (
            $optimize
            && function_exists('imagecreatefromstring')
            && function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
        ) {
            $source = @imagecreatefromstring($content);

            if ($source !== false) {
                $canvas = imagecreatetruecolor(
                    $dimensions['width'],
                    $dimensions['height']
                );

                if ($canvas !== false) {
                    $preserveTransparency = in_array($mimeType, ['image/png', 'image/gif'], true)
                        && function_exists('imagepng');

                    if ($preserveTransparency) {
                        imagealphablending($canvas, false);
                        imagesavealpha($canvas, true);
                        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                        imagefilledrectangle(
                            $canvas,
                            0,
                            0,
                            $dimensions['width'],
                            $dimensions['height'],
                            $transparent
                        );
                    } else {
                        $white = imagecolorallocate($canvas, 255, 255, 255);
                        imagefill($canvas, 0, 0, $white);
                    }

                    imagecopyresampled(
                        $canvas,
                        $source,
                        0,
                        0,
                        0,
                        0,
                        $dimensions['width'],
                        $dimensions['height'],
                        imagesx($source),
                        imagesy($source)
                    );

                    ob_start();

                    if ($preserveTransparency) {
                        imagepng($canvas, null, 6);
                        $candidateMimeType = 'image/png';
                    } elseif (function_exists('imagejpeg')) {
                        imagejpeg($canvas, null, 76);
                        $candidateMimeType = 'image/jpeg';
                    } else {
                        $candidateMimeType = null;
                    }

                    $optimizedContent = ob_get_clean();
                    imagedestroy($canvas);
                    imagedestroy($source);

                    if (
                        $candidateMimeType !== null
                        && is_string($optimizedContent)
                        && $optimizedContent !== ''
                    ) {
                        $outputContent = $optimizedContent;
                        $outputMimeType = $candidateMimeType;
                    }
                } else {
                    imagedestroy($source);
                }
            }
        }

        return [
            'src' => 'data:' . $outputMimeType . ';base64,' . base64_encode($outputContent),
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'mime_type' => $outputMimeType,
        ];
    }
}


if (!function_exists('ticketPdfProjectImagePath')) {
    /**
     * Resuelve una ruta de imagen guardada en la base de datos respecto a la
     * raíz del proyecto y evita que una ruta relativa salga de esa carpeta.
     */
    function ticketPdfProjectImagePath(?string $relativePath): ?string
    {
        $relativePath = trim(str_replace('\\', '/', (string)$relativePath));

        if ($relativePath === '') {
            return null;
        }

        $projectRoot = realpath(dirname(__DIR__, 2));

        if ($projectRoot === false) {
            return null;
        }

        $candidate = $projectRoot . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, ltrim($relativePath, '/'));
        $resolved = realpath($candidate);

        if ($resolved === false || !is_file($resolved) || !is_readable($resolved)) {
            return null;
        }

        $rootPrefix = rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($resolved, $rootPrefix) ? $resolved : null;
    }
}

if (!function_exists('ticketPdfSquareProfilePayload')) {
    /**
     * Prepara una fotografía de perfil cuadrada para que el avatar conserve
     * una circunferencia perfecta en Dompdf. Con GD se recorta al centro; sin
     * GD se mantiene la imagen original, pero se fuerza un marco cuadrado.
     */
    function ticketPdfSquareProfilePayload(?string $relativePath, int $size = 72): ?array
    {
        $path = ticketPdfProjectImagePath($relativePath);

        if ($path === null) {
            return null;
        }

        $content = @file_get_contents($path);
        $imageInfo = @getimagesize($path);

        if ($content === false || $content === '' || !is_array($imageInfo)) {
            return null;
        }

        $mimeType = strtolower((string)($imageInfo['mime'] ?? ''));
        if (!str_starts_with($mimeType, 'image/')) {
            return null;
        }

        $size = max(24, min(240, $size));
        $outputContent = $content;
        $outputMimeType = $mimeType;

        if (
            function_exists('imagecreatefromstring')
            && function_exists('imagecreatetruecolor')
            && function_exists('imagecopyresampled')
        ) {
            $source = @imagecreatefromstring($content);

            if ($source !== false) {
                $sourceWidth = imagesx($source);
                $sourceHeight = imagesy($source);
                $cropSize = max(1, min($sourceWidth, $sourceHeight));
                $sourceX = max(0, (int)floor(($sourceWidth - $cropSize) / 2));
                $sourceY = max(0, (int)floor(($sourceHeight - $cropSize) / 2));
                $canvas = imagecreatetruecolor($size, $size);

                if ($canvas !== false) {
                    $preserveTransparency = in_array($mimeType, ['image/png', 'image/gif', 'image/webp'], true)
                        && function_exists('imagepng');

                    if ($preserveTransparency) {
                        imagealphablending($canvas, false);
                        imagesavealpha($canvas, true);
                        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
                        imagefilledrectangle($canvas, 0, 0, $size, $size, $transparent);
                    } else {
                        $white = imagecolorallocate($canvas, 255, 255, 255);
                        imagefill($canvas, 0, 0, $white);
                    }

                    imagecopyresampled(
                        $canvas,
                        $source,
                        0,
                        0,
                        $sourceX,
                        $sourceY,
                        $size,
                        $size,
                        $cropSize,
                        $cropSize
                    );

                    ob_start();
                    if ($preserveTransparency) {
                        imagepng($canvas, null, 6);
                        $candidateMimeType = 'image/png';
                    } elseif (function_exists('imagejpeg')) {
                        imagejpeg($canvas, null, 82);
                        $candidateMimeType = 'image/jpeg';
                    } else {
                        $candidateMimeType = null;
                    }
                    $candidateContent = ob_get_clean();

                    imagedestroy($canvas);
                    imagedestroy($source);

                    if (
                        $candidateMimeType !== null
                        && is_string($candidateContent)
                        && $candidateContent !== ''
                    ) {
                        $outputContent = $candidateContent;
                        $outputMimeType = $candidateMimeType;
                    }
                } else {
                    imagedestroy($source);
                }
            }
        }

        return [
            'src' => 'data:' . $outputMimeType . ';base64,' . base64_encode($outputContent),
            'width' => $size,
            'height' => $size,
            'mime_type' => $outputMimeType,
        ];
    }
}

if (!function_exists('ticketPdfAvatarContent')) {
    /**
     * Devuelve una foto de perfil incrustada cuando existe y las iniciales
     * como respaldo cuando el usuario no tiene fotografía o el archivo falta.
     */
    function ticketPdfAvatarContent(
        ?string $name,
        ?string $profilePhoto,
        int $size
    ): string {
        $payload = ticketPdfSquareProfilePayload($profilePhoto, max(48, $size * 2));

        if ($payload !== null) {
            return '<img class="avatar-photo" src="'
                . ticketPdfEscape($payload['src'])
                . '" width="' . (int)$size
                . '" height="' . (int)$size
                . '" alt="Foto de perfil">';
        }

        return ticketPdfEscape(ticketPdfInitials($name));
    }
}

if (!function_exists('ticketPdfAttachmentImagePayload')) {
    function ticketPdfAttachmentImagePayload(
        array $attachment,
        bool $optimize = false,
        int $maxWidth = 520,
        int $maxHeight = 260
    ): ?array {
        $mimeType = strtolower((string)($attachment['mime_type'] ?? ''));

        if (!str_starts_with($mimeType, 'image/')) {
            return null;
        }

        $path = ticketPdfAttachmentAbsolutePath($attachment);

        if ($path === null) {
            return null;
        }

        return ticketPdfLocalImagePayload(
            $path,
            $optimize,
            $maxWidth,
            $maxHeight,
            $mimeType
        );
    }
}

if (!function_exists('ticketPdfImageDataUri')) {
    /**
     * Compatibilidad con llamadas existentes. Para maquetar imágenes nuevas,
     * se recomienda ticketPdfAttachmentImagePayload(), que también devuelve
     * las dimensiones físicas.
     */
    function ticketPdfImageDataUri(
        array $attachment,
        bool $optimize = false,
        int $maxWidth = 520,
        int $maxHeight = 260
    ): ?string {
        $payload = ticketPdfAttachmentImagePayload(
            $attachment,
            $optimize,
            $maxWidth,
            $maxHeight
        );

        return $payload['src'] ?? null;
    }
}

if (!function_exists('ticketPdfRenderRichBody')) {
    function ticketPdfRenderRichBody(
        array $message,
        array $attachments,
        bool $embedInlineImages
    ): string {
        $format = strtolower((string)($message['message_format'] ?? 'plain'));
        $body = $format === 'html'
            ? ticketSanitizeRichHtml((string)($message['message'] ?? ''))
            : nl2br(ticketPdfEscape($message['message'] ?? ''));

        if (!$embedInlineImages) {
            $body = preg_replace('/<img\b[^>]*>/i', '', $body) ?? $body;
            return '<div class="pdf-rich-message">' . $body . '</div>';
        }

        foreach ($attachments as $attachment) {
            if ((int)($attachment['is_inline'] ?? 0) !== 1) {
                continue;
            }

            $attachmentId = (int)($attachment['id'] ?? 0);
            $dataUri = ticketPdfImageDataUri($attachment, false);

            if ($attachmentId <= 0 || $dataUri === null) {
                continue;
            }

            $publicUrl = '/helpdesk-php/download-message-attachment.php?id='
                . $attachmentId
                . '&inline=1';

            $body = str_replace($publicUrl, $dataUri, $body);
            $body = str_replace(
                htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8'),
                $dataUri,
                $body
            );
            $body = str_replace(
                str_replace('&', '&amp;', $publicUrl),
                $dataUri,
                $body
            );
        }

        return '<div class="pdf-rich-message">' . $body . '</div>';
    }
}

if (!function_exists('ticketPdfRenderInlineImages')) {
    function ticketPdfRenderInlineImages(array $attachments, int $limit = 2): string
    {
        $images = array_values(array_filter(
            $attachments,
            static fn(array $attachment): bool => (int)($attachment['is_inline'] ?? 0) === 1
        ));

        $visible = $limit > 0
            ? array_slice($images, 0, $limit)
            : $images;

        if (empty($visible)) {
            return '';
        }

        $rendered = [];
        foreach ($visible as $attachment) {
            $payload = ticketPdfAttachmentImagePayload(
                $attachment,
                true,
                265,
                145
            );

            if ($payload === null) {
                continue;
            }

            $rendered[] = [
                'payload' => $payload,
                'name' => (string)($attachment['original_name'] ?? 'Imagen'),
            ];
        }

        if (empty($rendered)) {
            return '';
        }

        $html = '<table class="pdf-image-grid"><tbody>';

        foreach (array_chunk($rendered, 2) as $row) {
            $html .= '<tr>';

            foreach ($row as $item) {
                $payload = $item['payload'];
                $html .= '<td><div class="pdf-image-item">'
                    . '<img src="' . ticketPdfEscape($payload['src']) . '"'
                    . ' width="' . (int)$payload['width'] . '"'
                    . ' height="' . (int)$payload['height'] . '"'
                    . ' alt="Evidencia">'
                    . '<div class="pdf-image-caption">'
                    . ticketPdfEscape($item['name'])
                    . '</div></div></td>';
            }

            if (count($row) === 1) {
                $html .= '<td class="pdf-image-empty"></td>';
            }

            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        $hiddenCount = max(0, count($images) - count($visible));
        if ($hiddenCount > 0) {
            $html .= '<div class="pdf-more-files">+ ' . $hiddenCount
                . ' ' . ($hiddenCount === 1 ? 'imagen adicional' : 'imágenes adicionales')
                . ' listada' . ($hiddenCount === 1 ? '' : 's') . '</div>';
        }

        return $html;
    }
}

if (!function_exists('ticketPdfRenderDocuments')) {
    function ticketPdfRenderDocuments(array $attachments): string
    {
        $documents = array_values(array_filter(
            $attachments,
            static fn(array $attachment): bool => (int)($attachment['is_inline'] ?? 0) !== 1
        ));

        if (empty($documents)) {
            return '';
        }

        $html = '<div class="pdf-attachments">';

        foreach ($documents as $attachment) {
            $extension = strtoupper(pathinfo(
                (string)($attachment['original_name'] ?? ''),
                PATHINFO_EXTENSION
            ));

            $html .= '<div class="pdf-attachment">'
                . '<span class="pdf-attachment-type">'
                . ticketPdfEscape($extension !== '' ? $extension : 'FILE')
                . '</span>'
                . '<span class="pdf-attachment-name">'
                . ticketPdfEscape($attachment['original_name'] ?? 'Archivo adjunto')
                . '</span>'
                . '<span class="pdf-attachment-size">'
                . ticketPdfEscape(ticketFormatBytes((int)($attachment['file_size'] ?? 0)))
                . '</span>'
                . '</div>';
        }

        return $html . '</div>';
    }
}

if (!function_exists('ticketPdfActivityLabel')) {
    function ticketPdfActivityLabel(?string $type): string
    {
        return match (strtoupper(trim((string)$type))) {
            'CREATED' => 'Ticket creado',
            'AUTO_ASSIGNED' => 'Asignación automática',
            'ASSIGNED' => 'Cambio de técnico',
            'LEVEL_ESCALATED' => 'Escalamiento de nivel',
            'LEVEL_DEESCALATED' => 'Cambio de nivel',
            'REPLIED' => 'Respuesta registrada',
            'INTERNAL_MESSAGE' => 'Nota interna registrada',
            'STATUS_CHANGED' => 'Cambio de estado',
            'PRIORITY_CHANGED' => 'Cambio de prioridad',
            'CATEGORY_CHANGED' => 'Cambio de categoría',
            'CLOSED' => 'Ticket cerrado',
            'REOPENED' => 'Ticket reabierto',
            'SLA_BREACHED' => 'SLA vencido',
            default => ticketPdfStatusLabel($type ?: 'Actividad'),
        };
    }
}

if (!function_exists('ticketPdfActivityIsCritical')) {
    function ticketPdfActivityIsCritical(array $activity): bool
    {
        $type = strtoupper((string)($activity['activity_type'] ?? ''));

        if (in_array($type, [
            'CREATED',
            'AUTO_ASSIGNED',
            'ASSIGNED',
            'LEVEL_ESCALATED',
            'LEVEL_DEESCALATED',
            'CLOSED',
            'REOPENED',
            'SLA_BREACHED',
        ], true)) {
            return true;
        }

        return $type === 'STATUS_CHANGED'
            && strtoupper((string)($activity['new_value'] ?? '')) === 'CERRADO';
    }
}

if (!function_exists('ticketPdfPrepareExecutiveActivities')) {
    function ticketPdfPrepareExecutiveActivities(array $activities, int $recentLimit = 15): array
    {
        $recent = ticketPdfLastItems($activities, $recentLimit);
        $selected = [];

        foreach ($recent as $activity) {
            $selected[(int)($activity['id'] ?? spl_object_id((object)$activity))] = $activity;
        }

        foreach ($activities as $activity) {
            if (ticketPdfActivityIsCritical($activity)) {
                $selected[(int)($activity['id'] ?? spl_object_id((object)$activity))] = $activity;
            }
        }

        $selected = array_values($selected);
        usort($selected, static function (array $a, array $b): int {
            $dateCompare = strcmp((string)($a['created_at'] ?? ''), (string)($b['created_at'] ?? ''));

            return $dateCompare !== 0
                ? $dateCompare
                : ((int)($a['id'] ?? 0) <=> (int)($b['id'] ?? 0));
        });

        $critical = array_values(array_filter($selected, 'ticketPdfActivityIsCritical'));
        $minor = array_values(array_filter(
            $selected,
            static fn(array $activity): bool => !ticketPdfActivityIsCritical($activity)
        ));

        $availableMinorSlots = max(0, 8 - count($critical));
        $visibleMinor = $availableMinorSlots > 0
            ? array_slice($minor, -$availableMinorSlots)
            : [];

        $visibleIds = [];
        foreach (array_merge($critical, $visibleMinor) as $activity) {
            $visibleIds[(int)($activity['id'] ?? 0)] = true;
        }

        $explicit = array_values(array_filter(
            $selected,
            static fn(array $activity): bool => isset($visibleIds[(int)($activity['id'] ?? 0)])
        ));

        $grouped = array_values(array_filter(
            $selected,
            static fn(array $activity): bool => !isset($visibleIds[(int)($activity['id'] ?? 0)])
        ));

        return [
            'selected' => $selected,
            'explicit' => $explicit,
            'grouped' => $grouped,
        ];
    }
}

if (!function_exists('ticketPdfFindClosedAt')) {
    function ticketPdfFindClosedAt(array $ticket, array $activities): ?string
    {
        if (!empty($ticket['closed_at'])) {
            return (string)$ticket['closed_at'];
        }

        if (($ticket['status'] ?? '') !== 'CERRADO') {
            return null;
        }

        $candidates = [];

        foreach ($activities as $activity) {
            $type = strtoupper((string)($activity['activity_type'] ?? ''));
            $newValue = strtoupper((string)($activity['new_value'] ?? ''));

            if (
                $type === 'CLOSED'
                || ($type === 'STATUS_CHANGED' && $newValue === 'CERRADO')
            ) {
                $candidates[] = (string)($activity['created_at'] ?? '');
            }
        }

        $candidates = array_values(array_filter($candidates));

        if (!empty($candidates)) {
            sort($candidates);
            return end($candidates) ?: null;
        }

        return !empty($ticket['updated_at']) ? (string)$ticket['updated_at'] : null;
    }
}

if (!function_exists('ticketPdfDocumentRows')) {
    function ticketPdfDocumentRows(array $attachments): array
    {
        return array_values(array_filter(
            $attachments,
            static fn(array $attachment): bool => (int)($attachment['is_inline'] ?? 0) !== 1
        ));
    }
}
