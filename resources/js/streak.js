/**
 * The member dashboard's streak card: counts up on load, and celebrates when a check-in grows the streak
 * (the "streak-grew" event). Used as x-data="streakCard" with data-streak and data-state:
 * x-data itself must not change, or Livewire's refresh after the check-in would start the card again. The motion is in app.css.
 */
const EMBERS = 14;

window.streakCard = () => ({
    shown: 0,
    state: 'none', // alive | risk | none
    flare: false,
    embers: [],
    reached: null,
    frame: null,
    timer: null,

    still: () => matchMedia('(prefers-reduced-motion: reduce)').matches,

    init() {
        const count = Number(this.$el.dataset.streak);
        this.shown = count;
        this.state = this.$el.dataset.state;
        if (count > 1 && !this.still()) this.roll(0, count);
    },

    destroy() {
        cancelAnimationFrame(this.frame);
        clearTimeout(this.timer);
    },

    roll(from, to) {
        cancelAnimationFrame(this.frame);
        const start = performance.now();
        const duration = Math.min(1200, 300 + (to - from) * 60);
        const step = (now) => {
            const t = Math.min(1, Math.max(0, (now - start) / duration));
            this.shown = Math.round(from + (to - from) * (1 - (1 - t) ** 3));
            if (t < 1) this.frame = requestAnimationFrame(step);
        };
        this.shown = from;
        this.frame = requestAnimationFrame(step);
    },

    grew({ count: to, milestone }) {
        const from = this.shown;
        this.state = 'alive';
        this.reached = milestone ? to : null;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => {
            this.flare = false;
            this.embers = [];
            this.reached = null;
        }, 6000);
        if (this.still() || to <= from) {
            this.shown = to;
            return;
        }
        this.roll(from, to);
        this.flare = true;
        this.embers = Array.from({ length: milestone ? EMBERS * 2 : EMBERS }, (_, id) => ({
            id: `${to}-${id}`,
            style: `--x:${Math.round((Math.random() - 0.5) * 110)}px;--y:${-50 - Math.round(Math.random() * 90)}px;--d:${Math.round(Math.random() * 500)}ms;--s:${(3 + Math.random() * 5).toFixed(1)}px`,
        }));
    },
});
