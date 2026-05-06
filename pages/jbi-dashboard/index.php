<?php
/**
 * JBI full dashboard embedded into jbityres as a separate page.
 */

require_once __DIR__ . '/../../config/config.php';
requirePermission('dashboard.view');

$pageTitle = 'JBI Full Dashboard';
$breadcrumbs = [
    ['title' => 'Home', 'url' => BASE_URL . '/pages/jbi-dashboard/index.php'],
    ['title' => 'JBI Full Dashboard']
];

$inlineScripts = <<<JS
window.addEventListener('load', function() {
    var loader = document.getElementById('jbi-dashboard-loader');
    var content = document.getElementById('jbi-dashboard-content');

    if (content) {
        content.style.visibility = 'visible';
    }
    if (loader) {
        loader.style.display = 'none';
    }

    var CHART_TEXT = '#F8FAFC';
    var CHART_GRID = 'rgba(148, 163, 184, 0.35)';

    document.querySelectorAll('canvas.dashboard-chart').forEach(function(canvas) {
        var id = canvas.dataset.chartId;
        if (!id) {
            return;
        }
        var jsonScript = document.querySelector('script.chart-data[data-chart-id="' + id + '"]');
        if (!jsonScript) {
            return;
        }
        try {
            var config = JSON.parse(jsonScript.textContent || jsonScript.innerText || '{}');
            if (config && typeof Chart === 'function') {
                // Reference style: clean dark UI, high-contrast labels, readable lines/bars.
                config.options = config.options || {};
                config.options.maintainAspectRatio = false;
                config.options.interaction = config.options.interaction || { mode: 'index', intersect: false };
                config.options.plugins = config.options.plugins || {};
                config.options.plugins.legend = config.options.plugins.legend || {};
                config.options.plugins.legend.labels = config.options.plugins.legend.labels || {};
                config.options.plugins.legend.labels.color = CHART_TEXT;
                config.options.plugins.legend.labels.usePointStyle = true;
                config.options.plugins.legend.labels.boxWidth = 12;
                config.options.plugins.legend.labels.boxHeight = 12;

                if (config.options.scales) {
                    Object.keys(config.options.scales).forEach(function(axisKey) {
                        var axis = config.options.scales[axisKey] || {};
                        axis.ticks = axis.ticks || {};
                        axis.ticks.color = axis.ticks.color || CHART_TEXT;
                        if (axisKey === 'x') {
                            axis.ticks.autoSkip = false;
                            axis.ticks.maxRotation = 0;
                            axis.ticks.minRotation = 0;
                        }
                        axis.grid = axis.grid || {};
                        if (axis.grid.display !== false) {
                            axis.grid.color = axis.grid.color || CHART_GRID;
                            axis.grid.drawBorder = false;
                        }
                        config.options.scales[axisKey] = axis;
                    });
                }

                if (config.data && Array.isArray(config.data.datasets)) {
                    config.data.datasets.forEach(function(ds) {
                        if (config.type === 'line') {
                            ds.borderWidth = Math.max(3, Number(ds.borderWidth || 0));
                            ds.pointRadius = Math.max(4, Number(ds.pointRadius || 0));
                            ds.pointHoverRadius = Math.max(5, Number(ds.pointHoverRadius || 0));
                            ds.pointBorderWidth = Math.max(1.5, Number(ds.pointBorderWidth || 0));
                            ds.spanGaps = true;
                        } else if (config.type === 'bar') {
                            ds.borderWidth = Math.max(1.2, Number(ds.borderWidth || 0));
                            ds.borderSkipped = false;
                            if (!ds.borderColor) {
                                ds.borderColor = 'rgba(248, 250, 252, 0.25)';
                            }
                        }
                    });
                }

                new Chart(canvas, config);
            }
        } catch (err) {
            console.error('Dashboard chart init failed for', id, err);
        }
    });
});
JS;

include __DIR__ . '/../../includes/header.php';
?>

<div id="jbi-dashboard-loader" style="position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(10,15,30,0.85);z-index:1050;display:flex;align-items:center;justify-content:center;">
    <div class="text-center text-white">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="mt-3">Loading JBI Dashboard…</div>
    </div>
</div>

<div id="jbi-dashboard-content" style="visibility:hidden;">
    <?php require __DIR__ . '/maindashboard.php'; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>