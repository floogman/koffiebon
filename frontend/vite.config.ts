import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import { VitePWA } from 'vite-plugin-pwa'
import path from 'node:path'

// In de container draait de Laravel-API op poort 80; de Vite-dev-server proxyt
// /api daarnaartoe zodat de PWA same-origin met bearer-tokens werkt.
export default defineConfig({
    plugins: [
        react(),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.svg', 'icons/apple-touch-icon.png'],
            manifest: {
                name: 'Koffiebon',
                short_name: 'Koffiebon',
                description: 'Je prepaid koffiekaart — toon je QR aan de balie.',
                theme_color: '#1e1410',
                background_color: '#f5ece1',
                display: 'standalone',
                start_url: '/',
                scope: '/',
                icons: [
                    { src: '/icons/icon-192.png', sizes: '192x192', type: 'image/png' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png' },
                    { src: '/icons/icon-512.png', sizes: '512x512', type: 'image/png', purpose: 'maskable' },
                ],
            },
            workbox: {
                // App-shell cachen; API-calls (incl. QR) nooit cachen — saldo/QR vereisen netwerk.
                navigateFallbackDenylist: [/^\/api/],
                runtimeCaching: [],
            },
        }),
    ],
    // De VITE_*-variabelen (o.a. Reverb) staan in de root-.env naast de Laravel-app.
    envDir: path.resolve(__dirname, '..'),
    resolve: {
        alias: { '@shared': path.resolve(__dirname, 'src/shared') },
    },
    server: {
        host: '0.0.0.0',
        port: 5173,
        proxy: {
            '/api': { target: 'http://localhost:80', changeOrigin: true },
        },
    },
})
