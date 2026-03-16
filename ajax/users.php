<?php
/**
 * Users AJAX Handler
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Auth.php';

// Ensure user is logged in and has permission
requirePermission('users.view');

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, 'Invalid security token');
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$auth = new Auth();

try {
    switch ($action) {
        case 'list':
            handleGetList($auth);
            break;
            
        case 'get':
            handleGetUser($auth);
            break;
            
        case 'create':
            requirePermission('users.create');
            handleCreateUser($auth);
            break;
            
        case 'update':
            requirePermission('users.edit');
            handleUpdateUser($auth);
            break;
            
        case 'delete':
            requirePermission('users.delete');
            handleDeleteUser($auth);
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
} catch (Exception $e) {
    error_log('Users AJAX error: ' . $e->getMessage());
    jsonResponse(false, 'An error occurred while processing your request');
}

/**
 * Get users list with pagination and filtering
 */
function handleGetList($auth) {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? RECORDS_PER_PAGE);
    $limit = min($limit, MAX_RECORDS_PER_PAGE);
    
    $filters = [];
    
    // Search filter
    if (!empty($_GET['search'])) {
        $filters['search'] = sanitize($_GET['search']);
    }
    
    // Role filter
    if (!empty($_GET['role'])) {
        $filters['role'] = sanitize($_GET['role']);
    }
    
    // Status filter
    if (!empty($_GET['status'])) {
        $filters['status'] = sanitize($_GET['status']);
    }
    
    $result = $auth->getAllUsers($page, $limit, $filters);
    
    if ($result['success']) {
        jsonResponse(true, 'Users retrieved successfully', $result['data'], [
            'pagination' => $result['pagination']
        ]);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Get single user
 */
function handleGetUser($auth) {
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(false, 'Invalid user ID');
    }
    
    $user = $auth->getUserById($id);
    
    if ($user) {
        // Remove password from response
        unset($user['password']);
        jsonResponse(true, 'User retrieved successfully', $user);
    } else {
        jsonResponse(false, 'User not found');
    }
}

/**
 * Create new user
 */
function handleCreateUser($auth) {
    $data = [
        'username' => sanitize($_POST['username'] ?? ''),
        'full_name' => sanitize($_POST['full_name'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'role' => sanitize($_POST['role'] ?? ROLE_USER)
    ];
    
    $result = $auth->register($data);
    
    if ($result['success']) {
        jsonResponse(true, $result['message'], ['user_id' => $result['user_id']]);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Update user
 */
function handleUpdateUser($auth) {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(false, 'Invalid user ID');
    }
    
    $data = [
        'username' => sanitize($_POST['username'] ?? ''),
        'full_name' => sanitize($_POST['full_name'] ?? ''),
        'email' => sanitize($_POST['email'] ?? ''),
        'role' => sanitize($_POST['role'] ?? ''),
        'status' => sanitize($_POST['status'] ?? '')
    ];
    
    // Only include password if provided
    if (!empty($_POST['password'])) {
        $data['password'] = $_POST['password'];
    }
    
    $result = $auth->updateUser($id, $data);
    
    if ($result['success']) {
        jsonResponse(true, $result['message']);
    } else {
        jsonResponse(false, $result['message']);
    }
}

/**
 * Delete user
 */
function handleDeleteUser($auth) {
    $id = (int)($_POST['id'] ?? 0);
    
    if ($id <= 0) {
        jsonResponse(false, 'Invalid user ID');
    }
    
    $result = $auth->deleteUser($id);
    
    if ($result['success']) {
        jsonResponse(true, $result['message']);
    } else {
        jsonResponse(false, $result['message']);
    }
}
?>