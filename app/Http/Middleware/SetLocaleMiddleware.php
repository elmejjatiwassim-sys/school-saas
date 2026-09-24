<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocaleMiddleware
{
    /**
     * Supported application locales.
     *
     * @var list<string>
     */
    public const SUPPORTED_LOCALES = ['ar', 'fr', 'en'];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale');

        if (! $locale && auth()->check() && auth()->user()->locale) {
            $locale = auth()->user()->locale;
            session(['locale' => $locale]);
        }

        if (! $locale || ! in_array($locale, self::SUPPORTED_LOCALES, true)) {
            $fallback = config('app.locale', 'ar');
            $locale = in_array($fallback, self::SUPPORTED_LOCALES, true) ? $fallback : 'ar';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
