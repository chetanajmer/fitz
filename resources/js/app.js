// Alpine.js ships with Livewire 4 and is started by it — do not import or start Alpine here.
import Chart from 'chart.js/auto';
import './share-card';
import './streak';
import './qr-scanner';
import './motion';

window.Chart = Chart;

/** Reads a palette colour from the CSS variables in resources/css/app.css (the single source of truth). */
const paletteColor = (name, fallback) => getComputedStyle(document.documentElement).getPropertyValue(`--gym-${name}`).trim() || fallback;

/**
 * Theme colours for chart datasets, e.g. `backgroundColor: chartColors.primary`.
 * `*Soft` variants are the same colour with ~15% alpha for area fills.
 */
const primary = paletteColor('orange', '#ff6b00');
const primaryLight = paletteColor('orange-light', '#ff8c38');
const muted = paletteColor('muted', '#a3a3a3');
window.chartColors = {
    primary,
    primarySoft: `${primary}26`,
    primaryHalf: `${primary}59`,
    primaryHover: paletteColor('orange-hover', '#e85d00'),
    primaryLight,
    primaryLightSoft: `${primaryLight}26`,
    muted,
};

/** Chart label / grid colours for the current panel theme; re-applies to charts already drawn. */
const applyChartTheme = () => {
    const dark = document.body.classList.contains('theme-dark');
    Chart.defaults.color = dark ? muted : '#6b6b6b';
    Chart.defaults.borderColor = dark ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.08)';
    Object.values(Chart.instances).forEach((chart) => {
        Object.values(chart.options.scales ?? {}).forEach((scale) => {
            if (scale.ticks) scale.ticks.color = Chart.defaults.color;
            if (scale.grid) scale.grid.color = Chart.defaults.borderColor;
        });
        if (chart.options.plugins?.legend?.labels) chart.options.plugins.legend.labels.color = Chart.defaults.color;
        chart.update('none');
    });
};
applyChartTheme();

/* Panel light / dark theme switch ([data-theme-toggle]); the choice is kept in a cookie for a year. */
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-theme-toggle]');
    if (!toggle) return;
    const dark = !document.body.classList.contains('theme-dark');
    document.body.classList.toggle('theme-dark', dark);
    document.cookie = `theme=${dark ? 'dark' : 'light'}; path=/; max-age=31536000; samesite=lax`;
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => button.setAttribute('aria-pressed', String(dark)));
    applyChartTheme();
});

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
