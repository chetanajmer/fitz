/**
 * Public landing page motion (welcome.blade.php).
 *
 * GSAP + ScrollTrigger drive scroll animations, SplitText reveals headings and Lenis
 * smooths wheel scrolling on desktop. Everything degrades to static, readable content when
 * JavaScript is off or prefers-reduced-motion is set; touch devices skip the
 * pointer-driven effects and smooth scrolling (no scroll hijacking).
 */
import 'lenis/dist/lenis.css';
import Lenis from 'lenis';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';

gsap.registerPlugin(ScrollTrigger, SplitText);

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = window.matchMedia('(pointer: fine)').matches;
const inr = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 0 });
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

/* ---------------------------------------------------------------- Loader */
const loader = $('[data-lp-loader]');
const hideLoader = () => {
    if (!loader || loader.dataset.done) return;
    loader.dataset.done = '1';
    // Safety net: remove even if animation frames are throttled (background tab, low-power mode).
    setTimeout(() => loader.isConnected && loader.remove(), 1200);
    if (reduceMotion) {
        loader.remove();
        heroEntrance();
        return;
    }
    gsap.timeline({ onComplete: () => loader.remove() })
        .to('[data-lp-loader-bar]', { scaleX: 1, duration: 0.35, ease: 'power2.out' })
        .to(loader, { autoAlpha: 0, duration: 0.45, ease: 'power2.inOut' })
        .add(heroEntrance, '-=0.25');
};
// Never hold the page: hide on load, or after 1.2s at the latest.
window.addEventListener('load', () => setTimeout(hideLoader, 150));
setTimeout(hideLoader, 1200);

/* --------------------------------------------------------- Smooth scroll */
let lenis = null;
if (!reduceMotion && finePointer) {
    lenis = new Lenis({ lerp: 0.12, anchors: { offset: -72 } });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);
}

/* ------------------------------------------------------------ Navigation */
const nav = $('[data-lp-nav]');
const syncNav = () => nav?.classList.toggle('is-scrolled', window.scrollY > 12);
syncNav();
window.addEventListener('scroll', syncNav, { passive: true });

const menu = $('[data-lp-menu]');
const menuToggle = $('[data-lp-menu-toggle]');
const setMenu = (open) => {
    if (!menu || !menuToggle) return;
    menuToggle.setAttribute('aria-expanded', String(open));
    menuToggle.classList.toggle('is-open', open);
    if (open) {
        menu.hidden = false;
        nav?.classList.add('is-scrolled');
        if (!reduceMotion) {
            gsap.fromTo(menu, { autoAlpha: 0, y: -8 }, { autoAlpha: 1, y: 0, duration: 0.25, ease: 'power2.out' });
            gsap.fromTo($$('a', menu), { autoAlpha: 0, x: -12 }, { autoAlpha: 1, x: 0, duration: 0.3, stagger: 0.03, ease: 'power2.out' });
        }
    } else {
        menu.hidden = true;
        syncNav();
    }
};
menuToggle?.addEventListener('click', () => setMenu(menu.hidden));
$$('a', menu ?? document.createElement('div')).forEach((link) => link.addEventListener('click', () => setMenu(false)));
document.addEventListener('keydown', (event) => event.key === 'Escape' && menu && !menu.hidden && setMenu(false));

// Highlight the nav link of the section in view.
const navLinks = $$('[data-lp-navlink]');
$$('[data-lp-section]').forEach((section) => {
    ScrollTrigger.create({
        trigger: section,
        start: 'top 50%',
        end: 'bottom 50%',
        onToggle: ({ isActive }) => isActive && navLinks.forEach((link) => link.classList.toggle('is-active', link.dataset.lpNavlink === section.id)),
    });
});

// Scroll progress bar.
const progress = $('[data-lp-progress]');
if (progress) {
    gsap.to(progress, { scaleX: 1, ease: 'none', scrollTrigger: { start: 0, end: 'max', scrub: 0.3 } });
}

/* -------------------------------------------------------- Number counters */
const runCounter = (el) => {
    const target = Number(el.dataset.count) || 0;
    if (reduceMotion || target === 0) {
        el.textContent = inr.format(target);
        return;
    }
    const proxy = { value: 0 };
    gsap.to(proxy, { value: target, duration: 1.8, ease: 'power3.out', onUpdate: () => { el.textContent = inr.format(Math.round(proxy.value)); } });
};
$$('[data-count]').forEach((el) => {
    if (reduceMotion || el.closest('#home')) return; // hero counters start with heroEntrance()
    el.textContent = '0';
    ScrollTrigger.create({ trigger: el, start: 'top 92%', once: true, onEnter: () => runCounter(el) });
});

