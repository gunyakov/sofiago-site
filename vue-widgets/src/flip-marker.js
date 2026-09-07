/**
 * FlipMarker — a circular, bordered map marker for MapLibre GL JS that flips 180° around its
 * vertical axis on hover/focus to reveal a "?" back face, and links to a URL on click.
 *
 * Deliberately framework-free (no Vue, no build-time CSS pipeline): plain DOM + a single
 * injected <style> tag, so this one file is a self-contained, drop-in plugin for any page that
 * already loads maplibre-gl — not just the Vue-mounted map islands in this project. It only
 * touches `maplibregl.Marker` (passed in explicitly, see the constructor), nothing Vue-specific.
 *
 * Usage (bundled, as in map.js):
 *   import maplibregl from 'maplibre-gl'
 *   import { FlipMarker, configureFlipMarker } from './flip-marker.js'
 *
 *   configureFlipMarker({ borderColor: '#ff5a1f' }) // once, e.g. to match a brand color
 *
 *   new FlipMarker(maplibregl, { iconUrl: point.map_icon_path, href: `/listings/${point.slug}` })
 *       .setLngLat([point.lng, point.lat])
 *       .addTo(map)
 *
 * Usage (no bundler at all — this file is also its own Vite entry, built straight to
 * public/assets/vue/flip-marker.js, see vue-widgets/vite.config.js):
 *   <script src="https://unpkg.com/maplibre-gl@4/dist/maplibre-gl.js"></script>
 *   <script type="module">
 *     import { FlipMarker } from '/assets/vue/flip-marker.js'
 *     new FlipMarker(maplibregl, { iconUrl: '...', href: '...' }).setLngLat([lng, lat]).addTo(map)
 *   </script>
 */

const DEFAULTS = {
    size: 44, // marker diameter, px
    borderColor: '#ff5a1f',
    borderWidth: 3,
    backSymbol: '?',
    // Deliberately its own setting, not derived from borderColor: the two used to be the same
    // value, and a white border (a very reasonable choice — see configureFlipMarker() call
    // sites) then rendered the "?" invisible, white-on-white. A dark, always-readable default
    // that ignores whatever the border is set to avoids that trap.
    backSymbolColor: '#333333',
    flipMs: 500,
}

// Module-level "settings" — configureFlipMarker() changes what every FlipMarker created
// afterwards falls back to (e.g. one call to set the site's brand border color), without
// forcing every call site to repeat it. Per-instance options in the constructor still win.
let sharedDefaults = { ...DEFAULTS }

export function configureFlipMarker(options) {
    sharedDefaults = { ...sharedDefaults, ...options }
}

let stylesInjected = false

/** Injected once per page, however many FlipMarkers get created — plain <style>, no CSS build step. */
function ensureStyles() {
    if (stylesInjected) {
        return
    }
    stylesInjected = true

    const style = document.createElement('style')
    style.textContent = `
        .sg-flip-marker { display: block; cursor: pointer; text-decoration: none; -webkit-tap-highlight-color: transparent; }
        .sg-flip-marker__scene { width: var(--sg-flip-size); height: var(--sg-flip-size); perspective: 400px; }
        .sg-flip-marker__card {
            position: relative;
            width: 100%;
            height: 100%;
            transition: transform var(--sg-flip-ms) ease;
            transform-style: preserve-3d;
        }
        .sg-flip-marker:hover .sg-flip-marker__card,
        .sg-flip-marker:focus-visible .sg-flip-marker__card {
            transform: rotateY(180deg);
        }
        .sg-flip-marker__face {
            position: absolute;
            inset: 0;
            border-radius: 50%;
            backface-visibility: hidden;
            border: var(--sg-flip-border-width) solid var(--sg-flip-border-color);
            background: #fff;
            box-shadow: 0 2px 8px rgba(0, 0, 0, .3);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .sg-flip-marker__face--front img { width: 100%; height: 100%; object-fit: cover; }
        .sg-flip-marker__face--back {
            transform: rotateY(180deg);
            font-weight: 700;
            font-family: system-ui, sans-serif;
            font-size: calc(var(--sg-flip-size) * 0.45);
            color: var(--sg-flip-symbol-color);
        }
    `;
    document.head.appendChild(style);
}

