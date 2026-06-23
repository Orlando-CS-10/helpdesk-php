<?php
$title = 'Herramientas';
$activePage = 'tools';
$pageTitle = 'Herramientas';
$pageSubtitle = 'Administra utilidades operativas para clasificación, respuestas y soporte.';

$categories = $categories ?? [];
$activeCategories = $activeCategories ?? [];
$templates = $templates ?? [];
$activeTemplates = $activeTemplates ?? [];
$priorities = $priorities ?? [];
$activePriorities = $activePriorities ?? [];
$closureReasons = $closureReasons ?? [];
$knowledgeArticles = $knowledgeArticles ?? [];
$activeKnowledgeArticles = $activeKnowledgeArticles ?? [];
$assignmentRules = $assignmentRules ?? [];
$activeAssignmentRules = $activeAssignmentRules ?? [];
$technicians = $technicians ?? [];
$monitorTickets = $monitorTickets ?? [];
$monitorSummary = $monitorSummary ?? [];
$toolsSetupReady = $toolsSetupReady ?? false;
$isAdmin = $isAdmin ?? false;
$currentRole = $currentRole ?? '';

$selectedTab = $_GET['tab'] ?? 'categories';
$validTabs = ['categories', 'templates', 'diagnostic', 'monitor', 'priorities', 'closure', 'knowledge', 'rules'];
if (!in_array($selectedTab, $validTabs, true)) {
    $selectedTab = 'categories';
}

$adminTopbarButtons = [
    ['href' => '/helpdesk-php/admin-tickets.php', 'class' => 'btn-secondary', 'text' => 'Ver tickets'],
];

$statusMessages = [
    'category_saved' => ['success', 'Categoría guardada correctamente.'],
    'category_deleted' => ['success', 'Categoría eliminada correctamente.'],
    'category_disabled' => ['warning', 'La categoría tenía tickets asociados, por eso fue desactivada.'],
    'category_empty' => ['error', 'La categoría necesita un nombre.'],
    'category_invalid' => ['error', 'No se pudo identificar la categoría.'],
    'template_saved' => ['success', 'Plantilla guardada correctamente.'],
    'template_deleted' => ['success', 'Plantilla eliminada correctamente.'],
    'template_empty' => ['error', 'La plantilla necesita título y contenido.'],
    'template_invalid' => ['error', 'No se pudo identificar la plantilla.'],
    'priority_saved' => ['success', 'Prioridad guardada correctamente.'],
    'priority_deleted' => ['success', 'Prioridad eliminada correctamente.'],
    'priority_disabled' => ['warning', 'La prioridad tenía tickets asociados, por eso fue desactivada.'],
    'priority_empty' => ['error', 'La prioridad necesita un nombre.'],
    'priority_invalid' => ['error', 'No se pudo identificar la prioridad.'],
    'closure_saved' => ['success', 'Motivo de cierre guardado correctamente.'],
    'closure_deleted' => ['success', 'Motivo de cierre eliminado correctamente.'],
    'closure_empty' => ['error', 'El motivo necesita un nombre.'],
    'closure_invalid' => ['error', 'No se pudo identificar el motivo.'],
    'knowledge_saved' => ['success', 'Artículo guardado correctamente.'],
    'knowledge_deleted' => ['success', 'Artículo eliminado correctamente.'],
    'knowledge_empty' => ['error', 'El artículo necesita título, problema y solución.'],
    'knowledge_invalid' => ['error', 'No se pudo identificar el artículo.'],
    'knowledge_upload_error' => ['error', 'No se pudo guardar el artículo. Revisa el formato, tamaño o cantidad de archivos adjuntos.'],
    'rule_saved' => ['success', 'Regla de asignación guardada correctamente.'],
    'rule_deleted' => ['success', 'Regla de asignación eliminada correctamente.'],
    'rule_empty' => ['error', 'La regla necesita un nombre.'],
    'rule_invalid' => ['error', 'No se pudo identificar la regla.'],
    'missing_tables' => ['error', 'Primero ejecuta el SQL actualizado del módulo Herramientas.'],
    'not_allowed' => ['error', 'Solo el administrador puede realizar esta acción.'],
    'error' => ['error', 'Ocurrió un error al procesar la acción.'],
];

$statusKey = $_GET['status'] ?? '';
$statusData = $statusMessages[$statusKey] ?? null;

if (!function_exists('toolIconSvgV8')) {
    function toolIconSvgV8(string $name): string
    {
        $icons = [
            'categories' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h6a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 11h6a2 2 0 0 1 2 2v1a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-1a2 2 0 0 1 2-2Zm10-11h4a2 2 0 0 1 2 2v1a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Zm0 9h4a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2h-4a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2Z"/></svg>',
            'templates' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9.4l-3.7 3.1A1 1 0 0 1 4 19.3V6a2 2 0 0 1 2-2Zm4 5h6a1 1 0 1 1 0 2H9a1 1 0 1 1 0-2Zm0 4h4a1 1 0 1 1 0 2H9a1 1 0 1 1 0-2Z"/></svg>',
            'diagnostic' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a1 1 0 0 1 .92.62l.9 2.16 2.32.2a1 1 0 0 1 .56 1.74l-1.76 1.52.53 2.28a1 1 0 0 1-1.48 1.08L12 10.4l-1.99 1.2a1 1 0 0 1-1.48-1.08l.53-2.28L7.3 6.72a1 1 0 0 1 .56-1.74l2.32-.2.9-2.16A1 1 0 0 1 12 2Zm-6.4 9.6a1 1 0 0 1 1.4 0l5.4 5.4a1 1 0 0 1 0 1.4l-2 2a1 1 0 0 1-1.4 0L3.6 15a1 1 0 0 1 0-1.4l2-2Z"/></svg>',
            'monitor' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13a8 8 0 1 1 16 0v1a1 1 0 1 1-2 0v-1a6 6 0 1 0-12 0v1a1 1 0 1 1-2 0v-1Zm8-5a1 1 0 0 1 1 1v3.2l2.3 2.3a1 1 0 0 1-1.4 1.4l-2.6-2.6A1 1 0 0 1 11 12.6V9a1 1 0 0 1 1-1Zm-7.5 9h15a1 1 0 0 1 .8 1.6l-1.5 2a1 1 0 0 1-.8.4H6a1 1 0 0 1-.8-.4l-1.5-2a1 1 0 0 1 .8-1.6Z"/></svg>',
            'priorities' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a1 1 0 0 1 .9.55l8 16A1 1 0 0 1 20 21H4a1 1 0 0 1-.9-1.45l8-16A1 1 0 0 1 12 3Zm0 5a1 1 0 0 0-1 1v4a1 1 0 1 0 2 0V9a1 1 0 0 0-1-1Zm0 8a1.2 1.2 0 1 0 0 2.4A1.2 1.2 0 0 0 12 16Z"/></svg>',
            'closure' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 16.2 4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2Z"/></svg>',
            'knowledge' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4a3 3 0 0 1 3-3h11a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H8a1 1 0 0 0 0 2h11a1 1 0 1 1 0 2H8a3 3 0 0 1-3-3V4Zm3-1a1 1 0 0 0-1 1v13.17A2.98 2.98 0 0 1 8 17h10V3H8Zm2 4h6a1 1 0 1 1 0 2h-6a1 1 0 1 1 0-2Zm0 4h5a1 1 0 1 1 0 2h-5a1 1 0 1 1 0-2Z"/></svg>',
            'rules' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3a4 4 0 0 1 3.87 3H13a4 4 0 1 1 0 2h-2.13A4 4 0 0 1 8 10.87V13a4 4 0 1 1-2 0v-2.13A4 4 0 0 1 7 3Zm0 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4Zm10 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4ZM7 15a2 2 0 1 0 0 4 2 2 0 0 0 0-4Z"/></svg>',
        ];
        return $icons[$name] ?? $icons['categories'];
    }
}

