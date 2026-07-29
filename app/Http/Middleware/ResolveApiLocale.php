<?php

namespace App\Http\Middleware;

use App\Services\LanguageRegistry;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveApiLocale
{
    public function __construct(private readonly LanguageRegistry $languages)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $explicit = $request->query('locale');
        if (is_string($explicit) && ! $this->languages->supports($explicit)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Locale is not supported.',
                'errors' => ['locale' => ['Locale is not supported.']],
            ], 422);
        }

        $candidates = [
            is_string($explicit) ? $explicit : null,
            $request->header('X-Locale'),
            $request->user()?->preferred_locale,
            ...($request->hasHeader('Accept-Language') ? $request->getLanguages() : []),
        ];

        $locale = $this->languages->defaultLocale();
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $this->languages->supports($candidate)) {
                $locale = $this->languages->normalize($candidate);
                break;
            }
        }

        app()->setLocale($locale);
        config()->set('app.fallback_locale', $this->languages->fallbackLocale());
        $request->attributes->set('content_locale', $locale);

        $response = $next($request);
        $response->headers->set('Content-Language', $locale);
        $response->headers->set('Vary', trim($response->headers->get('Vary', '').', Accept-Language, X-Locale', ', '));

        return $response;
    }
}
