<?php
/**
 * Dashboard AJAX Handler
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/FinalReportData.php';

// Ensure user is logged in and has permission
requirePermission('dashboard.view');

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, 'Invalid security token');
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$dataSource = new FinalReportData();

try {
    switch ($action) {
        case 'stats':
            handleGetStats($dataSource);
            break;
            
        case 'filter_options':
            handleGetFilterOptions($dataSource);
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    error_log('Dashboard AJAX error: ' . $e->getMessage());
    jsonResponse(false, 'An error occurred while processing your request');
}

/**
 * Get dashboard statistics from final_salesreportdata
 */
function handleGetStats($dataSource) {
    $filters = [];
    
    // Get date filters
    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }
    
    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }
    
    // Default to last 30 days if no date filter
    if (empty($filters['date_from']) && empty($filters['date_to'])) {
        $filters['date_from'] = date('Y-m-d', strtotime('-30 days'));
        $filters['date_to'] = date('Y-m-d');
    }
    
    $result = $dataSource->getDashboardStats($filters);
    
    if ($result['success']) {
        jsonResponse(true, 'Dashboard statistics retrieved successfully', $result['data']);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Get filter options from final_salesreportdata
 */
function handleGetFilterOptions($dataSource) {
    $result = $dataSource->getFilterOptions();
    
    if ($result['success']) {
        jsonResponse(true, 'Filter options retrieved successfully', $result['data']);
    } else {
        jsonResponse(false, $result['message']);
    }
}
?>