/* -------------------------------------------------------- Simple reveals */
// CSS handles the transition; ScrollTrigger only adds .is-in once.
ScrollTrigger.batch('.lp-reveal', {
    start: 'top 90%',
    once: true,
    onEnter: (batch) => batch.forEach((el) => el.classList.add('is-in')),
});

if (reduceMotion) {
    $$('.lp-reveal').forEach((el) => el.classList.add('is-in'));
} else {
    /* ------------------------------------------------ Split-text headings */
    $$('[data-split]').forEach((el) => {
        el.classList.remove('lp-reveal');
        if (el.closest('#home')) return; // hero headline is animated by heroEntrance()
        SplitText.create(el, {
            type: 'words',
            mask: 'words',
            autoSplit: true,
            onSplit: (self) => gsap.from(self.words, {
                yPercent: 110,
                duration: 0.9,
                ease: 'expo.out',
                stagger: 0.045,
                scrollTrigger: { trigger: el, start: 'top 88%', once: true },
            }),
        });
    });

    /* ------------------------------------------------- Parallax layers */
    $$('[data-parallax]').forEach((el) => {
        gsap.to(el, {
            yPercent: Number(el.dataset.parallax) * -100,
            ease: 'none',
            scrollTrigger: { trigger: el.closest('section') ?? el, start: 'top bottom', end: 'bottom top', scrub: true },
        });
    });

    /* ---------------------------------- Showcase: 3D perspective settle */
    const showcase = $('[data-lp-showcase]');
    if (showcase) {
        gsap.fromTo(showcase, { rotationX: 22, scale: 0.9, transformPerspective: 1600, transformOrigin: 'center top' }, {
            rotationX: 0,
            scale: 1,
            ease: 'none',
            scrollTrigger: { trigger: showcase, start: 'top 95%', end: 'top 25%', scrub: 0.6 },
        });
        gsap.from('[data-lp-notification]', {
            x: (i) => (i % 2 ? 60 : -60),
            autoAlpha: 0,
            duration: 0.8,
            stagger: 0.18,
            ease: 'back.out(1.6)',
            scrollTrigger: { trigger: showcase, start: 'top 60%', once: true },
        });
    }

    /* ------------------------------------- How it works: draw connector */
    const connector = $('[data-lp-connector]');
    if (connector) {
        gsap.fromTo(connector, { scaleX: 0 }, { scaleX: 1, ease: 'none', scrollTrigger: { trigger: connector.parentElement, start: 'top 75%', end: 'top 35%', scrub: 0.5 } });
    }

    /* ---------------------------------------------- Pointer interactions */
    if (finePointer) {
        // Magnetic buttons.
        $$('[data-magnetic]').forEach((el) => {
            const xTo = gsap.quickTo(el, 'x', { duration: 0.5, ease: 'elastic.out(1, 0.4)' });
            const yTo = gsap.quickTo(el, 'y', { duration: 0.5, ease: 'elastic.out(1, 0.4)' });
            el.addEventListener('pointermove', (event) => {
                const rect = el.getBoundingClientRect();
                xTo((event.clientX - rect.left - rect.width / 2) * 0.3);
                yTo((event.clientY - rect.top - rect.height / 2) * 0.4);
            });
            el.addEventListener('pointerleave', () => { xTo(0); yTo(0); });
        });

        // 3D tilt cards.
        $$('[data-tilt-card]').forEach((card) => {
            gsap.set(card, { transformPerspective: 900 });
            const rx = gsap.quickTo(card, 'rotationX', { duration: 0.5, ease: 'power3.out' });
            const ry = gsap.quickTo(card, 'rotationY', { duration: 0.5, ease: 'power3.out' });
            const lift = gsap.quickTo(card, 'y', { duration: 0.4, ease: 'power3.out' });
            card.addEventListener('pointermove', (event) => {
                const rect = card.getBoundingClientRect();
                ry(((event.clientX - rect.left) / rect.width - 0.5) * 12);
                rx(-((event.clientY - rect.top) / rect.height - 0.5) * 12);
                lift(-6);
            });
            card.addEventListener('pointerleave', () => { rx(0); ry(0); lift(0); });
        });

        // Hero depth layers follow the pointer.
        const hero = $('#home');
        const layers = $$('[data-depth]', hero ?? document).map((el) => ({
            depth: Number(el.dataset.depth),
            x: gsap.quickTo(el, 'x', { duration: 0.9, ease: 'power3.out' }),
            y: gsap.quickTo(el, 'y', { duration: 0.9, ease: 'power3.out' }),
        }));
        const tiltCard = $('[data-lp-tilt] .lp-tilt');
        hero?.addEventListener('pointermove', (event) => {
            const nx = event.clientX / window.innerWidth - 0.5;
            const ny = event.clientY / window.innerHeight - 0.5;
            layers.forEach((layer) => { layer.x(nx * layer.depth * 60); layer.y(ny * layer.depth * 60); });
            tiltCard?.style.setProperty('--ry', `${-12 + nx * 16}deg`);
            tiltCard?.style.setProperty('--rx', `${14 - ny * 12}deg`);
        });
        hero?.addEventListener('pointerleave', () => {
            layers.forEach((layer) => { layer.x(0); layer.y(0); });
            tiltCard?.style.removeProperty('--ry');
            tiltCard?.style.removeProperty('--rx');
        });

        // Spotlight that follows the cursor across a section / card.
        $$('[data-lp-glow], [data-lp-spotlight]').forEach((el) => {
            el.addEventListener('pointermove', (event) => {
                const rect = el.getBoundingClientRect();
                el.style.setProperty('--mx', `${event.clientX - rect.left}px`);
                el.style.setProperty('--my', `${event.clientY - rect.top}px`);
            });
        });
    }
}

