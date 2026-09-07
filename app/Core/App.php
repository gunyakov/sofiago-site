<?php

declare(strict_types=1);

namespace Sofiago\Core;

/**
 * Tiny service registry. Boot once in public/index.php, then reach services through the
 * app()/db()/auth()/... helper functions in app/Core/helpers.php rather than passing this
 * object around everywhere.
 */
final class App
{
    private static ?App $instance = null;

    public readonly Config $config;
    public readonly DB $db;
    public readonly Session $session;
    public readonly Auth $auth;
    public readonly Template $view;
    public readonly Mailer $mailer;
    public readonly Lang $lang;

    /** @param array<string, mixed> $config */
    private function __construct(array $config)
    {
        // Pinned to match DB::__construct()'s `SET time_zone = '+00:00'` — see the comment
        // there for why both sides must agree before anything calls date()/time() or NOW().
        date_default_timezone_set('UTC');

        $this->config = new Config($config);
        $this->db = new DB($this->config);
        $this->session = new Session($this->config);
        $this->view = new Template(__DIR__ . '/../templates');
        $this->auth = new Auth($this->db, $this->config);
        $this->mailer = new Mailer($this->config);
        $this->lang = new Lang(__DIR__ . '/../../lang');

        $this->view->share([
            'currentUser' => $this->auth->user(),
            'csrfToken' => $this->session->csrfToken(),
            'baseUrl' => rtrim((string) $this->config->get('app.base_url', ''), '/'),
            'locale' => $this->lang->locale(),
        ]);
    }

    /** @param array<string, mixed> $config */
    public static function boot(array $config): self
    {
        return self::$instance ??= new self($config);
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('App::boot() must run before App::get().');
        }

        return self::$instance;
    }
}
