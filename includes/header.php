<?php
/**
 * Common Header Include
 */

if (!defined('BASE_URL')) {
    require_once __DIR__ . '/../config/config.php';
}

requireAuth(); // Ensure user is logged in

$currentUser = getCurrentUser();
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <!-- Custom CSS -->
    <link href="<?php echo BASE_URL; ?>/assets/css/style.css" rel="stylesheet">
    
    <style>
        :root {
            --bg-color: <?php echo DASHBOARD_COLORS['bg']; ?>;
            --surface-color: <?php echo DASHBOARD_COLORS['surface']; ?>;
            --card-color: <?php echo DASHBOARD_COLORS['card']; ?>;
            --border-color: <?php echo DASHBOARD_COLORS['border']; ?>;
            --text-color: <?php echo DASHBOARD_COLORS['text']; ?>;
            --muted-color: <?php echo DASHBOARD_COLORS['muted']; ?>;
            --teal-color: <?php echo DASHBOARD_COLORS['teal']; ?>;
            --gold-color: <?php echo DASHBOARD_COLORS['gold']; ?>;
            --rose-color: <?php echo DASHBOARD_COLORS['rose']; ?>;
            --blue-color: <?php echo DASHBOARD_COLORS['blue']; ?>;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
        }
        
        .main-sidebar {
            background-color: var(--surface-color);
        }
        
        .content-wrapper {
            background-color: var(--bg-color);
        }
        
        .card {
            background-color: var(--card-color);
            border: 1px solid var(--border-color);
            color: var(--text-color);
        }
        
        .card-header {
            background-color: var(--surface-color);
            border-bottom: 1px solid var(--border-color);
        }
        
        .navbar-dark {
            background-color: var(--surface-color) !important;
        }
        
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link {
            color: var(--muted-color);
        }
        
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link:hover {
            background-color: var(--card-color);
            color: var(--text-color);
        }
        
        .sidebar-dark-primary .nav-sidebar > .nav-item > .nav-link.active {
            background-color: var(--teal-color);
            color: white;
        }
        
        .table-dark {
            background-color: var(--card-color);
            color: var(--text-color);
        }
        
        .table-dark th,
        .table-dark td {
            border-color: var(--border-color);
        }
        
        .btn-primary {
            background-color: var(--teal-color);
            border-color: var(--teal-color);
        }
        
        .btn-primary:hover {
            background-color: #0f9488;
            border-color: #0f9488;
        }
    </style>
    
    <script>
        // Global configuration
        window.AppConfig = {
            baseUrl: '<?php echo BASE_URL; ?>',
            csrfToken: '<?php echo generateCSRFToken(); ?>',
            user: <?php echo json_encode($currentUser); ?>
        };
    </script>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
    <div class="wrapper">
        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-dark">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button">
                        <i class="fas fa-bars"></i>
                    </a>
                </li>
            </ul>
            
            <!-- Right navbar links -->
            <ul class="navbar-nav ml-auto">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-2"></i>
                        <?php echo htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/setup_2fa.php"><i class="fas fa-shield-alt me-2"></i>Two-Factor Auth</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>/logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>Logout
                        </a></li>
                    </ul>
                </li>
            </ul>
        </nav>
        
        <!-- Main Sidebar Container -->
        <aside class="main-sidebar sidebar-dark-primary elevation-4">
            <!-- Brand Logo -->
            <a href="<?php echo BASE_URL; ?>/pages/dashboard/index.php" class="brand-link">
                <i class="fas fa-chart-line brand-image"></i>
                <span class="brand-text font-weight-light"><?php echo APP_NAME; ?></span>
            </a>
            
            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar user panel -->
                <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                    <div class="image">
                        <i class="fas fa-user-circle fa-2x text-muted"></i>
                    </div>
                    <div class="info">
                        <a href="#" class="d-block"><?php echo htmlspecialchars($currentUser['full_name'] ?? $currentUser['username']); ?></a>
                        <small class="text-muted"><?php echo ucfirst($currentUser['role']); ?></small>
                    </div>
                </div>
                
                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>/pages/dashboard/index.php" 
                               class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/') !== false && strpos($_SERVER['REQUEST_URI'], '/jbi-dashboard/') === false) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Dashboard</p>
                            </a>
                        </li>
                        
                        <?php if (hasPermission('dashboard.view')): ?>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>/pages/jbi-dashboard/index.php" 
                               class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/jbi-dashboard/') !== false) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-chart-line"></i>
                                <p>JBI Dashboard</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('sales.view')): ?>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>/pages/sales/index.php" 
                               class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/sales/') !== false) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-chart-bar"></i>
                                <p>Sales Reports</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>/pages/tyre-dashboard/index.php" 
                               class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/tyre-dashboard/') !== false) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-tachometer-alt"></i>
                                <p>Tyre Analytics</p>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('users.view')): ?>
                        <li class="nav-item">
                            <a href="<?php echo BASE_URL; ?>/pages/users/index.php" 
                               class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/users/') !== false) ? 'active' : ''; ?>">
                                <i class="nav-icon fas fa-users"></i>
                                <p>User Management</p>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        </aside>
        
        <!-- Content Wrapper -->
        <div class="content-wrapper">
            <!-- Content Header -->
            <div class="content-header">
                <div class="container-fluid">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <h1 class="m-0"><?php echo htmlspecialchars($pageTitle); ?></h1>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-right">
                                <?php if (isset($breadcrumbs) && is_array($breadcrumbs)): ?>
                                    <?php foreach ($breadcrumbs as $crumb): ?>
                                        <?php if (isset($crumb['url'])): ?>
                                            <li class="breadcrumb-item">
                                                <a href="<?php echo $crumb['url']; ?>"><?php echo htmlspecialchars($crumb['title']); ?></a>
                                            </li>
                                        <?php else: ?>
                                            <li class="breadcrumb-item active"><?php echo htmlspecialchars($crumb['title']); ?></li>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <li class="breadcrumb-item"><a href="<?php echo BASE_URL; ?>/pages/dashboard/index.php">Home</a></li>
                                    <li class="breadcrumb-item active"><?php echo htmlspecialchars($pageTitle); ?></li>
                                <?php endif; ?>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">