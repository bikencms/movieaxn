<?php

namespace App\Http\Middleware;

use Closure;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;


class LastModified
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
        $response = $next($request);
        $date = DateTime::createFromFormat('Y-m-d H:i:s', Cache::get('gmtTime'));
        if ($date) {
            $response->header('Last-Modified', $date->format('D, d M Y H:i:s \G\M\T'));
        }
        return $response;
    }
}
