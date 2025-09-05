<?php

namespace App\Http\Controllers\Member;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Circle;
use App\Models\Role;

class SettingController extends Controller
{
    public function index(Request $get, $id=0)
    {
        $data = [];
        $data['tab'] = $get->tab;
        $data['user'] = \Auth::user();
        $data['state'] = Circle::all(['state']);
        return view('member.profile')->with($data);
    }

    public function profileUpdate(\App\Http\Requests\Member $post)
    {
        switch ($post->actiontype) {
            case 'password':
                $credentials = [
                    'mobile'   => \Auth::user()->mobile,
                    'password' => $post->oldpassword
                ];
        
                if(!\Auth::validate($credentials)){
                    return response()->json(['errors' =>  ['oldpassword'=>'Please enter corret old password']], 422);
                }

                $response = User::where('id', $post->id)->update([
                    'password' => bcrypt($post->password),
                    "resetpwd" => "changed"
                ]);
                break;

            case 'scheme':
                $response = User::where('id', $post->id)->update([
                    "scheme_id" => $post->scheme_id
                ]);
                break;

            default : 
                $response = false;
                break;
        }
            
        try{
            if($response){
                return response()->json(['status' => "TXN", "message" => 'success']);
            }else{
                return response()->json(['status' => "ERR", "message" => 'fail']);
            }
        } catch (\Exception $e) {
            \DB::table('log_500')->insert([
                'line' => json_encode($post->all()),
                'file' => $e->getFile(),
                'log'  => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
