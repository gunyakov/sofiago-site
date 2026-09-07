import { defineConfig } from 'vite'

// Separate build, run as its own `vite build --config vite.flip-marker.config.js` step (see
// package.json) — produces the standalone public/assets/vue/flip-marker.js drop-in that
// flip-marker.js's own file-header docs promise: a plain `<script type="module">` include for
// any MapLibre page, no bundler needed. Kept out of the main vite.config.js on purpose (see the
// comment there) so map.js inlines this module's source instead of importing it as a separate,
// separately-cached file at runtime.
//
// Uses Vite's library mode (build.lib), not a plain rollupOptions.input entry like
// vite.config.js's — flip-marker.js has no top-level side effects (it only declares a class and
// some functions; nothing runs just by loading the module), and Vite's regular app-build
// tree-shaking assumes an unreferenced, side-effect-free entry is dead code and deletes it
// wholesale, exports included ("Generated an empty chunk" — caught by actually opening the
// built file, not just checking that `vite build` exited 0). Library mode is built for exactly
// this case: an entry whose whole job is its exports, not anything it executes on load.
export default defineConfig({
    build: {
        outDir: '../public/assets/vue',
        emptyOutDir: false,
        lib: {
            entry: 'src/flip-marker.js',
            formats: ['es'],
            fileName: () => 'flip-marker.js',
        },
    },
})
