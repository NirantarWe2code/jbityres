<?php
/**
 * Sales Data AJAX Handler (sales_data via SalesData)
 */

@ini_set('display_errors', '0');
@ini_set('html_errors', '0');

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
$dataSource = new SalesData();

try {
    switch ($action) {
        case 'list':
            handleGetList($dataSource);
            break;
            
        case 'get':
            handleGetRecord($dataSource);
            break;
            
        case 'create':
            requirePermission('sales.create');
            handleCreateRecord($dataSource);
            break;
            
        case 'update':
            requirePermission('sales.edit');
            handleUpdateRecord($dataSource);
            break;
            
        case 'delete':
            requirePermission('sales.delete');
            handleDeleteRecord($dataSource);
            break;
            
        case 'filter_options':
            handleGetFilterOptions($dataSource);
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Throwable $e) {
    error_log('Sales AJAX error: ' . $e->getMessage());
    jsonResponse(false, 'An error occurred while processing your request');
}

/**
 * Get sales records list with pagination and filtering
 */
function handleGetList($dataSource) {
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
    
    $result = $dataSource->getAllRecords($page, $limit, $filters);
    
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
function handleGetRecord($dataSource) {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(false, 'Invalid record ID');
    }
    
    $result = $dataSource->getRecordById($id);
    
    if ($result['success']) {
        jsonResponse(true, 'Sales record retrieved successfully', $result['data']);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Create new sales record
 */
function handleCreateRecord($dataSource) {
    $data = [
        'Dated' => $_POST['dated'] ?? '',
        'Business_Name' => sanitize($_POST['business_name'] ?? ''),
        'Sales_Rep' => sanitize($_POST['sales_rep'] ?? ''),
        'Invoice_Num' => sanitize($_POST['invoice_num'] ?? ''),
        'product' => sanitize($_POST['product'] ?? ''),
        'Delivery_Profile' => sanitize($_POST['delivery_profile'] ?? ''),
        'Quantity' => (float)($_POST['quantity'] ?? 0),
        'Unit_Price' => (float)($_POST['unit_price'] ?? 0),
        'Purchase_Price' => (float)($_POST['purchase_price'] ?? 0)
    ];
    
    $result = $dataSource->createRecord($data);
    
    if ($result['success']) {
        jsonResponse(true, $result['message'], ['record_id' => $result['id']]);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Update sales record
 */
function handleUpdateRecord($dataSource) {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(false, 'Invalid record ID');
    }
    
    // Send both lowercase and legacy-cased keys so update works with either schema.
    $businessName = sanitize($_POST['business_name'] ?? '');
    $salesRep = sanitize($_POST['sales_rep'] ?? '');
    $invoiceNum = sanitize($_POST['invoice_num'] ?? '');
    $dated = $_POST['dated'] ?? '';
    $product = sanitize($_POST['product'] ?? '');
    $deliveryProfile = sanitize($_POST['delivery_profile'] ?? '');
    $quantity = (float)($_POST['quantity'] ?? 0);
    $unitPrice = (float)($_POST['unit_price'] ?? 0);
    $purchasePrice = (float)($_POST['purchase_price'] ?? 0);
    $totalAmount = (float)($_POST['total_amount'] ?? 0);
    if ($totalAmount <= 0 && $quantity > 0 && $unitPrice > 0) {
        $totalAmount = round($quantity * $unitPrice, 2);
    }
    $poNumber = sanitize($_POST['po_number'] ?? '');
    $rewardInclusive = sanitize($_POST['reward_inclusive'] ?? '');
    $deliveryRoutes = sanitize($_POST['delivery_routes'] ?? '');

    $data = [
        // Canonical keys
        'dated' => $dated,
        'business_name' => $businessName,
        'sales_rep' => $salesRep,
        'invoice_num' => $invoiceNum,
        'product' => $product,
        'delivery_profile' => $deliveryProfile,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'purchase_price' => $purchasePrice,
        'total_amount' => $totalAmount,
        'po_number' => $poNumber,
        'reward_inclusive' => $rewardInclusive,
        'delivery_routes' => $deliveryRoutes,

        // Legacy-cased keys
        'Dated' => $dated,
        'Business_Name' => $businessName,
        'Sales_Rep' => $salesRep,
        'Invoice_Num' => $invoiceNum,
        'Delivery_Profile' => $deliveryProfile,
        'Quantity' => $quantity,
        'Unit_Price' => $unitPrice,
        'Purchase_Price' => $purchasePrice,
        'Total_Amount' => $totalAmount,
        'PONumber' => $poNumber,
        'Reward_inclusive' => $rewardInclusive,
        'Delivery_Routes' => $deliveryRoutes
    ];
    
    $result = $dataSource->updateRecord($id, $data);
    
    if ($result['success']) {
        jsonResponse(true, $result['message']);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Delete sales record
 */
function handleDeleteRecord($dataSource) {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(false, 'Invalid record ID');
    }
    
    $result = $dataSource->deleteRecord($id);
    
    if ($result['success']) {
        jsonResponse(true, $result['message']);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Get filter options
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