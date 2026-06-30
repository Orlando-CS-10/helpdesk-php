<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');
require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_tools.php';

if (!systemToolsTableExists($pdo, 'system_technical_logs')) {
    http_response_code(404);
    exit('La tabla de registros técnicos no está disponible.');
}

$filters = systemToolsTechnicalLogNormalizeFilters($_GET);
$logs = systemToolsTechnicalLogs($pdo, $filters, 5000, 0);

$csvSafe = static function (mixed $value): string {
    $text = (string) $value;
    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
        $text = "'" . $text;
    }
    return $text;
};

$fileName = 'registros_tecnicos_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('X-Content-Type-Options: nosniff');

echo "\xEF\xBB\xBF";
$output = fopen('php://output', 'wb');
fputcsv($output, ['ID', 'Nivel', 'Módulo', 'Mensaje', 'Usuario', 'Correo', 'IP', 'Fecha', 'Contexto'], ';');

foreach ($logs as $log) {
    $context = is_array($log['context'] ?? null) ? $log['context'] : [];
    $request = is_array($context['request'] ?? null) ? $context['request'] : [];
    fputcsv($output, [
        (int) ($log['id'] ?? 0),
        $csvSafe($log['level'] ?? ''),
        $csvSafe($log['module'] ?? ''),
        $csvSafe($log['message'] ?? ''),
        $csvSafe($log['user_name'] ?? 'Sistema'),
        $csvSafe($log['user_email'] ?? ''),
        $csvSafe($request['ip'] ?? ''),
        $csvSafe($log['created_at'] ?? ''),
        $csvSafe($context ? json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''),
    ], ';');
}

fclose($output);
exit;
