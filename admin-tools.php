<?php
require_once __DIR__ . '/app/helpers/session.php';
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/knowledge_base.php';
require_once __DIR__ . '/app/helpers/business_hours.php';
require_once __DIR__ . '/app/helpers/sla_helper.php';

requireLogin();

$currentUser = user();
$currentRole = strtoupper((string)($currentUser['role'] ?? ''));
$allowedRoles = ['ADMIN', 'TECH'];

if (!in_array($currentRole, $allowedRoles, true)) {
    header('Location: /helpdesk-php/home.php');
    exit;
}

$isAdmin = $currentRole === 'ADMIN';

if (!function_exists('adminToolsTableExistsV8')) {
    function adminToolsTableExistsV8(PDO $pdo, string $table): bool
    {
        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE :table_name');
            $stmt->execute(['table_name' => $table]);
            return (bool)$stmt->fetch(PDO::FETCH_NUM);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('adminToolsColumnExistsV8')) {
    function adminToolsColumnExistsV8(PDO $pdo, string $table, string $column): bool
    {
        try {
            $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :column_name");
            $stmt->execute(['column_name' => $column]);
            return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('adminToolsNormalizeCodeV8')) {
    function adminToolsNormalizeCodeV8(string $value): string
    {
        $value = trim($value);
        $map = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
            'á' => 'A', 'é' => 'E', 'í' => 'I', 'ó' => 'O', 'ú' => 'U', 'ñ' => 'N',
        ];
        $value = strtr($value, $map);
        $value = strtoupper($value);
        $value = preg_replace('/[^A-Z0-9]+/', '_', $value) ?? '';
        $value = trim($value, '_');
        return $value !== '' ? substr($value, 0, 50) : 'OTROS';
    }
}

if (!function_exists('adminToolsRedirectV8')) {
    function adminToolsRedirectV8(string $status, string $tab = 'categories'): void
    {
        header('Location: /helpdesk-php/admin-tools.php?status=' . urlencode($status) . '&tab=' . urlencode($tab));
        exit;
    }
}

if (!function_exists('adminToolsContractLabelV8')) {
    function adminToolsContractLabelV8(string $contract): string
    {
        $contract = strtolower(trim($contract));
        return $contract === '24_7' || $contract === '24/7' ? '24/7' : '8/5';
    }
}

if (!function_exists('adminToolsNormalizeContractV8')) {
    function adminToolsNormalizeContractV8(?string $contract): string
    {
        $contract = strtolower(trim((string)$contract));
        return in_array($contract, ['24_7', '24/7', '24x7'], true) ? '24_7' : '8_5';
    }
}

if (!function_exists('adminToolsIsWithinContractHoursV8')) {
    function adminToolsIsWithinContractHoursV8(?string $dateTime, ?string $contract): bool
    {
        $contract = adminToolsNormalizeContractV8($contract);
        if ($contract === '24_7') {
            return true;
        }

        if (empty($dateTime)) {
            return false;
        }

        try {
            $date = new DateTime($dateTime, new DateTimeZone('America/Lima'));
        } catch (Throwable $e) {
            return false;
        }

        $dayOfWeek = (int)$date->format('N');
        if ($dayOfWeek > 5) {
            return false;
        }

        $minutes = ((int)$date->format('H') * 60) + (int)$date->format('i');
        return $minutes >= (8 * 60) && $minutes <= (17 * 60);
    }
}

if (!function_exists('adminToolsElapsedHoursV8')) {
    function adminToolsElapsedHoursV8(?string $startDateTime, ?string $endDateTime, ?string $contract): float
    {
        if (empty($startDateTime) || empty($endDateTime)) {
            return 0.0;
        }

        $contract = adminToolsNormalizeContractV8($contract);

        try {
            $start = new DateTime($startDateTime, new DateTimeZone('America/Lima'));
            $end = new DateTime($endDateTime, new DateTimeZone('America/Lima'));
        } catch (Throwable $e) {
            return 0.0;
        }

        if ($end <= $start) {
            return 0.0;
        }

        if ($contract === '24_7') {
            return round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 2);
        }

        $totalMinutes = 0;
        $current = clone $start;

        while ($current < $end) {
            $dayOfWeek = (int)$current->format('N');
            if ($dayOfWeek <= 5) {
                $workStart = clone $current;
                $workStart->setTime(8, 0, 0);

                $workEnd = clone $current;
                $workEnd->setTime(17, 0, 0);

                $periodStart = $current > $workStart ? clone $current : clone $workStart;
                $periodEnd = $end < $workEnd ? clone $end : clone $workEnd;

                if ($periodEnd > $periodStart) {
                    $totalMinutes += (int)(($periodEnd->getTimestamp() - $periodStart->getTimestamp()) / 60);
                }
            }

            $current->modify('tomorrow');
            $current->setTime(0, 0, 0);
        }

        return round($totalMinutes / 60, 2);
    }
}

if (!function_exists('adminToolsHoursToClockV8')) {
    function adminToolsHoursToClockV8(float|int|string|null $hours): string
    {
        if ($hours === null || $hours === '' || !is_numeric($hours)) {
            return '00:00:00';
        }
        $totalSeconds = (int)round(((float)$hours) * 3600);
        $h = intdiv($totalSeconds, 3600);
        $m = intdiv($totalSeconds % 3600, 60);
        $s = $totalSeconds % 60;
        return sprintf('%02d:%02d:%02d', $h, $m, $s);
    }
}

if (!function_exists('adminToolsSlaInfoV8')) {
    function adminToolsSlaInfoV8(array $ticket): array
    {
        $contract = adminToolsNormalizeContractV8($ticket['sla_contract_type'] ?? '8_5');
        $now = (new DateTime('now', new DateTimeZone('America/Lima')))->format('Y-m-d H:i:s');
        $slaHours = (float)($ticket['sla_hours'] ?? 0);
        $elapsed = adminToolsElapsedHoursV8($ticket['created_at'] ?? null, $now, $contract);
        $progress = $slaHours > 0 ? min(100, round(($elapsed / $slaHours) * 100)) : 0;
        $paused = $contract === '8_5' && !adminToolsIsWithinContractHoursV8($now, $contract);

        if ($slaHours <= 0 || empty($ticket['created_at'])) {
            $label = 'Pendiente';
            $type = 'within';
        } elseif ($elapsed >= $slaHours) {
            $label = 'Vencido';
            $type = 'expired';
        } elseif ($elapsed >= ($slaHours * 0.75)) {
            $label = 'Por vencer';
            $type = 'warning';
        } else {
            $label = 'Dentro del SLA';
            $type = 'within';
        }

        if ($paused && $type !== 'expired') {
            $type = 'paused';
            $label = 'Pausado';
        }

        return [
            'label' => $label,
            'type' => $type,
            'progress' => $progress,
            'elapsed' => $elapsed,
            'elapsed_label' => adminToolsHoursToClockV8($elapsed),
            'contract_label' => adminToolsContractLabelV8($contract),
            'is_paused' => $paused,
        ];
    }
}

$categoryTableReady = adminToolsTableExistsV8($pdo, 'ticket_categories');
$templateTableReady = adminToolsTableExistsV8($pdo, 'response_templates');
$priorityTableReady = adminToolsTableExistsV8($pdo, 'ticket_priorities');
$closureTableReady = adminToolsTableExistsV8($pdo, 'closure_reasons');
$knowledgeTableReady = adminToolsTableExistsV8($pdo, 'knowledge_base_articles');
$knowledgeContentReady = $knowledgeTableReady && adminToolsColumnExistsV8($pdo, 'knowledge_base_articles', 'content_html');
$knowledgeAttachmentsTableReady = adminToolsTableExistsV8($pdo, 'knowledge_base_attachments');
$ruleTableReady = adminToolsTableExistsV8($pdo, 'assignment_rules');
$ticketsTableReady = adminToolsTableExistsV8($pdo, 'tickets');
$usersTableReady = adminToolsTableExistsV8($pdo, 'users');
$companyModuleReady = adminToolsTableExistsV8($pdo, 'client_companies');
$toolsSetupReady = $categoryTableReady
    && $templateTableReady
    && $priorityTableReady
    && $closureTableReady
    && $knowledgeTableReady
    && $knowledgeContentReady
    && $knowledgeAttachmentsTableReady
    && $ruleTableReady;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');
    $tabByAction = [
        'save_category' => 'categories', 'delete_category' => 'categories',
        'save_template' => 'templates', 'delete_template' => 'templates',
        'save_priority' => 'priorities', 'delete_priority' => 'priorities',
        'save_closure_reason' => 'closure', 'delete_closure_reason' => 'closure',
        'save_knowledge' => 'knowledge', 'delete_knowledge' => 'knowledge',
        'save_rule' => 'rules', 'delete_rule' => 'rules',
    ];
    $fallbackTab = $tabByAction[$action] ?? 'categories';

    if (!$isAdmin) {
        adminToolsRedirectV8('not_allowed', $fallbackTab);
    }

    if (!$toolsSetupReady) {
        adminToolsRedirectV8('missing_tables', $fallbackTab);
    }

    try {
        switch ($action) {
            case 'save_category': {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));
                $code = adminToolsNormalizeCodeV8((string)($_POST['code'] ?? $name));
                $description = trim((string)($_POST['description'] ?? ''));
                $color = trim((string)($_POST['color'] ?? '#ff7a00'));
                $isActive = isset($_POST['is_active']) ? 1 : 0;

                if ($name === '') {
                    adminToolsRedirectV8('category_empty', 'categories');
                }

                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                    $color = '#ff7a00';
                }

                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE ticket_categories SET code = :code, name = :name, description = :description, color = :color, is_active = :is_active WHERE id = :id');
                    $stmt->execute(['code' => $code, 'name' => $name, 'description' => $description, 'color' => $color, 'is_active' => $isActive, 'id' => $id]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO ticket_categories (code, name, description, color, is_active) VALUES (:code, :name, :description, :color, :is_active)');
                    $stmt->execute(['code' => $code, 'name' => $name, 'description' => $description, 'color' => $color, 'is_active' => $isActive]);
                }
                adminToolsRedirectV8('category_saved', 'categories');
            }

            case 'delete_category': {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    adminToolsRedirectV8('category_invalid', 'categories');
                }
                $stmt = $pdo->prepare('SELECT code FROM ticket_categories WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $id]);
                $code = (string)($stmt->fetchColumn() ?: '');
                $using = 0;
                if ($ticketsTableReady && $code !== '') {
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE category = :code');
                    $stmt->execute(['code' => $code]);
                    $using = (int)$stmt->fetchColumn();
                }
                if ($using > 0) {
                    $stmt = $pdo->prepare('UPDATE ticket_categories SET is_active = 0 WHERE id = :id');
                    $stmt->execute(['id' => $id]);
                    adminToolsRedirectV8('category_disabled', 'categories');
                }
                $stmt = $pdo->prepare('DELETE FROM ticket_categories WHERE id = :id');
                $stmt->execute(['id' => $id]);
                adminToolsRedirectV8('category_deleted', 'categories');
            }

            case 'save_template': {
                $id = (int)($_POST['id'] ?? 0);
                $title = trim((string)($_POST['title'] ?? ''));
                $categoryId = trim((string)($_POST['category_id'] ?? ''));
                $content = trim((string)($_POST['content'] ?? ''));
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                if ($title === '' || $content === '') {
                    adminToolsRedirectV8('template_empty', 'templates');
                }
                $categoryIdValue = $categoryId !== '' ? (int)$categoryId : null;
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE response_templates SET title = :title, category_id = :category_id, content = :content, is_active = :is_active WHERE id = :id');
                    $stmt->execute(['title' => $title, 'category_id' => $categoryIdValue, 'content' => $content, 'is_active' => $isActive, 'id' => $id]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO response_templates (title, category_id, content, is_active) VALUES (:title, :category_id, :content, :is_active)');
                    $stmt->execute(['title' => $title, 'category_id' => $categoryIdValue, 'content' => $content, 'is_active' => $isActive]);
                }
                adminToolsRedirectV8('template_saved', 'templates');
            }

            case 'delete_template': {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    adminToolsRedirectV8('template_invalid', 'templates');
                }
                $stmt = $pdo->prepare('DELETE FROM response_templates WHERE id = :id');
                $stmt->execute(['id' => $id]);
                adminToolsRedirectV8('template_deleted', 'templates');
            }

            case 'save_priority': {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));
                $code = adminToolsNormalizeCodeV8((string)($_POST['code'] ?? $name));
                $slaHours = max(0.5, (float)($_POST['sla_hours'] ?? 8));
                $color = trim((string)($_POST['color'] ?? '#ff7a00'));
                $sortOrder = (int)($_POST['sort_order'] ?? 1);
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                if ($name === '') {
                    adminToolsRedirectV8('priority_empty', 'priorities');
                }
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                    $color = '#ff7a00';
                }
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE ticket_priorities SET code = :code, name = :name, sla_hours = :sla_hours, color = :color, sort_order = :sort_order, is_active = :is_active WHERE id = :id');
                    $stmt->execute(['code' => $code, 'name' => $name, 'sla_hours' => $slaHours, 'color' => $color, 'sort_order' => $sortOrder, 'is_active' => $isActive, 'id' => $id]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO ticket_priorities (code, name, sla_hours, color, sort_order, is_active) VALUES (:code, :name, :sla_hours, :color, :sort_order, :is_active)');
                    $stmt->execute(['code' => $code, 'name' => $name, 'sla_hours' => $slaHours, 'color' => $color, 'sort_order' => $sortOrder, 'is_active' => $isActive]);
                }
                adminToolsRedirectV8('priority_saved', 'priorities');
            }

            case 'delete_priority': {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    adminToolsRedirectV8('priority_invalid', 'priorities');
                }
                $stmt = $pdo->prepare('SELECT code FROM ticket_priorities WHERE id = :id LIMIT 1');
                $stmt->execute(['id' => $id]);
                $code = (string)($stmt->fetchColumn() ?: '');
                $using = 0;
                if ($ticketsTableReady && $code !== '') {
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE priority = :code');
                    $stmt->execute(['code' => $code]);
                    $using = (int)$stmt->fetchColumn();
                }
                if ($using > 0) {
                    $stmt = $pdo->prepare('UPDATE ticket_priorities SET is_active = 0 WHERE id = :id');
                    $stmt->execute(['id' => $id]);
                    adminToolsRedirectV8('priority_disabled', 'priorities');
                }
                $stmt = $pdo->prepare('DELETE FROM ticket_priorities WHERE id = :id');
                $stmt->execute(['id' => $id]);
                adminToolsRedirectV8('priority_deleted', 'priorities');
            }

            case 'save_closure_reason': {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));
                $code = adminToolsNormalizeCodeV8((string)($_POST['code'] ?? $name));
                $description = trim((string)($_POST['description'] ?? ''));
                $requiresComment = isset($_POST['requires_comment']) ? 1 : 0;
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                if ($name === '') {
                    adminToolsRedirectV8('closure_empty', 'closure');
                }
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE closure_reasons SET code = :code, name = :name, description = :description, requires_comment = :requires_comment, is_active = :is_active WHERE id = :id');
                    $stmt->execute(['code' => $code, 'name' => $name, 'description' => $description, 'requires_comment' => $requiresComment, 'is_active' => $isActive, 'id' => $id]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO closure_reasons (code, name, description, requires_comment, is_active) VALUES (:code, :name, :description, :requires_comment, :is_active)');
                    $stmt->execute(['code' => $code, 'name' => $name, 'description' => $description, 'requires_comment' => $requiresComment, 'is_active' => $isActive]);
                }
                adminToolsRedirectV8('closure_saved', 'closure');
            }

            case 'delete_closure_reason': {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    adminToolsRedirectV8('closure_invalid', 'closure');
                }
                $stmt = $pdo->prepare('DELETE FROM closure_reasons WHERE id = :id');
                $stmt->execute(['id' => $id]);
                adminToolsRedirectV8('closure_deleted', 'closure');
            }

            case 'save_knowledge': {
                $id = (int)($_POST['id'] ?? 0);
                $title = trim((string)($_POST['title'] ?? ''));
                $categoryId = trim((string)($_POST['category_id'] ?? ''));
                $problem = trim((string)($_POST['problem_summary'] ?? ''));
                $contentHtml = knowledgeBaseSanitizeHtml((string)($_POST['content_html'] ?? ''));
                $solutionText = knowledgeBasePlainText($contentHtml);
                $keywords = trim((string)($_POST['keywords'] ?? ''));
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                $deleteAttachmentIds = array_values(array_filter(
                    array_map('intval', (array)($_POST['delete_attachment_ids'] ?? [])),
                    static fn(int $value): bool => $value > 0
                ));

                if ($title === '' || $problem === '' || $solutionText === '') {
                    adminToolsRedirectV8('knowledge_empty', 'knowledge');
                }

                $preparedUploads = knowledgeBasePrepareUploads($_FILES['attachments'] ?? []);
                $categoryIdValue = $categoryId !== '' ? (int)$categoryId : null;
                $movedNewFiles = [];
                $filesToDeleteAfterCommit = [];

                $pdo->beginTransaction();

                try {
                    if ($id > 0) {
                        $stmt = $pdo->prepare(
                            'UPDATE knowledge_base_articles
                             SET title = :title,
                                 category_id = :category_id,
                                 problem_summary = :problem_summary,
                                 solution_steps = :solution_steps,
                                 content_html = :content_html,
                                 keywords = :keywords,
                                 is_active = :is_active
                             WHERE id = :id'
                        );
                        $stmt->execute([
                            'title' => $title,
                            'category_id' => $categoryIdValue,
                            'problem_summary' => $problem,
                            'solution_steps' => $solutionText,
                            'content_html' => $contentHtml,
                            'keywords' => $keywords,
                            'is_active' => $isActive,
                            'id' => $id,
                        ]);

                        $articleId = $id;
                    } else {
                        $stmt = $pdo->prepare(
                            'INSERT INTO knowledge_base_articles
                                (title, category_id, problem_summary, solution_steps, content_html, keywords, is_active)
                             VALUES
                                (:title, :category_id, :problem_summary, :solution_steps, :content_html, :keywords, :is_active)'
                        );
                        $stmt->execute([
                            'title' => $title,
                            'category_id' => $categoryIdValue,
                            'problem_summary' => $problem,
                            'solution_steps' => $solutionText,
                            'content_html' => $contentHtml,
                            'keywords' => $keywords,
                            'is_active' => $isActive,
                        ]);

                        $articleId = (int)$pdo->lastInsertId();
                    }

                    if ($deleteAttachmentIds !== []) {
                        $placeholders = implode(',', array_fill(0, count($deleteAttachmentIds), '?'));
                        $selectAttachments = $pdo->prepare(
                            "SELECT id, file_path
                             FROM knowledge_base_attachments
                             WHERE article_id = ?
                               AND id IN ($placeholders)"
                        );
                        $selectAttachments->execute(array_merge([$articleId], $deleteAttachmentIds));
                        $filesToDeleteAfterCommit = $selectAttachments->fetchAll(PDO::FETCH_ASSOC) ?: [];

                        $deleteAttachments = $pdo->prepare(
                            "DELETE FROM knowledge_base_attachments
                             WHERE article_id = ?
                               AND id IN ($placeholders)"
                        );
                        $deleteAttachments->execute(array_merge([$articleId], $deleteAttachmentIds));
                    }

                    if ($preparedUploads !== []) {
                        knowledgeBaseEnsureUploadDirectory();

                        $insertAttachment = $pdo->prepare(
                            'INSERT INTO knowledge_base_attachments
                                (article_id, original_name, stored_name, file_path, mime_type, file_size, is_image, uploaded_by)
                             VALUES
                                (:article_id, :original_name, :stored_name, :file_path, :mime_type, :file_size, :is_image, :uploaded_by)'
                        );

                        foreach ($preparedUploads as $upload) {
                            $storedName = knowledgeBaseGenerateStoredName($upload['extension']);
                            $destination = knowledgeBaseUploadDirectory() . '/' . $storedName;

                            if (!move_uploaded_file($upload['tmp_name'], $destination)) {
                                throw new RuntimeException('No se pudo guardar uno de los archivos adjuntos.');
                            }

                            $movedNewFiles[] = $destination;
                            $insertAttachment->execute([
                                'article_id' => $articleId,
                                'original_name' => $upload['original_name'],
                                'stored_name' => $storedName,
                                'file_path' => knowledgeBaseRelativeUploadPath($storedName),
                                'mime_type' => $upload['mime_type'],
                                'file_size' => $upload['file_size'],
                                'is_image' => $upload['is_image'],
                                'uploaded_by' => (int)($currentUser['id'] ?? 0) ?: null,
                            ]);
                        }
                    }

                    $pdo->commit();

                    foreach ($filesToDeleteAfterCommit as $attachment) {
                        $absolutePath = knowledgeBaseAbsolutePath((string)($attachment['file_path'] ?? ''));
                        if ($absolutePath && is_file($absolutePath)) {
                            @unlink($absolutePath);
                        }
                    }

                    adminToolsRedirectV8('knowledge_saved', 'knowledge');
                } catch (Throwable $knowledgeException) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    foreach ($movedNewFiles as $movedFile) {
                        if (is_file($movedFile)) {
                            @unlink($movedFile);
                        }
                    }

                    throw $knowledgeException;
                }
            }

            case 'delete_knowledge': {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    adminToolsRedirectV8('knowledge_invalid', 'knowledge');
                }

                $attachmentRows = [];
                if ($knowledgeAttachmentsTableReady) {
                    $stmt = $pdo->prepare('SELECT file_path FROM knowledge_base_attachments WHERE article_id = :article_id');
                    $stmt->execute(['article_id' => $id]);
                    $attachmentRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                $stmt = $pdo->prepare('DELETE FROM knowledge_base_articles WHERE id = :id');
                $stmt->execute(['id' => $id]);

                foreach ($attachmentRows as $attachment) {
                    $absolutePath = knowledgeBaseAbsolutePath((string)($attachment['file_path'] ?? ''));
                    if ($absolutePath && is_file($absolutePath)) {
                        @unlink($absolutePath);
                    }
                }

                adminToolsRedirectV8('knowledge_deleted', 'knowledge');
            }

            case 'save_rule': {
                $id = (int)($_POST['id'] ?? 0);
                $name = trim((string)($_POST['name'] ?? ''));
                $categoryId = trim((string)($_POST['category_id'] ?? ''));
                $priorityCode = trim((string)($_POST['priority_code'] ?? ''));
                $supportLevel = min(3, max(1, (int)($_POST['support_level'] ?? 1)));
                $technicianId = trim((string)($_POST['technician_id'] ?? ''));
                $description = trim((string)($_POST['description'] ?? ''));
                $isActive = isset($_POST['is_active']) ? 1 : 0;
                if ($name === '') {
                    adminToolsRedirectV8('rule_empty', 'rules');
                }
                $categoryIdValue = $categoryId !== '' ? (int)$categoryId : null;
                $priorityCodeValue = $priorityCode !== '' ? adminToolsNormalizeCodeV8($priorityCode) : null;
                $technicianIdValue = $technicianId !== '' ? (int)$technicianId : null;
                if ($id > 0) {
                    $stmt = $pdo->prepare('UPDATE assignment_rules SET name = :name, category_id = :category_id, priority_code = :priority_code, support_level = :support_level, technician_id = :technician_id, description = :description, is_active = :is_active WHERE id = :id');
                    $stmt->execute(['name' => $name, 'category_id' => $categoryIdValue, 'priority_code' => $priorityCodeValue, 'support_level' => $supportLevel, 'technician_id' => $technicianIdValue, 'description' => $description, 'is_active' => $isActive, 'id' => $id]);
                } else {
                    $stmt = $pdo->prepare('INSERT INTO assignment_rules (name, category_id, priority_code, support_level, technician_id, description, is_active) VALUES (:name, :category_id, :priority_code, :support_level, :technician_id, :description, :is_active)');
                    $stmt->execute(['name' => $name, 'category_id' => $categoryIdValue, 'priority_code' => $priorityCodeValue, 'support_level' => $supportLevel, 'technician_id' => $technicianIdValue, 'description' => $description, 'is_active' => $isActive]);
                }
                adminToolsRedirectV8('rule_saved', 'rules');
            }

            case 'delete_rule': {
                $id = (int)($_POST['id'] ?? 0);
                if ($id <= 0) {
                    adminToolsRedirectV8('rule_invalid', 'rules');
                }
                $stmt = $pdo->prepare('DELETE FROM assignment_rules WHERE id = :id');
                $stmt->execute(['id' => $id]);
                adminToolsRedirectV8('rule_deleted', 'rules');
            }
        }
    } catch (Throwable $e) {
        if ($fallbackTab === 'knowledge' && $e instanceof RuntimeException) {
            adminToolsRedirectV8('knowledge_upload_error', 'knowledge');
        }
        adminToolsRedirectV8('error', $fallbackTab);
    }
}

