<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackOrderSource
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->hasSession() || $this->shouldSkip($request)) {
            return $next($request);
        }

        $source = $this->detectSource($request);
        $current = $request->session()->get('order_source_channel');

        if ($source) {
            $request->session()->put('order_source_channel', $source);
        } elseif (!$current && $request->isMethod('GET')) {
            $request->session()->put('order_source_channel', 'direct');
        }

        return $next($request);
    }

    private function shouldSkip(Request $request): bool
    {
        return $request->is('admin*')
            || $request->is('api*')
            || $request->is('public/*')
            || $request->is('storage/*')
            || $request->is('vendor/*');
    }

    private function detectSource(Request $request): ?string
    {
        if ($request->query('fbclid')) {
            return 'facebook';
        }

        if ($request->query('ttclid')) {
            return 'tiktok';
        }

        $source = strtolower(trim((string) $request->query('utm_source', $request->query('source', ''))));
        $referer = strtolower((string) $request->headers->get('referer', ''));
        $haystack = $source . ' ' . $referer;

        if (str_contains($haystack, 'facebook') || preg_match('/(^|\W)fb($|\W)/', $haystack) || str_contains($haystack, 'm.me') || str_contains($haystack, 'messenger')) {
            return 'facebook';
        }

        if (str_contains($haystack, 'tiktok') || str_contains($haystack, 'tik tok')) {
            return 'tiktok';
        }

        return null;
    }
}
