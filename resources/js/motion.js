/**
 * Small shared motion helpers (loaded with app.js on every page). No libraries.
 *
 *  - [data-count="42"]: counts up from 0 once the number scrolls into view. Optional
 *    data-count-label="₹1,200" is the text shown when done (formatted values).
 *  - .pm-loadbar: a thin progress bar shown while a same-tab link loads the next page.
 *  - .pm-qr: the QR card plays its scan line once when it first appears.
 *
 * With prefers-reduced-motion everything shows its final state at once.
 */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function countUp(el) {
    if (el.dataset.counted) return;
    el.dataset.counted = '1';
    const target = Number(el.dataset.count);
    const label = el.dataset.countLabel ?? el.textContent.trim();
    if (!Number.isFinite(target) || target <= 0 || reduceMotion) {
        el.textContent = label;
        return;
    }
    const start = performance.now();
    const duration = Math.min(1400, 500 + target * 25);
    const step = (now) => {
        const t = Math.min((now - start) / duration, 1);
        el.textContent = String(Math.round(target * (1 - Math.pow(1 - t, 3))));
        if (t < 1) {
            requestAnimationFrame(step);
        } else {
            el.textContent = label;
        }
    };
    requestAnimationFrame(step);
}

let counterObserver = null;

function observeCounters(root = document) {
    const counters = root.querySelectorAll('[data-count]:not([data-counted])');
    if (!counters.length) return;
    if (!('IntersectionObserver' in window)) {
        counters.forEach(countUp);
        return;
    }
    counterObserver ??= new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                countUp(entry.target);
                counterObserver.unobserve(entry.target);
            }
        });
    }, { threshold: 0.4 });
    counters.forEach((el) => counterObserver.observe(el));
}

function setupLoadBar() {
    const bar = document.querySelector('.pm-loadbar');
    if (!bar || reduceMotion) return;
    document.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');
        if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        if (link.target === '_blank' || link.hasAttribute('download') || link.origin !== location.origin) return;
        if (link.pathname === location.pathname && link.hash) return;
        bar.classList.add('is-loading');
    });
    // Back/forward cache: never leave the bar running on a restored page.
    window.addEventListener('pageshow', () => bar.classList.remove('is-loading'));
}

function init() {
    observeCounters();
    setupLoadBar();
    document.querySelectorAll('.pm-qr').forEach((qr) => qr.classList.add('is-scanning'));
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

// Livewire may add new numbers after an update; count those too (already-counted ones are skipped).
document.addEventListener('livewire:init', () => {
    window.Livewire?.hook?.('morphed', ({ el }) => observeCounters(el));
});
