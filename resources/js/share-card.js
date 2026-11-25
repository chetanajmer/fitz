/**
 * Draws a member's AI report as a poster image in the browser and hands it to the phone's share menu.
 * Nothing is uploaded and no public link is made. Used as x-data="shareCard({...})".
 */
const WIDTH = 1080;
const HEIGHTS = { post: 1350, story: 1920 };
const BLACK = '#111111';
const WHITE = '#ffffff';

/** The gym's accent colour, which its dashboard theme may have changed. */
const accentColor = () => {
    const value = getComputedStyle(document.documentElement).getPropertyValue('--gym-orange').trim();
    return /^#[0-9a-f]{6}$/i.test(value) ? value : '#ff6b00';
};

/** WCAG luminance of a #rrggbb colour. Above 0.18, black text reads better on it than white. */
const luminance = (hex) => {
    const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};
const MARGIN = 72;

/** The three slanted change bars, as on the report. */
const drawBars = (ctx, step, y, color) => {
    const gap = 24;
    const bar = (WIDTH - MARGIN * 2 - gap * 2) / 3;
    [0, 1, 2].forEach((i) => {
        const x = MARGIN + i * (bar + gap);
        ctx.fillStyle = color;
        ctx.globalAlpha = i < step ? 1 : 0.2;
        ctx.beginPath();
        ctx.moveTo(x + 24, y);
        ctx.lineTo(x + bar, y);
        ctx.lineTo(x + bar - 24, y + 110);
        ctx.lineTo(x, y + 110);
        ctx.fill();
        ctx.globalAlpha = 1;
    });
};

const loadImage = (src) =>
    new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = src;
    });

/** Largest Anton size, up to `max`, at which the text fits the width. */
const fitText = (ctx, text, width, max) => {
    ctx.font = `${max}px Anton`;
    return Math.min(max, Math.floor((max * width) / ctx.measureText(text).width));
};

/** Cover-fit a photo into a box, keeping the top (head and torso) in view. */
const drawCover = (ctx, image, x, y, w, h) => {
    const scale = Math.max(w / image.width, h / image.height);
    ctx.drawImage(image, x + (w - image.width * scale) / 2, y + Math.min(0, (h - image.height * scale) * 0.25), image.width * scale, image.height * scale);
};

const drawPhotos = (ctx, photos, height, seam) => {
    const middle = WIDTH / 2;
    const lean = 46;
    photos.forEach((photo, i) => {
        ctx.save();
        ctx.beginPath();
        const edges = i === 0 ? [0, middle + lean, middle - lean, 0] : [middle + lean, WIDTH, WIDTH, middle - lean];
        ctx.moveTo(edges[0], 0);
        ctx.lineTo(edges[1], 0);
        ctx.lineTo(edges[2], height);
        ctx.lineTo(edges[3], height);
        ctx.clip();
        drawCover(ctx, photo, i === 0 ? 0 : middle - lean, 0, middle + lean, height);
        ctx.restore();
    });
    const fade = ctx.createLinearGradient(0, height * 0.7, 0, height);
    fade.addColorStop(0, 'rgba(17,17,17,0)');
    fade.addColorStop(1, BLACK);
    ctx.fillStyle = fade;
    ctx.fillRect(0, height * 0.7, WIDTH, height * 0.3 + 1);
    ctx.strokeStyle = seam;
    ctx.lineWidth = 10;
    ctx.beginPath();
    ctx.moveTo(middle + lean, 0);
    ctx.lineTo(middle - lean, height);
    ctx.stroke();
};

