/**
 * Sales Controller JavaScript
 */

class SalesController {
    constructor() {
        this.currentPage = 1;
        this.itemsPerPage = 100;
        this.sortColumn = null;
        this.sortDirection = 'asc';
        this.filters = {};
        this.editingId = null;
    }
    
    init() {
        console.log('Sales controller initializing...');
        const perPageSelect = document.getElementById('salesPerPage');
        if (perPageSelect) {
            perPageSelect.value = String(this.itemsPerPage);
        }
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

        // Per-page selector
        $('#salesPerPage').on('change', (e) => {
            const nextLimit = parseInt(e.target.value, 10);
            if (!Number.isFinite(nextLimit) || nextLimit <= 0) {
                return;
            }
            this.itemsPerPage = nextLimit;
            this.currentPage = 1;
            this.loadSalesData();
        });
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
            tbody.innerHTML = '<tr><td colspan="8" class="text-center text-muted">No sales records found</td></tr>';
            return;
        }
        
        let html = '';
        records.forEach(record => {
            const businessName = this.getField(record, ['business_name', 'Business_Name']);
            const deliveryProfile = this.getField(record, ['delivery_profile', 'Delivery_Profile', 'delivery_name', 'Delivery_Name']);
            const salesRep = this.getField(record, ['sales_rep', 'Sales_Rep']);
            const invoiceNum = this.getField(record, ['invoice_num', 'Invoice_Num']);
            const dated = this.getField(record, ['dated', 'Dated']);
            const product = this.getField(record, ['product', 'Product']);

            html += `
                <tr>
                    <td>${record.id || ''}</td>
                    <td title="${businessName || ''}">${this.truncateText(businessName || '', 28)}</td>
                    <td title="${deliveryProfile || ''}">${this.truncateText(deliveryProfile || '', 24)}</td>
                    <td>${salesRep || ''}</td>
                    <td>${invoiceNum || ''}</td>
                    <td>${Utils.formatDate(dated)}</td>
                    <td title="${product || ''}">${this.truncateText(product || '', 34)}</td>
                    <td>
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-info btn-sm" onclick="viewRecord(${record.id})" title="View Details">
                                <i class="fas fa-eye"></i>
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
    
    getField(record, keys) {
        for (const key of keys) {
            if (record[key] !== undefined && record[key] !== null) {
                return record[key];
            }
        }
        return '';
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

        const current = Number(pagination.current_page) || 1;
        const total = Number(pagination.total_pages) || 1;
        const visible = this.getVisiblePages(current, total);

        const btnBase = 'border-radius:8px;padding:6px 12px;cursor:pointer;font-size:14px;font-weight:700;min-width:52px;border:1px solid #243652;background:transparent;color:#e2e8f0;';
        const btnActive = 'background:#14d8d2;color:#041522;border-color:#14d8d2;';
        const btnDisabled = 'opacity:.45;cursor:not-allowed;';

        const navBtn = (label, page, disabled) => {
            const attrs = disabled
                ? 'type="button" disabled'
                : `type="button" onclick="changePage(${page})"`;
            const st = btnBase + (disabled ? btnDisabled : '');
            return `<button ${attrs} style="${st}">${label}</button>`;
        };

        const numberBtn = (page) => {
            const isActive = page === current;
            const st = btnBase + (isActive ? btnActive : '');
            if (isActive) {
                return `<button type="button" disabled style="${st}${btnDisabled}">${page}</button>`;
            }
            return `<button type="button" onclick="changePage(${page})" style="${st}">${page}</button>`;
        };

        let html = '<div style="display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;border:1px solid #1e3353;border-radius:12px;padding:8px 10px;background:rgba(10,22,44,0.35);">';
        html += navBtn('First', 1, current <= 1);
        html += navBtn('Prev', current - 1, current <= 1);

        visible.forEach((item) => {
            if (item === '...') {
                html += '<span style="color:#64748b;font-weight:700;padding:0 2px;">...</span>';
            } else {
                html += numberBtn(item);
            }
        });

        html += navBtn('Next', current + 1, current >= total);
        html += navBtn('Last', total, current >= total);
        html += '</div>';

        paginationContainer.innerHTML = html;
    }

    getVisiblePages(current, total) {
        if (total <= 1) return [1];

        const delta = 2;
        const range = [];
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - delta && i <= current + delta)) {
                range.push(i);
            }
        }

        const out = [];
        let prev = 0;
        range.forEach((page) => {
            if (prev) {
                if (page - prev === 2) out.push(prev + 1);
                else if (page - prev > 2) out.push('...');
            }
            out.push(page);
            prev = page;
        });
        return out;
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
        const fromRecord = (keys) => this.getField(record, keys);
        setVal('recordId', record.id);
        setVal('businessName', fromRecord(['business_name', 'Business_Name']));
        setVal('salesRep', fromRecord(['sales_rep', 'Sales_Rep']));
        setVal('invoiceNum', fromRecord(['invoice_num', 'Invoice_Num']));
        setVal('dated', Utils.getDatePart(fromRecord(['dated', 'Dated'])));
        setVal('product', fromRecord(['product', 'Product']));
        setVal('deliveryProfile', fromRecord(['delivery_profile', 'Delivery_Profile', 'delivery_name', 'Delivery_Name']));
        setVal('quantity', fromRecord(['quantity', 'Quantity']));
        setVal('unitPrice', fromRecord(['unit_price', 'Unit_Price']));
        setVal('purchasePrice', fromRecord(['purchase_price', 'Purchase_Price']));
        setVal('totalAmount', fromRecord(['total_amount', 'Total_Amount', 'line_revenue']));
        setVal('poNumber', fromRecord(['po_number', 'PONumber']));
        setVal('rewardInclusive', fromRecord(['reward_inclusive', 'Reward_inclusive']));
        setVal('deliveryRoutes', fromRecord(['delivery_routes', 'Delivery_Routes']));
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
        const val = (k, fallback = '—') => {
            const raw = v(k).trim();
            return raw === '' ? fallback : raw;
        };
        const detailItem = (label, value) => `
            <div class="mb-2 p-2 rounded" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                <div class="text-uppercase fw-bold mb-1" style="font-size:10px;letter-spacing:.05em;color:#cbd5e1;">${label}</div>
                <div style="font-size:14px;color:#f8fafc;line-height:1.25;">${value}</div>
            </div>
        `;
        
        const content = `
            <div class="rounded p-2 mb-2" style="background:linear-gradient(135deg, rgba(14,165,233,.15), rgba(20,184,166,.14));border:1px solid rgba(45,212,191,.25);">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <div class="text-uppercase fw-bold" style="font-size:11px;letter-spacing:.06em;color:#cbd5e1;">Invoice</div>
                        <div style="font-size:18px;font-weight:700;color:#f8fafc;">${val('invoice_num')}</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-info text-dark px-2 py-1">Rep: ${val('sales_rep')}</span>
                        <span class="badge bg-secondary px-2 py-1">Date: ${formattedDate || '—'}</span>
                        <span class="badge bg-${marginClass} px-2 py-1">Margin: ${Utils.formatPercentage(record.gp_margin || 0)}</span>
                    </div>
                </div>
            </div>
            <div class="row g-2">
                <div class="col-lg-4 col-md-6">
                    ${detailItem('Business Name', val('business_name'))}
                    ${detailItem('Delivery Profile', val('delivery_name'))}
                    ${detailItem('Delivery Routes', val('delivery_routes'))}
                    ${detailItem('Invoice Number', val('invoice_num'))}
                    ${detailItem('Order Number', val('order_num'))}
                    ${detailItem('PO Number', val('po_number'))}
                </div>
                <div class="col-lg-4 col-md-6">
                    ${detailItem('Product', val('product'))}
                    ${detailItem('Stock ID', val('stock_id'))}
                    ${detailItem('Quantity', val('quantity'))}
                    ${detailItem('Date', formattedDate || '—')}
                    ${detailItem('Sales Rep', val('sales_rep'))}
                    ${detailItem('Reward Inclusive', val('reward_inclusive'))}
                </div>
                <div class="col-lg-4 col-md-12">
                    ${detailItem('Address', val('address'))}
                    ${detailItem('Unit Price', Utils.formatCurrency(record.unit_price || 0))}
                    ${detailItem('Unit GST', Utils.formatCurrency(record.unit_gst || 0))}
                    ${detailItem('Purchase Price', Utils.formatCurrency(record.purchase_price || 0))}
                    ${detailItem('Total Amount', Utils.formatCurrency(record.total_amount || 0))}
                    ${detailItem('Gross Profit', Utils.formatCurrency(record.gross_profit || 0))}
                    ${detailItem('Account Type', val('account_type'))}
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
                <td colspan="8" class="text-center text-danger py-4">
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