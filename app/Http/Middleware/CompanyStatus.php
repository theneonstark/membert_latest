<?php

namespace App\Http\Middleware;

use Closure;

class CompanyStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if(\Request::is('member/profile/update')
        ){
            if($request->actiontype == "password"){
                return $next($request);
            }
        }
        
        $ip = \DB::table('portal_settings')->where('code', 'whitelistip')->first(['value']);

        if($ip->value != "::1" && $ip->value != $request->ip()){
            abort(403);
        }

        $user = \DB::table('users')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->leftJoin('companies', 'companies.id', '=', 'users.company_id')
            ->where('users.id', session("loginid"))->first(['users.id', 'roles.slug as slug', 'users.status', 'companies.status as companystatus', 'users.permission_change', 'users.kyc']);
        
        if($user && !$user->companystatus && $user->id !="489"){
            abort(503);
        }

        if($user && $user->slug !="admin" && $user->status == "blocked"){
            return redirect(route('logout'));
        }

        if($user->permission_change == "yes"){
            \Storage::disk('permission')->delete("permissions/permission".$user->id);
            \DB::table("users")->where("id", $user->id)->update(['permission_change' => "no"]);
        }

        if($request->user() && $request->user()->role->slug !="admin" && $request->user()->kyc == "pending" && !\Request::is('onboard/complete')){
            return redirect(route('onboarding'));
        }

        if(!in_array($user->kyc, ["verified", "approved", "pending"])){
            return redirect(route("home"));
        }

        return $next($request);
    }
}
