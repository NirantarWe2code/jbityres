/**
 * Common JavaScript Functions
 * Simple AJAX-based system utilities
 */

// Global utilities
window.Utils = {
    /**
     * Show toast notification
     */
    showToast: function(message, type = 'info', title = null) {
        try {
            const toast = document.getElementById('toast');
            if (!toast) return;
            const toastBody = toast.querySelector('.toast-body');
            const toastTitle = toast.querySelector('.toast-title');
            const toastIcon = toast.querySelector('.toast-icon');
            if (!toastBody) return;

            toastBody.textContent = message;
            if (toastTitle) {
                const titles = { success: 'Success', error: 'Error', warning: 'Warning', info: 'Information' };
                toastTitle.textContent = title || titles[type] || 'Notification';
            }
            if (toastIcon) toastIcon.className = { success: 'fas fa-check-circle', error: 'fas fa-exclamation-triangle', warning: 'fas fa-exclamation-circle', info: 'fas fa-info-circle' }[type] || 'fas fa-info-circle';
            toast.className = 'toast ' + ({ success: 'bg-success text-white', error: 'bg-danger text-white', warning: 'bg-warning text-dark', info: 'bg-info text-white' }[type] || 'bg-info text-white');

            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        } catch (e) {
            console.warn('showToast failed:', e);
        }
    },
    
    /**
     * Show loading overlay
     */
    showLoading: function(message = 'Loading...') {
        const overlay = document.getElementById('loadingOverlay');
        if (!overlay) return;
        const loadingText = overlay.querySelector('.loading-text');
        if (loadingText) loadingText.textContent = message;
        overlay.style.display = 'flex';
    },
    
    /**
     * Hide loading overlay
     */
    hideLoading: function() {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) overlay.style.display = 'none';
    },
    
    /**
     * Format currency
     */
    formatCurrency: function(amount) {
        return 'A$' + parseFloat(amount).toLocaleString('en-AU', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    },
    
    /**
     * Format percentage
     */
    formatPercentage: function(value) {
        return parseFloat(value).toFixed(1) + '%';
    },
    
    /**
     * Get date part (YYYY-MM-DD) from datetime string - no timezone conversion
     */
    getDatePart: function(dateStr) {
        if (!dateStr) return '';
        const s = String(dateStr).trim().substring(0, 10);
        return (s && s.match(/^\d{4}-\d{2}-\d{2}$/)) ? s : '';
    },
    
    /**
     * Format date for display - uses date part only to avoid timezone off-by-one
     */
    formatDate: function(date) {
        const part = this.getDatePart(date);
        if (!part) return '';
        const [y, m, d] = part.split('-').map(Number);
        const dObj = new Date(y, m - 1, d); // local time, no UTC
        return dObj.toLocaleDateString('en-AU', { year: 'numeric', month: 'short', day: 'numeric' });
    },
    
    /**
     * Get margin class for styling
     */
    getMarginClass: function(margin) {
        const m = parseFloat(margin || 0);
        if (m >= 30) return 'success';
        if (m >= 20) return 'warning';
        return 'danger';
    },
    
    /**
     * Debounce function
     */
    debounce: function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },
    
    /**
     * Confirm dialog
     */
    confirm: function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }
};

// AJAX Helper Class
window.AjaxHelper = {
    /**
     * Make AJAX request
     */
    request: function(url, data = {}, method = 'POST') {
        return new Promise((resolve, reject) => {
            // Add CSRF token
            if (method === 'POST' && window.AppConfig && window.AppConfig.csrfToken) {
                data.csrf_token = window.AppConfig.csrfToken;
            }
            
            $.ajax({
                url: url,
                method: method,
                data: data,
                dataType: 'json',
                success: function(response) {
                    resolve(response);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', error);
                    
                    let errorMessage = 'Request failed';
                    try {
                        const response = JSON.parse(xhr.responseText);
                        errorMessage = response.message || errorMessage;
                    } catch (e) {
                        errorMessage = error || errorMessage;
                    }
                    
                    reject({
                        success: false,
                        message: errorMessage,
                        status: xhr.status
                    });
                }
            });
        });
    },
    
    /**
     * GET request
     */
    get: function(url, data = {}) {
        const queryString = $.param(data);
        const fullUrl = queryString ? `${url}?${queryString}` : url;
        return this.request(fullUrl, {}, 'GET');
    },
    
    /**
     * POST request
     */
    post: function(url, data = {}) {
        return this.request(url, data, 'POST');
    }
};

