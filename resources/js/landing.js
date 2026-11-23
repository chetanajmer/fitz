/**
 * Public landing page interactions (welcome.blade.php).
 * Motion lives in CSS; this file only toggles classes and small values,
 * and honours prefers-reduced-motion.
 */
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const inr = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 });

/* Sticky nav: transparent at the top, dark once scrolled. */
const nav = document.querySelector('[data-lp-nav]');
const syncNav = () => nav?.classList.toggle('is-scrolled', window.scrollY > 12);
syncNav();
window.addEventListener('scroll', syncNav, { passive: true });

/* Mobile menu. */
const menu = document.querySelector('[data-lp-menu]');
const menuToggle = document.querySelector('[data-lp-menu-toggle]');
const setMenu = (open) => {
    if (!menu || !menuToggle) return;
    menu.hidden = !open;
    menuToggle.setAttribute('aria-expanded', String(open));
    if (open) nav?.classList.add('is-scrolled');
    else syncNav();
};
menuToggle?.addEventListener('click', () => setMenu(menu.hidden));
menu?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenu(false)));

/* Animated counters. */
const animateCount = (el) => {
    const target = Number(el.dataset.count);
    if (!Number.isFinite(target) || reduceMotion || target === 0) {
        el.textContent = inr.format(target || 0);
        return;
    }
    const duration = 1600;
    const start = performance.now();
    const tick = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - progress, 3);
        el.textContent = inr.format(Math.round(target * eased));
        if (progress < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
};

/* Scroll reveal: adds .is-in once, and kicks off counters inside. */
const revealTargets = document.querySelectorAll('.lp-reveal');
if ('IntersectionObserver' in window) {
    const revealObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                entry.target.classList.add('is-in');
                entry.target.querySelectorAll('[data-count]').forEach(animateCount);
                revealObserver.unobserve(entry.target);
            });
        },
        { threshold: 0.15, rootMargin: '0px 0px -40px 0px' },
    );
    revealTargets.forEach((el) => revealObserver.observe(el));

    /* Highlight the nav link of the section in view. */
    const links = document.querySelectorAll('[data-lp-navlink]');
    const sectionObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) return;
                links.forEach((link) => link.classList.toggle('is-active', link.dataset.lpNavlink === entry.target.id));
            });
        },
        { rootMargin: '-45% 0px -50% 0px' },
    );
    document.querySelectorAll('[data-lp-section]').forEach((section) => sectionObserver.observe(section));
} else {
    revealTargets.forEach((el) => el.classList.add('is-in'));
}

/* 3D hero tilt that follows the pointer (desktop pointers only). */
const tilt = document.querySelector('[data-lp-tilt]');
if (tilt && !reduceMotion && window.matchMedia('(pointer: fine)').matches) {
    const card = tilt.querySelector('.lp-tilt');
    const hero = tilt.closest('section');
    hero?.addEventListener('pointermove', (event) => {
        const rect = tilt.getBoundingClientRect();
        const x = (event.clientX - rect.left) / rect.width - 0.5;
        const y = (event.clientY - rect.top) / rect.height - 0.5;
        card.style.setProperty('--ry', `${-12 + x * 14}deg`);
        card.style.setProperty('--rx', `${14 - y * 12}deg`);
    });
    hero?.addEventListener('pointerleave', () => {
        card.style.removeProperty('--ry');
        card.style.removeProperty('--rx');
    });
}

/* Pointer-following glow on cards. */
document.querySelectorAll('[data-lp-glow]').forEach((card) => {
    card.addEventListener('pointermove', (event) => {
        const rect = card.getBoundingClientRect();
        card.style.setProperty('--mx', `${event.clientX - rect.left}px`);
        card.style.setProperty('--my', `${event.clientY - rect.top}px`);
    });
});

/* Testimonial slider. */
document.querySelectorAll('[data-lp-slider]').forEach((slider) => {
    const track = slider.querySelector('[data-lp-slider-track]');
    const dots = [...slider.querySelectorAll('[data-lp-slider-dot]')];
    const count = track.children.length;
    let index = 0;
    let timer = null;

    const go = (next) => {
        index = (next + count) % count;
        track.style.transform = `translateX(-${index * 100}%)`;
        dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
        [...track.children].forEach((slide, i) => slide.setAttribute('aria-hidden', String(i !== index)));
    };
    const play = () => {
        if (reduceMotion) return;
        clearInterval(timer);
        timer = setInterval(() => go(index + 1), 6000);
    };

    slider.querySelector('[data-lp-slider-prev]')?.addEventListener('click', () => { go(index - 1); play(); });
    slider.querySelector('[data-lp-slider-next]')?.addEventListener('click', () => { go(index + 1); play(); });
    dots.forEach((dot, i) => dot.addEventListener('click', () => { go(i); play(); }));
    slider.addEventListener('pointerenter', () => clearInterval(timer));
    slider.addEventListener('pointerleave', play);
    slider.addEventListener('focusin', () => clearInterval(timer));

    go(0);
    play();
});

/* Pricing calculator: members × live per-member rate. */
document.querySelectorAll('[data-lp-calc]').forEach((calc) => {
    const rate = Number(calc.dataset.rate) || 0;
    const input = calc.querySelector('[data-lp-calc-input]');
    const members = calc.querySelector('[data-lp-calc-members]');
    const total = calc.querySelector('[data-lp-calc-total]');
    const formula = calc.querySelector('[data-lp-calc-formula]');

    const update = () => {
        const count = Number(input.value);
        members.textContent = inr.format(count);
        formula.textContent = inr.format(count);
        total.textContent = `₹${inr.format(count * rate)}`;
    };
    input.addEventListener('input', update);
    update();
});
