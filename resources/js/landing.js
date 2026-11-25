/**
 * Public landing page motion (welcome.blade.php, "Chalk & Volt").
 *
 * GSAP + ScrollTrigger drive the scroll work, SplitText slams the headings in and Lenis smooths
 * wheel scrolling on desktop. Every section is readable without JavaScript, and with
 * prefers-reduced-motion everything is shown in its final state (no scroll-jacking, no loops).
 */
import 'lenis/dist/lenis.css';
import Lenis from 'lenis';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';

gsap.registerPlugin(ScrollTrigger, SplitText);

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
const finePointer = window.matchMedia('(pointer: fine)').matches;
const inr = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });
const $ = (selector, root = document) => root.querySelector(selector);
const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];

/* --------------------------------------------------------- Smooth scroll */
let lenis = null;
if (!reduceMotion && finePointer) {
    lenis = new Lenis({ lerp: 0.11, anchors: { offset: -72 } });
    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => lenis.raf(time * 1000));
    gsap.ticker.lagSmoothing(0);
}

/* ---------------------------------------------------------------- Loader
 * The bar fills while the page loads (at least 0.9 s so it reads as intentional, never more than 2.5 s),
 * then the dumbbell does a last lift and the shutter rolls up. `loaderDone` resolves as the shutter
 * starts rolling, so the hero headline slams in underneath it. */
const loaderDone = new Promise((resolve) => {
    const loader = $('[data-lp-loader]');
    if (!loader || reduceMotion) {
        loader?.remove();
        resolve();
        return;
    }
    lenis?.stop();
    document.documentElement.style.overflow = 'hidden';

    const status = $('[data-lp-loader-status]', loader);
    const count = $('[data-lp-loader-count]', loader);
    const progress = { value: 0 };
    const showProgress = () => {
        gsap.set('[data-lp-loader-bar]', { scaleX: progress.value / 100 });
        count.textContent = String(Math.round(progress.value));
        status.textContent = progress.value < 35 ? 'Chalking up' : progress.value < 75 ? 'Loading plates' : 'Lifting';
    };
    // Creep towards 90% while waiting for the page.
    const creep = gsap.to(progress, { value: 90, duration: 2.2, ease: 'power2.out', onUpdate: showProgress });

    let finished = false;
    const finish = () => {
        if (finished) return;
        finished = true;
        creep.kill();
        gsap.timeline({
            onComplete: () => {
                loader.remove();
                document.documentElement.style.overflow = '';
                lenis?.start();
                // The scrollbar is back now, so pins measured while it was hidden must re-measure.
                ScrollTrigger.refresh();
            },
        })
            .to(progress, { value: 100, duration: 0.35, ease: 'power2.out', onUpdate: showProgress })
            .to('.lp-db-scene', { scale: 1.25, y: -40, duration: 0.35, ease: 'back.out(2)' }, '<0.1')
            .add(resolve, '+=0.05')
            .to(loader, { yPercent: -100, duration: 0.85, ease: 'power4.inOut' });
    };
    const ready = new Promise((done) => (document.readyState === 'complete' ? done() : window.addEventListener('load', done, { once: true })));
    Promise.all([ready, new Promise((done) => setTimeout(done, 900))]).then(finish);
    setTimeout(finish, 2500);
});

/* ------------------------------------------ Feature tour: horizontal on desktop
 * Set up before every other ScrollTrigger so they all measure with the pin's extra height. */
const tour = $('[data-lp-tour]');
const track = $('[data-lp-track]');
const counter = $('[data-lp-tour-count]');
if (tour && track && !reduceMotion) {
    gsap.matchMedia().add('(min-width: 1024px)', () => {
        tour.classList.add('is-pinned');
        const cards = $$('[data-lp-card]', track);
        const distance = () => Math.max(0, track.scrollWidth - window.innerWidth);
        const slide = gsap.to(track, {
            x: () => -distance(),
            ease: 'none',
            scrollTrigger: {
                trigger: tour,
                start: 'top top',
                end: () => `+=${distance()}`,
                pin: true,
                scrub: 0.6,
                invalidateOnRefresh: true,
                onUpdate: (self) => {
                    if (counter) counter.textContent = String(Math.min(cards.length, Math.round(self.progress * (cards.length - 1)) + 1)).padStart(2, '0');
                },
            },
        });
        // Cards swing up into place as they slide in.
        cards.forEach((card, i) => gsap.from(card, {
            yPercent: 18,
            rotate: i % 2 ? -7 : 7,
            ease: 'none',
            scrollTrigger: { trigger: card, containerAnimation: slide, start: 'left 100%', end: 'left 65%', scrub: true },
        }));
        return () => tour.classList.remove('is-pinned');
    });
}

