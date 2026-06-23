<?php

declare(strict_types=1);

if (!function_exists('knowledgeBaseProjectRoot')) {
    function knowledgeBaseProjectRoot(): string
    {
        return dirname(__DIR__, 2);
    }
}

if (!function_exists('knowledgeBaseUploadDirectory')) {
    function knowledgeBaseUploadDirectory(): string
    {
        return knowledgeBaseProjectRoot() . '/public/uploads/knowledge-base';
    }
}

if (!function_exists('knowledgeBaseEnsureUploadDirectory')) {
    function knowledgeBaseEnsureUploadDirectory(): void
    {
        $directory = knowledgeBaseUploadDirectory();

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear la carpeta de archivos de la base de conocimientos.');
        }
    }
}

if (!function_exists('knowledgeBaseAllowedFiles')) {
    function knowledgeBaseAllowedFiles(): array
    {
        return [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'webp' => ['image/webp'],
            'gif' => ['image/gif'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword', 'application/octet-stream'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
            'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
            'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
            'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
            'txt' => ['text/plain', 'application/octet-stream'],
            'csv' => ['text/plain', 'text/csv', 'application/vnd.ms-excel', 'application/octet-stream'],
            'zip' => ['application/zip', 'application/x-zip-compressed', 'multipart/x-zip', 'application/octet-stream'],
        ];
    }
}

if (!function_exists('knowledgeBaseIsImageExtension')) {
    function knowledgeBaseIsImageExtension(string $extension): bool
    {
        return in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }
}

if (!function_exists('knowledgeBaseNormalizeUploads')) {
    function knowledgeBaseNormalizeUploads(array $files): array
    {
        if (!isset($files['name'])) {
            return [];
        }

        if (!is_array($files['name'])) {
            return [$files];
        }

        $normalized = [];
        $count = count($files['name']);

        for ($index = 0; $index < $count; $index++) {
            $normalized[] = [
                'name' => $files['name'][$index] ?? '',
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            ];
        }

        return $normalized;
    }
}

if (!function_exists('knowledgeBasePrepareUploads')) {
    function knowledgeBasePrepareUploads(array $files, int $maximumFiles = 8, int $maximumBytes = 10485760): array
    {
        $prepared = [];
        $allowed = knowledgeBaseAllowedFiles();
        $normalized = knowledgeBaseNormalizeUploads($files);
        $validCount = 0;

        foreach ($normalized as $file) {
            $error = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $validCount++;
            if ($validCount > $maximumFiles) {
                throw new RuntimeException("Solo se permiten hasta {$maximumFiles} archivos por artículo.");
            }

            if ($error !== UPLOAD_ERR_OK) {
                throw new RuntimeException('Uno de los archivos no pudo cargarse correctamente.');
            }

            $size = (int)($file['size'] ?? 0);
            if ($size <= 0 || $size > $maximumBytes) {
                throw new RuntimeException('Cada archivo debe pesar como máximo 10 MB.');
            }

            $originalName = trim((string)($file['name'] ?? ''));
            $extension = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));

            if ($extension === '' || !isset($allowed[$extension])) {
                throw new RuntimeException('Formato no permitido: ' . ($extension !== '' ? $extension : 'sin extensión') . '.');
            }

            $tmpName = (string)($file['tmp_name'] ?? '');
            if ($tmpName === '' || !is_uploaded_file($tmpName)) {
                throw new RuntimeException('No se pudo validar uno de los archivos cargados.');
            }

            $detectedMime = trim((string)($file['type'] ?? '')) ?: 'application/octet-stream';

            if (knowledgeBaseIsImageExtension($extension)) {
                $imageInfo = @getimagesize($tmpName);
                $detectedMime = is_array($imageInfo) ? (string)($imageInfo['mime'] ?? '') : '';

                if ($detectedMime === '' || !in_array($detectedMime, $allowed[$extension], true)) {
                    throw new RuntimeException('La imagen no es válida: ' . $originalName . '.');
                }
            } elseif ($extension === 'pdf') {
                $signature = '';
                $handle = @fopen($tmpName, 'rb');
                if (is_resource($handle)) {
                    $signature = (string)fread($handle, 4);
                    fclose($handle);
                }

                if ($signature !== '%PDF') {
                    throw new RuntimeException('El PDF no es válido: ' . $originalName . '.');
                }

                $detectedMime = 'application/pdf';
            } elseif (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detectedMime = (string)($finfo->file($tmpName) ?: $detectedMime);
            }

            if (!in_array($detectedMime, $allowed[$extension], true)) {
                throw new RuntimeException('El contenido del archivo no coincide con su extensión: ' . $originalName . '.');
            }

            $prepared[] = [
                'original_name' => $originalName,
                'extension' => $extension,
                'mime_type' => $detectedMime,
                'file_size' => $size,
                'tmp_name' => $tmpName,
                'is_image' => knowledgeBaseIsImageExtension($extension) ? 1 : 0,
            ];
        }

        return $prepared;
    }
}

