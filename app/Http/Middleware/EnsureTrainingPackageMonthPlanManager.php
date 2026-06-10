<?php

namespace App\Http\Middleware;

use App\Support\TrainingPackageMonthPlanAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTrainingPackageMonthPlanManager
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! TrainingPackageMonthPlanAccess::canManage($request->user())) {
            abort(403);
        }

        return $next($request);
    }
}
