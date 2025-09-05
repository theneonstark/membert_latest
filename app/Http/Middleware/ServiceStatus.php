<?php

namespace App\Http\Middleware;

use Closure;

class ServiceStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $request['via'] = "portal";
        $request['user_id'] = session("loginid");
        return $next($request);
    }
}
