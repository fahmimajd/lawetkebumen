import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const host = env.VITE_HOST || 'localhost';
    const port = Number(env.VITE_PORT || 5173);
    const origin = env.VITE_DEV_SERVER_URL || `http://${host}:${port}`;
    const appUrl = env.APP_URL || `http://${host}:9000`;

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
        ],
        server: {
            host: '0.0.0.0',
            port,
            strictPort: true,
            origin,
            cors: {
                origin: appUrl,
                credentials: true,
            },
            hmr: {
                host,
                port,
            },
        },
    };
});