/* ------------------------------------------------------------ Navigation */
const nav = $('[data-lp-nav]');
const menu = $('[data-lp-menu]');
const menuToggle = $('[data-lp-menu-toggle]');
const syncNav = () => nav?.classList.toggle('is-scrolled', window.scrollY > 12 || (menu && !menu.hidden));
syncNav();
window.addEventListener('scroll', syncNav, { passive: true });

const setMenu = (open) => {
    if (!menu || !menuToggle) return;
    menuToggle.setAttribute('aria-expanded', String(open));
    menuToggle.classList.toggle('is-open', open);
    menu.hidden = !open;
    syncNav();
    if (open && !reduceMotion) {
        gsap.from($$('a', menu), { yPercent: 60, autoAlpha: 0, duration: 0.4, stagger: 0.035, ease: 'power3.out' });
    }
};
menuToggle?.addEventListener('click', () => setMenu(menu.hidden));
$$('a', menu ?? document.createElement('div')).forEach((link) => link.addEventListener('click', () => setMenu(false)));
document.addEventListener('keydown', (event) => event.key === 'Escape' && menu && !menu.hidden && setMenu(false));

const navLinks = $$('[data-lp-navlink]');
$$('[data-lp-section]').forEach((section) => {
    ScrollTrigger.create({
        trigger: section,
        start: 'top 50%',
        end: 'bottom 50%',
        onToggle: ({ isActive }) => isActive && navLinks.forEach((link) => link.classList.toggle('is-active', link.dataset.lpNavlink === section.id)),
    });
});

const progress = $('[data-lp-progress]');
if (progress) {
    gsap.to(progress, { scaleX: 1, ease: 'none', scrollTrigger: { start: 0, end: 'max', scrub: 0.3 } });
}

/* ------------------------------------------------------ Reveals & loops */
ScrollTrigger.batch('.lp-reveal', { start: 'top 88%', once: true, onEnter: (batch) => batch.forEach((el) => el.classList.add('is-in')) });

// CSS loops only run while their section is on screen.
$$('[data-lp-live]').forEach((el) => ScrollTrigger.create({ trigger: el, start: 'top bottom', end: 'bottom top', toggleClass: 'is-live' }));

// Draw a heartbeat line (paths use pathLength="1").
// Hero heartbeat: a monitor-style sweep from left to right, with a glowing pen dot riding the tip of the line.
const sweepEcg = (wrap) => {
    if (!wrap) return;
    const svg = $('svg', wrap);
    const paths = $$('path', svg);
    const pen = $('[data-lp-ecg-pen]', wrap);
    if (!svg || !paths.length) return;
    const guide = paths[paths.length - 1];
    const length = guide.getTotalLength();
    const progress = { value: 0 };
    gsap.timeline()
        .set(pen, { autoAlpha: 1 })
        .to(progress, {
            value: 1,
            duration: 2.6,
            ease: 'none',
            onUpdate: () => {
                gsap.set(paths, { strokeDashoffset: 1 - progress.value });
                // The SVG is stretched (preserveAspectRatio="none"), so map the tip to screen pixels.
                const tip = guide.getPointAtLength(progress.value * length);
                const box = svg.getBoundingClientRect();
                const viewBox = svg.viewBox.baseVal;
                gsap.set(pen, {
                    x: (tip.x / viewBox.width) * box.width - pen.offsetWidth / 2,
                    y: (tip.y / viewBox.height) * box.height - pen.offsetHeight / 2,
                });
            },
        })
        .to(pen, { scale: 2.2, autoAlpha: 0, duration: 0.4, ease: 'power2.out' });
};

