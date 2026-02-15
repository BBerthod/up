<?php

namespace App\Http\Controllers;

use App\Services\MetricsService;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(MetricsService $metricsService): Response
    {
        return Inertia::render('Dashboard', [
            'metrics' => $metricsService->getDashboardMetrics(),
        ]);
    }
}
