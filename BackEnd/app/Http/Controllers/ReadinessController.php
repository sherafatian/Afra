<?php

namespace App\Http\Controllers;

use App\Services\Readiness;
use Illuminate\Http\JsonResponse;

class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $state = app(Readiness::class)->inspect(app()->isProduction());

        // Public probes expose only availability, never exception details or key/database paths.
        return response()->json(['data' => ['ready_for_requests' => $state['ready_for_requests'], 'release_acceptance' => $state['release_acceptance']]], $state['ready_for_requests'] ? 200 : 503);
    }
}