if (reduceMotion) {
    $$('.lp-reveal').forEach((el) => el.classList.add('is-in'));
    $('[data-lp-hero-title]')?.classList.add('is-lit');
} else {
    /* ------------------------------------------------ Hero entrance */
    const heroEntrance = () => {
        const title = $('[data-lp-hero-title]');
        if (!title || title.dataset.done) return;
        title.dataset.done = '1';
        const split = SplitText.create(title, { type: 'chars', mask: 'chars' });
        gsap.set(title, { visibility: 'visible', animation: 'none' });
        gsap.timeline()
            .from(split.chars, { yPercent: -115, duration: 0.7, ease: 'back.out(2.2)', stagger: 0.05 })
            .add(() => title.classList.add('is-lit'), '-=0.25')
            .add(() => sweepEcg($('#home [data-lp-ecg]')?.parentElement), 0.15)
            .from('[data-lp-tile]', { y: 60, rotate: (i) => [-8, 6, -4][i] ?? 0, autoAlpha: 0, duration: 0.8, ease: 'back.out(1.6)', stagger: 0.12 }, '-=1.4');
    };
    // Wait for Anton so the characters are split at their real widths (never longer than 800 ms), and for the loader.
    const fontsReady = Promise.race([document.fonts?.ready ?? Promise.resolve(), new Promise((resolve) => setTimeout(resolve, 800))]);
    Promise.all([fontsReady, loaderDone]).then(heroEntrance);

    // Hero tiles and price sticker follow the pointer.
    if (finePointer) {
        const layers = $$('#home [data-lp-depth]').map((el) => ({
            depth: Number(el.dataset.lpDepth),
            x: gsap.quickTo(el, 'x', { duration: 0.8, ease: 'power3.out' }),
            y: gsap.quickTo(el, 'y', { duration: 0.8, ease: 'power3.out' }),
        }));
        $('#home')?.addEventListener('pointermove', (event) => {
            const dx = event.clientX / window.innerWidth - 0.5;
            const dy = event.clientY / window.innerHeight - 0.5;
            layers.forEach(({ depth, x, y }) => { x(dx * 26 * depth); y(dy * 20 * depth); });
        });
    }

    /* ------------------------------------------------ Heading slams */
    $$('[data-lp-slam]').forEach((heading) => {
        SplitText.create(heading, {
            type: 'words',
            mask: 'words',
            autoSplit: true,
            // Words drop in, then any lime highlight wipes in behind them.
            onSplit: (self) => gsap.timeline({ scrollTrigger: { trigger: heading, start: 'top 85%', once: true } })
                .from(self.words, { yPercent: -110, duration: 0.75, ease: 'back.out(1.8)', stagger: 0.07 })
                .from($$('.lp-hl', heading), { backgroundSize: '0% 100%', duration: 0.5, ease: 'power3.inOut' }, '-=0.35'),
        });
    });

    /* ------------------------------------------------ Scroll-drawn ECG lines */
    $$('[data-lp-ecg-scrub]').forEach((svg) => {
        gsap.to($$('path', svg), { strokeDashoffset: 0, ease: 'none', scrollTrigger: { trigger: svg, start: 'top 85%', end: 'top 35%', scrub: 0.6 } });
    });

    /* ------------------------------------------------ Heartbeat spine (wide screens) */
    const spine = $('[data-lp-spine]');
    const main = $('#main');
    if (spine && main && window.matchMedia('(min-width: 1440px)').matches) {
        const path = $('path', spine);
        const build = () => {
            const height = main.offsetHeight;
            spine.style.height = `${height}px`;
            spine.setAttribute('viewBox', `0 0 40 ${height}`);
            let d = 'M20 0';
            $$('[data-lp-section]', main).forEach((section) => {
                const top = section.getBoundingClientRect().top - main.getBoundingClientRect().top + 90;
                d += ` V${top} l-9 14 l18 -40 l-9 26`;
            });
            path.setAttribute('d', `${d} V${height}`);
        };
        build();
        ScrollTrigger.addEventListener('refresh', build);
        gsap.to(path, { strokeDashoffset: 0, ease: 'none', scrollTrigger: { trigger: main, start: 'top top', end: 'bottom bottom', scrub: 0.4 } });
    }

}

/* --------------------------------------------------------- Scoreboard */
$$('[data-lp-flaps]').forEach((board) => {
    const flaps = $$('[data-lp-flap]', board);
    if (reduceMotion || !flaps.length) return;
    ScrollTrigger.create({
        trigger: board,
        start: 'top 80%',
        once: true,
        onEnter: () => flaps.forEach((flap, i) => {
            let ticks = 8 + i * 3;
            const timer = setInterval(() => {
                flap.textContent = ticks > 0 ? String(Math.floor(Math.random() * 10)) : flap.dataset.lpFlap;
                flap.classList.remove('is-flip');
                void flap.offsetWidth;
                flap.classList.add('is-flip');
                if (ticks-- <= 0) clearInterval(timer);
            }, 70);
        }),
    });
});

