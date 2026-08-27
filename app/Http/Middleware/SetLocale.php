<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get('locale', $request->cookie('locale', config('app.locale', 'fr')));
        $locale = in_array($locale, config('app.supported_locales', ['fr', 'en']), true) ? $locale : config('app.fallback_locale', 'fr');
        App::setLocale($locale);

        return $next($request);
    }
}
