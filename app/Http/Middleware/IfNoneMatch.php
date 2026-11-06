<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IfNoneMatch
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

        if ($request->isMethod('HEAD')) {
            $request->setMethod('GET');
        }

        //Handle response
        $response = $next($request);

        $etag = '"'.md5($response->getContent()).'"';
        $noneMatch = $request->getETags();

        if (in_array($etag, $noneMatch)) {
            $response->setNotModified();
        }

        $request->setMethod($method);

        return $response;
    }
}