/* --------------------------------------------------------- Member app phone */
const appSteps = $$('[data-lp-app-step]');
const appScreens = $$('[data-lp-app-screen]');
if (appSteps.length) {
    let current = 0;
    let autoTimer = null;
    const show = (index) => {
        current = index;
        appSteps.forEach((step) => step.setAttribute('aria-pressed', String(Number(step.dataset.lpAppStep) === index)));
        appScreens.forEach((screen) => screen.classList.toggle('is-active', Number(screen.dataset.lpAppScreen) === index));
    };
    const stopAuto = () => { clearInterval(autoTimer); autoTimer = null; };
    appSteps.forEach((step) => step.addEventListener('click', () => { stopAuto(); show(Number(step.dataset.lpAppStep)); }));

    gsap.matchMedia().add({ desktop: '(min-width: 1024px)', reduce: '(prefers-reduced-motion: reduce)' }, ({ conditions }) => {
        if (conditions.reduce) return;
        if (conditions.desktop) {
            // Scrollytelling: the step in the middle of the screen drives the phone.
            $$('[data-lp-app-row]').forEach((row, i) => ScrollTrigger.create({
                trigger: row, start: 'top 55%', end: 'bottom 55%', onToggle: ({ isActive }) => isActive && show(i),
            }));
            return;
        }
        // Phones: cycle the screens while the section is visible, until the visitor taps a step.
        const cycle = ScrollTrigger.create({
            trigger: '#members',
            start: 'top 70%',
            end: 'bottom 30%',
            onToggle: ({ isActive }) => {
                stopAuto();
                if (isActive) autoTimer = setInterval(() => show((current + 1) % appSteps.length), 2800);
            },
        });
        return () => { cycle.kill(); stopAuto(); };
    });
}

/* --------------------------------------------------------- A day at your gym */
const day = $('[data-lp-day]');
if (day) {
    const events = $$('[data-lp-event]', day);
    const dots = $$('[data-lp-event-dot]', day);
    const clock = $('[data-lp-day-clock]', day);
    const marker = $('[data-lp-day-marker]', day);
    const played = $('[data-lp-day-progress]', day);
    const opening = Number(day.dataset.lpDayStart);
    const closing = Number(day.dataset.lpDayEnd);
    // [position on the line in %, minutes after midnight]: opening time, every event, closing time.
    const stops = [[0, opening], ...events.map((event) => [Number(event.dataset.x), Number(event.dataset.minutes)]), [100, closing]];
    const minutesAt = (position) => {
        for (let i = 1; i < stops.length; i++) {
            const [p0, m0] = stops[i - 1];
            const [p1, m1] = stops[i];
            if (position <= p1) return m0 + (m1 - m0) * ((position - p0) / (p1 - p0 || 1));
        }
        return closing;
    };
    // Always "HH:MM AM" (8 characters) in 5-minute steps, so the clock never changes width and ticks calmly.
    const formatTime = (minutes) => {
        const rounded = Math.floor(minutes / 5) * 5;
        const hours = Math.floor(rounded / 60);
        return `${String(((hours + 11) % 12) + 1).padStart(2, '0')}:${String(rounded % 60).padStart(2, '0')} ${hours < 12 ? 'AM' : 'PM'}`;
    };
    const clockCells = clock ? $$('.lp-clock-ch', clock) : [];
    const setClock = (minutes) => {
        [...formatTime(minutes)].forEach((char, i) => {
            if (clockCells[i] && clockCells[i].textContent !== char) clockCells[i].textContent = char;
        });
    };
    const light = (index, on) => {
        events[index]?.classList.toggle('is-on', on);
        dots[index]?.classList.toggle('is-on', on);
    };

    if (reduceMotion) {
        events.forEach((event, i) => light(i, true));
        setClock(closing);
    } else {
        day.classList.add('is-scrubbing');
        gsap.matchMedia().add({ desktop: '(min-width: 1024px)', mobile: '(max-width: 1023.98px)' }, ({ conditions }) => {
            if (conditions.desktop) {
                // Scrolling plays the day: the marker runs along the line, the clock ticks, events light up.
                const state = { position: 0 };
                gsap.to(state, {
                    position: 100,
                    ease: 'none',
                    // scrub: 1 lets the day catch up smoothly instead of jumping when the visitor scrolls fast.
                    scrollTrigger: { trigger: day, start: 'top 70%', end: 'bottom 45%', scrub: 1 },
                    onUpdate: () => {
                        marker.style.left = `${state.position}%`;
                        played.style.scale = `${state.position / 100} 1`;
                        setClock(minutesAt(state.position));
                        events.forEach((event, i) => light(i, state.position >= Number(event.dataset.x)));
                    },
                });
                return;
            }
            // Phones: each event lights up as it scrolls into view.
            events.forEach((event, i) => ScrollTrigger.create({
                trigger: event,
                start: 'top 80%',
                onEnter: () => light(i, true),
                onLeaveBack: () => light(i, false),
            }));
        });
    }
}