if (!function_exists('knowledgeBaseGenerateStoredName')) {
    function knowledgeBaseGenerateStoredName(string $extension): string
    {
        return date('YmdHis') . '_' . bin2hex(random_bytes(12)) . '.' . strtolower($extension);
    }
}

if (!function_exists('knowledgeBaseRelativeUploadPath')) {
    function knowledgeBaseRelativeUploadPath(string $storedName): string
    {
        return 'public/uploads/knowledge-base/' . basename($storedName);
    }
}

if (!function_exists('knowledgeBaseAbsolutePath')) {
    function knowledgeBaseAbsolutePath(string $relativePath): ?string
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $prefix = 'public/uploads/knowledge-base/';

        if (!str_starts_with($relativePath, $prefix)) {
            return null;
        }

        $basename = basename($relativePath);
        if ($basename === '' || $basename === '.' || $basename === '..') {
            return null;
        }

        return knowledgeBaseUploadDirectory() . '/' . $basename;
    }
}

if (!function_exists('knowledgeBaseFormatBytes')) {
    function knowledgeBaseFormatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / 1048576, 1) . ' MB';
    }
}

if (!function_exists('knowledgeBaseLegacyToHtml')) {
    function knowledgeBaseLegacyToHtml(?string $content): string
    {
        $content = trim((string)$content);
        if ($content === '') {
            return '';
        }

        $lines = preg_split('/\R+/', $content) ?: [];
        $paragraphs = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            // Elimina la numeración que el sistema antiguo generaba automáticamente.
            $line = preg_replace('/^\s*(?:\d+[.)]|[-•])\s*/u', '', $line) ?? $line;
            $paragraphs[] = '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        return implode("\n", $paragraphs);
    }
}

if (!function_exists('knowledgeBaseSanitizeStyle')) {
    function knowledgeBaseSanitizeStyle(string $style): string
    {
        $allowedProperties = ['color', 'background-color', 'text-align', 'font-size'];
        $clean = [];

        foreach (explode(';', $style) as $declaration) {
            if (!str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);

            if (!in_array($property, $allowedProperties, true)) {
                continue;
            }

            if (preg_match('/expression|javascript|url\s*\(/i', $value)) {
                continue;
            }

            if ($property === 'text-align' && !in_array(strtolower($value), ['left', 'center', 'right', 'justify'], true)) {
                continue;
            }

            if ($property === 'font-size' && !preg_match('/^(?:[1-9]|[1-6][0-9]|7[0-2])(?:px|pt|%)$/i', $value)) {
                continue;
            }

            if (in_array($property, ['color', 'background-color'], true) && !preg_match('/^(?:#[0-9a-f]{3,8}|rgb\([0-9, .%]+\)|rgba\([0-9, .%]+\)|[a-z]{3,20})$/i', $value)) {
                continue;
            }

            $clean[] = $property . ': ' . $value;
        }

        return implode('; ', $clean);
    }
}


