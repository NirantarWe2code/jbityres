<?php
/**
 * Sales Data AJAX Handler
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/SalesData.php';

// Ensure user is logged in and has permission
requirePermission('sales.view');

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, 'Invalid security token');
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$salesData = new SalesData();

try {
    switch ($action) {
        case 'list':
            handleGetList($salesData);
            break;
            
        case 'get':
            handleGetRecord($salesData);
            break;
            
        case 'create':
            requirePermission('sales.create');
            handleCreateRecord($salesData);
            break;
            
        case 'update':
            requirePermission('sales.edit');
            handleUpdateRecord($salesData);
            break;
            
        case 'delete':
            requirePermission('sales.delete');
            handleDeleteRecord($salesData);
            break;
            
        case 'filter_options':
            handleGetFilterOptions($salesData);
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    error_log('Sales AJAX error: ' . $e->getMessage());
    jsonResponse(false, 'An error occurred while processing your request');
}

/**
 * Get sales records list with pagination and filtering
 */
function handleGetList($salesData) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);
    $limit = min($limit, MAX_RECORDS_PER_PAGE);
    
    $filters = [];
    
    // Search filter
    if (!empty($_GET['search'])) {
        $filters['search'] = sanitize($_GET['search']);
    }
    
    // Date filters
    if (!empty($_GET['date_from'])) {
        $filters['date_from'] = $_GET['date_from'];
    }
    
    if (!empty($_GET['date_to'])) {
        $filters['date_to'] = $_GET['date_to'];
    }
    
    // Business filter
    if (!empty($_GET['business_name'])) {
        $filters['business_name'] = sanitize($_GET['business_name']);
    }
    
    // Sales rep filter
    if (!empty($_GET['sales_rep'])) {
        $filters['sales_rep'] = sanitize($_GET['sales_rep']);
    }
    
    // Product filter
    if (!empty($_GET['product'])) {
        $filters['product'] = sanitize($_GET['product']);
    }
    
    // Sorting
    if (!empty($_GET['sort_column'])) {
        $filters['sort_column'] = sanitize($_GET['sort_column']);
    }
    
    if (!empty($_GET['sort_direction'])) {
        $filters['sort_direction'] = sanitize($_GET['sort_direction']);
    }
    
    $result = $salesData->getAllRecords($page, $limit, $filters);
    
    if ($result['success']) {
        jsonResponse(true, 'Sales records retrieved successfully', $result['data'], [
            'pagination' => $result['pagination']
        ]);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Get single sales record
 */
function handleGetRecord($salesData) {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(false, 'Invalid record ID');
    }
    
    $result = $salesData->getRecordById($id);
    
    if ($result['success']) {
        jsonResponse(true, 'Sales record retrieved successfully', $result['data']);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Create new sales record
 */
function handleCreateRecord($salesData) {
    $data = [
        'invoice_num' => sanitize($_POST['invoice_num'] ?? ''),
        'dated' => $_POST['dated'] ?? '',
        'business_name' => sanitize($_POST['business_name'] ?? ''),
        'sales_rep' => sanitize($_POST['sales_rep'] ?? ''),
        'product' => sanitize($_POST['product'] ?? ''),
        'quantity' => (float)($_POST['quantity'] ?? 0),
        'unit_price' => (float)($_POST['unit_price'] ?? 0),
        'cost_price' => (float)($_POST['cost_price'] ?? 0),
        'notes' => sanitize($_POST['notes'] ?? '')
    ];
    
    $result = $salesData->createRecord($data);
    
    if ($result['success']) {
        jsonResponse(true, $result['message'], ['record_id' => $result['record_id']]);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Update sales record
 */
function handleUpdateRecord($salesData) {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(false, 'Invalid record ID');
    }
    
    $data = [
        'invoice_num' => sanitize($_POST['invoice_num'] ?? ''),
        'dated' => $_POST['dated'] ?? '',
        'business_name' => sanitize($_POST['business_name'] ?? ''),
        'sales_rep' => sanitize($_POST['sales_rep'] ?? ''),
        'product' => sanitize($_POST['product'] ?? ''),
        'quantity' => (float)($_POST['quantity'] ?? 0),
        'unit_price' => (float)($_POST['unit_price'] ?? 0),
        'cost_price' => (float)($_POST['cost_price'] ?? 0),
        'notes' => sanitize($_POST['notes'] ?? '')
    ];
    
    $result = $salesData->updateRecord($id, $data);
    
    if ($result['success']) {
        jsonResponse(true, $result['message']);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Delete sales record
 */
function handleDeleteRecord($salesData) {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(false, 'Invalid record ID');
    }
    
    $result = $salesData->deleteRecord($id);
    
    if ($result['success']) {
        jsonResponse(true, $result['message']);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Get filter options
 */
function handleGetFilterOptions($salesData) {
    $result = $salesData->getFilterOptions();
    
    if ($result['success']) {
        jsonResponse(true, 'Filter options retrieved successfully', $result['data']);
    } else {
        jsonResponse(false, $result['message']);
    }
}
?>