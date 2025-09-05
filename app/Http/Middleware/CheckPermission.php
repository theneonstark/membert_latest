<?php

namespace App\Http\Middleware;

use Closure;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $permissions)
    {
        $permissions = explode("|", $permissions);
        foreach ($permissions as  $value) {
            $permit = \DB::table('permissions')->where('slug', $value)->first(['id']);
            $ids[]= $permit->id;
        }

        $count = \DB::table('user_permissions')->whereIn('permission_id', $ids)->where('user_id', $request->user()->id)->count();
        if($count > 0 || $request->user()->role->slug == "admin"){
            return $next($request);
        }else{
            abort(401);
        }
    }
}
