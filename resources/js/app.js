// Alpine.js ships with Livewire 4 and is started by it — do not import or start Alpine here.
import Chart from 'chart.js/auto';

window.Chart = Chart;

// Panels use the dark theme: muted labels and faint grid lines.
if (document.body.classList.contains('theme-dark')) {
    Chart.defaults.color = '#9a9aa3';
    Chart.defaults.borderColor = 'rgba(255,255,255,0.06)';
}

/**
 * Small Chart.js helper used from Alpine: x-data x-init="renderChart($refs.canvas, config)".
 * Destroys any previous chart on the same canvas (Livewire re-renders).
 */
window.renderChart = (canvas, config) => {
    const existing = Chart.getChart(canvas);
    if (existing) existing.destroy();

    return new Chart(canvas, {
        ...config,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: (config.data?.datasets?.length ?? 0) > 1, position: 'bottom' } },
            scales: config.type === 'doughnut' ? {} : { y: { beginAtZero: true, ticks: { precision: 0 } } },
            ...(config.options || {}),
        },
    });
};
