import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/landing.js', 'resources/js/gym-page.js'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600, 700],
                }),
                // Landing page only (welcome.blade.php); not preloaded so other pages don't fetch them.
                bunny('Anton', { weights: [400], preload: false }),
                bunny('Inter Tight', { weights: [400, 500, 600, 700, 800], preload: false }),
                bunny('JetBrains Mono', { weights: [500, 700], preload: false }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
