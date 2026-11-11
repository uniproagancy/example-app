import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google, local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/css/filament/admin/theme.css'],
            refresh: true,
            fonts: [
                local('HelveticaRegular', {
                    alias: 'helvetica-regular',
                    optimizedFallbacks: false,
                    variable: '--font-family-body',
                    fallbacks: ['ui-sans-serif', 'system-ui', 'sans-serif'],
                    variants: [
                        { src: 'resources/fonts/Linotype-HelveticaNeueLTGEO55Roman.ttf', weight: '100 500' },
                        { src: 'resources/fonts/Linotype-HelveticaNeueLTGEO75Bold.ttf', weight: '600 900' },
                    ],
                }),
                local('HelveticaGeoCaps', {
                    alias: 'helvetica-geo-caps',
                    optimizedFallbacks: false,
                    variable: '--font-family-caps',
                    fallbacks: ['HelveticaRegular', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                    variants: [
                        { src: 'resources/fonts/helvetica-neue-lt-geo-55-roman-caps.ttf', weight: '100 900' },
                    ],
                }),
                local('BPGNinoMtavruli', {
                    alias: 'bpg-nino-mtavruli',
                    optimizedFallbacks: false,
                    variable: '--font-family-mtavruli',
                    fallbacks: ['HelveticaGeoCaps', 'ui-sans-serif', 'sans-serif'],
                    preload: false,
                    variants: [
                        { src: 'resources/fonts/bpg_nino_mtavruli_normal.ttf', weight: '100 900' },
                    ],
                }),
                google('Montserrat', {
                    alias: 'montserrat',
                    optimizedFallbacks: false,
                    variable: '--font-family-montserrat',
                    weights: [700, 800],
                    subsets: ['latin'],
                    fallbacks: ['HelveticaRegular', 'ui-sans-serif', 'sans-serif'],
                }),
                google('Manrope', {
                    alias: 'manrope',
                    optimizedFallbacks: false,
                    variable: '--font-family-manrope',
                    weights: [500, 600, 700],
                    subsets: ['latin'],
                    fallbacks: ['HelveticaRegular', 'ui-sans-serif', 'sans-serif'],
                }),
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
