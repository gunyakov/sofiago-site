<?php

declare(strict_types=1);

namespace Sofiago\Controllers;

use Sofiago\Middleware\Guards;

final class DashboardController
{
    public function index(array $params): void
    {
        Guards::requireAuth();

        echo view('dashboard/index.tpl.php', ['title' => t('dashboard.index.title')], 'layout/dashboard.tpl.php');
    }
}