// Data Table Helper
window.DataTable = {
    /**
     * Initialize data table with pagination
     */
    init: function(containerId, options = {}) {
        const defaultOptions = {
            page: 1,
            limit: 25,
            sortColumn: null,
            sortDirection: 'asc',
            filters: {},
            ajaxUrl: '',
            columns: [],
            actions: []
        };
        
        const config = { ...defaultOptions, ...options };
        
        return {
            config: config,
            
            load: function() {
                return this.loadData();
            },
            
            loadData: function() {
                const params = {
                    page: this.config.page,
                    limit: this.config.limit,
                    sort_column: this.config.sortColumn,
                    sort_direction: this.config.sortDirection,
                    ...this.config.filters
                };
                
                return AjaxHelper.get(this.config.ajaxUrl, params)
                    .then(response => {
                        if (response.success) {
                            this.renderTable(response.data);
                            this.renderPagination(response.pagination);
                            return response;
                        } else {
                            throw new Error(response.message || 'Failed to load data');
                        }
                    });
            },
            
            renderTable: function(records) {
                const container = document.getElementById(containerId);
                const tbody = container.querySelector('tbody');
                
                if (!records || records.length === 0) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="${this.config.columns.length + (this.config.actions.length > 0 ? 1 : 0)}" 
                                class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-2x mb-3 d-block"></i>
                                No records found
                            </td>
                        </tr>
                    `;
                    return;
                }
                
                const rows = records.map(record => {
                    let row = '<tr>';
                    
                    // Data columns
                    this.config.columns.forEach(column => {
                        let value = record[column.field] || '';
                        
                        if (column.formatter) {
                            value = column.formatter(value, record);
                        }
                        
                        row += `<td>${value}</td>`;
                    });
                    
                    // Actions column
                    if (this.config.actions.length > 0) {
                        row += '<td>';
                        this.config.actions.forEach(action => {
                            row += `<button class="btn btn-${action.class} btn-sm me-1" 
                                           onclick="${action.handler}(${record.id})" 
                                           title="${action.title}">
                                        <i class="${action.icon}"></i>
                                    </button>`;
                        });
                        row += '</td>';
                    }
                    
                    row += '</tr>';
                    return row;
                }).join('');
                
                tbody.innerHTML = rows;
            },
            
            renderPagination: function(pagination) {
                const paginationContainer = document.getElementById(containerId + '_pagination');
                if (!paginationContainer) return;
                
                if (pagination.total_pages <= 1) {
                    paginationContainer.innerHTML = '';
                    return;
                }
                
                let html = '<nav><ul class="pagination pagination-sm justify-content-center">';
                
                // Previous button
                if (pagination.current_page > 1) {
                    html += `<li class="page-item">
                                <a class="page-link" href="#" onclick="changePage(${pagination.current_page - 1})">
                                    <i class="fas fa-chevron-left"></i>
                                </a>
                             </li>`;
                }
                
                // Page numbers
                const startPage = Math.max(1, pagination.current_page - 2);
                const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);
                
                for (let i = startPage; i <= endPage; i++) {
                    const activeClass = i === pagination.current_page ? 'active' : '';
                    html += `<li class="page-item ${activeClass}">
                                <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
                             </li>`;
                }
                
                // Next button
                if (pagination.current_page < pagination.total_pages) {
                    html += `<li class="page-item">
                                <a class="page-link" href="#" onclick="changePage(${pagination.current_page + 1})">
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                             </li>`;
                }
                
                html += '</ul></nav>';
                paginationContainer.innerHTML = html;
                
                // Update info
                const infoContainer = document.getElementById(containerId + '_info');
                if (infoContainer) {
                    const start = ((pagination.current_page - 1) * pagination.records_per_page) + 1;
                    const end = Math.min(pagination.current_page * pagination.records_per_page, pagination.total_records);
                    infoContainer.textContent = `Showing ${start} to ${end} of ${pagination.total_records} entries`;
                }
            },
            
            changePage: function(page) {
                this.config.page = page;
                return this.loadData();
            },
            
            setFilters: function(filters) {
                this.config.filters = { ...this.config.filters, ...filters };
                this.config.page = 1; // Reset to first page
                return this.loadData();
            },
            
            sort: function(column) {
                if (this.config.sortColumn === column) {
                    this.config.sortDirection = this.config.sortDirection === 'asc' ? 'desc' : 'asc';
                } else {
                    this.config.sortColumn = column;
                    this.config.sortDirection = 'asc';
                }
                return this.loadData();
            }
        };
    }
};

// Initialize common functionality when DOM is ready
$(document).ready(function() {
    console.log('Common JavaScript loaded');
    
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert:not(.alert-permanent)').fadeOut();
    }, 5000);
    
    // Initialize tooltips
    if (typeof bootstrap !== 'undefined') {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function(tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
});