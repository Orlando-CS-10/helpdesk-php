<?php

/**
 * Utilidades para mensajes enriquecidos y archivos adjuntos de tickets.
 *
 * Compatible con:
 * - ticket_messages
 * - ticket_internal_messages
 * - ticket_message_attachments
 */

if (date_default_timezone_get() !== 'America/Lima') {
    date_default_timezone_set('America/Lima');
}

if (!function_exists('ticketTableExists')) {
    function ticketTableExists(PDO $pdo, string $table): bool
    {
        static $cache = [];
        $key = spl_object_id($pdo) . ':' . $table;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.tables
                 WHERE table_schema = DATABASE()
                   AND table_name = :table_name'
            );
            $statement->execute(['table_name' => $table]);
            $cache[$key] = (bool)$statement->fetchColumn();
        } catch (Throwable $exception) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}

if (!function_exists('ticketColumnExists')) {
    function ticketColumnExists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $key = spl_object_id($pdo) . ':' . $table . ':' . $column;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        try {
            $statement = $pdo->prepare(
                'SELECT COUNT(*)
                 FROM information_schema.columns
                 WHERE table_schema = DATABASE()
                   AND table_name = :table_name
                   AND column_name = :column_name'
            );
            $statement->execute([
                'table_name' => $table,
                'column_name' => $column,
            ]);
            $cache[$key] = (bool)$statement->fetchColumn();
        } catch (Throwable $exception) {
            $cache[$key] = false;
        }

        return $cache[$key];
    }
}

