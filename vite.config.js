import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import fs from 'fs';

export default defineConfig({
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        https: {
            key: fs.readFileSync('./ssl/space-wars-3002.local-key.pem'),
            cert: fs.readFileSync('./ssl/space-wars-3002.local.pem'),
        },
        hmr: {
            host: 'space-wars-3002.local',
            protocol: 'wss',
        },
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(), // 👈 this is the critical piece
    ],
});
