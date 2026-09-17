<?php

declare(strict_types=1);

/**
 * Small global helpers used from controllers and .tpl.php templates. Kept as plain functions
 * (rather than static methods) so templates stay readable: <?= e($listing['title']) ?>.
 */

use Sofiago\Core\App;
use Sofiago\Core\Auth;
use Sofiago\Core\DB;

function app(): App
{
    return App::get();
}

function db(): DB
{
    return app()->db;
}

function auth(): Auth
{
    return app()->auth;
}

/** @param array<string, string|int> $replace ':name' => value substitutions — see Lang::get(). */
function t(string $key, array $replace = []): string
{
    return app()->lang->get($key, $replace);
}

/**
 * Like t(), but in an explicit locale rather than the current request's — for text addressed to
 * a specific user (an email) in *their* stored locale, not whichever locale happens to be active
 * for the request sending it. See Lang::getFor().
 *
 * @param array<string, string|int> $replace
 */
function t_for(string $locale, string $key, array $replace = []): string
{
    return app()->lang->getFor($locale, $key, $replace);
}

function locale(): string
{
    return app()->lang->locale();
}

/**
 * The current page's URL with `lang` swapped to $locale — every other query param (search
 * filters, pagination, view=grid, ...) untouched. Used by the nav's language switcher; clicking
 * it is also what actually persists the choice (Lang::resolve() sets the cookie on a ?lang= hit).
 */
function lang_switch_url(string $locale): string
{
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $query = $_GET;
    $query['lang'] = $locale;

    return url(ltrim($path, '/') . '?' . http_build_query($query));
}

/**
 * The current request's canonical URL. `lang` is always dropped — it's a cookie-set display
 * preference (see Lang::resolve()), not distinct content, so `?lang=en` on an already-English
 * page would otherwise canonicalize to a byte-identical duplicate of the plain URL. Callers can
 * drop further params that don't change the underlying content either (e.g. /explore's `view`,
 * list vs grid being a display toggle over the same result set). Remaining params are sorted so
 * the same request in a different param order still canonicalizes to one URL.
 *
 * @param array<int, string> $alsoDrop
 */
function canonical_url(array $alsoDrop = []): string
{
    $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
    $query = $_GET;
    foreach ([...$alsoDrop, 'lang'] as $param) {
        unset($query[$param]);
    }
    ksort($query);

    $qs = http_build_query($query);

    return url(ltrim($path, '/') . ($qs !== '' ? '?' . $qs : ''));
}

/** Render pages/{$page} wrapped in the main layout. */
/**
 * $layout lets dashboard/admin controllers opt into the separate dashboard shell
 * (layout/dashboard.tpl.php — sidebar + topbar, ListOn's own dashboard theme assets) instead
 * of the public site's layout/main.tpl.php. See memory/dashboard theme note for why they're
 * deliberately two different visual systems rather than one shared chrome.
 */
function view(string $page, array $data = [], string $layout = 'layout/main.tpl.php'): string
{
    return app()->view->renderPage($page, $data, $layout);
}

/** Render a template without a layout (used for error pages, emails, ajax fragments). */
function render(string $name, array $data = []): string
{
    return app()->view->render($name, $data);
}

function partial(string $name, array $data = []): string
{
    return app()->view->partial($name, $data);
}

/** HTML-escape for safe output. Every dynamic value in a .tpl.php file should go through this. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim((string) app()->config->get('app.base_url', ''), '/');

    return $base . '/' . ltrim($path, '/');
}

/**
 * Appends a `?v=<mtime>` cache-buster whenever the file actually exists on disk, so a stale
 * build can never survive behind Cloudflare's edge cache (it sets a 10-year Cache-Control on
 * static assets at the edge, outside our control — see docs/deploy.md). Every redeploy that
 * changes a file's mtime therefore gets a fresh cache key automatically, with no manifest.json
 * or renamed-file bookkeeping needed.
 */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $url = url('assets/' . $path);

    if (defined('SOFIAGO_PUBLIC_PATH')) {
        $full = SOFIAGO_PUBLIC_PATH . '/assets/' . $path;
        if (is_file($full)) {
            $url .= '?v=' . filemtime($full);
        }
    }

    return $url;
}

/**
 * The map Vue island (vue-widgets/src/map.js, built into public/assets/vue/) is only pulled
 * in on pages that actually have a #map-explore or #map-listing div — pass these into the
 * `pageStyles`/`pageScripts` view() data on those pages only, see layout/main.tpl.php.
 */
function map_widget_styles(): string
{
    return '<link rel="stylesheet" href="' . e(asset('vue/map.css')) . '">';
}

function map_widget_scripts(): string
{
    return '<script type="module" src="' . e(asset('vue/map.js')) . '"></script>';
}

function gallery_widget_scripts(): string
{
    return '<script type="module" src="' . e(asset('vue/gallery.js')) . '"></script>';
}

function csrf_token(): string
{
    return app()->session->csrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

/**
 * The whole previous $_POST, if the form re-rendered after a validation error — memoized since
 * Session::pullOld() clears it on read. Plain old() below reads a flat key out of this; a
 * template with a nested field (e.g. hours[mon][open]) reads old_all()['hours'] directly, since
 * old() only handles flat string values.
 *
 * @return array<string, mixed>
 */
function old_all(): array
{
    static $old = null;
    $old ??= app()->session->pullOld();

    return $old;
}

/** Value the user previously typed into $key, if the form re-rendered after a validation error. */
function old(string $key, string $default = ''): string
{
    return e(old_all()[$key] ?? $default);
}

function flash(string $key): mixed
{
    return app()->session->flash($key);
}

/** @param array<int, string> $errors */
function flash_errors(array $errors): void
{
    app()->session->flash('form_errors', $errors);
}

/** @return array<int, string> */
function errors(): array
{
    static $errors = null;
    $errors ??= (array) (app()->session->flash('form_errors') ?? []);

    return $errors;
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/** Abort the request with a status code + a small template from app/templates/errors/. */
function abort(int $status, string $message = ''): never
{
    http_response_code($status);

    $titles = [403 => t('errors.title_403'), 419 => t('errors.title_419'), 500 => t('errors.title_500')];
    $specific = __DIR__ . "/../templates/errors/{$status}.tpl.php";

    if (is_file($specific)) {
        echo render("errors/{$status}.tpl.php", ['message' => $message]);
    } else {
        echo render('errors/generic.tpl.php', [
            'status' => $status,
            'title' => $titles[$status] ?? t('errors.default_title'),
            'message' => $message,
        ]);
    }

    exit;
}

/**
 * JSON equivalent of abort() — for sofiago-flutter's API controllers, which have no HTML error
 * template to render. $extra merges into the JSON body alongside 'error' (e.g. validation
 * messages) — see ApiListingController for that usage.
 *
 * @param array<string, mixed> $extra
 */
function abortJson(int $status, string $error, array $extra = []): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $error, ...$extra], JSON_UNESCAPED_UNICODE);
    exit;
}
