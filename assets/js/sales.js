/**
 * Sales Controller JavaScript
 */

class SalesController {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 25;
        this.sortColumn = null;
        this.sortDirection = 'asc';
        this.filters = {};
        this.editingId = null;
    }
    
    init() {
        console.log('Sales controller initializing...');
        this.setupEventListeners();
        this.loadFilterOptions();
        this.loadSalesData();
    }
    
    setupEventListeners() {
        // Filter form submission
        $('#salesFilters').on('submit', (e) => {
            e.preventDefault();
            this.applyFilters();
        });
        
        // Reset filters
        $('#resetFilters').on('click', () => {
            this.resetFilters();
        });
        
        // Add record button
        $('#addRecord').on('click', () => {
            this.showAddModal();
        });
        
        // Sales form submission
        $('#salesForm').on('submit', (e) => {
            e.preventDefault();
            this.saveRecord();
        });
        
        // Auto-calculate total_amount from quantity * unit_price
        $('#quantity, #unitPrice').on('input', () => {
            const q = parseFloat($('#quantity').val()) || 0;
            const p = parseFloat($('#unitPrice').val()) || 0;
            const total = document.getElementById('totalAmount');
            if (total && (q > 0 || p > 0)) total.value = (q * p).toFixed(2);
        });
        
        // Search input with debounce
        $('#searchTerm').on('input', Utils.debounce(() => {
            this.applyFilters();
        }, 500));
        
        // Date filters
        $('#dateFrom, #dateTo').on('change', () => {
            this.applyFilters();
        });
        
        // Select filters
        $('#businessFilter, #salesRepFilter').on('change', () => {
            this.applyFilters();
        });
        
        // Product filter with debounce
        $('#productFilter').on('input', Utils.debounce(() => {
            this.applyFilters();
        }, 500));
        
        // Table sorting
        $('#salesTable th[data-sort]').on('click', (e) => {
            const column = $(e.currentTarget).data('sort');
            this.handleSort(column);
        });
        
        // Export buttons
        $('#exportCsv').on('click', () => this.exportData('csv'));
        $('#exportExcel').on('click', () => this.exportData('excel'));
    }
    
    async loadFilterOptions() {
        try {
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + '/ajax/sales.php',
                { action: 'filter_options' }
            );
            
            if (response.success) {
                this.populateFilterDropdowns(response.data);
            }
        } catch (error) {
            console.error('Failed to load filter options:', error);
        }
    }
    
    populateFilterDropdowns(data) {
        if (!data) return;
        // Populate business filter
        const businessSelect = document.getElementById('businessFilter');
        if (!businessSelect) return;
        businessSelect.innerHTML = '<option value="">All Businesses</option>';
        (data.businesses || []).forEach(business => {
            const option = document.createElement('option');
            option.value = business;
            option.textContent = business;
            businessSelect.appendChild(option);
        });
        
        // Populate sales rep filter
        const salesRepSelect = document.getElementById('salesRepFilter');
        if (!salesRepSelect) return;
        salesRepSelect.innerHTML = '<option value="">All Sales Reps</option>';
        (data.sales_reps || []).forEach(rep => {
            const option = document.createElement('option');
            option.value = rep;
            option.textContent = rep;
            salesRepSelect.appendChild(option);
        });
    }
    
    applyFilters() {
        const formData = new FormData(document.getElementById('salesFilters'));
        this.filters = {};
        
        for (let [key, value] of formData.entries()) {
            if (value.trim()) {
                this.filters[key] = value.trim();
            }
        }
        
        this.currentPage = 1; // Reset to first page
        console.log('Applying filters:', this.filters);
        this.loadSalesData();
    }
    
    resetFilters() {
        document.getElementById('salesFilters').reset();
        this.filters = {};
        this.currentPage = 1;
        this.loadSalesData();
    }
    
    async loadSalesData() {
        try {
            const params = {
                action: 'list',
                page: this.currentPage,
                limit: this.itemsPerPage,
                sort_column: this.sortColumn,
                sort_direction: this.sortDirection,
                ...this.filters
            };
            
            console.log('Loading sales data with params:', params);
            
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + '/ajax/sales.php',
                params
            );
            
            if (response.success) {
                const records = response.data || [];
                const pagination = response.pagination || response.meta?.pagination || {};
                this.displaySalesData(records);
                this.updatePagination(pagination);
                this.updateTableInfo(pagination);
            } else {
                throw new Error(response.message || 'Failed to load sales data');
            }
            
        } catch (error) {
            console.error('Sales data load error:', error);
            Utils.showToast('Failed to load sales data: ' + error.message, 'error');
            this.showErrorState();
        } finally {
            Utils.hideLoading();
        }
    }
    
    displaySalesData(records) {
        const tbody = document.querySelector('#salesTable tbody');
        if (!tbody) {
            console.error('Sales table tbody not found');
            return;
        }
        
        if (!records || records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="13" class="text-center text-muted">No sales records found</td></tr>';
            return;
        }
        
        let html = '';
        records.forEach(record => {
            html += `
                <tr>
                    <td>${record.id || ''}</td>
                    <td>${record.Invoice_Num || ''}</td>
                    <td>${Utils.formatDate(record.Dated)}</td>
                    <td title="${record.Business_Name || ''}">${this.truncateText(record.Business_Name || '', 20)}</td>
                    <td>${record.Sales_Rep || ''}</td>
                    <td title="${record.product || ''}">${this.truncateText(record.product || '', 25)}</td>
                    <td title="${record.Delivery_Profile || ''}">${this.truncateText(record.Delivery_Profile || '', 15)}</td>
                    <td class="text-right">${parseFloat(record.Quantity || 0).toFixed(2)}</td>
                    <td class="text-right">$${parseFloat(record.Unit_Price || 0).toFixed(2)}</td>
                    <td class="text-right">$${parseFloat(record.Purchase_Price || 0).toFixed(2)}</td>
                    <td class="text-right">$${parseFloat(record.gross_profit || 0).toFixed(2)}</td>
                    <td class="text-right">${parseFloat(record.gp_margin || 0).toFixed(1)}%</td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-info btn-sm" onclick="viewRecord(${record.id})" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-warning btn-sm" onclick="editRecord(${record.id})" title="Edit Record">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button type="button" class="btn btn-danger btn-sm" onclick="deleteRecord(${record.id})" title="Delete Record">
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
        if (!text) return '';
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }
    
    updatePagination(pagination) {
        const paginationContainer = document.getElementById('pagination');
        
        if (pagination.total_pages <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }
        
        let html = '<ul class="pagination pagination-sm mb-0">';
        
        // Previous button
        if (pagination.current_page > 1) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1})">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            `;
        }
        
        // Page numbers
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            const activeClass = i === pagination.current_page ? 'active' : '';
            html += `
                <li class="page-item ${activeClass}">
                    <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
                </li>
            `;
        }
        
        // Next button
        if (pagination.current_page < pagination.total_pages) {
            html += `
                <li class="page-item">
                    <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1})">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            `;
        }
        
        html += '</ul>';
        paginationContainer.innerHTML = html;
    }
    
    updateTableInfo(pagination) {
        const start = ((pagination.current_page - 1) * pagination.records_per_page) + 1;
        const end = Math.min(pagination.current_page * pagination.records_per_page, pagination.total_records);
        
        document.getElementById('tableInfo').textContent = 
            `Showing ${start} to ${end} of ${pagination.total_records} entries`;
    }
    
    changePage(page) {
        this.currentPage = page;
        this.loadSalesData();
    }
    
    handleSort(column) {
        if (this.sortColumn === column) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = column;
            this.sortDirection = 'asc';
        }
        
        // Update sort icons
        $('#salesTable th[data-sort] i').removeClass('fa-sort-up fa-sort-down').addClass('fa-sort');
        const currentHeader = $(`#salesTable th[data-sort="${column}"] i`);
        currentHeader.removeClass('fa-sort').addClass(this.sortDirection === 'asc' ? 'fa-sort-up' : 'fa-sort-down');
        
        this.loadSalesData();
    }
    
    showAddModal() {
        this.editingId = null;
        document.getElementById('salesModalLabel').textContent = 'Add Sales Record';
        document.getElementById('salesForm').reset();
        document.getElementById('recordId').value = '';
        
        // Set default date to today
        document.getElementById('dated').value = new Date().toISOString().split('T')[0];
        
        const modal = new bootstrap.Modal(document.getElementById('salesModal'));
        modal.show();
    }
    
    async editRecord(id) {
        try {
            Utils.showLoading('Loading record...');
            
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + '/ajax/sales.php',
                { action: 'get', id: id }
            );
            
            if (response.success) {
                this.editingId = id;
                this.populateForm(response.data);
                
                document.getElementById('salesModalLabel').textContent = 'Edit Sales Record';
                const modal = new bootstrap.Modal(document.getElementById('salesModal'));
                modal.show();
            } else {
                throw new Error(response.message || 'Failed to load record');
            }
            
        } catch (error) {
            console.error('Edit record error:', error);
            Utils.showToast('Failed to load record: ' + error.message, 'error');
        } finally {
            Utils.hideLoading();
        }
    }
    
    populateForm(record) {
        const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val ?? ''; };
        setVal('recordId', record.id);
        setVal('businessName', record.Business_Name);
        setVal('salesRep', record.Sales_Rep);
        setVal('invoiceNum', record.Invoice_Num);
        setVal('dated', Utils.getDatePart(record.Dated));
        setVal('product', record.product);
        setVal('deliveryProfile', record.Delivery_Profile);
        setVal('quantity', record.Quantity);
        setVal('unitPrice', record.Unit_Price);
        setVal('purchasePrice', record.Purchase_Price);
    }
    
    async saveRecord() {
        try {
            const formData = new FormData(document.getElementById('salesForm'));
            const data = {};
            
            for (let [key, value] of formData.entries()) {
                data[key] = value;
            }
            
            data.action = this.editingId ? 'update' : 'create';
            
            Utils.showLoading('Saving record...');
            
            const response = await AjaxHelper.post(
                window.AppConfig.baseUrl + '/ajax/sales.php',
                data
            );
            
            if (response.success) {
                Utils.showToast(response.message, 'success');
                
                const modal = bootstrap.Modal.getInstance(document.getElementById('salesModal'));
                modal.hide();
                
                this.loadSalesData(); // Refresh the table
            } else {
                throw new Error(response.message || 'Failed to save record');
            }
            
        } catch (error) {
            console.error('Save record error:', error);
            Utils.showToast('Failed to save record: ' + error.message, 'error');
        } finally {
            Utils.hideLoading();
        }
    }
    
    async viewRecord(id) {
        try {
            Utils.showLoading('Loading record details...');
            
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + '/ajax/sales.php',
                { action: 'get', id: id }
            );
            
            if (response.success) {
                this.showViewModal(response.data);
            } else {
                throw new Error(response.message || 'Failed to load record');
            }
            
        } catch (error) {
            console.error('View record error:', error);
            Utils.showToast('Failed to load record: ' + error.message, 'error');
        } finally {
            Utils.hideLoading();
        }
    }
    
    showViewModal(record) {
        const marginClass = Utils.getMarginClass(record.gp_margin || 0);
        const formattedDate = Utils.formatDate(record.dated);
        const v = (k) => (record[k] ?? '').toString().replace(/</g, '&lt;').replace(/>/g, '&gt;');
        
        const content = `
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3"><label class="form-label"><strong>Business_Name:</strong></label><p class="form-control-plaintext">${v('business_name')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Delivery_Profile (delivery_name):</strong></label><p class="form-control-plaintext">${v('delivery_name')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>delivery_routes:</strong></label><p class="form-control-plaintext">${v('delivery_routes')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Sales_Rep:</strong></label><p class="form-control-plaintext">${v('sales_rep')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>AccountType:</strong></label><p class="form-control-plaintext">${v('account_type')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>address:</strong></label><p class="form-control-plaintext">${v('address')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Invoice_Num:</strong></label><p class="form-control-plaintext">${v('invoice_num')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Order_Num:</strong></label><p class="form-control-plaintext">${v('order_num')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Dated:</strong></label><p class="form-control-plaintext">${formattedDate}</p></div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3"><label class="form-label"><strong>product:</strong></label><p class="form-control-plaintext">${v('product')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>stock_id:</strong></label><p class="form-control-plaintext">${v('stock_id')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Quantity:</strong></label><p class="form-control-plaintext">${v('quantity')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Unit_Price:</strong></label><p class="form-control-plaintext">${Utils.formatCurrency(record.unit_price || 0)}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Unit_GST:</strong></label><p class="form-control-plaintext">${Utils.formatCurrency(record.unit_gst || 0)}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Total_Amount:</strong></label><p class="form-control-plaintext">${Utils.formatCurrency(record.total_amount || 0)}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>PONumber:</strong></label><p class="form-control-plaintext">${v('po_number')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Purchase_Price:</strong></label><p class="form-control-plaintext">${Utils.formatCurrency(record.purchase_price || 0)}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Reward_inclusive:</strong></label><p class="form-control-plaintext">${v('reward_inclusive')}</p></div>
                    <div class="mb-3"><label class="form-label"><strong>Gross Profit / GP Margin:</strong></label><p class="form-control-plaintext">${Utils.formatCurrency(record.gross_profit || 0)} / <span class="badge badge-${marginClass}">${Utils.formatPercentage(record.gp_margin || 0)}</span></p></div>
                </div>
            </div>
        `;
        
        document.getElementById('viewModalBody').innerHTML = content;
        const modal = new bootstrap.Modal(document.getElementById('viewModal'));
        modal.show();
    }
    
    deleteRecord(id) {
        Utils.confirm('Are you sure you want to delete this sales record?', async () => {
            try {
                Utils.showLoading('Deleting record...');
                
                const response = await AjaxHelper.post(
                    window.AppConfig.baseUrl + '/ajax/sales.php',
                    { action: 'delete', id: id }
                );
                
                if (response.success) {
                    Utils.showToast(response.message, 'success');
                    this.loadSalesData(); // Refresh the table
                } else {
                    throw new Error(response.message || 'Failed to delete record');
                }
                
            } catch (error) {
                console.error('Delete record error:', error);
                Utils.showToast('Failed to delete record: ' + error.message, 'error');
            } finally {
                Utils.hideLoading();
            }
        });
    }
    
    exportData(format) {
        // This would typically generate a download link or trigger server-side export
        Utils.showToast(`Export to ${format.toUpperCase()} functionality would be implemented here`, 'info');
    }
    
    showErrorState() {
        const tbody = document.querySelector('#salesTable tbody');
        tbody.innerHTML = `
            <tr>
                <td colspan="12" class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-triangle fa-2x mb-3 d-block"></i>
                    Failed to load sales data
                    <br>
                    <button class="btn btn-primary btn-sm mt-2" onclick="window.salesController.loadSalesData()">
                        <i class="fas fa-retry me-1"></i>Try Again
                    </button>
                </td>
            </tr>
        `;
    }
}

// Initialize when DOM is ready
$(document).ready(function() {
    console.log('Sales script loaded');
});