if (!function_exists('toolsMonitorBadgeClassV8')) {
    function toolsMonitorBadgeClassV8(string $type): string
    {
        return match ($type) {
            'expired' => 'danger-pill',
            'warning' => 'pending-pill',
            'paused' => 'neutral-pill',
            default => 'success-pill',
        };
    }
}

if (!function_exists('toolsShortTextV8')) {
    function toolsShortTextV8(?string $text, int $limit = 120): string
    {
        $text = trim((string)$text);
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) <= $limit) {
                return $text;
            }
            return mb_substr($text, 0, $limit) . '...';
        }

        if (strlen($text) <= $limit) {
            return $text;
        }

        return substr($text, 0, $limit) . '...';
    }
}

require_once __DIR__ . '/../layouts/header.php';

// Carga directa del CSS del módulo con versión automática.
// Esto evita que el navegador conserve una versión antigua importada desde app.css.
$toolsCssPath = dirname(__DIR__, 3) . '/public/assets/css/admin-tools.css';
$toolsCssVersion = is_file($toolsCssPath) ? (string) filemtime($toolsCssPath) : (string) time();
?>
<link rel="stylesheet" href="/helpdesk-php/public/assets/css/admin-tools.css?v=<?= htmlspecialchars($toolsCssVersion, ENT_QUOTES) ?>-knowledge-width-2">

<script>document.body.classList.add('admin-tools-body');</script>

