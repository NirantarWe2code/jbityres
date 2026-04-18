<?php
/**
 * Sales Reports Page
 */

require_once __DIR__ . '/../../config/config.php';
requirePermission('sales.view');

$pageTitle = 'Sales Reports';
$breadcrumbs = [
    ['title' => 'Home', 'url' => BASE_URL . '/pages/jbi-dashboard/index.php'],
    ['title' => 'Sales Reports']
];

$pageScripts = [
    BASE_URL . '/assets/js/sales.js?v=' . filemtime(__DIR__ . '/../../assets/js/sales.js')
];

include __DIR__ . '/../../includes/header.php';
?>

<!-- Filters Section -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-filter me-2"></i>Search & Filters
                </h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-tool" data-card-widget="collapse">
                        <i class="fas fa-minus"></i>
                    </button>
                </div>
            </div>
            <div class="card-body">
                <form id="salesFilters" class="row">
                    <div class="col-md-3">
                        <label for="searchTerm" class="form-label">Search</label>
                        <input type="text" class="form-control" id="searchTerm" name="search" 
                               placeholder="Invoice, business, product...">
                    </div>
                    <div class="col-md-3">
                        <label for="dateFrom" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="dateFrom" name="date_from">
                    </div>
                    <div class="col-md-3">
                        <label for="dateTo" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="dateTo" name="date_to">
                    </div>
                    <div class="col-md-3">
                        <label for="businessFilter" class="form-label">Business</label>
                        <select class="form-select" id="businessFilter" name="business_name">
                            <option value="">All Businesses</option>
                        </select>
                    </div>
                    <div class="col-md-3 mt-3">
                        <label for="salesRepFilter" class="form-label">Sales Rep</label>
                        <select class="form-select" id="salesRepFilter" name="sales_rep">
                            <option value="">All Sales Reps</option>
                        </select>
                    </div>
                    <div class="col-md-3 mt-3">
                        <label for="productFilter" class="form-label">Product</label>
                        <input type="text" class="form-control" id="productFilter" name="product" 
                               placeholder="Product name...">
                    </div>
                    <div class="col-md-6 mt-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                            <button type="button" class="btn btn-secondary" id="resetFilters">
                                <i class="fas fa-undo me-2"></i>Reset
                            </button>
                            <?php if (hasPermission('sales.create')): ?>
                            <button type="button" class="btn btn-success" id="addRecord">
                                <i class="fas fa-plus me-2"></i>Add Record
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Data Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-table me-2"></i>Sales Records
                </h3>
                <div class="card-tools">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-default" id="exportCsv">
                            <i class="fas fa-file-csv me-1"></i>CSV
                        </button>
                        <button type="button" class="btn btn-default" id="exportExcel">
                            <i class="fas fa-file-excel me-1"></i>Excel
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive p-0" style="overflow-x: auto; overflow-y: hidden;">
                                                                            <table id="salesTable" class="table table-striped table-hover table-sm" style="min-width: 1200px;">
                        <thead class="thead-dark">
                            <tr>
                                <th style="width: 60px;">id</th>
                                <th style="width: 160px;">business_name</th>
                                <th style="width: 140px;">delivery_profile</th>
                                <th style="width: 100px;">sales_rep</th>
                                <th style="width: 110px;">account_type</th>
                                <th style="width: 220px;">address</th>
                                <th style="width: 100px;">invoice_num</th>
                                <th style="width: 90px;">order_num</th>
                                <th style="width: 120px;">dated</th>
                                <th style="width: 220px;">product</th>
                                <th style="width: 120px;" class="text-center">actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="11" class="text-center">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="sr-only">Loading...</span>
                                    </div>
                                    Loading sales data...
                                </td>
                            </tr>
                        </tbody>
                    </table>
            </div>
            <div class="card-footer">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span id="tableInfo">Showing 0 to 0 of 0 entries</span>
                    </div>
                    <nav>
                        <div id="pagination"></div>
                    </nav>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal fade" id="salesModal" tabindex="-1" aria-labelledby="salesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="salesModalLabel">Add Sales Record</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="salesForm">
                <div class="modal-body" style="max-height: 70vh; overflow-y: auto;">
                    <input type="hidden" id="recordId" name="id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="businessName" class="form-label">Business_Name *</label>
                                <input type="text" class="form-control" id="businessName" name="business_name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="salesRep" class="form-label">Sales_Rep</label>
                                <input type="text" class="form-control" id="salesRep" name="sales_rep">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="invoiceNum" class="form-label">Invoice_Num *</label>
                                <input type="text" class="form-control" id="invoiceNum" name="invoice_num" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="dated" class="form-label">Dated *</label>
                                <input type="date" class="form-control" id="dated" name="dated" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="product" class="form-label">Product *</label>
                                <input type="text" class="form-control" id="product" name="product" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="deliveryProfile" class="form-label">Delivery_Profile</label>
                                <input type="text" class="form-control" id="deliveryProfile" name="delivery_profile">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="quantity" class="form-label">Quantity *</label>
                                <input type="number" class="form-control" id="quantity" name="quantity" min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="unitPrice" class="form-label">Unit_Price *</label>
                                <input type="number" class="form-control" id="unitPrice" name="unit_price" min="0" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="purchasePrice" class="form-label">Purchase_Price</label>
                                <input type="number" class="form-control" id="purchasePrice" name="purchase_price" min="0" step="0.01">
                            </div>
                        </div>
                    </div>
                            <div class="mb-3">
                                <label for="totalAmount" class="form-label">Total_Amount</label>
                                <input type="number" class="form-control" id="totalAmount" name="total_amount" min="0" step="0.01" placeholder="Auto from Qty×Unit_Price">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="poNumber" class="form-label">PONumber</label>
                                <input type="text" class="form-control" id="poNumber" name="po_number">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="purchasePrice" class="form-label">Purchase_Price</label>
                                <input type="number" class="form-control" id="purchasePrice" name="purchase_price" min="0" step="0.01">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="rewardInclusive" class="form-label">Reward_inclusive</label>
                                <select class="form-select" id="rewardInclusive" name="reward_inclusive">
                                    <option value="No">No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="deliveryRoutes" class="form-label">delivery_routes</label>
                        <input type="text" class="form-control" id="deliveryRoutes" name="delivery_routes">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewModalLabel">
                    <i class="fas fa-eye me-2"></i>Sales Record Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be loaded dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?php
$inlineScripts = "
// Initialize sales page when DOM is ready
$(document).ready(function() {
    console.log('Sales page loaded');
    
    if (typeof SalesController !== 'undefined') {
        window.salesController = new SalesController();
        window.salesController.init();
    } else {
        console.error('SalesController class not found');
    }
});

// Global functions for pagination and actions
function changePage(page) {
    if (window.salesController) {
        window.salesController.changePage(page);
    }
}

function viewRecord(id) {
    if (window.salesController) {
        window.salesController.viewRecord(id);
    }
}

function editRecord(id) {
    if (window.salesController) {
        window.salesController.editRecord(id);
    }
}

function deleteRecord(id) {
    if (window.salesController) {
        window.salesController.deleteRecord(id);
    }
}
";

include __DIR__ . '/../../includes/footer.php';
?>

<style>
#salesTable th,
#salesTable td {
    white-space: nowrap;
}
</style>