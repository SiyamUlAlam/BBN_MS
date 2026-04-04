<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Services\DashboardService;

final class DashboardController
{
    public function __construct(private readonly DashboardService $dashboard = new DashboardService())
    {
    }

    public function summary(Request $request): void
    {
        $month = (string) ($request->query['month'] ?? date('Y-m'));

        Response::json([
            'status' => 'success',
            'data' => $this->dashboard->summary($month),
        ]);
    }
}