$categories = [];
$activeCategories = [];
$templates = [];
$activeTemplates = [];
$priorities = [];
$activePriorities = [];
$closureReasons = [];
$knowledgeArticles = [];
$activeKnowledgeArticles = [];
$knowledgeAttachmentsByArticle = [];
$assignmentRules = [];
$activeAssignmentRules = [];
$technicians = [];
$monitorTickets = [];
$monitorSummary = ['total' => 0, 'expired' => 0, 'warning' => 0, 'paused' => 0, 'within' => 0];

if ($categoryTableReady) {
    try {
        $stmt = $pdo->query('SELECT id, code, name, description, color, is_active, created_at, updated_at FROM ticket_categories ORDER BY is_active DESC, name ASC');
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $activeCategories = array_values(array_filter($categories, static fn($item) => (int)($item['is_active'] ?? 0) === 1));
    } catch (Throwable $e) {
        $categories = [];
        $activeCategories = [];
    }
}

if (empty($activeCategories)) {
    $activeCategories = [
        ['id' => 0, 'code' => 'RED', 'name' => 'Red', 'description' => 'Conectividad e internet.', 'color' => '#ff7a00', 'is_active' => 1],
        ['id' => 0, 'code' => 'ACCESO', 'name' => 'Acceso', 'description' => 'Usuarios, claves y permisos.', 'color' => '#ff7a00', 'is_active' => 1],
        ['id' => 0, 'code' => 'HARDWARE', 'name' => 'Hardware', 'description' => 'Equipos y periféricos.', 'color' => '#ff7a00', 'is_active' => 1],
        ['id' => 0, 'code' => 'SOFTWARE', 'name' => 'Software', 'description' => 'Aplicaciones instaladas.', 'color' => '#ff7a00', 'is_active' => 1],
        ['id' => 0, 'code' => 'SISTEMA', 'name' => 'Sistema', 'description' => 'Plataformas internas.', 'color' => '#ff7a00', 'is_active' => 1],
        ['id' => 0, 'code' => 'OTROS', 'name' => 'Otros', 'description' => 'Casos generales.', 'color' => '#ff7a00', 'is_active' => 1],
    ];
}

