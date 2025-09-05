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
        if(\Request::is("fund/utility/transaction")){
            switch ($post->type) {
                case 'request':
                case 'return':
                    return $next($post);
                    break;
            }
        }

        if($post->has("amount")){
            if($post->has("user_id")){
                $user = \DB::table("users")->where("id", $post->user_id)->first(['mainwallet', 'aepswallet', 'matmwallet', 'lockedwallet', 'collectionwallet']);
            }else{
                $user = \DB::table("users")->where("id", session("loginid"))->first(['mainwallet', 'aepswallet', 'matmwallet', 'lockedwallet', 'collectionwallet']);
            }
            $totalBalance = $user->mainwallet + $user->aepswallet + $user->matmwallet + $user->collectionwallet;
            
            if($post->amount > ($totalBalance - $user->lockedwallet)){
                return response()->json(['statuscode' => "ERR", "message" => 'Insufficient Fund In Wallet']);
            }
        }
        return $next($post);
    }
}
