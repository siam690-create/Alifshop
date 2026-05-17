<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class NormalizeDoubleSlashes
{
    public function handle(Request $request, Closure $next)
    {
        $uri = $request->server('REQUEST_URI', '');

        if (str_contains($uri, '//')) {
            $normalizedUri = preg_replace('#/+#', '/', $uri);

            if ($normalizedUri !== null) {
                return redirect($normalizedUri, 301);
            }
        }

        return $next($request);
    }
}