if ($templateTableReady) {
    try {
        $stmt = $pdo->query('SELECT rt.id, rt.title, rt.category_id, rt.content, rt.is_active, rt.created_at, rt.updated_at, tc.name AS category_name, tc.code AS category_code FROM response_templates rt LEFT JOIN ticket_categories tc ON tc.id = rt.category_id ORDER BY rt.is_active DESC, rt.title ASC');
        $templates = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $activeTemplates = array_values(array_filter($templates, static fn($item) => (int)($item['is_active'] ?? 0) === 1));
    } catch (Throwable $e) {
        $templates = [];
        $activeTemplates = [];
    }
}

if ($priorityTableReady) {
    try {
        $stmt = $pdo->query('SELECT id, code, name, sla_hours, color, sort_order, is_active, created_at, updated_at FROM ticket_priorities ORDER BY is_active DESC, sort_order ASC, sla_hours ASC');
        $priorities = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $activePriorities = array_values(array_filter($priorities, static fn($item) => (int)($item['is_active'] ?? 0) === 1));
    } catch (Throwable $e) {
        $priorities = [];
        $activePriorities = [];
    }
}

if (empty($activePriorities)) {
    $activePriorities = [
        ['id' => 0, 'code' => 'ALTA', 'name' => 'Alta', 'sla_hours' => 4, 'color' => '#ef4444', 'sort_order' => 1, 'is_active' => 1],
        ['id' => 0, 'code' => 'MEDIA', 'name' => 'Media', 'sla_hours' => 8, 'color' => '#f59e0b', 'sort_order' => 2, 'is_active' => 1],
        ['id' => 0, 'code' => 'BAJA', 'name' => 'Baja', 'sla_hours' => 24, 'color' => '#22c55e', 'sort_order' => 3, 'is_active' => 1],
    ];
}