export class FlipMarker {
    /**
     * @param {{Marker: new (options: object) => object}} maplibregl - the maplibre-gl namespace
     *   the host page is already using (an ES import, or the global UMD script sets
     *   `window.maplibregl`) — only `.Marker` is used, so any build that exposes it works.
     * @param {object} options
     * @param {string} options.iconUrl - front-face image URL
     * @param {string} [options.href] - URL both faces navigate to on click; omit for a static
     *   marker that still flips on hover but isn't a link
     * @param {string} [options.title] - accessible label (aria-label/title, and the front
     *   image's alt text)
     * @param {string} [options.borderColor] - ring color; falls back to configureFlipMarker()'s value
     * @param {number} [options.borderWidth] - ring thickness in px
     * @param {number} [options.size] - marker diameter in px
     * @param {string} [options.backSymbol] - plain text shown on the flipped-to back face
     * @param {string} [options.backSymbolColor] - back face text color (independent of
     *   borderColor on purpose — see DEFAULTS.backSymbolColor)
     * @param {number} [options.flipMs] - flip transition duration in ms
     */
    constructor(maplibregl, options) {
        if (!maplibregl || typeof maplibregl.Marker !== 'function') {
            throw new Error('FlipMarker: pass the maplibregl namespace (with a .Marker class) as the first argument');
        }
        if (!options || !options.iconUrl) {
            throw new Error('FlipMarker: options.iconUrl is required');
        }

        ensureStyles();

        const opts = { ...sharedDefaults, ...options };
        this._marker = new maplibregl.Marker({ element: this._buildElement(opts), anchor: 'center' });
    }

    _buildElement(opts) {
        const root = document.createElement(opts.href ? 'a' : 'div');
        root.className = 'sg-flip-marker';
        root.style.setProperty('--sg-flip-size', opts.size + 'px');
        root.style.setProperty('--sg-flip-border-color', opts.borderColor);
        root.style.setProperty('--sg-flip-border-width', opts.borderWidth + 'px');
        root.style.setProperty('--sg-flip-symbol-color', opts.backSymbolColor);
        root.style.setProperty('--sg-flip-ms', opts.flipMs + 'ms');

        if (opts.href) {
            root.href = opts.href;
        } else {
            root.tabIndex = 0; // still focusable/flippable via keyboard without being a link
        }
        if (opts.title) {
            root.title = opts.title;
            root.setAttribute('aria-label', opts.title);
        }

        const scene = document.createElement('div');
        scene.className = 'sg-flip-marker__scene';

        const card = document.createElement('div');
        card.className = 'sg-flip-marker__card';

        const front = document.createElement('div');
        front.className = 'sg-flip-marker__face sg-flip-marker__face--front';
        const img = document.createElement('img');
        img.src = opts.iconUrl;
        img.alt = opts.title || '';
        front.appendChild(img);

        const back = document.createElement('div');
        back.className = 'sg-flip-marker__face sg-flip-marker__face--back';
        back.textContent = opts.backSymbol; // textContent, not innerHTML — no markup expected/allowed here

        card.appendChild(front);
        card.appendChild(back);
        scene.appendChild(card);
        root.appendChild(scene);

        return root;
    }

    setLngLat(lngLat) {
        this._marker.setLngLat(lngLat);

        return this;
    }

    addTo(map) {
        this._marker.addTo(map);

        return this;
    }

    remove() {
        this._marker.remove();

        return this;
    }

    getElement() {
        return this._marker.getElement();
    }

    getLngLat() {
        return this._marker.getLngLat();
    }
}
