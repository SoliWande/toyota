import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/dealer-registration.css', 'resources/js/dealer-registration.js', 'resources/css/competition.css', 'resources/js/competition.js', 'resources/css/login.css', 'resources/js/login.js', 'resources/css/account.css', 'resources/js/account.js', 'resources/css/account-recovery.css', 'resources/css/staff-pages.css', 'resources/js/staff-pages.js'],
            refresh: true,
        }),
    ],
});
