import { defineConfig } from 'vite'

// Builds small ES module bundles straight into public/assets/vue/ — plain
// <script type="module"> includes from the .tpl.php pages, no Node process needed on the
// server (see docs/deploy.md). ES modules (not IIFE) because we have more than one entry
// point and IIFE/UMD don't support that in Rollup.
//
// flip-marker.js is deliberately NOT an entry here (see vite.flip-marker.config.js for its own,
// separate build) — map.js is the only thing in *this* build that imports it, so Rollup inlines
// its source straight into map.js instead of emitting it as a shared chunk map.js then has to
// `import` from a second file at runtime. That second-file version is exactly what broke on
// new.sofiago.eu: map.js gets a fresh `?v=<mtime>` URL from asset() every deploy, but a raw
// `import "./flip-marker.js"` inside it has no such cache-buster, and nginx/Cloudflare cache
// static assets for ~10 years — so a fixed flip-marker.js could sit re-deployed on the origin
// while every real visitor kept getting Cloudflare's stale cached copy indefinitely.
export default defineConfig({
    build: {
        outDir: '../public/assets/vue',
        emptyOutDir: false,
        rollupOptions: {
            input: {
                map: 'src/map.js',
                gallery: 'src/gallery.js',
            },
            output: {
                format: 'es',
                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: '[name].[ext]',
            },
        },
    },
})
