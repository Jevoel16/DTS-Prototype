// Theme Chart.js text and grid lines to match light/dark mode, and re-theme live charts on toggle
(function () {
    function applyChartTheme() {
        var isDark = document.documentElement.classList.contains('dark');
        var text = isDark ? '#d1d5db' : '#4b5563';
        var grid = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';
        // Defaults cover charts created later
        Chart.defaults.color = text;
        Chart.defaults.borderColor = grid;
        // Existing charts have their colors baked into their options, so set them explicitly
        Object.values(Chart.instances).forEach(function (chart) {
            var o = chart.options;
            Object.values(o.scales || {}).forEach(function (s) {
                if (s.ticks) s.ticks.color = text;
                if (s.title) s.title.color = text;
                if (s.pointLabels) s.pointLabels.color = text;
                if (s.grid) s.grid.color = grid;
                if (s.angleLines) s.angleLines.color = grid;
            });
            if (o.plugins && o.plugins.legend && o.plugins.legend.labels) o.plugins.legend.labels.color = text;
            if (o.plugins && o.plugins.title) o.plugins.title.color = text;
            chart.update('none');
        });
    }
    applyChartTheme();
    new MutationObserver(applyChartTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
})();
