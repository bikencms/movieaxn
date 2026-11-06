<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SetEtag
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $method = $request->getMethod();

        // Support using HEAD method for checking If-None-Match
        if ($request->isMethod('HEAD')) {
            $request->setMethod('GET');
        }

        $response = $next($request);

        // Setting etag
        $etag = md5($response->getContent());
        $response->setEtag($etag);

        $request->setMethod($method);

        return $response;
    }
}
