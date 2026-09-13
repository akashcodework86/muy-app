<?php

namespace App\Http\Controllers\Api\Incubatee;

use App\Http\Controllers\Controller;
use App\Services\IncubateeAppPayloadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request, IncubateeAppPayloadService $payload): JsonResponse
    {
        return response()->json($payload->dashboard($request->user()));
    }
}
