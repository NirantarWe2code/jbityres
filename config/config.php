<?php
/**
 * Final Report System Configuration
 * Simple AJAX-based system without API
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sales');

// Application Configuration
define('APP_NAME', 'Final Report System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/finalReport');

// Security Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour
define('CSRF_TOKEN_NAME', 'csrf_token');

// Pagination Configuration
define('RECORDS_PER_PAGE', 25);
define('MAX_RECORDS_PER_PAGE', 100);

// User Roles
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_ADMIN', 'admin');
define('ROLE_USER', 'user');

// Dashboard Colors (same as original system)
define('DASHBOARD_COLORS', [
    'bg' => '#1a1a1a',
    'surface' => '#2d2d2d',
    'card' => '#363636',
    'border' => '#404040',
    'teal' => '#14b8a6',
    'teal_dim' => 'rgba(20, 184, 166, 0.1)',
    'gold' => '#f59e0b',
    'rose' => '#f43f5e',
    'blue' => '#3b82f6',
    'purple' => '#8b5cf6',
    'green' => '#10b981',
    'text' => '#f8fafc',
    'muted' => '#94a3b8',
    'dim' => '#64748b'
]);

// Debug Mode (set to false in production)
define('DEBUG_MODE', true);

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Australia/Sydney');

/**
 * Generate CSRF Token
 */
function generateCSRFToken()
{
    if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Verify CSRF Token
 */
function verifyCSRFToken($token)
{
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

/**
 * Get Base URL
 */
function getBaseUrl()
{
    return BASE_URL;
}

/**
 * Check if user is logged in
 */
function isLoggedIn()
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user
 */
function getCurrentUser()
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['full_name'] ?? '',
        'role' => $_SESSION['role'] ?? ROLE_USER,
        'email' => $_SESSION['email'] ?? ''
    ];
}

/**
 * Check if user has permission
 */
function hasPermission($permission)
{
    $user = getCurrentUser();
    if (!$user)
        return false;

    // Super admin has all permissions
    if ($user['role'] === ROLE_SUPER_ADMIN)
        return true;

    // Basic permission mapping
    $rolePermissions = [
        ROLE_ADMIN => [
            'dashboard.view',
            'sales.view',
            'sales.create',
            'sales.edit',
            'sales.delete',
            'users.view',
            'users.create',
            'users.edit'
        ],
        ROLE_USER => [
            'dashboard.view',
            'sales.view'
        ]
    ];

    return in_array($permission, $rolePermissions[$user['role']] ?? []);
}

/**
 * Redirect to login if not authenticated
 */
function requireAuth()
{
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/**
 * Redirect to login if no permission
 */
function requirePermission($permission)
{
    requireAuth();
    if (!hasPermission($permission)) {
        header('HTTP/1.1 403 Forbidden');
        die('Access denied');
    }
}

/**
 * JSON Response Helper
 */
function jsonResponse($success, $message = '', $data = null, $meta = [])
{
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ];
    
    // Add meta data if provided
    if (!empty($meta)) {
        $response = array_merge($response, $meta);
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

/**
 * Get client IP (proxy/Cloudflare/localhost - fetches public IP when on localhost)
 */
function getClientIp() {
    $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    $localFallback = '';
    foreach ($keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = trim((string)$_SERVER[$key]);
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
                    return $ip;
                }
                $localFallback = $ip;
            }
        }
    }
    if ($localFallback !== '' || empty($_SERVER['REMOTE_ADDR'])) {
        $ctx = stream_context_create(['http' => ['timeout' => 3]]);
        $public = @file_get_contents('https://api.ipify.org?format=text', false, $ctx);
        if ($public && filter_var(trim($public), FILTER_VALIDATE_IP)) {
            return trim($public);
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? $localFallback ?: '0.0.0.0';
}

/**
 * Sanitize input
 */
function sanitize($input)
{
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Format currency
 */
function formatCurrency($amount)
{
    return 'A$' . number_format((float) $amount, 2);
}

/**
 * Format percentage
 */
function formatPercentage($value)
{
    return number_format((float) $value, 1) . '%';
}

/**
 * Get margin class for styling
 */
function getMarginClass($margin)
{
    $m = (float) $margin;
    if ($m >= 30)
        return 'success';
    if ($m >= 20)
        return 'warning';
    return 'danger';
}
?>