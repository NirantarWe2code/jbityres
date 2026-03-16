<?php
/**
 * Tyre Analytics AJAX Handler
 * Data for Tyre Dashboard (TyreDashboard.jsx style + formulas.html calculations)
 */
@ini_set('display_errors', '0'); // Prevent PHP notices/warnings from polluting JSON response
ob_start(); // Capture any stray output (PHP notices, etc.)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/SalesData.php';

requirePermission('dashboard.view');

$action = $_GET['action'] ?? $_POST['action'] ?? 'analytics';

if ($action !== 'analytics') {
    ob_end_clean();
    jsonResponse(false, 'Invalid action');
}

$filters = [];
if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];
if (empty($filters['date_from']) && empty($filters['date_to'])) {
    $filters['date_from'] = date('Y-m-d', strtotime('-12 months'));
    $filters['date_to'] = date('Y-m-d');
}

try {
    $salesData = new SalesData();
    $result = $salesData->getTyreAnalytics($filters);

    ob_end_clean(); // Discard any PHP notices/warnings (from getTyreAnalytics) before JSON
    header('Content-Type: application/json; charset=utf-8');

    if ($result['success']) {
        $data = $result['data'];
        // Add frontend aliases (tyre-dashboard.js expects by_day, by_hour, byArea)
        if (is_array($data)) {
            $data['by_day'] = $data['byDayOfWeek'] ?? [];
            $data['by_hour'] = $data['byHour'] ?? [];
            $data['byArea'] = $data['areas'] ?? [];
        }
        echo json_encode(['success' => true, 'message' => 'Analytics retrieved', 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Failed to fetch analytics', 'data' => null]);
    }
} catch (Throwable $e) {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Server error: ' . (defined('DEBUG_MODE') && DEBUG_MODE ? $e->getMessage() : 'Please check server logs'),
        'data' => null
    ]);
}
exit;