if (!function_exists('ticketNormalizeUploadedFiles')) {
    function ticketNormalizeUploadedFiles(?array $files): array
    {
        if (empty($files) || !isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [[
                'name' => (string)($files['name'] ?? ''),
                'type' => (string)($files['type'] ?? ''),
                'tmp_name' => (string)($files['tmp_name'] ?? ''),
                'error' => (int)($files['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($files['size'] ?? 0),
            ]];
        }

        $normalized = [];
        $count = count($files['name']);

        for ($index = 0; $index < $count; $index++) {
            $normalized[] = [
                'name' => (string)($files['name'][$index] ?? ''),
                'type' => (string)($files['type'][$index] ?? ''),
                'tmp_name' => (string)($files['tmp_name'][$index] ?? ''),
                'error' => (int)($files['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($files['size'][$index] ?? 0),
            ];
        }

        return $normalized;
    }
}

if (!function_exists('ticketSanitizeFileName')) {
    function ticketSanitizeFileName(string $name): string
    {
        $name = trim($name);
        $name = preg_replace('/[^\pL\pN._ -]+/u', '_', $name) ?? 'archivo';
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        $name = trim($name, " ._\t\n\r\0\x0B");

        return $name !== '' ? $name : 'archivo';
    }
}

if (!function_exists('ticketStorageBasePath')) {
    function ticketStorageBasePath(): string
    {
        return dirname(__DIR__, 2) . '/storage/ticket-message-attachments';
    }
}

if (!function_exists('ticketEnsureStorageDirectory')) {
    function ticketEnsureStorageDirectory(): string
    {
        $relativeFolder = date('Y/m');
        $folder = ticketStorageBasePath() . '/' . $relativeFolder;

        if (!is_dir($folder) && !mkdir($folder, 0775, true) && !is_dir($folder)) {
            throw new RuntimeException('No se pudo crear la carpeta para los archivos del mensaje.');
        }

        return $relativeFolder;
    }
}

if (!function_exists('ticketDetectMimeType')) {
    function ticketDetectMimeType(string $tmpPath): string
    {
        if ($tmpPath === '' || !is_file($tmpPath)) {
            return 'application/octet-stream';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);

        return is_string($mime) && $mime !== ''
            ? strtolower($mime)
            : 'application/octet-stream';
    }
}

if (!function_exists('ticketUploadSingleMessageFile')) {
    function ticketUploadSingleMessageFile(
        array $file,
        int $ticketId,
        bool $isInline
    ): array {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('No se recibió el archivo seleccionado.');
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Uno de los archivos no pudo cargarse correctamente.');
        }

        $tmpPath = (string)($file['tmp_name'] ?? '');
        $size = (int)($file['size'] ?? 0);
        $originalName = ticketSanitizeFileName((string)($file['name'] ?? 'archivo'));

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new RuntimeException('El archivo recibido no es válido.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $mimeType = ticketDetectMimeType($tmpPath);

        $allowedImages = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'gif' => ['image/gif'],
        ];

        $allowedDocuments = [
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/octet-stream',
            ],
            'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ],
            'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
            'pptx' => [
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                'application/zip',
                'application/octet-stream',
            ],
            'txt' => ['text/plain', 'application/octet-stream'],
            'csv' => ['text/plain', 'text/csv', 'application/vnd.ms-excel', 'application/octet-stream'],
            'zip' => ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'],
        ];

        $maxBytes = $isInline ? 10 * 1024 * 1024 : 15 * 1024 * 1024;

        if ($size <= 0 || $size > $maxBytes) {
            throw new RuntimeException(
                $isInline
                    ? 'Cada imagen debe pesar como máximo 10 MB.'
                    : 'Cada documento debe pesar como máximo 15 MB.'
            );
        }

        if ($isInline) {
            if (!isset($allowedImages[$extension]) || !in_array($mimeType, $allowedImages[$extension], true)) {
                throw new RuntimeException('Solo se permiten imágenes JPG, PNG, WEBP o GIF dentro del mensaje.');
            }
        } else {
            if (!isset($allowedDocuments[$extension])) {
                throw new RuntimeException(
                    'Formato no permitido. Usa PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, CSV o ZIP.'
                );
            }

            if (!in_array($mimeType, $allowedDocuments[$extension], true)) {
                throw new RuntimeException('El contenido del documento no coincide con su extensión.');
            }
        }

        $relativeFolder = ticketEnsureStorageDirectory();
        $storedName = sprintf(
            'ticket_%d_%s_%s.%s',
            $ticketId,
            $isInline ? 'img' : 'doc',
            bin2hex(random_bytes(12)),
            $extension
        );

        $relativePath = $relativeFolder . '/' . $storedName;
        $absolutePath = ticketStorageBasePath() . '/' . $relativePath;

        if (!move_uploaded_file($tmpPath, $absolutePath)) {
            throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
        }

        return [
            'original_name' => $originalName,
            'stored_name' => $storedName,
            'mime_type' => $mimeType,
            'file_size' => $size,
            'storage_path' => $relativePath,
            'is_inline' => $isInline ? 1 : 0,
            'absolute_path' => $absolutePath,
        ];
    }
}

if (!function_exists('ticketSanitizeInlineStyle')) {
    function ticketSanitizeInlineStyle(string $style): string
    {
        $allowedProperties = [
            'text-align',
            'color',
            'background-color',
            'font-size',
            'font-weight',
            'font-style',
            'text-decoration',
        ];

        $safeDeclarations = [];

        foreach (explode(';', $style) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);

            if (!in_array($property, $allowedProperties, true)) {
                continue;
            }

            if ($value === '' || preg_match('/url\s*\(|expression\s*\(|javascript:/i', $value)) {
                continue;
            }

            $isSafe = match ($property) {
                'text-align' => in_array(strtolower($value), ['left', 'center', 'right', 'justify'], true),
                'font-size' => (bool)preg_match('/^(8|9|10|11|12|13|14|15|16|18|20|22|24|28|32|36|40|48)px$/', $value),
                'font-weight' => in_array(strtolower($value), ['normal', 'bold', '600', '700', '800', '900'], true),
                'font-style' => in_array(strtolower($value), ['normal', 'italic'], true),
                'text-decoration' => (bool)preg_match('/^(none|underline|line-through)(\s+(underline|line-through))?$/i', $value),
                'color', 'background-color' => (bool)preg_match(
                    '/^(#[0-9a-f]{3,8}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)|rgba\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*(0|1|0?\.\d+)\s*\)|[a-z]{3,20})$/i',
                    $value
                ),
                default => false,
            };

            if ($isSafe) {
                $safeDeclarations[] = $property . ': ' . $value;
            }
        }

        return implode('; ', $safeDeclarations);
    }
}


if (!function_exists('ticketSanitizeRichHtmlFallback')) {
    function ticketSanitizeRichHtmlFallback(string $html): string
    {
        $html = preg_replace(
            '#<(script|style|iframe|object|embed|form|input|button|textarea|select|option|link|meta|base)\b[^>]*>.*?</\1>#is',
            '',
            $html
        ) ?? '';

        $allowedTags = '<p><div><br><strong><b><em><i><u><s><strike><h1><h2><h3><blockquote><ul><ol><li><a><span><pre><code><img>';
        $html = strip_tags($html, $allowedTags);

        $html = preg_replace_callback(
            '/<([a-z0-9]+)\b([^>]*)>/i',
            static function (array $matches): string {
                $tag = strtolower($matches[1]);
                $rawAttributes = $matches[2] ?? '';

                $allowedAttributes = match ($tag) {
                    'a' => ['href', 'target', 'rel', 'style'],
                    'img' => ['src', 'alt', 'title', 'style'],
                    default => ['style'],
                };

                $safeAttributes = [];

                preg_match_all(
                    '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(["\'])(.*?)\2/s',
                    $rawAttributes,
                    $attributeMatches,
                    PREG_SET_ORDER
                );

                foreach ($attributeMatches as $attributeMatch) {
                    $name = strtolower($attributeMatch[1]);
                    $value = trim($attributeMatch[3]);

                    if (!in_array($name, $allowedAttributes, true)) {
                        continue;
                    }

                    if ($name === 'style') {
                        $value = ticketSanitizeInlineStyle($value);

                        if ($value === '') {
                            continue;
                        }
                    }

                    if ($tag === 'a' && $name === 'href') {
                        if (!preg_match('/^(https?:\/\/|mailto:|\/|#)/i', $value)) {
                            continue;
                        }

                        $safeAttributes['target'] = '_blank';
                        $safeAttributes['rel'] = 'noopener noreferrer';
                    }

                    if ($tag === 'img' && $name === 'src') {
                        $safeImage = str_starts_with(
                            $value,
                            '/helpdesk-php/download-message-attachment.php?id='
                        ) || preg_match('/^__INLINE_ATTACHMENT_\d+__$/', $value);

                        if (!$safeImage) {
                            continue;
                        }
                    }

                    $safeAttributes[$name] = $value;
                }

                if ($tag === 'img' && empty($safeAttributes['src'])) {
                    return '';
                }

                $attributesHtml = '';

                foreach ($safeAttributes as $name => $value) {
                    $attributesHtml .= ' '
                        . $name
                        . '="'
                        . htmlspecialchars($value, ENT_QUOTES, 'UTF-8')
                        . '"';
                }

                return '<' . $tag . $attributesHtml . '>';
            },
            $html
        ) ?? '';

        return trim($html);
    }
}

if (!function_exists('ticketSanitizeRichHtml')) {
    function ticketSanitizeRichHtml(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        if (strlen($html) > 150000) {
            $html = substr($html, 0, 150000);
        }

        if (!class_exists('DOMDocument')) {
            return ticketSanitizeRichHtmlFallback($html);
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        $wrapped = '<?xml encoding="UTF-8"><div id="ticket-rich-root">' . $html . '</div>';
        $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('ticket-rich-root');

        if (!$root) {
            return '';
        }

        $allowedTags = [
            'p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike',
            'h1', 'h2', 'h3', 'blockquote', 'ul', 'ol', 'li', 'a', 'span',
            'pre', 'code', 'img',
        ];

        $dangerousTags = [
            'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
            'button', 'textarea', 'select', 'option', 'link', 'meta', 'base',
        ];

        $sanitizeNode = function (DOMNode $node) use (&$sanitizeNode, $allowedTags, $dangerousTags): void {
            $children = [];

            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                if ($child instanceof DOMComment) {
                    $child->parentNode?->removeChild($child);
                    continue;
                }

                if (!($child instanceof DOMElement)) {
                    continue;
                }

                $tag = strtolower($child->tagName);

                if (in_array($tag, $dangerousTags, true)) {
                    $child->parentNode?->removeChild($child);
                    continue;
                }

                if (!in_array($tag, $allowedTags, true)) {
                    $parent = $child->parentNode;

                    if ($parent) {
                        while ($child->firstChild) {
                            $parent->insertBefore($child->firstChild, $child);
                        }

                        $parent->removeChild($child);
                    }

                    continue;
                }

                $allowedAttributes = match ($tag) {
                    'a' => ['href', 'target', 'rel', 'style'],
                    'img' => ['src', 'alt', 'title', 'style'],
                    default => ['style'],
                };

                $attributes = [];

                foreach ($child->attributes as $attribute) {
                    $attributes[] = $attribute->name;
                }

                foreach ($attributes as $attributeName) {
                    $lowerName = strtolower($attributeName);

                    if (!in_array($lowerName, $allowedAttributes, true)) {
                        $child->removeAttribute($attributeName);
                        continue;
                    }

                    $value = trim($child->getAttribute($attributeName));

                    if ($lowerName === 'style') {
                        $safeStyle = ticketSanitizeInlineStyle($value);

                        if ($safeStyle === '') {
                            $child->removeAttribute($attributeName);
                        } else {
                            $child->setAttribute('style', $safeStyle);
                        }

                        continue;
                    }

                    if ($tag === 'a' && $lowerName === 'href') {
                        $isSafeLink = preg_match('/^(https?:\/\/|mailto:|\/|#)/i', $value);

                        if (!$isSafeLink) {
                            $child->removeAttribute($attributeName);
                        } else {
                            $child->setAttribute('target', '_blank');
                            $child->setAttribute('rel', 'noopener noreferrer');
                        }

                        continue;
                    }

                    if ($tag === 'img' && $lowerName === 'src') {
                        $isSafeImage = str_starts_with(
                            $value,
                            '/helpdesk-php/download-message-attachment.php?id='
                        ) || preg_match('/^__INLINE_ATTACHMENT_\d+__$/', $value);

                        if (!$isSafeImage) {
                            $child->removeAttribute($attributeName);
                        }
                    }
                }

                if ($tag === 'img' && !$child->hasAttribute('src')) {
                    $child->parentNode?->removeChild($child);
                    continue;
                }

                $sanitizeNode($child);
            }
        };

        $sanitizeNode($root);

        $safeHtml = '';

        foreach ($root->childNodes as $child) {
            $safeHtml .= $document->saveHTML($child);
        }

        return trim($safeHtml);
    }
}

if (!function_exists('ticketMessageHasContent')) {
    function ticketMessageHasContent(string $html, array $preparedFiles = []): bool
    {
        $plainText = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $plainText !== ''
            || str_contains($html, '<img')
            || !empty($preparedFiles);
    }
}

if (!function_exists('ticketPrepareRichMessage')) {
    function ticketPrepareRichMessage(
        int $ticketId,
        string $rawHtml,
        ?array $inlineFiles,
        ?array $documentFiles
    ): array {
        $rawHtml = trim($rawHtml);
        $savedPaths = [];
        $preparedFiles = [];
        $inlineUploads = ticketNormalizeUploadedFiles($inlineFiles);
        $documentUploads = ticketNormalizeUploadedFiles($documentFiles);

        $inlineUploads = array_values(array_filter(
            $inlineUploads,
            static fn(array $file): bool => ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ));

        $documentUploads = array_values(array_filter(
            $documentUploads,
            static fn(array $file): bool => ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE
        ));

        if (count($inlineUploads) > 8) {
            throw new RuntimeException('Solo puedes insertar hasta 8 imágenes por mensaje.');
        }

        if (count($documentUploads) > 8) {
            throw new RuntimeException('Solo puedes adjuntar hasta 8 documentos por mensaje.');
        }

        try {
            foreach ($inlineUploads as $index => $file) {
                $uploaded = ticketUploadSingleMessageFile($file, $ticketId, true);
                $uploaded['token'] = '__INLINE_ATTACHMENT_' . $index . '__';
                $uploaded['inline_index'] = $index;
                $preparedFiles[] = $uploaded;
                $savedPaths[] = $uploaded['absolute_path'];
            }

            $rawHtml = preg_replace_callback(
                '/<img\b[^>]*data-inline-index=["\'](\d+)["\'][^>]*>/i',
                static function (array $matches) use ($preparedFiles): string {
                    $index = (int)$matches[1];

                    foreach ($preparedFiles as $preparedFile) {
                        if (
                            (int)($preparedFile['is_inline'] ?? 0) === 1
                            && (int)($preparedFile['inline_index'] ?? -1) === $index
                        ) {
                            return '<img src="' . $preparedFile['token'] . '" alt="Imagen adjunta">';
                        }
                    }

                    return '';
                },
                $rawHtml
            ) ?? $rawHtml;

            foreach ($preparedFiles as $preparedFile) {
                if (
                    (int)($preparedFile['is_inline'] ?? 0) === 1
                    && !str_contains($rawHtml, (string)$preparedFile['token'])
                ) {
                    @unlink((string)$preparedFile['absolute_path']);
                }
            }

            $preparedFiles = array_values(array_filter(
                $preparedFiles,
                static function (array $preparedFile) use ($rawHtml): bool {
                    if ((int)($preparedFile['is_inline'] ?? 0) !== 1) {
                        return true;
                    }

                    return str_contains($rawHtml, (string)$preparedFile['token']);
                }
            ));

            foreach ($documentUploads as $file) {
                $uploaded = ticketUploadSingleMessageFile($file, $ticketId, false);
                $uploaded['token'] = null;
                $preparedFiles[] = $uploaded;
                $savedPaths[] = $uploaded['absolute_path'];
            }

            $safeHtml = ticketSanitizeRichHtml($rawHtml);

            if (!ticketMessageHasContent($safeHtml, $preparedFiles)) {
                throw new RuntimeException('El mensaje no puede estar vacío.');
            }

            if (trim(html_entity_decode(strip_tags($safeHtml), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === ''
                && !str_contains($safeHtml, '<img')
                && !empty($preparedFiles)
            ) {
                $safeHtml = '<p>Archivo adjunto.</p>';
            }

            return [
                'html' => $safeHtml,
                'files' => $preparedFiles,
                'saved_paths' => $savedPaths,
            ];
        } catch (Throwable $exception) {
            foreach ($savedPaths as $path) {
                if (is_string($path) && is_file($path)) {
                    @unlink($path);
                }
            }

            throw $exception;
        }
    }
}

if (!function_exists('ticketPersistPreparedAttachments')) {
    function ticketPersistPreparedAttachments(
        PDO $pdo,
        int $ticketId,
        string $scope,
        int $messageId,
        int $uploadedBy,
        array $preparedFiles,
        string $html
    ): array {
        $scope = strtoupper($scope) === 'INTERNAL' ? 'INTERNAL' : 'PUBLIC';

        if (!ticketTableExists($pdo, 'ticket_message_attachments')) {
            if (!empty($preparedFiles)) {
                throw new RuntimeException(
                    'Falta ejecutar el SQL de ticket_message_attachments antes de subir archivos.'
                );
            }

            return [
                'html' => $html,
                'attachment_ids' => [],
            ];
        }

        $statement = $pdo->prepare(
            'INSERT INTO ticket_message_attachments (
                ticket_id,
                message_scope,
                message_id,
                uploaded_by,
                original_name,
                stored_name,
                mime_type,
                file_size,
                storage_path,
                is_inline,
                created_at
            ) VALUES (
                :ticket_id,
                :message_scope,
                :message_id,
                :uploaded_by,
                :original_name,
                :stored_name,
                :mime_type,
                :file_size,
                :storage_path,
                :is_inline,
                NOW()
            )'
        );

        $attachmentIds = [];

        foreach ($preparedFiles as $preparedFile) {
            $statement->execute([
                'ticket_id' => $ticketId,
                'message_scope' => $scope,
                'message_id' => $messageId,
                'uploaded_by' => $uploadedBy,
                'original_name' => $preparedFile['original_name'],
                'stored_name' => $preparedFile['stored_name'],
                'mime_type' => $preparedFile['mime_type'],
                'file_size' => $preparedFile['file_size'],
                'storage_path' => $preparedFile['storage_path'],
                'is_inline' => (int)$preparedFile['is_inline'],
            ]);

            $attachmentId = (int)$pdo->lastInsertId();
            $attachmentIds[] = $attachmentId;

            if ((int)$preparedFile['is_inline'] === 1 && !empty($preparedFile['token'])) {
                $url = '/helpdesk-php/download-message-attachment.php?id='
                    . $attachmentId
                    . '&inline=1';

                $html = str_replace((string)$preparedFile['token'], $url, $html);
            }
        }

        return [
            'html' => ticketSanitizeRichHtml($html),
            'attachment_ids' => $attachmentIds,
        ];
    }
}

if (!function_exists('ticketCleanupPreparedFiles')) {
    function ticketCleanupPreparedFiles(array $prepared): void
    {
        foreach (($prepared['saved_paths'] ?? []) as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }
}

if (!function_exists('ticketRenderStoredMessage')) {
    function ticketRenderStoredMessage(?string $message, ?string $format = 'plain'): string
    {
        $message = (string)$message;
        $format = strtolower((string)$format);

        if ($format === 'html') {
            return ticketSanitizeRichHtml($message);
        }

        return nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
    }
}

if (!function_exists('ticketFormatBytes')) {
    function ticketFormatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }
}

if (!function_exists('ticketLoadAttachmentsMap')) {
    function ticketLoadAttachmentsMap(PDO $pdo, string $scope, array $messageIds): array
    {
        $map = [];

        if (
            empty($messageIds)
            || !ticketTableExists($pdo, 'ticket_message_attachments')
        ) {
            return $map;
        }

        $messageIds = array_values(array_unique(array_filter(
            array_map('intval', $messageIds),
            static fn(int $id): bool => $id > 0
        )));

        if (empty($messageIds)) {
            return $map;
        }

        $placeholders = implode(',', array_fill(0, count($messageIds), '?'));
        $scope = strtoupper($scope) === 'INTERNAL' ? 'INTERNAL' : 'PUBLIC';

        $statement = $pdo->prepare(
            "SELECT *
             FROM ticket_message_attachments
             WHERE message_scope = ?
               AND message_id IN ($placeholders)
             ORDER BY created_at ASC, id ASC"
        );

        $statement->execute(array_merge([$scope], $messageIds));

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $attachment) {
            $messageId = (int)$attachment['message_id'];
            $map[$messageId] ??= [];
            $map[$messageId][] = $attachment;
        }

        return $map;
    }
}

if (!function_exists('ticketRenderAttachmentList')) {
    function ticketRenderAttachmentList(array $attachments): string
    {
        $documents = array_values(array_filter(
            $attachments,
            static fn(array $attachment): bool => (int)($attachment['is_inline'] ?? 0) !== 1
        ));

        if (empty($documents)) {
            return '';
        }

        $html = '<div class="ticket-message-attachments">';

        foreach ($documents as $attachment) {
            $id = (int)($attachment['id'] ?? 0);
            $name = htmlspecialchars(
                (string)($attachment['original_name'] ?? 'Archivo adjunto'),
                ENT_QUOTES,
                'UTF-8'
            );
            $size = ticketFormatBytes((int)($attachment['file_size'] ?? 0));
            $extension = strtoupper(pathinfo((string)$attachment['original_name'], PATHINFO_EXTENSION));
            $extension = $extension !== '' ? $extension : 'FILE';

            $html .= '
                <a
                    class="ticket-message-attachment"
                    href="/helpdesk-php/download-message-attachment.php?id=' . $id . '"
                    target="_blank"
                    rel="noopener">
                    <span class="ticket-message-attachment-icon">' . htmlspecialchars($extension) . '</span>
                    <span class="ticket-message-attachment-copy">
                        <strong>' . $name . '</strong>
                        <small>' . htmlspecialchars($size) . '</small>
                    </span>
                    <span class="ticket-message-attachment-action">Descargar</span>
                </a>';
        }

        $html .= '</div>';

        return $html;
    }
}

if (!function_exists('ticketDeleteMessageAttachments')) {
    function ticketDeleteMessageAttachments(
        PDO $pdo,
        string $scope,
        int $messageId
    ): array {
        if (!ticketTableExists($pdo, 'ticket_message_attachments')) {
            return [];
        }

        $scope = strtoupper($scope) === 'INTERNAL' ? 'INTERNAL' : 'PUBLIC';

        $statement = $pdo->prepare(
            'SELECT id, storage_path
             FROM ticket_message_attachments
             WHERE message_scope = :message_scope
               AND message_id = :message_id'
        );
        $statement->execute([
            'message_scope' => $scope,
            'message_id' => $messageId,
        ]);

        $attachments = $statement->fetchAll(PDO::FETCH_ASSOC);

        $delete = $pdo->prepare(
            'DELETE FROM ticket_message_attachments
             WHERE message_scope = :message_scope
               AND message_id = :message_id'
        );
        $delete->execute([
            'message_scope' => $scope,
            'message_id' => $messageId,
        ]);

        $paths = [];

        foreach ($attachments as $attachment) {
            $relativePath = ltrim((string)($attachment['storage_path'] ?? ''), '/');

            if ($relativePath !== '') {
                $paths[] = ticketStorageBasePath() . '/' . $relativePath;
            }
        }

        return $paths;
    }
}

if (!function_exists('ticketDeletePhysicalFiles')) {
    function ticketDeletePhysicalFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if (is_string($path) && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