<div class="admin-shell admin-tools-shell">
    <?php require_once __DIR__ . '/../layouts/admin-sidebar.php'; ?>

    <div class="admin-main">
        <?php require_once __DIR__ . '/../layouts/admin-topbar.php'; ?>

        <main class="admin-content admin-tools-content">
            <section class="tools-hero-card">
                <div>
                    <span class="tools-eyebrow">Centro operativo</span>
                    <h1>Herramientas</h1>
                    <p>Configura catálogos, respuestas, reglas y recursos de soporte desde un solo panel.</p>
                </div>
                <div class="tools-hero-badge">
                    <strong><?= count($validTabs) ?></strong>
                    <span>herramientas</span>
                </div>
            </section>

            <?php if ($statusData): ?>
                <div class="tools-alert <?= htmlspecialchars($statusData[0]) ?>">
                    <?= htmlspecialchars($statusData[1]) ?>
                </div>
            <?php endif; ?>

            <?php if (!$toolsSetupReady): ?>
                <div class="tools-alert warning">
                    Ejecuta primero <strong>sql_herramientas_8.sql</strong> en phpMyAdmin para activar todas las herramientas.
                </div>
            <?php endif; ?>

            <section class="tools-workspace">
                <aside class="tools-side-menu">
                    <div class="tools-side-title">
                        <span>Herramientas</span>
                        <small>Selecciona una opción</small>
                    </div>

                    <?php
                    $menuItems = [
                        ['categories', 'Categorías', 'Clasificación de tickets.'],
                        ['templates', 'Plantillas', 'Respuestas rápidas.'],
                        ['diagnostic', 'Diagnóstico rápido', 'Sugerencias por síntomas.'],
                        ['monitor', 'Monitor SLA', 'Tickets críticos.'],
                        ['priorities', 'Prioridades', 'Horas SLA y niveles.'],
                        ['closure', 'Motivos de cierre', 'Causas de finalización.'],
                        ['knowledge', 'Base de conocimiento', 'Soluciones frecuentes.'],
                        ['rules', 'Reglas de asignación', 'Derivación técnica.'],
                    ];
                    ?>
                    <?php foreach ($menuItems as [$tab, $label, $hint]): ?>
                        <button type="button" class="tools-menu-item <?= $selectedTab === $tab ? 'active' : '' ?>" data-tool-tab="<?= htmlspecialchars($tab) ?>">
                            <span class="tools-menu-icon"><?= toolIconSvgV8($tab) ?></span>
                            <span>
                                <strong><?= htmlspecialchars($label) ?></strong>
                                <small><?= htmlspecialchars($hint) ?></small>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </aside>

                <section class="tools-usage-panel">
                    <article class="tools-panel <?= $selectedTab === 'categories' ? 'active' : '' ?>" data-tool-panel="categories">
                        <div class="tools-panel-header">
                            <div>
                                <span>Uso de la herramienta</span>
                                <h2>Categorías de tickets</h2>
                                <p>Administra las categorías que se usarán para clasificar incidencias.</p>
                            </div>
                            <div class="tools-panel-counter"><strong><?= count($categories ?: $activeCategories) ?></strong><small>categorías</small></div>
                        </div>

                        <?php if ($isAdmin): ?>
                            <form method="POST" class="tools-form-card" id="categoryForm">
                                <input type="hidden" name="action" value="save_category">
                                <input type="hidden" name="id" id="categoryId" value="">
                                <div class="tools-form-grid">
                                    <div class="form-group"><label>Nombre</label><input type="text" id="categoryName" name="name" placeholder="Ej. Correo corporativo" required></div>
                                    <div class="form-group"><label>Código</label><input type="text" id="categoryCode" name="code" placeholder="Ej. CORREO"></div>
                                    <div class="form-group tools-color-field"><label>Color</label><input type="color" id="categoryColor" name="color" value="#ff7a00"></div>
                                </div>
                                <div class="form-group"><label>Descripción</label><textarea id="categoryDescription" name="description" rows="2" placeholder="Breve descripción de la categoría"></textarea></div>
                                <div class="tools-form-actions">
                                    <label class="tools-check"><input type="checkbox" name="is_active" id="categoryActive" checked> Activa</label>
                                    <button type="button" class="btn-secondary" id="clearCategoryForm">Limpiar</button>
                                    <button type="submit" class="admin-tool-action-btn">Guardar categoría</button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="tools-list-grid">
                            <?php foreach (($categories ?: $activeCategories) as $category): ?>
                                <?php $isActiveCategory = (int)($category['is_active'] ?? 1) === 1; ?>
                                <div class="tools-data-card <?= !$isActiveCategory ? 'is-disabled' : '' ?>">
                                    <div class="tools-data-top">
                                        <span class="tools-color-dot" style="background: <?= htmlspecialchars($category['color'] ?? '#ff7a00') ?>"></span>
                                        <div><strong><?= htmlspecialchars($category['name'] ?? 'Categoría') ?></strong><small><?= htmlspecialchars($category['code'] ?? 'SIN_CODIGO') ?></small></div>
                                        <span class="tools-state <?= $isActiveCategory ? 'on' : 'off' ?>"><?= $isActiveCategory ? 'Activa' : 'Inactiva' ?></span>
                                    </div>
                                    <p><?= htmlspecialchars($category['description'] ?: 'Sin descripción.') ?></p>
                                    <?php if ($isAdmin && (int)($category['id'] ?? 0) > 0): ?>
                                        <div class="tools-card-actions">
                                            <button type="button" class="btn-secondary" data-edit-category data-id="<?= (int)$category['id'] ?>" data-name="<?= htmlspecialchars($category['name'] ?? '', ENT_QUOTES) ?>" data-code="<?= htmlspecialchars($category['code'] ?? '', ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($category['description'] ?? '', ENT_QUOTES) ?>" data-color="<?= htmlspecialchars($category['color'] ?? '#ff7a00', ENT_QUOTES) ?>" data-active="<?= $isActiveCategory ? '1' : '0' ?>">Editar</button>
                                            <form method="POST" onsubmit="return confirm('¿Deseas quitar esta categoría?');"><input type="hidden" name="action" value="delete_category"><input type="hidden" name="id" value="<?= (int)$category['id'] ?>"><button type="submit" class="btn-danger-soft">Quitar</button></form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="tools-panel <?= $selectedTab === 'templates' ? 'active' : '' ?>" data-tool-panel="templates">
                        <div class="tools-panel-header"><div><span>Uso de la herramienta</span><h2>Plantillas de respuesta</h2><p>Crea respuestas rápidas para reducir tiempos de atención.</p></div><div class="tools-panel-counter"><strong><?= count($templates) ?></strong><small>plantillas</small></div></div>

                        <?php if ($isAdmin): ?>
                            <form method="POST" class="tools-form-card" id="templateForm">
                                <input type="hidden" name="action" value="save_template"><input type="hidden" name="id" id="templateId" value="">
                                <div class="tools-form-grid two-cols">
                                    <div class="form-group"><label>Título</label><input type="text" id="templateTitle" name="title" placeholder="Ej. Primera atención" required></div>
                                    <div class="form-group"><label>Categoría</label><select id="templateCategory" name="category_id"><option value="">General</option><?php foreach ($activeCategories as $category): ?><?php if ((int)($category['id'] ?? 0) > 0): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div>
                                </div>
                                <div class="form-group"><label>Contenido</label><textarea id="templateContent" name="content" rows="4" placeholder="Texto de respuesta para el técnico" required></textarea></div>
                                <div class="tools-form-actions"><label class="tools-check"><input type="checkbox" name="is_active" id="templateActive" checked> Activa</label><button type="button" class="btn-secondary" id="clearTemplateForm">Limpiar</button><button type="submit" class="admin-tool-action-btn">Guardar plantilla</button></div>
                            </form>
                        <?php endif; ?>

                        <div class="tools-list-grid">
                            <?php foreach ($templates as $template): ?>
                                <?php $isActiveTemplate = (int)($template['is_active'] ?? 1) === 1; $templateDomId = 'templateText' . (int)$template['id']; ?>
                                <div class="tools-data-card <?= !$isActiveTemplate ? 'is-disabled' : '' ?>">
                                    <div class="tools-data-top"><div><strong><?= htmlspecialchars($template['title'] ?? 'Plantilla') ?></strong><small><?= htmlspecialchars($template['category_name'] ?? 'General') ?></small></div><span class="tools-state <?= $isActiveTemplate ? 'on' : 'off' ?>"><?= $isActiveTemplate ? 'Activa' : 'Inactiva' ?></span></div>
                                    <p id="<?= htmlspecialchars($templateDomId) ?>"><?= nl2br(htmlspecialchars($template['content'] ?? '')) ?></p>
                                    <div class="tools-card-actions"><button type="button" class="btn-secondary" data-copy-template="<?= htmlspecialchars($templateDomId) ?>">Copiar</button><?php if ($isAdmin): ?><button type="button" class="btn-secondary" data-edit-template data-id="<?= (int)$template['id'] ?>" data-title="<?= htmlspecialchars($template['title'] ?? '', ENT_QUOTES) ?>" data-category="<?= htmlspecialchars((string)($template['category_id'] ?? ''), ENT_QUOTES) ?>" data-content="<?= htmlspecialchars($template['content'] ?? '', ENT_QUOTES) ?>" data-active="<?= $isActiveTemplate ? '1' : '0' ?>">Editar</button><form method="POST" onsubmit="return confirm('¿Deseas eliminar esta plantilla?');"><input type="hidden" name="action" value="delete_template"><input type="hidden" name="id" value="<?= (int)$template['id'] ?>"><button type="submit" class="btn-danger-soft">Eliminar</button></form><?php endif; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="tools-panel <?= $selectedTab === 'diagnostic' ? 'active' : '' ?>" data-tool-panel="diagnostic">
                        <div class="tools-panel-header"><div><span>Uso de la herramienta</span><h2>Diagnóstico rápido</h2><p>Selecciona síntomas y obtén categoría, prioridad, nivel, plantilla y artículo sugerido.</p></div><div class="tools-panel-counter"><strong>Reglas</strong><small>operativas</small></div></div>
                        <div class="tools-diagnostic-wrap">
                            <div class="tools-diagnostic-grid">
                                <?php $symptoms = [
                                    'sin_internet' => 'Sin internet', 'lentitud' => 'Lentitud', 'credenciales' => 'Error de credenciales', 'permisos' => 'Permisos insuficientes', 'equipo' => 'Equipo no responde', 'impresora' => 'Problema con impresora', 'sistema' => 'Sistema caído', 'software' => 'Software con error'
                                ]; ?>
                                <?php foreach ($symptoms as $value => $label): ?><label><input type="checkbox" value="<?= htmlspecialchars($value) ?>"> <span><?= htmlspecialchars($label) ?></span></label><?php endforeach; ?>
                            </div>
                            <div class="tools-result-card" id="diagnosticResult"><span>Resultado</span><strong>Selecciona uno o más síntomas.</strong><p>El diagnóstico usará las reglas, categorías, prioridades y base de conocimiento activas.</p></div>
                        </div>
                        <div class="tools-form-actions"><button type="button" class="admin-tool-action-btn" id="diagnosticBtn">Generar diagnóstico</button><button type="button" class="btn-secondary" id="clearDiagnosticBtn">Limpiar</button></div>
                    </article>

                    <article class="tools-panel <?= $selectedTab === 'monitor' ? 'active' : '' ?>" data-tool-panel="monitor">
                        <div class="tools-panel-header"><div><span>Uso de la herramienta</span><h2>Monitor SLA</h2><p>Revisa tickets abiertos, vencidos, por vencer o pausados.</p></div><div class="tools-panel-counter"><strong><?= (int)($monitorSummary['total'] ?? 0) ?></strong><small>tickets</small></div></div>
                        <div class="tools-monitor-summary">
                            <button type="button" class="active" data-monitor-filter="all"><strong><?= (int)($monitorSummary['total'] ?? 0) ?></strong><span>Todos</span></button>
                            <button type="button" data-monitor-filter="expired"><strong><?= (int)($monitorSummary['expired'] ?? 0) ?></strong><span>Vencidos</span></button>
                            <button type="button" data-monitor-filter="warning"><strong><?= (int)($monitorSummary['warning'] ?? 0) ?></strong><span>Por vencer</span></button>
                            <button type="button" data-monitor-filter="paused"><strong><?= (int)($monitorSummary['paused'] ?? 0) ?></strong><span>Pausados</span></button>
                        </div>
                        <div class="tools-monitor-list">
                            <?php if (empty($monitorTickets)): ?><div class="tools-empty-state"><strong>No hay tickets críticos</strong><span>No se encontraron tickets abiertos para mostrar.</span></div><?php endif; ?>
                            <?php foreach ($monitorTickets as $ticket): ?>
                                <a class="tools-monitor-item" data-monitor-type="<?= htmlspecialchars($ticket['sla_monitor_type'] ?? 'within') ?>" href="/helpdesk-php/ticket-detail.php?id=<?= (int)$ticket['id'] ?>">
                                    <div><strong>#<?= (int)$ticket['id'] ?> · <?= htmlspecialchars(toolsShortTextV8($ticket['subject'] ?? 'Sin asunto', 70)) ?></strong><small><?= htmlspecialchars($ticket['company_name'] ?? 'Sin empresa') ?> · <?= htmlspecialchars($ticket['requester_name'] ?? 'Solicitante') ?></small></div>
                                    <div class="tools-monitor-meta"><span class="status-pill <?= toolsMonitorBadgeClassV8($ticket['sla_monitor_type'] ?? 'within') ?>"><?= htmlspecialchars($ticket['sla_label'] ?? 'SLA') ?></span><small><?= (int)($ticket['sla_progress'] ?? 0) ?>% · <?= htmlspecialchars($ticket['sla_contract_label'] ?? '8/5') ?></small></div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="tools-panel <?= $selectedTab === 'priorities' ? 'active' : '' ?>" data-tool-panel="priorities">
                        <div class="tools-panel-header"><div><span>Uso de la herramienta</span><h2>Prioridades</h2><p>Configura prioridad, color y horas objetivo del SLA.</p></div><div class="tools-panel-counter"><strong><?= count($priorities ?: $activePriorities) ?></strong><small>prioridades</small></div></div>
                        <?php if ($isAdmin): ?>
                            <form method="POST" class="tools-form-card" id="priorityForm"><input type="hidden" name="action" value="save_priority"><input type="hidden" name="id" id="priorityId" value="">
                                <div class="tools-form-grid">
                                    <div class="form-group"><label>Nombre</label><input type="text" name="name" id="priorityName" placeholder="Ej. Crítica" required></div>
                                    <div class="form-group"><label>Código</label><input type="text" name="code" id="priorityCode" placeholder="Ej. CRITICA"></div>
                                    <div class="form-group"><label>SLA horas</label><input type="number" step="0.5" min="0.5" name="sla_hours" id="prioritySla" value="8"></div>
                                    <div class="form-group"><label>Orden</label><input type="number" name="sort_order" id="priorityOrder" value="1"></div>
                                    <div class="form-group tools-color-field"><label>Color</label><input type="color" name="color" id="priorityColor" value="#ff7a00"></div>
                                </div>
                                <div class="tools-form-actions"><label class="tools-check"><input type="checkbox" name="is_active" id="priorityActive" checked> Activa</label><button type="button" class="btn-secondary" id="clearPriorityForm">Limpiar</button><button type="submit" class="admin-tool-action-btn">Guardar prioridad</button></div>
                            </form>
                        <?php endif; ?>
                        <div class="tools-list-grid compact">
                            <?php foreach (($priorities ?: $activePriorities) as $priority): ?><?php $isActivePriority = (int)($priority['is_active'] ?? 1) === 1; ?>
                                <div class="tools-data-card <?= !$isActivePriority ? 'is-disabled' : '' ?>"><div class="tools-data-top"><span class="tools-color-dot" style="background: <?= htmlspecialchars($priority['color'] ?? '#ff7a00') ?>"></span><div><strong><?= htmlspecialchars($priority['name']) ?></strong><small><?= htmlspecialchars($priority['code']) ?> · <?= htmlspecialchars((string)$priority['sla_hours']) ?> h SLA</small></div><span class="tools-state <?= $isActivePriority ? 'on' : 'off' ?>"><?= $isActivePriority ? 'Activa' : 'Inactiva' ?></span></div><?php if ($isAdmin && (int)($priority['id'] ?? 0) > 0): ?><div class="tools-card-actions"><button type="button" class="btn-secondary" data-edit-priority data-id="<?= (int)$priority['id'] ?>" data-name="<?= htmlspecialchars($priority['name'] ?? '', ENT_QUOTES) ?>" data-code="<?= htmlspecialchars($priority['code'] ?? '', ENT_QUOTES) ?>" data-sla="<?= htmlspecialchars((string)($priority['sla_hours'] ?? '8'), ENT_QUOTES) ?>" data-color="<?= htmlspecialchars($priority['color'] ?? '#ff7a00', ENT_QUOTES) ?>" data-order="<?= (int)($priority['sort_order'] ?? 1) ?>" data-active="<?= $isActivePriority ? '1' : '0' ?>">Editar</button><form method="POST" onsubmit="return confirm('¿Deseas quitar esta prioridad?');"><input type="hidden" name="action" value="delete_priority"><input type="hidden" name="id" value="<?= (int)$priority['id'] ?>"><button type="submit" class="btn-danger-soft">Quitar</button></form></div><?php endif; ?></div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="tools-panel <?= $selectedTab === 'closure' ? 'active' : '' ?>" data-tool-panel="closure">
                        <div class="tools-panel-header"><div><span>Uso de la herramienta</span><h2>Motivos de cierre</h2><p>Define causas estándar para finalizar tickets.</p></div><div class="tools-panel-counter"><strong><?= count($closureReasons) ?></strong><small>motivos</small></div></div>
                        <?php if ($isAdmin): ?>
                            <form method="POST" class="tools-form-card" id="closureForm"><input type="hidden" name="action" value="save_closure_reason"><input type="hidden" name="id" id="closureId" value=""><div class="tools-form-grid two-cols"><div class="form-group"><label>Nombre</label><input type="text" name="name" id="closureName" placeholder="Ej. Solucionado" required></div><div class="form-group"><label>Código</label><input type="text" name="code" id="closureCode" placeholder="Ej. SOLUCIONADO"></div></div><div class="form-group"><label>Descripción</label><textarea name="description" id="closureDescription" rows="2"></textarea></div><div class="tools-form-actions"><label class="tools-check"><input type="checkbox" name="requires_comment" id="closureRequires"> Requiere comentario</label><label class="tools-check"><input type="checkbox" name="is_active" id="closureActive" checked> Activo</label><button type="button" class="btn-secondary" id="clearClosureForm">Limpiar</button><button type="submit" class="admin-tool-action-btn">Guardar motivo</button></div></form>
                        <?php endif; ?>
                        <div class="tools-list-grid compact"><?php foreach ($closureReasons as $reason): ?><?php $isActiveReason = (int)($reason['is_active'] ?? 1) === 1; ?><div class="tools-data-card <?= !$isActiveReason ? 'is-disabled' : '' ?>"><div class="tools-data-top"><div><strong><?= htmlspecialchars($reason['name']) ?></strong><small><?= htmlspecialchars($reason['code']) ?> <?= (int)($reason['requires_comment'] ?? 0) === 1 ? '· requiere comentario' : '' ?></small></div><span class="tools-state <?= $isActiveReason ? 'on' : 'off' ?>"><?= $isActiveReason ? 'Activo' : 'Inactivo' ?></span></div><p><?= htmlspecialchars($reason['description'] ?: 'Sin descripción.') ?></p><?php if ($isAdmin): ?><div class="tools-card-actions"><button type="button" class="btn-secondary" data-edit-closure data-id="<?= (int)$reason['id'] ?>" data-name="<?= htmlspecialchars($reason['name'] ?? '', ENT_QUOTES) ?>" data-code="<?= htmlspecialchars($reason['code'] ?? '', ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($reason['description'] ?? '', ENT_QUOTES) ?>" data-requires="<?= (int)($reason['requires_comment'] ?? 0) ?>" data-active="<?= $isActiveReason ? '1' : '0' ?>">Editar</button><form method="POST" onsubmit="return confirm('¿Deseas eliminar este motivo?');"><input type="hidden" name="action" value="delete_closure_reason"><input type="hidden" name="id" value="<?= (int)$reason['id'] ?>"><button type="submit" class="btn-danger-soft">Eliminar</button></form></div><?php endif; ?></div><?php endforeach; ?></div>
                    </article>

                    <article class="tools-panel <?= $selectedTab === 'knowledge' ? 'active' : '' ?>" data-tool-panel="knowledge">
                        <div class="tools-panel-header">
                            <div>
                                <span>Uso de la herramienta</span>
                                <h2>Base de conocimiento</h2>
                                <p>Crea artículos completos, agrega formato libre y adjunta archivos de apoyo.</p>
                            </div>
                            <div class="tools-panel-counter">
                                <strong><?= count($knowledgeArticles) ?></strong>
                                <small>artículos</small>
                            </div>
                        </div>

                        <?php if ($isAdmin): ?>
                            <form method="POST" enctype="multipart/form-data" class="tools-form-card knowledge-editor-form" id="knowledgeForm">
                                <input type="hidden" name="action" value="save_knowledge">
                                <input type="hidden" name="id" id="knowledgeId" value="">
                                <input type="hidden" name="content_html" id="knowledgeContentHtml" value="">

                                <div class="tools-form-grid two-cols">
                                    <div class="form-group">
                                        <label for="knowledgeTitle">Título</label>
                                        <input type="text" name="title" id="knowledgeTitle" required placeholder="Ej. Usuario no puede iniciar sesión">
                                    </div>

                                    <div class="form-group">
                                        <label for="knowledgeCategory">Categoría</label>
                                        <select name="category_id" id="knowledgeCategory">
                                            <option value="">General</option>
                                            <?php foreach ($activeCategories as $category): ?>
                                                <?php if ((int)($category['id'] ?? 0) > 0): ?>
                                                    <option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="knowledgeProblem">Resumen del problema</label>
                                    <textarea name="problem_summary" id="knowledgeProblem" rows="3" required placeholder="Describe de forma breve el problema que resuelve este artículo."></textarea>
                                </div>

                                <div class="form-group">
                                    <label>Contenido del artículo</label>

                                    <div class="knowledge-editor-shell">
                                        <div class="knowledge-editor-toolbar" role="toolbar" aria-label="Formato del artículo">
                                            <select class="knowledge-toolbar-select" id="knowledgeFormatBlock" title="Tipo de texto">
                                                <option value="p">Párrafo</option>
                                                <option value="h2">Título</option>
                                                <option value="h3">Subtítulo</option>
                                                <option value="h4">Título pequeño</option>
                                                <option value="blockquote">Cita</option>
                                                <option value="pre">Código</option>
                                            </select>

                                            <select class="knowledge-toolbar-select" id="knowledgeFontSize" title="Tamaño del texto">
                                                <option value="">Tamaño</option>
                                                <option value="2">Pequeño</option>
                                                <option value="3">Normal</option>
                                                <option value="4">Mediano</option>
                                                <option value="5">Grande</option>
                                                <option value="6">Muy grande</option>
                                            </select>

                                            <span class="knowledge-toolbar-separator"></span>

                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="bold" title="Negrita"><strong>B</strong></button>
                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="italic" title="Cursiva"><em>I</em></button>
                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="underline" title="Subrayado"><u>U</u></button>
                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="strikeThrough" title="Tachado"><s>S</s></button>

                                            <label class="knowledge-color-control" title="Color del texto">
                                                <span>A</span>
                                                <input type="color" id="knowledgeTextColor" value="#0f172a">
                                            </label>

                                            <label class="knowledge-color-control" title="Color de resaltado">
                                                <span>▰</span>
                                                <input type="color" id="knowledgeHighlightColor" value="#fff3bf">
                                            </label>

                                            <span class="knowledge-toolbar-separator"></span>

                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="insertUnorderedList" title="Viñetas">• Lista</button>
                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="insertOrderedList" title="Numeración">1. Lista</button>
                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="outdent" title="Reducir sangría">←</button>
                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="indent" title="Aumentar sangría">→</button>

                                            <span class="knowledge-toolbar-separator"></span>

                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="justifyLeft" title="Alinear a la izquierda">≡</button>
                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="justifyCenter" title="Centrar">≡</button>
                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="justifyRight" title="Alinear a la derecha">≡</button>
                                            <button type="button" class="knowledge-toolbar-btn" id="knowledgeLinkBtn" title="Insertar enlace">🔗</button>
                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="insertHorizontalRule" title="Separador">―</button>

                                            <span class="knowledge-toolbar-separator"></span>

                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="undo" title="Deshacer">↶</button>
                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="redo" title="Rehacer">↷</button>
                                            <button type="button" class="knowledge-toolbar-btn" data-editor-command="removeFormat" title="Quitar formato">Tx</button>
                                        </div>

                                        <div
                                            id="knowledgeEditor"
                                            class="knowledge-rich-editor"
                                            contenteditable="true"
                                            role="textbox"
                                            aria-multiline="true"
                                            data-placeholder="Escribe el contenido. Presionar Enter crea un nuevo párrafo; las viñetas o la numeración solo aparecen cuando tú las eliges."></div>
                                    </div>

                                    <small class="knowledge-field-help">
                                        Puedes usar títulos, negrita, cursiva, subrayado, colores, alineación, viñetas, numeración y enlaces.
                                    </small>
                                </div>

                                <div class="form-group">
                                    <label for="knowledgeAttachments">Imágenes y archivos adjuntos</label>
                                    <label class="knowledge-upload-zone" for="knowledgeAttachments">
                                        <span class="knowledge-upload-icon">＋</span>
                                        <span>
                                            <strong>Seleccionar archivos</strong>
                                            <small>JPG, PNG, WEBP, GIF, PDF, Word, Excel, PowerPoint, TXT, CSV o ZIP. Máximo 8 archivos de 10 MB.</small>
                                        </span>
                                    </label>
                                    <input
                                        class="knowledge-file-input"
                                        type="file"
                                        name="attachments[]"
                                        id="knowledgeAttachments"
                                        multiple
                                        accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip">

                                    <div class="knowledge-selected-files" id="knowledgeSelectedFiles" hidden></div>

                                    <div class="knowledge-existing-files" id="knowledgeExistingFiles" hidden>
                                        <strong>Archivos actuales</strong>
                                        <div id="knowledgeExistingFilesList"></div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="knowledgeKeywords">Palabras clave</label>
                                    <input type="text" name="keywords" id="knowledgeKeywords" placeholder="login, red, impresora">
                                </div>

                                <div class="tools-form-actions">
                                    <label class="tools-check">
                                        <input type="checkbox" name="is_active" id="knowledgeActive" checked>
                                        Activo
                                    </label>
                                    <button type="button" class="btn-secondary" id="clearKnowledgeForm">Limpiar</button>
                                    <button type="submit" class="admin-tool-action-btn">Guardar artículo</button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="tools-list-grid knowledge-admin-grid">
                            <?php if (empty($knowledgeArticles)): ?>
                                <div class="tools-empty-state">
                                    <strong>No hay artículos registrados</strong>
                                    <span>Crea el primer artículo desde el editor superior.</span>
                                </div>
                            <?php endif; ?>

                            <?php foreach ($knowledgeArticles as $article): ?>
                                <?php
                                $isActiveArticle = (int)($article['is_active'] ?? 1) === 1;
                                $articleDomId = 'knowledgeText' . (int)$article['id'];
                                $articleAttachments = $article['attachments'] ?? [];
                                ?>
                                <div class="tools-data-card knowledge-admin-card <?= !$isActiveArticle ? 'is-disabled' : '' ?>">
                                    <div class="tools-data-top">
                                        <div>
                                            <strong><?= htmlspecialchars($article['title']) ?></strong>
                                            <small>
                                                <?= htmlspecialchars($article['category_name'] ?? 'General') ?>
                                                · <?= count($articleAttachments) ?> archivo<?= count($articleAttachments) === 1 ? '' : 's' ?>
                                            </small>
                                        </div>
                                        <span class="tools-state <?= $isActiveArticle ? 'on' : 'off' ?>">
                                            <?= $isActiveArticle ? 'Activo' : 'Inactivo' ?>
                                        </span>
                                    </div>

                                    <p><strong>Problema:</strong> <?= htmlspecialchars(toolsShortTextV8($article['problem_summary'] ?? '', 160)) ?></p>

                                    <div class="knowledge-admin-preview" id="<?= htmlspecialchars($articleDomId) ?>">
                                        <?= nl2br(htmlspecialchars(toolsShortTextV8($article['content_text'] ?? '', 260))) ?>
                                    </div>

                                    <?php if (!empty($article['keywords'])): ?>
                                        <small class="knowledge-admin-keywords"><?= htmlspecialchars($article['keywords']) ?></small>
                                    <?php endif; ?>

                                    <div class="tools-card-actions">
                                        <a class="btn-secondary" href="/helpdesk-php/knowledge-article.php?id=<?= (int)$article['id'] ?>" target="_blank" rel="noopener">Ver artículo</a>
                                        <button type="button" class="btn-secondary" data-copy-template="<?= htmlspecialchars($articleDomId) ?>">Copiar texto</button>

                                        <?php if ($isAdmin): ?>
                                            <button type="button" class="btn-secondary" data-edit-knowledge data-id="<?= (int)$article['id'] ?>">Editar</button>

                                            <form method="POST" onsubmit="return confirm('¿Deseas eliminar este artículo y todos sus archivos adjuntos?');">
                                                <input type="hidden" name="action" value="delete_knowledge">
                                                <input type="hidden" name="id" value="<?= (int)$article['id'] ?>">
                                                <button type="submit" class="btn-danger-soft">Eliminar</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>

                    <article class="tools-panel <?= $selectedTab === 'rules' ? 'active' : '' ?>" data-tool-panel="rules">
                        <div class="tools-panel-header"><div><span>Uso de la herramienta</span><h2>Reglas de asignación</h2><p>Define sugerencias de nivel técnico o técnico responsable según categoría y prioridad.</p></div><div class="tools-panel-counter"><strong><?= count($assignmentRules) ?></strong><small>reglas</small></div></div>
                        <?php if ($isAdmin): ?>
                            <form method="POST" class="tools-form-card" id="ruleForm"><input type="hidden" name="action" value="save_rule"><input type="hidden" name="id" id="ruleId" value=""><div class="tools-form-grid"><div class="form-group"><label>Nombre</label><input type="text" name="name" id="ruleName" required placeholder="Ej. Red alta prioridad"></div><div class="form-group"><label>Categoría</label><select name="category_id" id="ruleCategory"><option value="">Cualquiera</option><?php foreach ($activeCategories as $category): ?><?php if ((int)($category['id'] ?? 0) > 0): ?><option value="<?= (int)$category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option><?php endif; ?><?php endforeach; ?></select></div><div class="form-group"><label>Prioridad</label><select name="priority_code" id="rulePriority"><option value="">Cualquiera</option><?php foreach ($activePriorities as $priority): ?><option value="<?= htmlspecialchars($priority['code']) ?>"><?= htmlspecialchars($priority['name']) ?></option><?php endforeach; ?></select></div><div class="form-group"><label>Nivel técnico</label><select name="support_level" id="ruleLevel"><option value="1">Nivel 1</option><option value="2">Nivel 2</option><option value="3">Nivel 3</option></select></div><div class="form-group"><label>Técnico específico</label><select name="technician_id" id="ruleTechnician"><option value="">Sin técnico fijo</option><?php foreach ($technicians as $tech): ?><option value="<?= (int)$tech['id'] ?>"><?= htmlspecialchars($tech['name']) ?><?= !empty($tech['tech_level']) ? ' · N' . (int)$tech['tech_level'] : '' ?></option><?php endforeach; ?></select></div></div><div class="form-group"><label>Descripción</label><textarea name="description" id="ruleDescription" rows="2"></textarea></div><div class="tools-form-actions"><label class="tools-check"><input type="checkbox" name="is_active" id="ruleActive" checked> Activa</label><button type="button" class="btn-secondary" id="clearRuleForm">Limpiar</button><button type="submit" class="admin-tool-action-btn">Guardar regla</button></div></form>
                        <?php endif; ?>
                        <div class="tools-list-grid compact"><?php foreach ($assignmentRules as $rule): ?><?php $isActiveRule = (int)($rule['is_active'] ?? 1) === 1; ?><div class="tools-data-card <?= !$isActiveRule ? 'is-disabled' : '' ?>"><div class="tools-data-top"><div><strong><?= htmlspecialchars($rule['name']) ?></strong><small><?= htmlspecialchars($rule['category_name'] ?? 'Cualquier categoría') ?> · <?= htmlspecialchars($rule['priority_code'] ?? 'Cualquier prioridad') ?> · Nivel <?= (int)$rule['support_level'] ?></small></div><span class="tools-state <?= $isActiveRule ? 'on' : 'off' ?>"><?= $isActiveRule ? 'Activa' : 'Inactiva' ?></span></div><p><?= htmlspecialchars($rule['description'] ?: 'Sin descripción.') ?></p><small>Técnico: <?= htmlspecialchars($rule['technician_name'] ?? 'Según disponibilidad') ?></small><?php if ($isAdmin): ?><div class="tools-card-actions"><button type="button" class="btn-secondary" data-edit-rule data-id="<?= (int)$rule['id'] ?>" data-name="<?= htmlspecialchars($rule['name'] ?? '', ENT_QUOTES) ?>" data-category="<?= htmlspecialchars((string)($rule['category_id'] ?? ''), ENT_QUOTES) ?>" data-priority="<?= htmlspecialchars($rule['priority_code'] ?? '', ENT_QUOTES) ?>" data-level="<?= (int)($rule['support_level'] ?? 1) ?>" data-technician="<?= htmlspecialchars((string)($rule['technician_id'] ?? ''), ENT_QUOTES) ?>" data-description="<?= htmlspecialchars($rule['description'] ?? '', ENT_QUOTES) ?>" data-active="<?= $isActiveRule ? '1' : '0' ?>">Editar</button><form method="POST" onsubmit="return confirm('¿Deseas eliminar esta regla?');"><input type="hidden" name="action" value="delete_rule"><input type="hidden" name="id" value="<?= (int)$rule['id'] ?>"><button type="submit" class="btn-danger-soft">Eliminar</button></form></div><?php endif; ?></div><?php endforeach; ?></div>
                    </article>
                </section>
            </section>
        </main>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const categories = <?= json_encode($activeCategories, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const templates = <?= json_encode($activeTemplates, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const priorities = <?= json_encode($activePriorities, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const rules = <?= json_encode($activeAssignmentRules, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const articles = <?= json_encode($activeKnowledgeArticles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const knowledgeRecords = <?= json_encode($knowledgeArticles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function activateTool(tab) {
        document.querySelectorAll('[data-tool-tab]').forEach(btn => btn.classList.toggle('active', btn.dataset.toolTab === tab));
        document.querySelectorAll('[data-tool-panel]').forEach(panel => panel.classList.toggle('active', panel.dataset.toolPanel === tab));
        const url = new URL(window.location.href);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url.toString());
    }

    document.querySelectorAll('[data-tool-tab]').forEach(button => button.addEventListener('click', () => activateTool(button.dataset.toolTab)));

    function value(id, val = '') { const el = document.getElementById(id); if (el) el.value = val; }
    function checked(id, val = true) { const el = document.getElementById(id); if (el) el.checked = val; }
    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, character => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        })[character]);
    }

    function clearCategoryForm() { value('categoryId'); value('categoryName'); value('categoryCode'); value('categoryDescription'); value('categoryColor', '#ff7a00'); checked('categoryActive', true); }
    document.getElementById('clearCategoryForm')?.addEventListener('click', clearCategoryForm);
    document.querySelectorAll('[data-edit-category]').forEach(button => button.addEventListener('click', function () { activateTool('categories'); value('categoryId', button.dataset.id); value('categoryName', button.dataset.name); value('categoryCode', button.dataset.code); value('categoryDescription', button.dataset.description); value('categoryColor', button.dataset.color || '#ff7a00'); checked('categoryActive', button.dataset.active === '1'); document.getElementById('categoryName')?.focus(); }));

    function clearTemplateForm() { value('templateId'); value('templateTitle'); value('templateCategory'); value('templateContent'); checked('templateActive', true); }
    document.getElementById('clearTemplateForm')?.addEventListener('click', clearTemplateForm);
    document.querySelectorAll('[data-edit-template]').forEach(button => button.addEventListener('click', function () { activateTool('templates'); value('templateId', button.dataset.id); value('templateTitle', button.dataset.title); value('templateCategory', button.dataset.category); value('templateContent', button.dataset.content); checked('templateActive', button.dataset.active === '1'); document.getElementById('templateTitle')?.focus(); }));

    function clearPriorityForm() { value('priorityId'); value('priorityName'); value('priorityCode'); value('prioritySla', '8'); value('priorityColor', '#ff7a00'); value('priorityOrder', '1'); checked('priorityActive', true); }
    document.getElementById('clearPriorityForm')?.addEventListener('click', clearPriorityForm);
    document.querySelectorAll('[data-edit-priority]').forEach(button => button.addEventListener('click', function () { activateTool('priorities'); value('priorityId', button.dataset.id); value('priorityName', button.dataset.name); value('priorityCode', button.dataset.code); value('prioritySla', button.dataset.sla); value('priorityColor', button.dataset.color || '#ff7a00'); value('priorityOrder', button.dataset.order || '1'); checked('priorityActive', button.dataset.active === '1'); document.getElementById('priorityName')?.focus(); }));

    function clearClosureForm() { value('closureId'); value('closureName'); value('closureCode'); value('closureDescription'); checked('closureRequires', false); checked('closureActive', true); }
    document.getElementById('clearClosureForm')?.addEventListener('click', clearClosureForm);
    document.querySelectorAll('[data-edit-closure]').forEach(button => button.addEventListener('click', function () { activateTool('closure'); value('closureId', button.dataset.id); value('closureName', button.dataset.name); value('closureCode', button.dataset.code); value('closureDescription', button.dataset.description); checked('closureRequires', button.dataset.requires === '1'); checked('closureActive', button.dataset.active === '1'); document.getElementById('closureName')?.focus(); }));

    const knowledgeForm = document.getElementById('knowledgeForm');
    const knowledgeEditor = document.getElementById('knowledgeEditor');
    const knowledgeContentHtml = document.getElementById('knowledgeContentHtml');
    const knowledgeAttachments = document.getElementById('knowledgeAttachments');
    const knowledgeSelectedFiles = document.getElementById('knowledgeSelectedFiles');
    const knowledgeExistingFiles = document.getElementById('knowledgeExistingFiles');
    const knowledgeExistingFilesList = document.getElementById('knowledgeExistingFilesList');

    function syncKnowledgeEditor() {
        if (knowledgeEditor && knowledgeContentHtml) {
            knowledgeContentHtml.value = knowledgeEditor.innerHTML.trim();
        }
    }

    function runEditorCommand(command, value = null) {
        if (!knowledgeEditor) return;
        knowledgeEditor.focus();
        document.execCommand(command, false, value);
        syncKnowledgeEditor();
    }

    document.querySelectorAll('[data-editor-command]').forEach(button => {
        button.addEventListener('click', function () {
            runEditorCommand(button.dataset.editorCommand);
        });
    });

    document.getElementById('knowledgeFormatBlock')?.addEventListener('change', function () {
        if (this.value) runEditorCommand('formatBlock', this.value);
    });

    document.getElementById('knowledgeFontSize')?.addEventListener('change', function () {
        if (this.value) runEditorCommand('fontSize', this.value);
        this.value = '';
    });

    document.getElementById('knowledgeTextColor')?.addEventListener('input', function () {
        runEditorCommand('foreColor', this.value);
    });

    document.getElementById('knowledgeHighlightColor')?.addEventListener('input', function () {
        if (!knowledgeEditor) return;
        knowledgeEditor.focus();
        const supported = document.queryCommandSupported && document.queryCommandSupported('hiliteColor');
        document.execCommand(supported ? 'hiliteColor' : 'backColor', false, this.value);
        syncKnowledgeEditor();
    });

    document.getElementById('knowledgeLinkBtn')?.addEventListener('click', function () {
        const url = window.prompt('Escribe la dirección del enlace:', 'https://');
        if (!url) return;
        runEditorCommand('createLink', url.trim());
    });

    knowledgeEditor?.addEventListener('input', syncKnowledgeEditor);
    knowledgeEditor?.addEventListener('blur', syncKnowledgeEditor);

    function formatClientFileSize(bytes) {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`;
        return `${(bytes / 1048576).toFixed(1)} MB`;
    }

    function renderSelectedKnowledgeFiles() {
        if (!knowledgeAttachments || !knowledgeSelectedFiles) return;
        const files = Array.from(knowledgeAttachments.files || []);

        if (!files.length) {
            knowledgeSelectedFiles.hidden = true;
            knowledgeSelectedFiles.innerHTML = '';
            return;
        }

        knowledgeSelectedFiles.hidden = false;
        knowledgeSelectedFiles.innerHTML = files.map(file => `
            <div class="knowledge-file-row">
                <span class="knowledge-file-kind">Nuevo</span>
                <span><strong>${escapeHtml(file.name)}</strong><small>${formatClientFileSize(file.size)}</small></span>
            </div>
        `).join('');
    }

    knowledgeAttachments?.addEventListener('change', renderSelectedKnowledgeFiles);

    function renderExistingKnowledgeFiles(attachments = []) {
        if (!knowledgeExistingFiles || !knowledgeExistingFilesList) return;

        if (!attachments.length) {
            knowledgeExistingFiles.hidden = true;
            knowledgeExistingFilesList.innerHTML = '';
            return;
        }

        knowledgeExistingFiles.hidden = false;
        knowledgeExistingFilesList.innerHTML = attachments.map(attachment => `
            <label class="knowledge-file-row knowledge-existing-file-row">
                <span class="knowledge-file-kind">${attachment.is_image == 1 ? 'Imagen' : 'Archivo'}</span>
                <span>
                    <a href="${escapeHtml(attachment.view_url || '#')}" target="_blank" rel="noopener">${escapeHtml(attachment.original_name || 'Archivo')}</a>
                    <small>${escapeHtml(attachment.formatted_size || '')}</small>
                </span>
                <span class="knowledge-delete-file">
                    <input type="checkbox" name="delete_attachment_ids[]" value="${Number(attachment.id || 0)}">
                    Quitar
                </span>
            </label>
        `).join('');
    }

    function clearKnowledgeForm() {
        value('knowledgeId');
        value('knowledgeTitle');
        value('knowledgeCategory');
        value('knowledgeProblem');
        value('knowledgeKeywords');
        checked('knowledgeActive', true);

        if (knowledgeEditor) knowledgeEditor.innerHTML = '';
        if (knowledgeContentHtml) knowledgeContentHtml.value = '';
        if (knowledgeAttachments) knowledgeAttachments.value = '';
        renderSelectedKnowledgeFiles();
        renderExistingKnowledgeFiles([]);
    }

    document.getElementById('clearKnowledgeForm')?.addEventListener('click', clearKnowledgeForm);

    document.querySelectorAll('[data-edit-knowledge]').forEach(button => button.addEventListener('click', function () {
        const article = knowledgeRecords.find(item => Number(item.id) === Number(button.dataset.id));
        if (!article) return;

        activateTool('knowledge');
        value('knowledgeId', article.id || '');
        value('knowledgeTitle', article.title || '');
        value('knowledgeCategory', article.category_id || '');
        value('knowledgeProblem', article.problem_summary || '');
        value('knowledgeKeywords', article.keywords || '');
        checked('knowledgeActive', Number(article.is_active || 0) === 1);

        if (knowledgeEditor) knowledgeEditor.innerHTML = article.content_html || '';
        syncKnowledgeEditor();

        if (knowledgeAttachments) knowledgeAttachments.value = '';
        renderSelectedKnowledgeFiles();
        renderExistingKnowledgeFiles(Array.isArray(article.attachments) ? article.attachments : []);

        document.getElementById('knowledgeTitle')?.focus();
        knowledgeForm?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }));

    knowledgeForm?.addEventListener('submit', function (event) {
        syncKnowledgeEditor();
        const plainText = knowledgeEditor ? knowledgeEditor.innerText.trim() : '';

        if (!plainText) {
            event.preventDefault();
            alert('Escribe el contenido del artículo antes de guardarlo.');
            knowledgeEditor?.focus();
        }
    });

    function clearRuleForm() { value('ruleId'); value('ruleName'); value('ruleCategory'); value('rulePriority'); value('ruleLevel', '1'); value('ruleTechnician'); value('ruleDescription'); checked('ruleActive', true); }
    document.getElementById('clearRuleForm')?.addEventListener('click', clearRuleForm);
    document.querySelectorAll('[data-edit-rule]').forEach(button => button.addEventListener('click', function () { activateTool('rules'); value('ruleId', button.dataset.id); value('ruleName', button.dataset.name); value('ruleCategory', button.dataset.category); value('rulePriority', button.dataset.priority); value('ruleLevel', button.dataset.level || '1'); value('ruleTechnician', button.dataset.technician); value('ruleDescription', button.dataset.description); checked('ruleActive', button.dataset.active === '1'); document.getElementById('ruleName')?.focus(); }));

    document.querySelectorAll('[data-copy-template]').forEach(button => button.addEventListener('click', async function () {
        const target = document.getElementById(button.dataset.copyTemplate);
        const text = target ? target.innerText.trim() : '';
        if (!text) return;
        try { await navigator.clipboard.writeText(text); const old = button.textContent; button.textContent = 'Copiado'; setTimeout(() => button.textContent = old, 1200); } catch (error) { alert('No se pudo copiar automáticamente. Selecciona el texto manualmente.'); }
    }));

    function findCategory(code) { return categories.find(item => String(item.code || '').toUpperCase() === code) || { code, name: code.charAt(0) + code.slice(1).toLowerCase() }; }
    function findPriority(code) { return priorities.find(item => String(item.code || '').toUpperCase() === code) || { code, name: code, sla_hours: 8 }; }
    function findTemplate(code) { return templates.find(item => String(item.category_code || '').toUpperCase() === code) || templates.find(item => !item.category_id) || null; }
    function findRule(categoryCode, priorityCode) {
        return rules.find(item => String(item.category_code || '').toUpperCase() === categoryCode && String(item.priority_code || '').toUpperCase() === priorityCode)
            || rules.find(item => String(item.category_code || '').toUpperCase() === categoryCode)
            || rules.find(item => String(item.priority_code || '').toUpperCase() === priorityCode)
            || null;
    }
    function findArticle(categoryCode, symptoms) {
        const symptomText = symptoms.join(' ');
        return articles.find(item => String(item.category_code || '').toUpperCase() === categoryCode)
            || articles.find(item => String(item.keywords || '').toLowerCase().split(',').some(keyword => symptomText.includes(keyword.trim().toLowerCase())))
            || null;
    }

    document.getElementById('diagnosticBtn')?.addEventListener('click', function () {
        const selected = Array.from(document.querySelectorAll('.tools-diagnostic-grid input:checked')).map(input => input.value);
        const result = document.getElementById('diagnosticResult');
        if (!selected.length) { result.innerHTML = '<span>Resultado</span><strong>Selecciona uno o más síntomas.</strong><p>El diagnóstico usará las reglas, categorías, prioridades y base de conocimiento activas.</p>'; return; }
        let categoryCode = 'OTROS'; let priorityCode = 'MEDIA'; let reason = 'Revisar el caso y validar el comportamiento reportado.';
        if (selected.includes('sin_internet') || selected.includes('lentitud')) { categoryCode = 'RED'; priorityCode = selected.includes('sin_internet') ? 'ALTA' : 'MEDIA'; reason = 'Validar conectividad, punto de red, IP/DNS y estabilidad del servicio.'; }
        else if (selected.includes('credenciales') || selected.includes('permisos')) { categoryCode = 'ACCESO'; priorityCode = 'MEDIA'; reason = 'Revisar credenciales, permisos y estado de la cuenta del usuario.'; }
        else if (selected.includes('equipo') || selected.includes('impresora')) { categoryCode = 'HARDWARE'; priorityCode = selected.includes('equipo') ? 'MEDIA' : 'BAJA'; reason = 'Validar estado físico, conexión, periféricos y pruebas básicas.'; }
        else if (selected.includes('sistema')) { categoryCode = 'SISTEMA'; priorityCode = 'ALTA'; reason = 'Revisar disponibilidad del sistema, errores recientes y alcance del impacto.'; }
        else if (selected.includes('software')) { categoryCode = 'SOFTWARE'; priorityCode = 'MEDIA'; reason = 'Validar instalación, configuración y comportamiento de la aplicación.'; }
        const category = findCategory(categoryCode); const priority = findPriority(priorityCode); const rule = findRule(categoryCode, priorityCode); const template = findTemplate(categoryCode); const article = findArticle(categoryCode, selected);
        const level = rule ? `Nivel ${rule.support_level || 1}` : (priorityCode === 'ALTA' ? 'Nivel 2' : 'Nivel 1');
        const technician = rule && rule.technician_name ? rule.technician_name : 'Según disponibilidad';
        const templateText = template ? template.content : 'No hay plantilla activa asociada.';
        const articleText = article ? `${article.title}: ${article.problem_summary}` : 'No se encontró artículo relacionado.';
        result.innerHTML = `<span>Resultado sugerido</span><strong>${category.name || categoryCode} · ${priority.name || priorityCode} · ${level}</strong><p>${reason}</p><div class="tools-suggested-template"><small>Regla de asignación</small><p>${rule ? rule.name + ' · Técnico: ' + technician : 'Sin regla específica. Usar criterio de carga técnica.'}</p></div><div class="tools-suggested-template"><small>Plantilla sugerida</small><p>${templateText}</p></div><div class="tools-suggested-template"><small>Base de conocimiento</small><p>${articleText}</p></div>`;
    });
    document.getElementById('clearDiagnosticBtn')?.addEventListener('click', function () { document.querySelectorAll('.tools-diagnostic-grid input').forEach(input => input.checked = false); document.getElementById('diagnosticResult').innerHTML = '<span>Resultado</span><strong>Selecciona uno o más síntomas.</strong><p>El diagnóstico usará las reglas, categorías, prioridades y base de conocimiento activas.</p>'; });

    document.querySelectorAll('[data-monitor-filter]').forEach(button => button.addEventListener('click', function () { const filter = button.dataset.monitorFilter; document.querySelectorAll('[data-monitor-filter]').forEach(btn => btn.classList.remove('active')); button.classList.add('active'); document.querySelectorAll('.tools-monitor-item').forEach(item => item.style.display = filter === 'all' || item.dataset.monitorType === filter ? '' : 'none'); }));
});
</script>

<?php require_once __DIR__ . '/../layouts/admin-footer.php'; ?>
