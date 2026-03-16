<?php
/**
 * Fix Sales.js File
 * Rebuild sales.js with proper structure
 */

echo "<h1>🔧 Fix Sales.js File</h1>";

try {
    $salesJsPath = __DIR__ . '/assets/js/sales.js';
    
    if (file_exists($salesJsPath)) {
        // Backup original
        copy($salesJsPath, $salesJsPath . '.backup.' . date('Y-m-d-H-i-s'));
        echo "✅ Backed up original sales.js<br>";
    }
    
    // Create clean sales.js content
    $salesJsContent = '/**
 * Sales Controller
 * Handles sales data management and UI interactions
 */

class SalesController {
    constructor() {
        this.currentPage = 1;
        this.recordsPerPage = 25;
        this.currentFilters = {};
        this.sortColumn = null;
        this.sortDirection = \'desc\';
    }
    
    init() {
        console.log(\'Sales controller initializing...\');
        this.setupEventListeners();
        this.loadSalesData();
    }
    
    setupEventListeners() {
        // Filter form submission
        const filterForm = document.getElementById(\'salesFilters\');
        if (filterForm) {
            filterForm.addEventListener(\'submit\', (e) => {
                e.preventDefault();
                this.applyFilters();
            });
        }
        
        // Reset filters
        const resetBtn = document.getElementById(\'resetFilters\');
        if (resetBtn) {
            resetBtn.addEventListener(\'click\', () => {
                this.resetFilters();
            });
        }
        
        // Add new record
        const addBtn = document.getElementById(\'addRecord\');
        if (addBtn) {
            addBtn.addEventListener(\'click\', () => {
                this.showAddModal();
            });
        }
        
        // Records per page change
        const perPageSelect = document.getElementById(\'recordsPerPage\');
        if (perPageSelect) {
            perPageSelect.addEventListener(\'change\', (e) => {
                this.recordsPerPage = parseInt(e.target.value);
                this.currentPage = 1;
                this.loadSalesData();
            });
        }
    }
    
    applyFilters() {
        const formData = new FormData(document.getElementById(\'salesFilters\'));
        this.currentFilters = {};
        
        for (let [key, value] of formData.entries()) {
            if (value.trim()) {
                this.currentFilters[key] = value.trim();
            }
        }
        
        console.log(\'Applying filters:\', this.currentFilters);
        this.currentPage = 1;
        this.loadSalesData();
    }
    
    resetFilters() {
        const filterForm = document.getElementById(\'salesFilters\');
        if (filterForm) {
            filterForm.reset();
        }
        
        this.currentFilters = {};
        this.currentPage = 1;
        this.loadSalesData();
    }
    
    async loadSalesData() {
        try {
            Utils.showLoading(\'Loading sales data...\');
            
            const params = {
                action: \'list\',
                page: this.currentPage,
                limit: this.recordsPerPage,
                ...this.currentFilters
            };
            
            if (this.sortColumn) {
                params.sort_column = this.sortColumn;
                params.sort_direction = this.sortDirection;
            }
            
            console.log(\'Loading sales data with params:\', params);
            
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + \'/ajax/sales.php\',
                params
            );
            
            if (response.success) {
                this.displaySalesData(response.data);
                this.updatePagination(response.pagination);
                this.updateTableInfo(response.pagination);
            } else {
                throw new Error(response.message || \'Failed to load sales data\');
            }
            
        } catch (error) {
            console.error(\'Sales data load error:\', error);
            Utils.showToast(\'Failed to load sales data: \' + error.message, \'error\');
            this.showErrorState();
        } finally {
            Utils.hideLoading();
        }
    }
    
    displaySalesData(records) {
        const tbody = document.querySelector(\'#salesTable tbody\');
        if (!tbody) {
            console.error(\'Sales table tbody not found\');
            return;
        }
        
        if (!records || records.length === 0) {
            tbody.innerHTML = \'<tr><td colspan="12" class="text-center text-muted">No sales records found</td></tr>\';
            return;
        }
        
        let html = \'\';
        records.forEach(record => {
            html += `
                <tr>
                    <td>${record.id || \'\'}</td>
                    <td>${record.invoice_num || \'\'}</td>
                    <td>${record.dated ? new Date(record.dated).toLocaleDateString() : \'\'}</td>
                    <td title="${record.business_name || \'\'}">${this.truncateText(record.business_name || \'\', 20)}</td>
                    <td>${record.sales_rep || \'\'}</td>
                    <td title="${record.product || \'\'}">${this.truncateText(record.product || \'\', 25)}</td>
                    <td class="text-right">${parseFloat(record.quantity || 0).toFixed(2)}</td>
                    <td class="text-right">$${parseFloat(record.unit_price || 0).toFixed(2)}</td>
                    <td class="text-right">$${parseFloat(record.line_revenue || 0).toFixed(2)}</td>
                    <td class="text-right">$${parseFloat(record.gross_profit || 0).toFixed(2)}</td>
                    <td class="text-right">${parseFloat(record.gp_margin || 0).toFixed(1)}%</td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-info btn-sm" onclick="salesController.viewRecord(${record.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-warning btn-sm" onclick="salesController.editRecord(${record.id})" title="Edit Record">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="salesController.deleteRecord(${record.id})" title="Delete Record">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
    }
    
    truncateText(text, maxLength) {
        if (!text) return \'\';
        return text.length > maxLength ? text.substring(0, maxLength) + \'...\' : text;
    }
    
    updatePagination(pagination) {
        const paginationContainer = document.getElementById(\'pagination\');
        if (!paginationContainer) return;
        
        if (!pagination || pagination.total_pages <= 1) {
            paginationContainer.innerHTML = \'\';
            return;
        }
        
        let html = \'<ul class="pagination pagination-sm mb-0">\';
        
        // Previous button
        if (pagination.current_page > 1) {
            html += `<li class="page-item">
                <a class="page-link" href="#" onclick="salesController.goToPage(${pagination.current_page - 1})">Previous</a>
            </li>`;
        }
        
        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === pagination.current_page ? \'active\' : \'\';
            html += `<li class="page-item ${activeClass}">
                <a class="page-link" href="#" onclick="salesController.goToPage(${i})">${i}</a>
            </li>`;
        }
        
        // Next button
        if (pagination.current_page < pagination.total_pages) {
            html += `<li class="page-item">
                <a class="page-link" href="#" onclick="salesController.goToPage(${pagination.current_page + 1})">Next</a>
            </li>`;
        }
        
        html += \'</ul>\';
        paginationContainer.innerHTML = html;
    }
    
    updateTableInfo(pagination) {
        const infoElement = document.getElementById(\'tableInfo\');
        if (!infoElement || !pagination) return;
        
        const start = ((pagination.current_page - 1) * pagination.records_per_page) + 1;
        const end = Math.min(pagination.current_page * pagination.records_per_page, pagination.total_records);
        
        infoElement.textContent = `Showing ${start} to ${end} of ${pagination.total_records} entries`;
    }
    
    goToPage(page) {
        this.currentPage = page;
        this.loadSalesData();
    }
    
    showErrorState() {
        const tbody = document.querySelector(\'#salesTable tbody\');
        if (tbody) {
            tbody.innerHTML = \'<tr><td colspan="12" class="text-center text-danger">Error loading data. Please try again.</td></tr>\';
        }
    }
    
    // Record operations
    async viewRecord(id) {
        try {
            Utils.showLoading(\'Loading record...\');
            
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + \'/ajax/sales.php\',
                { action: \'get\', id: id }
            );
            
            if (response.success) {
                this.showRecordModal(response.data, \'view\');
            } else {
                throw new Error(response.message || \'Failed to load record\');
            }
            
        } catch (error) {
            console.error(\'Error loading record:\', error);
            Utils.showToast(\'Failed to load record: \' + error.message, \'error\');
        } finally {
            Utils.hideLoading();
        }
    }
    
    async editRecord(id) {
        try {
            Utils.showLoading(\'Loading record...\');
            
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + \'/ajax/sales.php\',
                { action: \'get\', id: id }
            );
            
            if (response.success) {
                this.showRecordModal(response.data, \'edit\');
            } else {
                throw new Error(response.message || \'Failed to load record\');
            }
            
        } catch (error) {
            console.error(\'Error loading record:\', error);
            Utils.showToast(\'Failed to load record: \' + error.message, \'error\');
        } finally {
            Utils.hideLoading();
        }
    }
    
    async deleteRecord(id) {
        if (!confirm(\'Are you sure you want to delete this record?\')) {
            return;
        }
        
        try {
            Utils.showLoading(\'Deleting record...\');
            
            const response = await AjaxHelper.post(
                window.AppConfig.baseUrl + \'/ajax/sales.php\',
                { 
                    action: \'delete\', 
                    id: id,
                    csrf_token: window.AppConfig.csrfToken
                }
            );
            
            if (response.success) {
                Utils.showToast(\'Record deleted successfully\', \'success\');
                this.loadSalesData();
            } else {
                throw new Error(response.message || \'Failed to delete record\');
            }
            
        } catch (error) {
            console.error(\'Error deleting record:\', error);
            Utils.showToast(\'Failed to delete record: \' + error.message, \'error\');
        } finally {
            Utils.hideLoading();
        }
    }
    
    showRecordModal(record, mode) {
        // Create modal dynamically
        const modalId = \'recordModal\';
        let existingModal = document.getElementById(modalId);
        if (existingModal) {
            existingModal.remove();
        }
        
        const isReadOnly = mode === \'view\';
        const modalTitle = mode === \'view\' ? \'View Record\' : \'Edit Record\';
        
        const modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">${modalTitle}</h5>
                            <button type="button" class="close" data-dismiss="modal">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <form id="recordForm">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Invoice Number</label>
                                            <input type="text" class="form-control" name="invoice_num" value="${record.invoice_num || \'\'}" ${isReadOnly ? \'readonly\' : \'\'}>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Date</label>
                                            <input type="datetime-local" class="form-control" name="dated" value="${record.dated ? record.dated.substring(0, 16) : \'\'}" ${isReadOnly ? \'readonly\' : \'\'}>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Business Name</label>
                                    <input type="text" class="form-control" name="business_name" value="${record.business_name || \'\'}" ${isReadOnly ? \'readonly\' : \'\'}>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Sales Rep</label>
                                            <input type="text" class="form-control" name="sales_rep" value="${record.sales_rep || \'\'}" ${isReadOnly ? \'readonly\' : \'\'}>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label>Product</label>
                                            <input type="text" class="form-control" name="product" value="${record.product || \'\'}" ${isReadOnly ? \'readonly\' : \'\'}>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Quantity</label>
                                            <input type="number" step="0.01" class="form-control" name="quantity" value="${record.quantity || \'\'}" ${isReadOnly ? \'readonly\' : \'\'}>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Unit Price</label>
                                            <input type="number" step="0.01" class="form-control" name="unit_price" value="${record.unit_price || \'\'}" ${isReadOnly ? \'readonly\' : \'\'}>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Line Revenue</label>
                                            <input type="number" step="0.01" class="form-control" name="line_revenue" value="${record.line_revenue || \'\'}" ${isReadOnly ? \'readonly\' : \'\'}>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="id" value="${record.id}">
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            ${!isReadOnly ? \'<button type="button" class="btn btn-primary" onclick="salesController.saveRecord()">Save Changes</button>\' : \'\'}
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML(\'beforeend\', modalHtml);
        $(\'#\' + modalId).modal(\'show\');
    }
    
    showAddModal() {
        const emptyRecord = {
            id: \'\',
            invoice_num: \'\',
            dated: \'\',
            business_name: \'\',
            sales_rep: \'\',
            product: \'\',
            quantity: \'\',
            unit_price: \'\',
            line_revenue: \'\'
        };
        
        this.showRecordModal(emptyRecord, \'edit\');
        document.querySelector(\'#recordModal .modal-title\').textContent = \'Add New Record\';
    }
    
    async saveRecord() {
        try {
            const form = document.getElementById(\'recordForm\');
            const formData = new FormData(form);
            
            const data = {};
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            data.csrf_token = window.AppConfig.csrfToken;
            
            const isEdit = data.id && data.id !== \'\';
            data.action = isEdit ? \'update\' : \'create\';
            
            Utils.showLoading(isEdit ? \'Updating record...\' : \'Creating record...\');
            
            const response = await AjaxHelper.post(
                window.AppConfig.baseUrl + \'/ajax/sales.php\',
                data
            );
            
            if (response.success) {
                Utils.showToast(response.message || \'Record saved successfully\', \'success\');
                $(\'#recordModal\').modal(\'hide\');
                this.loadSalesData();
            } else {
                throw new Error(response.message || \'Failed to save record\');
            }
            
        } catch (error) {
            console.error(\'Error saving record:\', error);
            Utils.showToast(\'Failed to save record: \' + error.message, \'error\');
        } finally {
            Utils.hideLoading();
        }
    }
}

// Initialize when DOM is ready
$(document).ready(function() {
    if (typeof SalesController !== \'undefined\') {
        window.salesController = new SalesController();
        window.salesController.init();
    } else {
        console.error(\'SalesController class not found\');
    }
});';
    
    // Write the clean file
    file_put_contents($salesJsPath, $salesJsContent);
    echo "✅ Created clean sales.js file<br>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
    echo "<h4>✅ Sales.js Fixed!</h4>";
    echo "<ul>";
    echo "<li>✅ Removed syntax errors</li>";
    echo "<li>✅ Clean class structure</li>";
    echo "<li>✅ Proper method definitions</li>";
    echo "<li>✅ Complete CRUD functionality</li>";
    echo "<li>✅ Error handling</li>";
    echo "</ul>";
    echo "<p><strong>Next:</strong> Test the sales page</p>";
    echo "<p><a href='pages/sales/index.php' target='_blank' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px;'>🧪 Test Sales Page</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<h3>❌ Fix Failed:</h3>";
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 4px; color: #721c24;'>";
    echo "<strong>Error:</strong> " . $e->getMessage() . "<br>";
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; background: #f8f9fa; }
h1, h3, h4 { color: #333; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
ul, ol { margin: 10px 0; }
li { margin: 5px 0; }
</style>