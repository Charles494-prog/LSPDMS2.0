import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appOrigin = env.VITE_APP_ORIGIN || env.APP_URL || 'http://localhost';
    const viteHost = env.VITE_DEV_SERVER_HOST || 'localhost';
    // The dev server is for this PC only (LAN mode uses compiled assets), so it listens on
    // loopback unless VITE_DEV_SERVER_BIND names another address on purpose.
    const viteBind = env.VITE_DEV_SERVER_BIND || '127.0.0.1';

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/messaging.js', 'resources/js/assistant.js', 'resources/js/chat-notifications.js', 'resources/js/dashboard.js', 'resources/js/delistment-charts.js', 'resources/js/motion.js', 'resources/js/workspace.js', 'resources/js/tasks.js'],
                refresh: true,
            }),
            tailwindcss(),
        ],
        server: {
            host: viteBind,
            port: 4000,
            origin: `http://${viteHost}:4000`,
            cors: {
                origin: appOrigin,
            },
            hmr: {
                host: viteHost,
            },
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
            fs: {
                // Pages only ever load the front-end sources and packages; without this Vite would
                // serve any file in the project folder (backups, sessions, logs, uploads, PHP code,
                // the launcher). Tailwind's @source scanning reads from disk, so it is unaffected.
                allow: ['resources', 'node_modules'],
                // Setting deny replaces Vite's defaults, so the first four entries restate them.
                deny: ['.env', '.env.*', '*.{crt,pem}', '**/.git/**', '**/storage/**', '**/bootstrap/cache/**', '**/database/**', '**/vendor/**', '*.sql', '*.log'],
            },
        },
    };
});
