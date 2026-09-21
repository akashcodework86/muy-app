<?php

namespace App\Http\Middleware;

use App\Support\IncubateeLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetIncubateeLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->role === 'incubatee') {
            $locale = IncubateeLocale::apply($request);
            view()->share('incubateeLocale', $locale);
        }

        return $next($request);
    }
}
