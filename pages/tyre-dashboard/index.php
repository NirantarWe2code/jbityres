<?php
/**
 * Tyre Analytics Dashboard
 * Based on TyreDashboard.jsx + formulas.html - connected to current system data
 */

require_once __DIR__ . '/../../config/config.php';
requirePermission('dashboard.view');

$pageTitle = 'Tyre Analytics';
$breadcrumbs = [
    ['title' => 'Home', 'url' => BASE_URL . '/pages/dashboard/index.php'],
    ['title' => 'Tyre Analytics']
];

$pageScripts = [
    BASE_URL . '/assets/js/tyre-dashboard.js?v=' . (file_exists(__DIR__ . '/../../assets/js/tyre-dashboard.js') ? filemtime(__DIR__ . '/../../assets/js/tyre-dashboard.js') : time())
];

include __DIR__ . '/../../includes/header.php';
?>

<!-- Filters -->
<div class="card mb-3">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-filter me-2"></i>Date Range</h3>
    </div>
    <div class="card-body">
        <form id="tyreFilters" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">From Date</label>
                <input type="date" class="form-control" id="tyreDateFrom" name="date_from" 
                       value="<?php echo date('Y-m-d', strtotime('-12 months')); ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">To Date</label>
                <input type="date" class="form-control" id="tyreDateTo" name="date_to" 
                       value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search me-1"></i>Apply
                </button>
                <button type="button" class="btn btn-secondary" id="tyreResetFilters">
                    <i class="fas fa-undo me-1"></i>Reset
                </button>
            </div>
        </form>
    </div>
</div>

<!-- KPI Cards -->
<div class="row mb-4" id="tyreKpiCards">
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card border-top border-primary border-3 h-100">
            <div class="card-body py-3">
                <small class="text-muted text-uppercase d-block">Revenue ex-GST</small>
                <h4 class="mb-0 text-primary" id="kpiRevenue">-</h4>
                <small class="text-muted" id="kpiRevenueGst">inc-GST: -</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card border-top border-warning border-3 h-100">
            <div class="card-body py-3">
                <small class="text-muted text-uppercase d-block">Gross Profit</small>
                <h4 class="mb-0 text-warning" id="kpiProfit">-</h4>
                <small class="text-muted" id="kpiMargin">Margin: -</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card border-top border-info border-3 h-100">
            <div class="card-body py-3">
                <small class="text-muted text-uppercase d-block">Total Units</small>
                <h4 class="mb-0 text-info" id="kpiUnits">-</h4>
                <small class="text-muted">Tyres sold</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card border-top border-secondary border-3 h-100">
            <div class="card-body py-3">
                <small class="text-muted text-uppercase d-block">Invoices</small>
                <h4 class="mb-0" id="kpiInvoices">-</h4>
                <small class="text-muted" id="kpiCustomers">customers</small>
            </div>
        </div>
    </div>
    <div class="col-lg-2 col-md-4 col-6 mb-3">
        <div class="card border-top border-success border-3 h-100">
            <div class="card-body py-3">
                <small class="text-muted text-uppercase d-block">Avg Invoice</small>
                <h4 class="mb-0 text-success" id="kpiAvgInvoice">-</h4>
                <small class="text-muted">ex-GST</small>
            </div>
        </div>
    </div>
</div>

<!-- Nav Tabs -->
<ul class="nav nav-tabs mb-3" id="tyreTabs" role="tablist">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tyreOverview">Overview</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tyreMonthly">Monthly</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tyreBrands">Brands</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tyreCustomers">Customers</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tyreReps">Sales Reps</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tyreAreas">Areas</a></li>
</ul>

<div class="tab-content" id="tyreTabContent">
    <!-- Overview -->
    <div class="tab-pane fade show active" id="tyreOverview">
        <div class="row">
            <div class="col-lg-8 mb-3">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Monthly Revenue (ex-GST)</h5></div>
                    <div class="card-body"><div class="chart-container" style="height:280px"><canvas id="tyreChartMonthly"></canvas></div></div>
                </div>
            </div>
            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">GP Margin % by Month</h5></div>
                    <div class="card-body"><div class="chart-container" style="height:280px"><canvas id="tyreChartMargin"></canvas></div></div>
                </div>
            </div>
            <div class="col-12 mb-3">
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">Brand Revenue (Margin Tiers: Red&lt;0% Amber 0-8% Green 8-12% Teal≥12%)</h5></div>
                    <div class="card-body"><div class="chart-container" style="height:300px"><canvas id="tyreChartBrands"></canvas></div></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly -->
    <div class="tab-pane fade" id="tyreMonthly">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Monthly Detail</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover" id="tyreMonthlyTable">
                    <thead><tr><th>Month</th><th class="text-end">Revenue ex-GST</th><th class="text-end">Revenue inc-GST</th><th class="text-end">Units</th><th class="text-end">Invoices</th><th class="text-end">GP</th><th class="text-end">GP Margin</th><th class="text-end">MoM %</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Brands -->
    <div class="tab-pane fade" id="tyreBrands">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Brand Summary (Revenue, Rev Share, Units, GP Margin)</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover" id="tyreBrandsTable">
                    <thead><tr><th>#</th><th>Brand</th><th class="text-end">Revenue ex-GST</th><th class="text-end">Rev %</th><th class="text-end">Units</th><th class="text-end">GP</th><th class="text-end">GP Margin</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Customers -->
    <div class="tab-pane fade" id="tyreCustomers">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Top Customers by Revenue</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover" id="tyreCustomersTable">
                    <thead><tr><th>#</th><th>Customer</th><th class="text-end">Revenue</th><th class="text-end">Rev %</th><th class="text-end">Units</th><th class="text-end">Invoices</th><th class="text-end">GP</th><th class="text-end">GP Margin</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reps -->
    <div class="tab-pane fade" id="tyreReps">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Sales Rep Metrics (Rev Share %, GP Margin)</h5></div>
            <div class="card-body">
                <div id="tyreRepsContent"></div>
            </div>
        </div>
    </div>

    <!-- Areas -->
    <div class="tab-pane fade" id="tyreAreas">
        <div class="card">
            <div class="card-header"><h5 class="mb-0">Delivery Area Analysis</h5></div>
            <div class="card-body table-responsive">
                <div id="tyreAreasContent"></div>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScripts = "
$(document).ready(function(){
    if (typeof TyreDashboard !== 'undefined') {
        window.tyreDashboard = new TyreDashboard();
        window.tyreDashboard.init();
    }
});
";
include __DIR__ . '/../../includes/footer.php';
?>