if ($closureTableReady) {
    try {
        $stmt = $pdo->query('SELECT id, code, name, description, requires_comment, is_active, created_at, updated_at FROM closure_reasons ORDER BY is_active DESC, name ASC');
        $closureReasons = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $closureReasons = [];
    }
}

if ($knowledgeTableReady) {
    try {
        $contentSelect = $knowledgeContentReady ? 'kb.content_html' : 'NULL AS content_html';
        $stmt = $pdo->query(
            "SELECT
                kb.id,
                kb.title,
                kb.category_id,
                kb.problem_summary,
                kb.solution_steps,
                $contentSelect,
                kb.keywords,
                kb.is_active,
                kb.created_at,
                kb.updated_at,
                tc.name AS category_name,
                tc.code AS category_code
             FROM knowledge_base_articles kb
             LEFT JOIN ticket_categories tc ON tc.id = kb.category_id
             ORDER BY kb.is_active DESC, kb.updated_at DESC, kb.title ASC"
        );
        $knowledgeArticles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($knowledgeAttachmentsTableReady && $knowledgeArticles !== []) {
            $attachmentStmt = $pdo->query(
                'SELECT id, article_id, original_name, file_path, mime_type, file_size, is_image, created_at
                 FROM knowledge_base_attachments
                 ORDER BY created_at ASC, id ASC'
            );
            $attachmentRows = $attachmentStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($attachmentRows as $attachment) {
                $articleId = (int)($attachment['article_id'] ?? 0);
                if ($articleId <= 0) {
                    continue;
                }

                $attachment['formatted_size'] = knowledgeBaseFormatBytes((int)($attachment['file_size'] ?? 0));
                $attachment['view_url'] = '/helpdesk-php/knowledge-attachment.php?id=' . (int)$attachment['id'];
                $attachment['download_url'] = '/helpdesk-php/knowledge-attachment.php?id=' . (int)$attachment['id'] . '&download=1';
                $knowledgeAttachmentsByArticle[$articleId][] = $attachment;
            }
        }

        foreach ($knowledgeArticles as &$knowledgeArticle) {
            $articleId = (int)($knowledgeArticle['id'] ?? 0);
            $contentHtml = trim((string)($knowledgeArticle['content_html'] ?? ''));

            if ($contentHtml === '') {
                $contentHtml = knowledgeBaseLegacyToHtml((string)($knowledgeArticle['solution_steps'] ?? ''));
            }

            $knowledgeArticle['content_html'] = $contentHtml;
            $knowledgeArticle['content_text'] = knowledgeBasePlainText($contentHtml);
            $knowledgeArticle['attachments'] = $knowledgeAttachmentsByArticle[$articleId] ?? [];
        }
        unset($knowledgeArticle);

        $activeKnowledgeArticles = array_values(array_filter(
            $knowledgeArticles,
            static fn($item) => (int)($item['is_active'] ?? 0) === 1
        ));
    } catch (Throwable $e) {
        $knowledgeArticles = [];
        $activeKnowledgeArticles = [];
        $knowledgeAttachmentsByArticle = [];
    }
}

