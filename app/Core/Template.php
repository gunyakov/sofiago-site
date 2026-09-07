<?php

declare(strict_types=1);

namespace Sofiago\Core;

/**
 * A deliberately simple view layer — spiritual successor to the old class.Template.php's
 * {KEY} string-replace engine, but the .tpl.php files are plain PHP (so loops/conditionals
 * work natively) and output is escaped by default via the global e() helper instead of being
 * echoed raw. See app/Core/helpers.php for the e()/partial()/asset()/url() functions templates
 * actually call.
 */
final class Template
{
    /** @var array<string, mixed> */
    private array $shared = [];

    public function __construct(private string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    /** Data available to every render() call (current user, flash messages, csrf token, ...). */
    public function share(array $data): void
    {
        $this->shared = array_merge($this->shared, $data);
    }

    /** @param array<string, mixed> $data */
    public function render(string $name, array $data = []): string
    {
        return $this->capture($this->resolve($name), array_merge($this->shared, $data));
    }

    /**
     * Renders app/templates/pages/{$page} as the `$content` variable of a layout template.
     *
     * @param array<string, mixed> $data
     */
    public function renderPage(string $page, array $data = [], string $layout = 'layout/main.tpl.php'): string
    {
        $content = $this->render('pages/' . $page, $data);

        return $this->render($layout, array_merge($data, ['content' => $content]));
    }

    /** @param array<string, mixed> $data */
    public function partial(string $name, array $data = []): string
    {
        return $this->render('partials/' . $name, $data);
    }

    private function resolve(string $name): string
    {
        $path = $this->basePath . '/' . ltrim($name, '/');

        if (!is_file($path)) {
            throw new \RuntimeException("Template not found: {$name}");
        }

        return $path;
    }

    /** @param array<string, mixed> $vars */
    private function capture(string $file, array $vars): string
    {
        $render = function () use ($file, $vars): void {
            extract($vars, EXTR_SKIP);
            include $file;
        };

        ob_start();

        try {
            $render();

            return ob_get_clean() ?: '';
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
    }
}
