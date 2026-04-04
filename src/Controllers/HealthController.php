<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;

final class HealthController
{
    public function index(Request $request): void
    {
        Response::json([
            'status' => 'ok',
            'service' => 'BBN ISP Management API',
            'timestamp' => date(DATE_ATOM),
        ]);
    }
}