/* --------------------------------------------------------- AI Coach compare */
$$('[data-lp-compare]').forEach((compare) => {
    const input = $('[data-lp-compare-input]', compare);
    const sync = () => compare.style.setProperty('--pos', `${input.value}%`);
    input?.addEventListener('input', sync);
    if (!reduceMotion && input) {
        // A one-time hint sweep so visitors notice it can be dragged.
        const proxy = { value: 50 };
        gsap.timeline({ scrollTrigger: { trigger: compare, start: 'top 70%', once: true } })
            .to(proxy, { value: 18, duration: 0.8, ease: 'power2.inOut', onUpdate: () => { input.value = proxy.value; sync(); } })
            .to(proxy, { value: 62, duration: 1, ease: 'power2.inOut', onUpdate: () => { input.value = proxy.value; sync(); } });
    }
});
$$('.lp-report').forEach((report) => ScrollTrigger.create({ trigger: report, start: 'top 75%', once: true, onEnter: () => report.classList.add('is-in') }));

/* --------------------------------------------------------- Discovery map */
const discover = $('[data-lp-discover]');
const map = $('[data-lp-map]');
if (discover && map) {
    const plane = $('[data-lp-map-plane]', map);

    // Hovering (or focusing) a gym in the list or on the map lights up both.
    const setActive = (key) => {
        discover.classList.toggle('has-active', Boolean(key));
        $$('[data-lp-map-key]', discover).forEach((el) => el.classList.toggle('is-active', el.dataset.lpMapKey === key));
    };
    $$('[data-lp-map-key]', discover).forEach((el) => {
        el.addEventListener('pointerenter', () => setActive(el.dataset.lpMapKey));
        el.addEventListener('pointerleave', () => setActive(null));
        el.addEventListener('focus', () => setActive(el.dataset.lpMapKey));
        el.addEventListener('blur', () => setActive(null));
    });

    if (!reduceMotion) {
        // Entrance: the map starts flat (seen from above), tilts into 3D, your gym rises and the pins drop in.
        gsap.timeline({ scrollTrigger: { trigger: map, start: 'top 75%', once: true } })
            .fromTo(plane, { '--tilt': 0, '--spin': 0 }, { '--tilt': 56, '--spin': -24, duration: 1.6, ease: 'power3.inOut' })
            .fromTo(plane, { '--h': '0px' }, { '--h': '64px', duration: 0.8, ease: 'back.out(1.8)' }, '-=0.5')
            .from($$('[data-lp-map-pin]', map), { y: -50, autoAlpha: 0, duration: 0.6, ease: 'back.out(2)', stagger: 0.09, clearProps: 'all' }, '-=0.4');

        if (finePointer) {
            // The map leans gently towards the pointer.
            const spinTo = gsap.quickTo(plane, '--spin-o', { duration: 0.9, ease: 'power3.out' });
            const tiltTo = gsap.quickTo(plane, '--tilt-o', { duration: 0.9, ease: 'power3.out' });

            // Cursor trail: recent pointer positions drawn as a fading, tapering clay line.
            const canvas = $('[data-lp-map-trail]', map);
            const context = canvas.getContext('2d');
            const lifetime = 500;
            let points = [];
            let frame = 0;
            const resize = () => {
                const ratio = Math.min(window.devicePixelRatio || 1, 2);
                canvas.width = map.clientWidth * ratio;
                canvas.height = map.clientHeight * ratio;
                context.setTransform(ratio, 0, 0, ratio, 0, 0);
            };
            new ResizeObserver(resize).observe(map);
            const draw = () => {
                const now = performance.now();
                points = points.filter((point) => now - point.t < lifetime);
                context.clearRect(0, 0, map.clientWidth, map.clientHeight);
                context.lineCap = 'round';
                for (let i = 1; i < points.length; i++) {
                    const life = 1 - (now - points[i].t) / lifetime;
                    context.strokeStyle = `rgba(255, 140, 56, ${life})`;
                    context.lineWidth = 1 + life * 9;
                    context.beginPath();
                    context.moveTo(points[i - 1].x, points[i - 1].y);
                    context.lineTo(points[i].x, points[i].y);
                    context.stroke();
                }
                const head = points[points.length - 1];
                if (head) {
                    context.fillStyle = '#ffffff';
                    context.beginPath();
                    context.arc(head.x, head.y, 5, 0, Math.PI * 2);
                    context.fill();
                }
                frame = points.length ? requestAnimationFrame(draw) : 0;
            };
            map.addEventListener('pointermove', (event) => {
                const box = map.getBoundingClientRect();
                points.push({ x: event.clientX - box.left, y: event.clientY - box.top, t: performance.now() });
                spinTo(((event.clientX - box.left) / box.width - 0.5) * 8);
                tiltTo(((event.clientY - box.top) / box.height - 0.5) * -8);
                if (!frame) frame = requestAnimationFrame(draw);
            });
            map.addEventListener('pointerleave', () => { spinTo(0); tiltTo(0); });
        }
    }
}