/* ---------------------------------------------------------- Hero entrance */
function heroEntrance() {
    const hero = $('#home');
    if (!hero) return;
    $$('.lp-reveal', hero).forEach((el) => el.classList.add('is-in'));
    if (reduceMotion) return;
    $$('[data-count]', hero).forEach((el) => gsap.delayedCall(0.6, () => runCounter(el)));

    const headline = $('[data-split]', hero);
    const tl = gsap.timeline({ defaults: { ease: 'expo.out' } });
    if (headline) {
        const split = SplitText.create(headline, { type: 'words', mask: 'words' });
        tl.from(split.words, { yPercent: 115, duration: 1.1, stagger: 0.06 });
    }
    // clearProps: the CSS copy-swap (hero slider) owns this line's transform / opacity afterwards.
    tl.from('[data-lp-hero-shine]', { yPercent: 40, autoAlpha: 0, filter: 'blur(12px)', duration: 1.2, clearProps: 'transform,opacity,visibility,filter' }, '-=0.8')
        .from('[data-lp-hero-mockup]', { y: 60, autoAlpha: 0, rotationY: -25, duration: 1.4 }, '-=1.1')
        .from('[data-depth] > *', { scale: 0.6, autoAlpha: 0, duration: 0.8, stagger: 0.12, ease: 'back.out(1.8)' }, '-=0.9');
}

