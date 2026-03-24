/**
 * Dashboard JavaScript Controller
 */

class Dashboard {
    constructor() {
        this.charts = {};
        this.currentFilters = {};
    }
    
    init() {
        console.log('Dashboard initializing...');
        this.setupEventListeners();
        this.loadDashboardData();
    }
    
    setupEventListeners() {
        // Filter form submission
        $('#dashboardFilters').on('submit', (e) => {
            e.preventDefault();
            this.applyFilters();
        });
        
        // Reset filters
        $('#resetFilters').on('click', () => {
            this.resetFilters();
        });
    }
    
    applyFilters() {
        const formData = new FormData(document.getElementById('dashboardFilters'));
        this.currentFilters = {};
        
        for (let [key, value] of formData.entries()) {
            if (value) {
                this.currentFilters[key] = value;
            }
        }
        
        console.log('Applying filters:', this.currentFilters);
        this.loadDashboardData();
    }
    
    resetFilters() {
        document.getElementById('dashboardFilters').reset();
        
        // Set default dates (last 30 days)
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);
        
        document.getElementById('dateFrom').value = thirtyDaysAgo.toISOString().split('T')[0];
        document.getElementById('dateTo').value = today.toISOString().split('T')[0];
        
        this.currentFilters = {};
        this.loadDashboardData();
    }
    
    async loadDashboardData() {
        try {
            Utils.showLoading('Loading dashboard data...');
            
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + '/ajax/dashboard.php',
                { action: 'stats', ...this.currentFilters }
            );
            
            if (response.success) {
                // Handle both response.data.stats and direct response.data formats
                const stats = response.data.stats || response.data;
                this.updateStatistics(stats);
                this.updateCharts(response.data);
                this.updateTables(response.data);
                Utils.showToast('Dashboard data loaded successfully', 'success');
            } else {
                throw new Error(response.message || 'Failed to load dashboard data');
            }
            
        } catch (error) {
            console.error('Dashboard load error:', error);
            Utils.showToast('Failed to load dashboard data: ' + error.message, 'error');
            this.showErrorState();
        } finally {
            Utils.hideLoading();
        }
    }
    
    updateStatistics(stats) {
        console.log('Updating statistics:', stats);
        
        if (!stats) {
            console.error('No stats data provided');
            return;
        }
        
        // Update statistics cards with safe access
        const totalSalesElement = document.getElementById('totalSales');
        if (totalSalesElement) {
            totalSalesElement.textContent = parseInt(stats.total_sales || 0).toLocaleString();
        }
        
        const totalRevenueElement = document.getElementById('totalRevenue');
        if (totalRevenueElement) {
            totalRevenueElement.textContent = Utils.formatCurrency(stats.total_revenue || 0);
        }
        
        const totalProfitElement = document.getElementById('totalProfit');
        if (totalProfitElement) {
            totalProfitElement.textContent = Utils.formatCurrency(stats.total_profit || 0);
        }
        
        const avgMarginElement = document.getElementById('avgMargin');
        if (avgMarginElement) {
            avgMarginElement.textContent = Utils.formatPercentage(stats.avg_margin || 0);
        }
        
        console.log('Statistics updated successfully');
    }
    
    updateCharts(data) {
        console.log('Updating charts:', data);
        
        // Update monthly sales chart
        this.updateMonthlySalesChart(data.monthly_sales || []);
        
        // Update top products chart
        this.updateTopProductsChart(data.top_products || []);
    }
    
    updateMonthlySalesChart(monthlySales) {
        const ctx = document.getElementById('monthlySalesChart');
        if (!ctx) return;
        
        // Destroy existing chart
        if (this.charts.monthlySales) {
            this.charts.monthlySales.destroy();
            this.charts.monthlySales = null;
        }
        
        const data = Array.isArray(monthlySales) ? monthlySales : [];
        const labels = data.map(item => {
            const m = item.month || item.Month || '';
            if (!m || m.length < 6) return 'N/A';
            const date = new Date(m + '-01');
            return isNaN(date.getTime()) ? m : date.toLocaleDateString('en-US', { year: 'numeric', month: 'short' });
        });
        const revenueData = data.map(item => parseFloat(item.revenue || item.total_amount || 0) || 0);
        const salesData = data.map(item => parseInt(item.sales_count || 0) || 0);
        
        if (labels.length === 0) {
            labels.push('No data');
            revenueData.push(0);
            salesData.push(0);
        }
        
        // Create chart
        this.charts.monthlySales = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Revenue (A$)',
                        data: revenueData,
                        borderColor: '#14b8a6',
                        backgroundColor: 'rgba(20, 184, 166, 0.1)',
                        fill: true,
                        tension: 0.4,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Sales Count',
                        data: salesData,
                        borderColor: '#f59e0b',
                        backgroundColor: 'rgba(245, 158, 11, 0.1)',
                        fill: false,
                        tension: 0.4,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: '#f8fafc'
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            color: '#94a3b8'
                        },
                        grid: {
                            color: '#404040'
                        }
                    },
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        ticks: {
                            color: '#94a3b8',
                            callback: function(value) {
                                return 'A$' + value.toLocaleString();
                            }
                        },
                        grid: {
                            color: '#404040'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        ticks: {
                            color: '#94a3b8'
                        },
                        grid: {
                            drawOnChartArea: false,
                            color: '#404040'
                        }
                    }
                }
            }
        });
    }
    
    updateTopProductsChart(topProducts) {
        const ctx = document.getElementById('topProductsChart');
        if (!ctx) return;
        
        // Destroy existing chart
        if (this.charts.topProducts) {
            this.charts.topProducts.destroy();
        }
        
        // Take top 5 products
        const products = topProducts.slice(0, 5);
        
        // Prepare data
        const labels = products.map(item => item.product);
        const data = products.map(item => parseFloat(item.revenue || 0));
        
        // Generate colors
        const colors = [
            '#14b8a6', '#f59e0b', '#f43f5e', '#8b5cf6', '#10b981'
        ];
        
        // Create chart
        this.charts.topProducts = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderWidth: 2,
                    borderColor: '#2d2d2d'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: '#f8fafc',
                            padding: 20,
                            usePointStyle: true
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + Utils.formatCurrency(context.parsed);
                            }
                        }
                    }
                }
            }
        });
    }
    
    updateTables(data) {
        console.log('Updating tables:', data);
        
        // Update top products table
        this.updateTopProductsTable(data.top_products || []);
        
        // Update top businesses table
        this.updateTopBusinessesTable(data.top_businesses || []);
    }
    
    updateTopProductsTable(topProducts) {
        const tbody = document.getElementById('topProductsTable');
        if (!tbody) return;
        
        if (topProducts.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No data available</td></tr>';
            return;
        }
        
        const rows = topProducts.slice(0, 5).map(product => `
            <tr>
                <td>
                    <strong>${product.product}</strong>
                </td>
                <td>
                    <span class="text-success">${Utils.formatCurrency(product.revenue)}</span>
                </td>
                <td>
                    <span class="badge badge-info">${product.sales_count}</span>
                </td>
            </tr>
        `).join('');
        
        tbody.innerHTML = rows;
    }
    
    updateTopBusinessesTable(topBusinesses) {
        const tbody = document.getElementById('topBusinessesTable');
        if (!tbody) return;
        
        if (topBusinesses.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No data available</td></tr>';
            return;
        }
        
        const rows = topBusinesses.slice(0, 5).map(business => `
            <tr>
                <td>
                    <strong>${business.business_name}</strong>
                </td>
                <td>
                    <span class="text-success">${Utils.formatCurrency(business.revenue)}</span>
                </td>
                <td>
                    <span class="badge badge-info">${business.sales_count}</span>
                </td>
            </tr>
        `).join('');
        
        tbody.innerHTML = rows;
    }
    
    showErrorState() {
        // Show error state in statistics
        document.getElementById('totalSales').textContent = 'Error';
        document.getElementById('totalRevenue').textContent = 'Error';
        document.getElementById('totalProfit').textContent = 'Error';
        document.getElementById('avgMargin').textContent = 'Error';
        
        // Show error in tables
        document.getElementById('topProductsTable').innerHTML = 
            '<tr><td colspan="3" class="text-center text-danger">Failed to load data</td></tr>';
        
        document.getElementById('topBusinessesTable').innerHTML = 
            '<tr><td colspan="3" class="text-center text-danger">Failed to load data</td></tr>';
    }
}

// Initialize dashboard when DOM is ready
$(document).ready(function() {
    console.log('Dashboard script loaded');
});