/* --------------------------------------------------------- Gym search typing */
const search = $('[data-lp-search]');
if (search && !reduceMotion) {
    const phrases = JSON.parse(search.dataset.lpSearchItems || '[]');
    let phrase = 0;
    const type = (text, i = 0) => {
        search.textContent = text.slice(0, i);
        if (i < text.length) return setTimeout(() => type(text, i + 1), 55);
        setTimeout(() => erase(text, text.length), 1800);
    };
    const erase = (text, i) => {
        search.textContent = text.slice(0, i);
        if (i > 0) return setTimeout(() => erase(text, i - 1), 22);
        phrase = (phrase + 1) % phrases.length;
        type(phrases[phrase]);
    };
    if (phrases.length > 1) ScrollTrigger.create({ trigger: search, start: 'top bottom', once: true, onEnter: () => setTimeout(() => erase(phrases[0], phrases[0].length), 1500) });
}

/* --------------------------------------------------------- Pricing: members cover it */
// Shows the per-member rate as one line on a member's receipt, so the gym can pass it on.
const calc = $('[data-lp-calc]');
if (calc) {
    const rate = Number(calc.dataset.rate) || 0;
    const feeInput = $('[data-lp-calc-fee]', calc);
    const set = (selector, text) => { const el = $(selector, calc); if (el) el.textContent = text; };
    const rupees = (amount) => `₹${inr.format(amount)}`;
    let period = 1;
    const update = () => {
        const fee = Number(feeInput.value);
        set('[data-lp-calc-fee-value]', rupees(fee));
        set('[data-lp-calc-per]', period === 12 ? '/ year' : '/ month');
        set('[data-lp-calc-fee-line]', rupees(fee * period));
        set('[data-lp-calc-rate-line]', rupees(rate * period));
        set('[data-lp-calc-member-pays]', rupees((fee + rate) * period));
        set('[data-lp-calc-share]', `${inr.format(Math.round((rate / fee) * 1000) / 10)}%`);
        feeInput.style.setProperty('--fill', `${((fee - feeInput.min) / (feeInput.max - feeInput.min)) * 100}%`);
    };
    feeInput.addEventListener('input', update);
    $$('[data-lp-period]', calc).forEach((button) => button.addEventListener('click', () => {
        period = Number(button.dataset.lpPeriod);
        $$('[data-lp-period]', calc).forEach((other) => other.setAttribute('aria-pressed', String(other === button)));
        update();
    }));
    update();
}

/* --------------------------------------------------------- Deep links */
// Opening the page at /#section: re-measure once pins exist, then land on the section.
if (window.location.hash) {
    window.addEventListener('load', () => {
        const target = document.getElementById(decodeURIComponent(window.location.hash.slice(1)));
        if (!target) return;
        ScrollTrigger.refresh();
        window.scrollTo(0, target.getBoundingClientRect().top + window.scrollY - 72);
    });
}
