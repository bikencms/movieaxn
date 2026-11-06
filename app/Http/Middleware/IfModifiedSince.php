<?php

namespace App\Http\Middleware;

use Closure;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class IfModifiedSince
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $date = DateTime::createFromFormat('D, d M Y H:i:s \G\M\T', $request->header('If-Modified-Since'));
        if ($date && $date->format('Y-m-d H:i:s') === Cache::get('gmtTime', '')) {
            return response('', 304);
        }
        return $next($request);
    }
}
