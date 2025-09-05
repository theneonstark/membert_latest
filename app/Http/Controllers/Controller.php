<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Carbon\Carbon;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    public function getAccBalance($id, $wallet)
    {
        $mywallet = \DB::table('users')->where('id', $id)->first([$wallet]);

        $mywallet = (array) $mywallet;
        return $mywallet[$wallet];
    }

    public function env_mode()
    {
        $code = \DB::table('portal_settings')->where('code', 'envmode')->first(['value']);
        if($code){
           return $code->value;
        }else{
            return "local";
        }
    }

    public function transcode()
    {
        $code = \DB::table('portal_settings')->where('code', 'transactioncode')->first(['value']);
        if($code){
           return $code->value;
        }else{
            return "none";
        }
    }
    
    public function pinbased()
    {
        $code = \DB::table('portal_settings')->where('code', 'pincheck')->first(['value']);
        if($code){
           return $code->value;
        }else{
            return "no";
        }
    }
}
