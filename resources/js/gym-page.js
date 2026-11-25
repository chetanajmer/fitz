/**
 * Public gym page motion (public/gym-profile.blade.php). Plain JS, no libraries:
 * scroll reveals via IntersectionObserver (count-ups are shared, in motion.js), a reading-progress bar, the sticky nav state,
 * active section links, and a light hero parallax on desktop pointers. Everything is readable without
 * JavaScript, and with prefers-reduced-motion nothing moves (CSS shows the final states).
 */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const desktopPointer = window.matchMedia('(pointer: fine) and (min-width: 1024px)').matches;
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

/* ------------------------------------------------- Reveals (count-ups: [data-count] in js/motion.js) */
const revealables = $$('.gp-reveal, .gp-img-reveal');
if ('IntersectionObserver' in window && !reduceMotion) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-in');
            observer.unobserve(entry.target);
        });
    }, { rootMargin: '0px 0px -10% 0px', threshold: 0.12 });
    revealables.forEach((el) => observer.observe(el));
} else {
    revealables.forEach((el) => el.classList.add('is-in'));
}

/* ------------------------------------- Active nav link for the section in view */
const navLinks = $$('[data-gp-nav-link]');
if (navLinks.length && 'IntersectionObserver' in window) {
    const byId = new Map(navLinks.map((link) => [link.getAttribute('href').slice(1), link]));
    const sectionObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            navLinks.forEach((link) => link.classList.remove('is-active'));
            byId.get(entry.target.id)?.classList.add('is-active');
        });
    }, { rootMargin: '-45% 0px -50% 0px' });
    byId.forEach((_, id) => {
        const section = document.getElementById(id);
        if (section) sectionObserver.observe(section);
    });
}

/* ----------------------- Scroll work, batched into one frame: progress, nav, parallax */
const progress = document.querySelector('[data-gp-progress]');
const nav = document.querySelector('[data-gp-nav]');
const parallax = desktopPointer && !reduceMotion ? $$('[data-gp-parallax]') : [];
let ticking = false;

const onScroll = () => {
    const y = window.scrollY;
    const max = document.documentElement.scrollHeight - window.innerHeight;
    if (progress) progress.style.transform = `scaleX(${max > 0 ? Math.min(y / max, 1) : 0})`;
    nav?.classList.toggle('is-scrolled', y > 12);
    if (y < window.innerHeight * 1.2) {
        parallax.forEach((el) => {
            el.style.translate = `0 ${(y * Number(el.dataset.gpParallax || 0.1)).toFixed(1)}px`;
        });
    }
    ticking = false;
};

window.addEventListener('scroll', () => {
    if (!ticking) {
        ticking = true;
        requestAnimationFrame(onScroll);
    }
}, { passive: true });
onScroll();

/* ------------------------------ Hero cards lean slightly towards the pointer (desktop only) */
const hero = document.querySelector('[data-gp-hero]');
const tilt = desktopPointer && !reduceMotion ? $$('[data-gp-tilt]') : [];
if (hero && tilt.length) {
    let frame = 0;
    hero.addEventListener('pointermove', (event) => {
        cancelAnimationFrame(frame);
        frame = requestAnimationFrame(() => {
            const rect = hero.getBoundingClientRect();
            const x = (event.clientX - rect.left) / rect.width - 0.5;
            const y = (event.clientY - rect.top) / rect.height - 0.5;
            tilt.forEach((el) => {
                const depth = Number(el.dataset.gpTilt || 10);
                el.style.transform = `translate3d(${(x * depth).toFixed(1)}px, ${(y * depth).toFixed(1)}px, 0)`;
            });
        });
    });
    hero.addEventListener('pointerleave', () => tilt.forEach((el) => { el.style.transform = ''; }));
}
