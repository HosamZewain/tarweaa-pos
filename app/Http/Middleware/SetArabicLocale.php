<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class SetArabicLocale
{
    public function handle(Request $request, Closure $next): mixed
    {
        App::setLocale('ar');

        return $next($request);
    }
}
