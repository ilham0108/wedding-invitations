import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/invitation.css',
                'resources/js/invitation.js',
                'resources/js/story.js',
                // Design packs (config/invitation.php > designs.*.assets)
                'resources/css/designs/mocha-glass.css',
                'resources/js/designs/mocha-glass.js',
                'resources/css/designs/botanical-cream.css',
                'resources/js/designs/botanical-cream.js',
            ],
            refresh: true,
        }),
    ],
});
