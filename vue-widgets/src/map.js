import { createApp, onMounted } from 'vue'
import maplibregl from 'maplibre-gl'
import 'maplibre-gl/dist/maplibre-gl.css'
import { FlipMarker, configureFlipMarker } from './flip-marker.js'

// Maptorium (the user's own map-tile provider project) — "bright" style.
const STYLE_URL = 'https://maptorium.net/api/style?key=33C8621AED7B2B20D17ED11A85DE4594&style=bright'

// White ring — the one "setting" FlipMarker exposes — reads cleanly against both the brand-
// orange default category icons (public/assets/img/category-icons/) and whatever color/photo an
// owner's own uploaded icon happens to be, wired once here rather than repeated at every call site.
configureFlipMarker({ borderColor: '#ffffff' })

/** category_slug -> its default marker icon when a listing has no map_icon_path of its own. */
function categoryIconUrl(categorySlug) {
    return `/assets/img/category-icons/${encodeURIComponent(categorySlug)}.svg`
}

/**
 * Full catalog map: fetches markers from the JSON endpoint and drops a FlipMarker for each — the
 * listing's own uploaded icon (dashboard/listing-form.tpl.php) if it has one, otherwise its
 * category's default icon. Every marker is a direct link to the listing (see flip-marker.js); the
 * old default-pin + hover-popup preview card is gone now that every listing gets a real icon one
 * way or the other, not just the minority with a custom upload.
 */
function mountExploreMap(el) {
    createApp({
        setup() {
            onMounted(async () => {
                const lat = parseFloat(el.dataset.lat)
                const lng = parseFloat(el.dataset.lng)
                const zoom = parseFloat(el.dataset.zoom || '12')

                const map = new maplibregl.Map({
                    container: el,
                    style: STYLE_URL,
                    center: [lng, lat],
                    zoom,
                })
                map.addControl(new maplibregl.NavigationControl(), 'top-right')

                // Hover a card in the results column (list or grid view, both render
                // data-lat/data-lng on listing-card(-list).tpl.php's outer .card) and the map
                // recenters on that listing — same idea as the theme's own half-map layout.
                // Delegated on the results column itself rather than one listener per card:
                // the list is plain server-rendered HTML, not a SPA, so cards never get added
                // or removed without a full page reload anyway. mouseover/mouseout (not
                // mouseenter/mouseleave, which don't bubble) + an activeCard guard so moving the
                // mouse between child elements inside the same card doesn't restart the pan on
                // every step.
                const listCol = document.querySelector('.explore-list-col')
                if (listCol) {
                    let activeCard = null
                    listCol.addEventListener('mouseover', (e) => {
                        const card = e.target.closest('[data-lat][data-lng]')
                        if (!card || card === activeCard) return
                        activeCard = card
                        const cardLat = parseFloat(card.dataset.lat)
                        const cardLng = parseFloat(card.dataset.lng)
                        if (Number.isNaN(cardLat) || Number.isNaN(cardLng)) return
                        map.easeTo({ center: [cardLng, cardLat], duration: 400 })
                    })
                    listCol.addEventListener('mouseout', (e) => {
                        const card = e.target.closest('[data-lat][data-lng]')
                        if (card && card === activeCard && !card.contains(e.relatedTarget)) {
                            activeCard = null
                        }
                    })
                }

                const apiUrl = el.dataset.api
                if (!apiUrl) {
                    return
                }

                let filters = {}
                try {
                    filters = JSON.parse(el.dataset.filters || '{}')
                } catch {
                    // ignore malformed data-filters, just show the unfiltered map
                }

                const params = new URLSearchParams()
                if (filters.q) params.set('q', filters.q)
                if (filters.category) params.set('category', filters.category)
                // Home page only (see home.tpl.php) — caps the result at a random,
                // VIP-first sample (Listing::randomFeatured()) instead of every matching
                // listing, which is what /explore's own map still gets (its data-filters
                // never sets this).
                if (filters.featured) params.set('featured', '1')

                try {
                    const query = params.toString()
                    const res = await fetch(apiUrl + (query ? '?' + query : ''))
                    const points = await res.json()

                    for (const point of points) {
                        new FlipMarker(maplibregl, {
                            iconUrl: point.map_icon_path || categoryIconUrl(point.category_slug),
                            href: `/listings/${encodeURIComponent(point.slug)}`,
                            title: point.title,
                        }).setLngLat([parseFloat(point.lng), parseFloat(point.lat)]).addTo(map)
                    }
                } catch (err) {
                    console.error('SofiaGO map: failed to load listings', err)
                }
            })

            return () => null
        },
    }).mount(el)
}

/** Single-pin map for a listing detail page — no fetch, coords come from data attributes. */
function mountSingleMap(el) {
    createApp({
        setup() {
            onMounted(() => {
                const lat = parseFloat(el.dataset.lat)
                const lng = parseFloat(el.dataset.lng)

                const map = new maplibregl.Map({
                    container: el,
                    style: STYLE_URL,
                    center: [lng, lat],
                    zoom: 15,
                })
                map.addControl(new maplibregl.NavigationControl(), 'top-right')

                // No `href` here — this map is already on the listing's own page, so the marker
                // has nowhere useful to link to; it still flips on hover, just isn't a link.
                new FlipMarker(maplibregl, {
                    iconUrl: el.dataset.icon || categoryIconUrl(el.dataset.category),
                    title: el.dataset.title,
                }).setLngLat([lng, lat]).addTo(map)
            })

            return () => null
        },
    }).mount(el)
}

/**
 * Location picker for the add/edit listing form: click anywhere to drop (or move) a marker,
 * writing the coordinates into the two hidden inputs named by data-lat-input/data-lng-input.
 * No geocoding — the address itself stays a plain free-text field the owner fills in separately.
 */
function mountPickerMap(el) {
    createApp({
        setup() {
            onMounted(() => {
                const defaultLat = parseFloat(el.dataset.lat || '42.6977')
                const defaultLng = parseFloat(el.dataset.lng || '23.3219')
                const hasInitial = el.dataset.lat && el.dataset.lng

                const latInput = document.getElementById(el.dataset.latInput)
                const lngInput = document.getElementById(el.dataset.lngInput)

                const map = new maplibregl.Map({
                    container: el,
                    style: STYLE_URL,
                    center: [defaultLng, defaultLat],
                    zoom: hasInitial ? 15 : 12,
                })
                map.addControl(new maplibregl.NavigationControl(), 'top-right')

                let marker = null

                function placeMarker(lng, lat) {
                    if (marker) {
                        marker.setLngLat([lng, lat])
                    } else {
                        marker = new maplibregl.Marker({ color: '#ff5a1f', draggable: true })
                            .setLngLat([lng, lat])
                            .addTo(map)
                        marker.on('dragend', () => {
                            const pos = marker.getLngLat()
                            writeCoords(pos.lng, pos.lat)
                        })
                    }
                    writeCoords(lng, lat)
                }

                function writeCoords(lng, lat) {
                    if (latInput) latInput.value = lat.toFixed(7)
                    if (lngInput) lngInput.value = lng.toFixed(7)
                }

                if (hasInitial) {
                    placeMarker(defaultLng, defaultLat)
                }

                map.on('click', (e) => placeMarker(e.lngLat.lng, e.lngLat.lat))
            })

            return () => null
        },
    }).mount(el)
}

function init() {
    const explore = document.getElementById('map-explore')
    if (explore) mountExploreMap(explore)

    const single = document.getElementById('map-listing')
    if (single) mountSingleMap(single)

    const picker = document.getElementById('map-picker')
    if (picker) mountPickerMap(picker)
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
} else {
    init()
}