if ($usersTableReady) {
    try {
        $stmt = $pdo->query("SELECT id, name, email, tech_level FROM users WHERE role = 'TECH' AND COALESCE(status, 'active') <> 'inactive' ORDER BY tech_level ASC, name ASC");
        $technicians = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $technicians = [];
    }
}

if ($ruleTableReady) {
    try {
        $stmt = $pdo->query('SELECT ar.id, ar.name, ar.category_id, ar.priority_code, ar.support_level, ar.technician_id, ar.description, ar.is_active, ar.created_at, ar.updated_at, tc.name AS category_name, tc.code AS category_code, u.name AS technician_name FROM assignment_rules ar LEFT JOIN ticket_categories tc ON tc.id = ar.category_id LEFT JOIN users u ON u.id = ar.technician_id ORDER BY ar.is_active DESC, ar.support_level ASC, ar.name ASC');
        $assignmentRules = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $activeAssignmentRules = array_values(array_filter($assignmentRules, static fn($item) => (int)($item['is_active'] ?? 0) === 1));
    } catch (Throwable $e) {
        $assignmentRules = [];
        $activeAssignmentRules = [];
    }
}

if ($ticketsTableReady && $usersTableReady) {
    $hasTicketCompanyId = adminToolsColumnExistsV8($pdo, 'tickets', 'company_id');
    $hasUserCompanyId = adminToolsColumnExistsV8($pdo, 'users', 'company_id');
    $hasUserCompanyText = adminToolsColumnExistsV8($pdo, 'users', 'company');

    $companyJoin = '';
    $companySelect = $hasUserCompanyText
        ? "COALESCE(NULLIF(u.company, ''), 'Sin empresa') AS company_name, '8_5' AS sla_contract_type"
        : "'Sin empresa' AS company_name, '8_5' AS sla_contract_type";

    if ($companyModuleReady) {
        if ($hasTicketCompanyId && $hasUserCompanyId) {
            $companyJoin = ' LEFT JOIN client_companies cc ON cc.id = COALESCE(t.company_id, u.company_id) ';
        } elseif ($hasTicketCompanyId) {
            $companyJoin = ' LEFT JOIN client_companies cc ON cc.id = t.company_id ';
        } elseif ($hasUserCompanyId) {
            $companyJoin = ' LEFT JOIN client_companies cc ON cc.id = u.company_id ';
        }

        if ($companyJoin !== '') {
            $legacyCompanyFallback = $hasUserCompanyText ? ", NULLIF(u.company, '')" : '';
            $companySelect = "COALESCE(NULLIF(cc.trade_name, ''), cc.business_name$legacyCompanyFallback, 'Sin empresa') AS company_name, COALESCE(cc.sla_contract_type, '8_5') AS sla_contract_type";
        }
    }

    $techFilter = '';
    $params = [];
    if ($currentRole === 'TECH') {
        $techFilter = ' AND t.assigned_to = :current_tech_id';
        $params['current_tech_id'] = (int)($currentUser['id'] ?? 0);
    }

    $sqlTickets = "
        SELECT
            t.id,
            t.subject,
            t.status,
            t.priority,
            t.category,
            t.created_at,
            t.first_response_at,
            t.closed_at,
            t.sla_hours,
            t.sla_met,
            t.support_level,
            u.name AS requester_name,
            tech.name AS technician_name,
            $companySelect
        FROM tickets t
        INNER JOIN users u ON u.id = t.requester_id
        LEFT JOIN users tech ON tech.id = t.assigned_to
        $companyJoin
        WHERE t.status <> 'CERRADO'
        $techFilter
        ORDER BY t.created_at ASC
        LIMIT 80
    ";

    try {
        $stmtTickets = $pdo->prepare($sqlTickets);
        $stmtTickets->execute($params);
        $rows = $stmtTickets->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        $rows = [];
    }

    foreach ($rows as $ticket) {
        $info = adminToolsSlaInfoV8($ticket);
        $monitorSummary['total']++;
        if (isset($monitorSummary[$info['type']])) {
            $monitorSummary[$info['type']]++;
        }
        $ticket['sla_label'] = $info['label'];
        $ticket['sla_monitor_type'] = $info['type'];
        $ticket['sla_progress'] = $info['progress'];
        $ticket['sla_elapsed_label'] = $info['elapsed_label'];
        $ticket['sla_contract_label'] = $info['contract_label'];
        $ticket['is_paused'] = $info['is_paused'];
        $monitorTickets[] = $ticket;
    }
}

ob_start();
require __DIR__ . '/app/views/admin/tools.php';
$pageContent = ob_get_clean();

echo $pageContent;
