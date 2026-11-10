import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/dealer-registration.css', 'resources/js/dealer-registration.js', 'resources/css/competition.css', 'resources/js/competition.js', 'resources/css/login.css', 'resources/js/login.js', 'resources/css/account.css', 'resources/js/account.js', 'resources/css/account-recovery.css', 'resources/css/staff-pages.css', 'resources/js/staff-pages.js', 'resources/css/admin.css', 'resources/css/admin-overview.css', 'resources/css/admin-dealers.css', 'resources/js/admin-dealers.js', 'resources/css/admin-account-approval.css', 'resources/js/admin-account-approval.js', 'resources/css/admin-submissions.css', 'resources/js/admin-submissions.js', 'resources/js/admin.js'],
            refresh: true,
        }),
    ],
});