window.shareCard = (report) => ({
    format: 'post',
    photos: false,
    numbers: true,
    name: false,
    canShare: false,
    ready: false,
    failed: false,
    file: null,
    drawing: 0,

    open() {
        this.$refs.dialog.showModal();
        this.draw();
    },

    async draw() {
        const run = ++this.drawing;
        this.ready = false;
        this.failed = false;
        try {
            await Promise.all([document.fonts.load('80px Anton'), document.fonts.load('600 40px "Instrument Sans"')]);
            const photos = this.photos && report.photos ? await Promise.all(report.photos.map(loadImage)) : null;
            if (run !== this.drawing) return;
            const canvas = this.$refs.canvas;
            this.paint(canvas, photos);
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
            if (run !== this.drawing) return;
            this.file = new File([blob], 'my-progress.jpg', { type: 'image/jpeg' });
            this.canShare = !!navigator.canShare?.({ files: [this.file] });
            this.ready = true;
        } catch {
            if (run === this.drawing) this.failed = true;
        }
    },

    paint(canvas, photos) {
        const height = HEIGHTS[this.format];
        canvas.width = WIDTH;
        canvas.height = height;
        const ctx = canvas.getContext('2d');
        const inner = WIDTH - MARGIN * 2;
        const accent = accentColor();
        const ink = photos || luminance(accent) < 0.18 ? WHITE : BLACK;
        // Figures on the black photo poster: the accent, unless it is too dark to read there.
        const figures = !photos ? ink : luminance(accent) < 0.12 ? WHITE : accent;
        const body = (size) => `600 ${size}px "Instrument Sans", sans-serif`;
        const stats = this.numbers ? report.stats.slice(0, 3) : [];

        ctx.fillStyle = photos ? BLACK : accent;
        ctx.fillRect(0, 0, WIDTH, height);
        // Stories are covered by the app's own controls at the top and bottom.
        const foot = height - (this.format === 'story' ? 230 : MARGIN);
        if (photos) drawPhotos(ctx, photos, height * 0.62, figures);
        else if (report.step) drawBars(ctx, report.step, this.format === 'story' ? 260 : MARGIN, ink);

        // Footer: gym, and the member's first name when they chose to show it.
        ctx.fillStyle = ink;
        ctx.textBaseline = 'alphabetic';
        ctx.font = body(36);
        ctx.fillText(report.gym, MARGIN, foot, this.name ? inner * 0.6 : inner);
        if (this.name) {
            ctx.textAlign = 'right';
            ctx.fillText(report.name, WIDTH - MARGIN, foot, inner * 0.35);
            ctx.textAlign = 'left';
        }
        ctx.fillRect(MARGIN, foot - 70, inner, 4);

        let y = foot - 120;
        if (stats.length) {
            const column = inner / stats.length;
            stats.forEach((stat, i) => {
                ctx.fillStyle = ink;
                ctx.font = body(32);
                ctx.fillText(stat.label, MARGIN + column * i, y, column - 24);
                ctx.fillStyle = figures;
                ctx.font = `${fitText(ctx, stat.value, column - 60, 100)}px Anton`;
                ctx.fillText(stat.value, MARGIN + column * i, y - 50);
            });
            y -= 200;
        }

        if (report.period) {
            ctx.fillStyle = ink;
            ctx.font = body(54);
            ctx.fillText(report.period, MARGIN, y, inner);
            y -= 96;
        }

        // Headline on one line under photos, otherwise one word per line.
        const lines = photos ? [report.headline] : report.headline.split(' ');
        const top = (this.format === 'story' ? 250 : MARGIN) + (report.step && !photos ? 160 : 0); // below the change bars
        ctx.fillStyle = ink;
        [...lines].reverse().forEach((line, i) => {
            const text = line.toUpperCase();
            const size = fitText(ctx, text, inner, photos ? 190 : Math.min(400, ((y - top) / (lines.length - i)) * 0.9));
            ctx.font = `${size}px Anton`;
            ctx.fillText(text, MARGIN, y);
            y -= size * 1.06;
        });
    },

    async share() {
        try {
            await navigator.share({ files: [this.file] });
        } catch (error) {
            if (error.name !== 'AbortError') this.save();
        }
    },

    save() {
        const link = document.createElement('a');
        link.href = URL.createObjectURL(this.file);
        link.download = this.file.name;
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 1000);
    },
});
