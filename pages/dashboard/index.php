<?php
/**
 * Dashboard Page
 */

require_once __DIR__ . '/../../config/config.php';

$pageTitle = 'Dashboard';
$breadcrumbs = [
    ['title' => 'Home', 'url' => BASE_URL . '/pages/dashboard/index.php'],
    ['title' => 'Dashboard']
];

$pageScripts = [
    BASE_URL . '/assets/js/dashboard.js?v=' . filemtime(__DIR__ . '/../../assets/js/dashboard.js')
];

include __DIR__ . '/../../includes/header.php';
?>

<div class="row">
    <!-- Date Filter -->
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter me-2"></i>Filters
                </h3>
            </div>
            <div class="card-body">
                <form id="dashboardFilters" class="row">
                    <div class="col-md-4">
                        <label for="dateFrom" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="dateFrom" name="date_from" 
                               value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="dateTo" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="dateTo" name="date_to" 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Apply Filters
                            </button>
                            <button type="button" class="btn btn-secondary" id="resetFilters">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row" id="statsCards">
    <div class="col-lg-3 col-6">
        <div class="card stats-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="stats-value" id="totalSales">-</h3>
                        <p class="stats-label">Total Sales</p>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-6">
        <div class="card stats-card" style="background: linear-gradient(135deg, var(--gold-color), #d97706);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="stats-value" id="totalRevenue">-</h3>
                        <p class="stats-label">Total Revenue</p>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-6">
        <div class="card stats-card" style="background: linear-gradient(135deg, var(--green-color), #059669);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="stats-value" id="totalProfit">-</h3>
                        <p class="stats-label">Total Profit</p>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-coins"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-6">
        <div class="card stats-card" style="background: linear-gradient(135deg, var(--purple-color), #7c3aed);">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h3 class="stats-value" id="avgMargin">-</h3>
                        <p class="stats-label">Avg Margin</p>
                    </div>
                    <div class="stats-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-area me-2"></i>Monthly Sales Trend
                </h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="monthlySalesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-chart-pie me-2"></i>Top Products
                </h3>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="topProductsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tables Row -->
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-trophy me-2"></i>Top Products by Revenue
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Revenue</th>
                                <th>Sales</th>
                            </tr>
                        </thead>
                        <tbody id="topProductsTable">
                            <tr>
                                <td colspan="3" class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-building me-2"></i>Top Businesses by Revenue
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Business</th>
                                <th>Revenue</th>
                                <th>Sales</th>
                            </tr>
                        </thead>
                        <tbody id="topBusinessesTable">
                            <tr>
                                <td colspan="3" class="text-center text-muted">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScripts = "
// Initialize dashboard when page loads
$(document).ready(function() {
    console.log('Dashboard page loaded');
    
    if (typeof Dashboard !== 'undefined') {
        window.dashboard = new Dashboard();
        window.dashboard.init();
    } else {
        console.error('Dashboard class not found');
    }
});
";

include __DIR__ . '/../../includes/footer.php';
?>