if (!function_exists('knowledgeBaseSanitizeHtmlFallback')) {
    function knowledgeBaseSanitizeHtmlFallback(string $html, string $allowedTagList): string
    {
        $html = preg_replace(
            '#<(script|style|iframe|object|embed|svg|math|form|button)\b[^>]*>.*?</\1>#is',
            '',
            $html
        ) ?? $html;

        $html = preg_replace('#<(input|meta|link|base)\b[^>]*\/?>#is', '', $html) ?? $html;
        $html = strip_tags($html, $allowedTagList);

        $allowedStyleTags = ['div', 'p', 'h2', 'h3', 'h4', 'span', 'blockquote'];
        $html = preg_replace_callback(
            '/<([a-z0-9]+)(\s[^>]*)?>/i',
            static function (array $matches) use ($allowedStyleTags): string {
                $tag = strtolower($matches[1]);
                $attributeText = $matches[2] ?? '';
                $attributes = [];

                if ($attributeText !== '' && preg_match_all('/([a-z0-9:-]+)\s*=\s*(["\'])(.*?)\2/is', $attributeText, $attributeMatches, PREG_SET_ORDER)) {
                    foreach ($attributeMatches as $attributeMatch) {
                        $name = strtolower($attributeMatch[1]);
                        $value = trim($attributeMatch[3]);

                        if ($name === 'style' && in_array($tag, $allowedStyleTags, true)) {
                            $safeStyle = knowledgeBaseSanitizeStyle($value);
                            if ($safeStyle !== '') {
                                $attributes[] = 'style="' . htmlspecialchars($safeStyle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                            }
                        } elseif ($tag === 'a' && $name === 'href') {
                            if ($value !== '' && !preg_match('/^(?:javascript|data|vbscript):/i', $value)) {
                                $attributes[] = 'href="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                            }
                        } elseif ($tag === 'a' && $name === 'target' && in_array($value, ['_blank', '_self'], true)) {
                            $attributes[] = 'target="' . $value . '"';
                            if ($value === '_blank') {
                                $attributes[] = 'rel="noopener noreferrer"';
                            }
                        } elseif ($tag === 'font' && $name === 'color' && preg_match('/^(?:#[0-9a-f]{3,8}|rgb\([0-9, .%]+\)|rgba\([0-9, .%]+\)|[a-z]{3,20})$/i', $value)) {
                            $attributes[] = 'color="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
                        } elseif ($tag === 'font' && $name === 'size' && preg_match('/^[1-7]$/', $value)) {
                            $attributes[] = 'size="' . $value . '"';
                        }
                    }
                }

                return '<' . $tag . ($attributes !== [] ? ' ' . implode(' ', array_unique($attributes)) : '') . '>';
            },
            $html
        ) ?? $html;

        return trim($html);
    }
}

if (!function_exists('knowledgeBaseSanitizeHtml')) {
    function knowledgeBaseSanitizeHtml(?string $html): string
    {
        $html = trim((string)$html);
        if ($html === '') {
            return '';
        }

        $allowedTagList = '<p><div><br><h2><h3><h4><strong><b><em><i><u><s><ul><ol><li><blockquote><a><span><font><hr><pre><code>';

        if (!class_exists('DOMDocument')) {
            return knowledgeBaseSanitizeHtmlFallback($html, $allowedTagList);
        }

        $dom = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="knowledge-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            return trim(strip_tags($html, $allowedTagList));
        }

        $allowedTags = [
            'div', 'p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's',
            'ul', 'ol', 'li', 'blockquote', 'a', 'span', 'font', 'hr', 'pre', 'code',
        ];

        $sanitizeNode = static function (DOMNode $node) use (&$sanitizeNode, $allowedTags): void {
            $children = [];
            foreach ($node->childNodes as $child) {
                $children[] = $child;
            }

            foreach ($children as $child) {
                if ($child instanceof DOMComment) {
                    $node->removeChild($child);
                    continue;
                }

                if ($child instanceof DOMElement) {
                    $tag = strtolower($child->tagName);

                    if (!in_array($tag, $allowedTags, true)) {
                        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'form', 'input', 'button'], true)) {
                            $node->removeChild($child);
                            continue;
                        }

                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }
                        $node->removeChild($child);
                        continue;
                    }

                    $attributes = [];
                    foreach ($child->attributes as $attribute) {
                        $attributes[] = $attribute->name;
                    }

                    foreach ($attributes as $attributeName) {
                        $name = strtolower($attributeName);
                        $value = trim($child->getAttribute($attributeName));
                        $keep = false;

                        if ($name === 'style' && in_array($tag, ['div', 'p', 'h2', 'h3', 'h4', 'span', 'blockquote'], true)) {
                            $safeStyle = knowledgeBaseSanitizeStyle($value);
                            if ($safeStyle !== '') {
                                $child->setAttribute('style', $safeStyle);
                                $keep = true;
                            }
                        } elseif ($tag === 'a' && $name === 'href') {
                            if ($value !== '' && !preg_match('/^(?:javascript|data|vbscript):/i', $value)) {
                                $keep = true;
                            }
                        } elseif ($tag === 'a' && $name === 'target' && in_array($value, ['_blank', '_self'], true)) {
                            $keep = true;
                        } elseif ($tag === 'a' && $name === 'rel') {
                            $keep = true;
                        } elseif ($tag === 'font' && in_array($name, ['color', 'size'], true)) {
                            if ($name === 'color' && preg_match('/^(?:#[0-9a-f]{3,8}|rgb\([0-9, .%]+\)|rgba\([0-9, .%]+\)|[a-z]{3,20})$/i', $value)) {
                                $keep = true;
                            }
                            if ($name === 'size' && preg_match('/^[1-7]$/', $value)) {
                                $keep = true;
                            }
                        }

                        if (!$keep) {
                            $child->removeAttribute($attributeName);
                        }
                    }

                    if ($tag === 'a' && $child->getAttribute('target') === '_blank') {
                        $child->setAttribute('rel', 'noopener noreferrer');
                    }

                    $sanitizeNode($child);
                }
            }
        };

        $xpath = new DOMXPath($dom);
        $root = $xpath->query('//*[@id="knowledge-root"]')->item(0) ?: $dom->documentElement;

        if (!$root) {
            return knowledgeBaseSanitizeHtmlFallback($html, $allowedTagList);
        }

        $sanitizeNode($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $dom->saveHTML($child);
        }

        return trim($output);
    }
}

if (!function_exists('knowledgeBasePlainText')) {
    function knowledgeBasePlainText(?string $html): string
    {
        $html = (string)$html;
        $html = preg_replace('/<(?:br|hr)\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/(?:p|div|h2|h3|h4|li|blockquote|pre)>/i', "\n", $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\t ]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
