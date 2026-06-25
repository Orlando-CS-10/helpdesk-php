<?php
require_once __DIR__ . '/app/helpers/session.php';
requireRole('ADMIN');

require_once __DIR__ . '/app/config/database.php';
require_once __DIR__ . '/app/helpers/system_sla.php';

$systemSlaReady = systemSlaModuleReady($pdo);
$systemSlaProfiles = $systemSlaReady ? systemSlaProfiles($pdo) : [];
$requestedProfileId = isset($_GET['profile_id']) ? (int) $_GET['profile_id'] : null;
$systemSlaSelectedProfile = $systemSlaReady
    ? systemSlaGetProfile($pdo, $requestedProfileId)
    : systemSlaDefaultProfile();
$systemSlaRecentAudit = $systemSlaReady ? systemSlaRecentAudit($pdo, 5) : [];
$systemSlaCsrfToken = systemSlaCsrfToken();

$systemSlaSummary = [
    'profiles' => count($systemSlaProfiles),
    'active' => count(array_filter($systemSlaProfiles, static fn (array $profile): bool => !empty($profile['is_active']))),
    'companies' => array_sum(array_map(static fn (array $profile): int => (int) ($profile['companies_count'] ?? 0), $systemSlaProfiles)),
    'default_name' => '',
];

foreach ($systemSlaProfiles as $profile) {
    if (!empty($profile['is_default'])) {
        $systemSlaSummary['default_name'] = (string) ($profile['name'] ?? '');
        break;
    }
}

require __DIR__ . '/app/views/admin/system-sla.php';
