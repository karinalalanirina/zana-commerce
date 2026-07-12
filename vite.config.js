import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',     // dashboard / admin / user shell
                'resources/css/frontend.css', // public landing pages — independent entry
                'resources/css/instaflow.css', // Instagram suite — pixel-exact ui/ mockup theme
                'resources/js/app.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    experimental: {
        renderBuiltUrl(filename, { hostType }) {
            return { relative: true };
        },
    },
});
