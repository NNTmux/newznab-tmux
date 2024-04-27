import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/forum/livewire-tailwind/css/forum.css',
                'resources/forum/livewire-tailwind/js/forum.js',
            ],
            refresh: true,
        }),
    ],
});
