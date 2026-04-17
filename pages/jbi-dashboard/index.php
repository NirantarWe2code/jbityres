<?php
/**
 * JBI full dashboard embedded into jbityres as a separate page.
 */

require_once __DIR__ . '/../../config/config.php';
requirePermission('dashboard.view');

$pageTitle = 'JBI Full Dashboard';
$breadcrumbs = [
    ['title' => 'Home', 'url' => BASE_URL . '/pages/dashboard/index.php'],
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