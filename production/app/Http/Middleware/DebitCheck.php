<?php

namespace App\Http\Middleware;

use Closure;

class DebitCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($post, Closure $next)
    {
        if($post->has("amount")){
            $user = \DB::table("users")->where("id", $post->user_id)->first(['mainwallet', 'aepswallet', 'matmwallet', 'lockedwallet']);
            $totalBalance = $user->mainwallet + $user->aepswallet + $user->matmwallet;
            
            if($post->amount > ($totalBalance - $user->lockedwallet)){
                return response()->json(['statuscode' => "ERR", "message" => 'Insufficient Wallet Balance']);
            }
        }
        return $next($post);
    }
}
