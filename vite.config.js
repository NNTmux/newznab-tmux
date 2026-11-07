import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

/**
 * @alpinejs/csp reads every global once at startup to build its list of safe values.
 * Reading `window.fence` (and the other Fenced Frame globals) makes Chrome log
 * "Fenced Frame API is deprecated", so skip them like Alpine already skips `styleMedia`.
 */
const ALPINE_CSP_GLOBAL_SCAN = 'if (key === "styleMedia" || key === "sharedStorage")';
const ALPINE_CSP_SKIPPED_GLOBALS = ['styleMedia', 'sharedStorage', 'fence', 'HTMLFencedFrameElement', 'FencedFrameConfig'];

function skipDeprecatedGlobalsInAlpineCsp() {
    return {
        name: 'nntmux:alpine-csp-skip-deprecated-globals',
        enforce: 'pre',
        transform(code, id) {
            if (!id.includes('@alpinejs/csp') || !code.includes(ALPINE_CSP_GLOBAL_SCAN)) {
                return null;
            }

            const condition = ALPINE_CSP_SKIPPED_GLOBALS.map((name) => `key === ${JSON.stringify(name)}`).join(' || ');

            return { code: code.replace(ALPINE_CSP_GLOBAL_SCAN, `if (${condition})`), map: null };
        },
    };
}

export default defineConfig(({ mode }) => {
    const environment = loadEnv(mode, process.cwd(), '');
    const vitePort = Number.parseInt(environment.VITE_PORT || '5173', 10);
    const viteDevServerHost = environment.VITE_DEV_SERVER_HOST || 'localhost';

    return {
        server: {
            host: '0.0.0.0',
            port: vitePort,
            strictPort: true,
            origin: `http://${viteDevServerHost}:${vitePort}`,
            hmr: {
                host: viteDevServerHost,
                port: vitePort,
            },
        },
        // Keep Alpine out of dependency pre-bundling so the transform above also applies in dev.
        optimizeDeps: {
            exclude: ['@alpinejs/csp'],
        },
        plugins: [
            skipDeprecatedGlobalsInAlpineCsp(),
            tailwindcss(),
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/forum/blade-tailwind/css/forum.css',
                    'resources/forum/blade-tailwind/js/forum.js',
                ],
                refresh: true,
            }),
        ],
    };
});
