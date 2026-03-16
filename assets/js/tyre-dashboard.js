/**
 * Tyre Analytics Dashboard - based on TyreDashboard.jsx + formulas.html
 * Uses Chart.js, connects to finalReport backend
 */

const TYRE_C = {
    bg: '#0A0F1E',
    surface: '#111827',
    card: '#151D2E',
    border: '#1E2D45',
    teal: '#00D4C8',
    gold: '#FFB74D',
    rose: '#F87171',
    blue: '#60A5FA',
    purple: '#A78BFA',
    green: '#34D399',
    text: '#E2E8F0',
    muted: '#64748B',
    dim: '#334155'
};

const YEAR_COLORS = ['#00D4C8', '#FFB74D', '#60A5FA', '#A78BFA', '#34D399'];
const BRAND_COLORS = ['#00D4C8', '#FFB74D', '#60A5FA', '#A78BFA', '#34D399', '#F87171', '#FB923C', '#E879F9'];

// Formulas from formulas.html
function fmtAUD(v) {
    if (v == null || isNaN(v)) return '\u2014';
    const abs = Math.abs(v);
    if (abs >= 1e6) return 'A$' + (v / 1e6).toFixed(2) + 'M';
    if (abs >= 1e3) return 'A$' + (v / 1e3).toFixed(0) + 'K';
    return 'A$' + v.toFixed(0);
}
function fmtAUDf(v) {
    if (v == null || isNaN(v)) return '\u2014';
    return 'A$' + v.toLocaleString('en-AU', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
}
function fmtPct(v) {
    if (v == null || isNaN(v)) return '\u2014';
    return v.toFixed(1) + '%';
}
function fmtNum(v) {
    if (v == null || isNaN(v)) return '\u2014';
    return v.toLocaleString('en-AU');
}

// Margin tiers from formulas.html: <0 red, 0-8 amber, 8-12 green, >=12 teal
function getMarginColor(margin) {
    const m = parseFloat(margin || 0);
    if (m < 0) return TYRE_C.rose;
    if (m < 8) return TYRE_C.gold;
    if (m < 12) return TYRE_C.green;
    return TYRE_C.teal;
}

class TyreDashboard {
    constructor() {
        this.charts = {};
        this.currentFilters = {};
        this.currentView = 'overview';
    }

    init() {
        this.setupEventListeners();
        this.loadAnalytics();
    }

    setupEventListeners() {
        $('#tyreFilters').on('submit', (e) => {
            e.preventDefault();
            this.applyFilters();
        });
        $('#tyreResetFilters').on('click', () => this.resetFilters());
        $('[data-tyre-view]').on('click', (e) => {
            this.currentView = $(e.currentTarget).data('tyre-view');
            this.renderView();
        });
    }

    applyFilters() {
        const form = document.getElementById('tyreFilters');
        this.currentFilters = {};
        new FormData(form).forEach((v, k) => { if (v) this.currentFilters[k] = v; });
        this.loadAnalytics();
    }

    resetFilters() {
        document.getElementById('tyreFilters').reset();
        const today = new Date();
        const d30 = new Date(today);
        d30.setDate(d30.getDate() - 30);
        document.getElementById('tyreDateFrom').value = d30.toISOString().split('T')[0];
        document.getElementById('tyreDateTo').value = today.toISOString().split('T')[0];
        this.currentFilters = {};
        this.loadAnalytics();
    }

    async loadAnalytics() {
        try {
            Utils.showLoading('Loading tyre analytics...');
            const params = { action: 'analytics', ...this.currentFilters };
            const response = await AjaxHelper.get(
                window.AppConfig.baseUrl + '/ajax/tyre_analytics.php',
                params
            );
            if (response.success) {
                this.data = response.data;
                this.renderAll();
                Utils.showToast('Analytics loaded', 'success');
            } else {
                throw new Error(response.message || 'Failed to load analytics');
            }
        } catch (err) {
            console.error('Tyre analytics error:', err);
            if (typeof Utils !== 'undefined' && Utils.showToast) Utils.showToast('Failed to load analytics', 'error');
            const el = document.getElementById('tyreTabContent');
            if (el) el.innerHTML = '<div class="card"><div class="card-body text-center py-5 text-danger">Failed to load analytics. Check console for details.</div></div>';
        } finally {
            Utils.hideLoading();
        }
    }

    renderAll() {
        if (!this.data) return;
        this.renderKPICards();
        this.renderView();
    }

    renderKPICards() {
        const t = this.data.totals || {};
        const set = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = val; };
        set('kpiRevenue', fmtAUD(t.revenue));
        set('kpiRevenueGst', 'inc-GST: ' + fmtAUD(t.revenue_inc_gst));
        set('kpiProfit', fmtAUD(t.profit));
        set('kpiMargin', 'Margin: ' + fmtPct(t.margin));
        set('kpiUnits', fmtNum(Math.round(t.units)));
        set('kpiCustomers', (t.customers || 0) + ' customers');
        set('kpiInvoices', fmtNum(t.invoices));
        set('kpiAvgInvoice', fmtAUD(t.avg_invoice));
    }

    renderView() {
        // Render all panels (Bootstrap tabs handle visibility)
        this.renderOverview();
        this.renderMonthly();
        this.renderBrands();
        this.renderCustomers();
        this.renderReps();
        this.renderActivity();
    }

    _destroyChart(canvas) {
        if (!canvas || typeof Chart === 'undefined') return;
        try {
            if (Chart.getChart) {
                const existing = Chart.getChart(canvas);
                if (existing) existing.destroy();
            }
        } catch (e) { /* ignore */ }
    }

    /** Replace canvas with fresh clone to avoid "already in use" error */
    _getFreshCanvas(id) {
        const old = document.getElementById(id);
        if (!old) return null;
        this._destroyChart(old);
        if (this.charts.monthlyRevenue && id === 'tyreChartMonthly') { this.charts.monthlyRevenue.destroy(); this.charts.monthlyRevenue = null; }
        if (this.charts.brandRevenue && id === 'tyreChartBrands') { this.charts.brandRevenue.destroy(); this.charts.brandRevenue = null; }
        if (this.charts.marginChart && id === 'tyreChartMargin') { this.charts.marginChart.destroy(); this.charts.marginChart = null; }
        try {
            const existing = Chart.getChart ? Chart.getChart(old) : null;
            if (existing) existing.destroy();
        } catch (e) { /* ignore */ }
        const clone = old.cloneNode(true);
        old.parentNode.replaceChild(clone, old);
        return clone;
    }

    renderOverview() {
        const monthly = this.data.monthly || [];
        let ctx = document.getElementById('tyreChartMonthly');
        if (ctx && monthly.length) {
            ctx = this._getFreshCanvas('tyreChartMonthly') || ctx;
            this.charts.monthlyRevenue = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: monthly.map(m => m.month),
                    datasets: [{
                        label: 'Revenue ex-GST',
                        data: monthly.map(m => m.revenue),
                        backgroundColor: TYRE_C.teal + '80',
                        borderColor: TYRE_C.teal,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: TYRE_C.text } } },
                    scales: {
                        x: { ticks: { color: TYRE_C.muted } },
                        y: { ticks: { color: TYRE_C.muted, callback: v => fmtAUD(v) } }
                    }
                }
            });
        }

        const brands = (this.data.brands || []).slice(0, 8);
        let ctx2 = document.getElementById('tyreChartBrands');
        if (ctx2 && brands.length) {
            ctx2 = this._getFreshCanvas('tyreChartBrands') || ctx2;
            this.charts.brandRevenue = new Chart(ctx2, {
                type: 'bar',
                data: {
                    labels: brands.map(b => b.brand),
                    datasets: [{
                        label: 'Revenue',
                        data: brands.map(b => b.revenue),
                        backgroundColor: brands.map(b => getMarginColor(b.margin) + '99'),
                        borderColor: brands.map(b => getMarginColor(b.margin)),
                        borderWidth: 1
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { ticks: { color: TYRE_C.muted, callback: v => fmtAUD(v) } },
                        y: { ticks: { color: TYRE_C.text } }
                    }
                }
            });
        }

        let ctx3 = document.getElementById('tyreChartMargin');
        if (ctx3 && monthly.length) {
            ctx3 = this._getFreshCanvas('tyreChartMargin') || ctx3;
            this.charts.marginChart = new Chart(ctx3, {
                type: 'line',
                data: {
                    labels: monthly.map(m => m.month),
                    datasets: [{
                        label: 'GP Margin %',
                        data: monthly.map(m => m.margin),
                        borderColor: TYRE_C.gold,
                        backgroundColor: TYRE_C.gold + '20',
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { labels: { color: TYRE_C.text } } },
                    scales: {
                        x: { ticks: { color: TYRE_C.muted } },
                        y: { ticks: { color: TYRE_C.muted, callback: v => v + '%' } }
                    }
                }
            });
        }
    }

    renderMonthly() {
        const monthly = this.data.monthly || [];
        const t = this.data.totals || {};
        let html = '';
        monthly.forEach((m, i) => {
            const prev = i > 0 ? monthly[i - 1] : null;
            const chg = prev && prev.revenue ? ((m.revenue - prev.revenue) / prev.revenue * 100) : null;
            const marginClr = getMarginColor(m.margin);
            html += `<tr>
                <td class="text-end fw-bold">${m.month}</td>
                <td class="text-end">${fmtAUDf(m.revenue)}</td>
                <td class="text-end text-muted">${fmtAUDf(m.revenue * 1.1)}</td>
                <td class="text-end">${fmtNum(Math.round(m.units))}</td>
                <td class="text-end">${fmtNum(m.invoices)}</td>
                <td class="text-end">${fmtAUDf(m.profit)}</td>
                <td class="text-end" style="color:${marginClr}">${fmtPct(m.margin)}</td>
                <td class="text-end" style="color:${chg != null ? (chg >= 0 ? TYRE_C.green : TYRE_C.rose) : TYRE_C.muted}">${chg != null ? (chg >= 0 ? '\u25B2 ' : '\u25BC ') + Math.abs(chg).toFixed(1) + '%' : '\u2014'}</td>
            </tr>`;
        });
        html += `<tr class="border-top border-teal"><td class="text-end fw-bold">TOTAL</td><td class="text-end fw-bold">${fmtAUDf(t.revenue)}</td><td class="text-end text-muted">${fmtAUDf(t.revenue_inc_gst)}</td><td class="text-end fw-bold">${fmtNum(Math.round(t.units))}</td><td class="text-end fw-bold">${fmtNum(t.invoices)}</td><td class="text-end fw-bold">${fmtAUDf(t.profit)}</td><td class="text-end fw-bold">${fmtPct(t.margin)}</td><td></td></tr>`;
        const tbl = document.getElementById('tyreMonthlyTable');
        const tbody = tbl?.querySelector('tbody');
        if (tbody) tbody.innerHTML = html;
    }

    renderBrands() {
        const brands = this.data.brands || [];
        const t = this.data.totals || {};
        let html = '';
        brands.forEach((b, i) => {
            const marginClr = getMarginColor(b.margin);
            const revPct = t.revenue ? (b.revenue / t.revenue * 100) : 0;
            html += `<tr>
                <td>${i + 1}</td>
                <td class="fw-bold">${b.brand || '(blank)'}</td>
                <td class="text-end">${fmtAUDf(b.revenue)}</td>
                <td class="text-end text-muted">${fmtPct(revPct)}</td>
                <td class="text-end">${fmtNum(Math.round(b.units))}</td>
                <td class="text-end" style="color:${b.profit < 0 ? TYRE_C.rose : TYRE_C.gold}">${fmtAUDf(b.profit)}</td>
                <td class="text-end fw-bold" style="color:${marginClr}">${fmtPct(b.margin)}</td>
            </tr>`;
        });
        html += '';
        const brandsTbl = document.getElementById('tyreBrandsTable');
        const brandsTbody = brandsTbl?.querySelector('tbody');
        if (brandsTbody) brandsTbody.innerHTML = html;
    }

    renderCustomers() {
        const customers = this.data.customers || [];
        const t = this.data.totals || {};
        let html = '';
        customers.forEach((c, i) => {
            const marginClr = getMarginColor(c.margin);
            const revPct = t.revenue ? (c.revenue / t.revenue * 100) : 0;
            html += `<tr>
                <td>${i + 1}</td>
                <td class="text-truncate" style="max-width:200px" title="${(c.customer || '').replace(/"/g, '&quot;')}">${c.customer || '(blank)'}</td>
                <td class="text-end">${fmtAUDf(c.revenue)}</td>
                <td class="text-end text-muted">${fmtPct(revPct)}</td>
                <td class="text-end">${fmtNum(Math.round(c.units))}</td>
                <td class="text-end">${fmtNum(c.invoices)}</td>
                <td class="text-end fw-bold" style="color:${marginClr}">${fmtPct(c.margin)}</td>
            </tr>`;
        });
        html += '';
        const custTbl = document.getElementById('tyreCustomersTable');
        const custTbody = custTbl?.querySelector('tbody');
        if (custTbody) custTbody.innerHTML = html;
    }

    renderReps() {
        const reps = this.data.reps || [];
        const t = this.data.totals || {};
        let html = '<div class="row">';
        reps.forEach((rep, i) => {
            const marginClr = getMarginColor(rep.margin);
            const share = t.revenue ? (rep.revenue / t.revenue * 100) : 0;
            html += `<div class="col-md-6 col-lg-4 mb-3">
                <div class="card h-100" style="border-left:4px solid ${YEAR_COLORS[i % YEAR_COLORS.length]}">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">${rep.rep || '(Unknown)'}</h6>
                        <div class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">Revenue ex-GST</span><span>${fmtAUDf(rep.revenue)}</span></div>
                        <div class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">Gross Profit</span><span>${fmtAUDf(rep.profit)}</span></div>
                        <div class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">GP Margin</span><span style="color:${marginClr}">${fmtPct(rep.margin)}</span></div>
                        <div class="d-flex justify-content-between py-1 border-bottom"><span class="text-muted">Units</span><span>${fmtNum(Math.round(rep.units))}</span></div>
                        <div class="d-flex justify-content-between py-1"><span class="text-muted">Rev Share</span><span>${fmtPct(share)}</span></div>
                    </div>
                </div>
            </div>`;
        });
        html += '</div>';
        const el = document.getElementById('tyreRepsContent');
        if (el) el.innerHTML = html || '<p class="text-muted">No rep data</p>';
    }

    renderActivity() {
        const byDay = this.data.by_day || [];
        const byHour = this.data.by_hour || [];
        const byArea = this.data.byArea || [];

        if (byDay.length) {
            if (this.charts.byDayChart) this.charts.byDayChart.destroy();
            const ctx = document.getElementById('tyreByDayChart');
            if (ctx) {
                this.charts.byDayChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: byDay.map(d => d.day),
                        datasets: [{ label: 'Invoices', data: byDay.map(d => d.invoices), backgroundColor: TYRE_C.teal + '80', borderColor: TYRE_C.teal }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: TYRE_C.muted } }, y: { ticks: { color: TYRE_C.muted } } } }
                });
            }
        }

        if (byHour.length) {
            if (this.charts.byHourChart) this.charts.byHourChart.destroy();
            const ctx = document.getElementById('tyreByHourChart');
            if (ctx) {
                this.charts.byHourChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: byHour.map(h => h.label),
                        datasets: [{ label: 'Invoices', data: byHour.map(h => h.invoices), backgroundColor: TYRE_C.blue + '80', borderColor: TYRE_C.blue }]
                    },
                    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: TYRE_C.muted } }, y: { ticks: { color: TYRE_C.muted } } } }
                });
            }
        }

        let areaHtml = '';
        if (byArea.length) {
            byArea.forEach((a, i) => {
                const marginClr = getMarginColor(a.margin);
                areaHtml += `<tr><td>${i + 1}</td><td>${a.area || '(Unknown)'}</td><td class="text-end">${fmtAUDf(a.revenue)}</td><td class="text-end">${fmtNum(Math.round(a.units))}</td><td class="text-end fw-bold" style="color:${marginClr}">${fmtPct(a.margin)}</td></tr>`;
            });
        } else {
            areaHtml = '<tr><td colspan="5" class="text-center text-muted py-4">No delivery area data (requires delivery_profile column)</td></tr>';
        }
        const areaTbl = document.getElementById('tyreAreasTable');
        const areaTbody = areaTbl?.querySelector('tbody');
        if (areaTbody) areaTbody.innerHTML = areaHtml;
    }
}

$(document).ready(function() {
    if (typeof TyreDashboard !== 'undefined') {
        window.tyreDashboard = new TyreDashboard();
        window.tyreDashboard.init();
    }
});
