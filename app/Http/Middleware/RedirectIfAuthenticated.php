<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string|null  $guard
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        // $code = \DB::table('portal_settings')->where('code', 'envmode')->first(['value']);
        // if($code){
        //     if($code->value == "server"){
        //         if (!$request->secure()) {
        //             return redirect()->secure($request->getRequestUri());
        //         }
        //     }
        // }

        // $ip = \DB::table('portal_settings')->where('code', 'whitelistip')->first(['value']);

        // if($ip->value != "::1" && $ip->value != $request->ip()){
        //     abort(403);
        // }
        
        if (Auth::guard($guard)->check()) {
            return redirect('/dashboard');
        }

        return $next($request);
    }
}