/* ------------------------------------------------- Hero showcase slider */
// Rotates dashboard → AI transform → streaks. The tab's CSS progress bar is the timer: when its animation ends we
// advance, so pausing the animation (hover / off-screen) pauses the slider too. Reduced motion: no auto-advance.
$$('[data-lp-hero-slider]').forEach((slider) => {
    const slides = $$('[data-hero-slide]', slider);
    const floats = $$('[data-hero-floats]', slider);
    const tabs = $$('[data-hero-tab]', slider);
    if (slides.length < 2) return;
    let index = 0;

    // Headline + sub-copy blocks (left column) come in groups of slides.length, in slide order.
    const copies = $$('[data-hero-copy]', slider.closest('section') ?? document);
    const swapCopy = (from, to) => copies.forEach((copy, i) => {
        const slideOf = i % slides.length;
        if (slideOf === from) {
            copy.classList.remove('is-active');
            copy.classList.add('is-leaving');
            copy.setAttribute('aria-hidden', 'true');
            setTimeout(() => copy.classList.remove('is-leaving'), 800);
        } else if (slideOf === to) {
            copy.classList.remove('is-leaving');
            copy.classList.add('is-active');
            copy.removeAttribute('aria-hidden');
        }
    });

    const show = (next) => {
        next = (next + slides.length) % slides.length;
        if (next === index) return;
        const leaving = slides[index];
        leaving.classList.remove('is-active');
        leaving.classList.add('is-leaving');
        setTimeout(() => leaving.classList.remove('is-leaving'), 1000);
        swapCopy(index, next);
        index = next;
        slides[index].classList.remove('is-leaving');
        slides[index].classList.add('is-active');
        floats.forEach((group, i) => group.classList.toggle('is-active', i === index));
        tabs.forEach((tab, i) => {
            tab.classList.toggle('is-active', i === index);
            tab.setAttribute('aria-pressed', String(i === index));
        });
        moveIndicator();
    };

    // Sliding pill under the active tab.
    const tabBar = $('[data-hero-tabs]', slider);
    const indicator = $('[data-hero-indicator]', slider);
    const moveIndicator = () => {
        if (!tabBar || !indicator) return;
        const tab = tabs[index];
        indicator.style.setProperty('--x', `${tab.offsetLeft}px`);
        indicator.style.setProperty('--w', `${tab.offsetWidth}px`);
        tabBar.classList.add('is-ready');
    };
    moveIndicator();
    window.addEventListener('resize', moveIndicator);
    document.fonts?.ready.then(moveIndicator);

    tabs.forEach((tab, i) => {
        tab.addEventListener('click', () => show(i));
        tab.querySelector('[data-hero-progress]')?.addEventListener('animationend', () => {
            if (tab.classList.contains('is-active')) show(index + 1);
        });
    });

    const setPaused = (paused) => slider.classList.toggle('is-paused', paused);
    slider.addEventListener('pointerenter', () => setPaused(true));
    slider.addEventListener('pointerleave', () => setPaused(false));
    slider.addEventListener('focusin', () => setPaused(true));
    slider.addEventListener('focusout', () => setPaused(false));
    new IntersectionObserver(([entry]) => slider.classList.toggle('is-offscreen', !entry.isIntersecting)).observe(slider);

    // Off-stage slides sit outside the hero; never let focus / scrollIntoView pan the overflow-hidden section.
    const section = slider.closest('section');
    section?.addEventListener('scroll', () => { section.scrollLeft = 0; section.scrollTop = 0; });
});

/* ------------------------------------------------------ Testimonial slider */
$$('[data-lp-slider]').forEach((slider) => {
    const track = $('[data-lp-slider-track]', slider);
    const dots = $$('[data-lp-slider-dot]', slider);
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
        if (reduceMotion || count < 2) return;
        clearInterval(timer);
        timer = setInterval(() => go(index + 1), 6000);
    };

    $('[data-lp-slider-prev]', slider)?.addEventListener('click', () => { go(index - 1); play(); });
    $('[data-lp-slider-next]', slider)?.addEventListener('click', () => { go(index + 1); play(); });
    dots.forEach((dot, i) => dot.addEventListener('click', () => { go(i); play(); }));
    slider.addEventListener('pointerenter', () => clearInterval(timer));
    slider.addEventListener('pointerleave', play);
    slider.addEventListener('focusin', () => clearInterval(timer));
    go(0);
    play();
});

/* ------------------------------------------------------ Pricing calculator */
$$('[data-lp-calc]').forEach((calc) => {
    const rate = Number(calc.dataset.rate) || 0;
    const input = $('[data-lp-calc-input]', calc);
    const members = $('[data-lp-calc-members]', calc);
    const total = $('[data-lp-calc-total]', calc);
    const formula = $('[data-lp-calc-formula]', calc);
    const periodLabel = $('[data-lp-calc-period-label]', calc);
    const periodButtons = $$('[data-lp-period]', calc);
    const indicator = $('[data-lp-period-indicator]', calc);
    let months = 1;
    const shown = { value: Number(input.value) * rate };

    const update = () => {
        const count = Number(input.value);
        members.textContent = inr.format(count);
        formula.textContent = months === 12 ? `${inr.format(count)} × 12 months` : inr.format(count);
        periodLabel.textContent = months === 12 ? 'Estimated yearly cost' : 'Estimated monthly cost';
        const value = count * rate * months;
        if (reduceMotion) {
            total.textContent = `₹${inr.format(value)}`;
            return;
        }
        gsap.to(shown, { value, duration: 0.6, ease: 'power3.out', overwrite: true, onUpdate: () => { total.textContent = `₹${inr.format(Math.round(shown.value))}`; } });
    };

    periodButtons.forEach((button) => button.addEventListener('click', () => {
        months = Number(button.dataset.lpPeriod);
        periodButtons.forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
        if (indicator) gsap.to(indicator, { xPercent: months === 12 ? 100 : 0, duration: 0.4, ease: 'power3.out' });
        update();
    }));
    input.addEventListener('input', update);
    update();
});

/* ----------------------------------------- Keep ScrollTrigger in sync */
window.addEventListener('load', () => ScrollTrigger.refresh());
