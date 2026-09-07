<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f8f8;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
        color: #22252a;
    }
    .error-page { width: 100%; max-width: 560px; padding: 24px; text-align: center; }
    .error-brand { display: inline-block; margin-bottom: 32px; text-decoration: none; }
    .error-card { background: #fff; border-radius: 24px; padding: 56px 40px; box-shadow: 0 10px 40px rgba(0, 0, 0, .06); }
    .error-code {
        font-family: 'Caveat', cursive;
        font-size: 5.5rem;
        line-height: 1;
        font-weight: 600;
        color: #F84525;
        margin-bottom: 8px;
    }
    .error-card h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 12px; }
    .error-card p { font-size: 1rem; color: #6b7280; line-height: 1.6; margin: 0 0 28px; }
    .error-btn {
        display: inline-block;
        background: #F84525;
        color: #fff;
        text-decoration: none;
        font-weight: 600;
        padding: 12px 32px;
        border-radius: 50px;
        transition: opacity .15s;
    }
    .error-btn:hover { opacity: .9; }
    /* Minimal shims for the Bootstrap utility classes partials/brand.tpl.php uses — these
       error pages are deliberately self-contained (no theme/bootstrap.min.css dependency),
       so a broken/unreachable asset bundle can never take the error page down with it. */
    .d-inline-flex { display: inline-flex; }
    .align-items-center { align-items: center; }
    .gap-2 { gap: .5rem; }
    .fw-bold { font-weight: 700; }
    .fs-3 { font-size: 1.5rem; }
    .text-dark { color: #22252a; }
    .text-white { color: #fff; }
    .text-primary { color: #F84525; }
    .font-caveat { font-family: 'Caveat', cursive; }
    .brand-mark img { vertical-align: middle; }
</style>
