<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ApiCredential;
use App\Models\Permission;
use Illuminate\Support\Str;
use App\Models\User;
use App\Exports\ReportExport;

class ApiController extends Controller
{
    public function index($type)
    {
        $data['type']     = $type;
        $data['services'] = Permission::where('type', "service")->orderBy('id', 'ASC')->get();
        $data['user_permissions'] = \DB::table("user_permissions")->where("user_id", \Auth::id())->get();
        return view("apitools.".$type)->with($data);
    }

    public function update(Request $post)
    {
        do {
            $post['api_key'] = Str::random(40);
        } while (ApiCredential::where("api_key", "=", $post->api_key)->first() instanceof ApiCredential);

        do {
            $post['aes_key'] = Str::random(32);
        } while (ApiCredential::where("aes_key", "=", $post->aes_key)->first() instanceof ApiCredential);

        do {
            $post['aes_iv'] = Str::random(16);
        } while (ApiCredential::where("aes_iv", "=", $post->aes_iv)->first() instanceof ApiCredential);

        $post['user_id'] = \Auth::id();
        $action = ApiCredential::updateOrCreate(['id'=> $post->id], $post->all());
        if ($action) {
            return response()->json(['status' => "success", "data" => \DB::table("api_credentials")->where("id", $action->id)->first()], 200);
        }else{
            return response()->json(['status' => "Task Failed, please try again"], 200);
        }
    }

    public function ip(Request $post)
    {
        $rules = array(
            'ip' => 'required'
        );
            
        $validator = \Validator::make($post->all(), $rules);
        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $key => $value) {
                $error = $value[0];
            }
            return response()->json(['statuscode'=>'ERR', 'message'=> $error]);
        }
        //$user = \Auth::user();
        // if(!$post->has("otp")){
        //     $otp = \Myhelper::notification("ip", $user->mobile, $user->name, $user->email);

        //     if($otp == "success"){
        //         return response()->json(['statuscode' => "TXNOTP", "message" => "success"]);
        //     }else{
        //         return response()->json(['statuscode' => "ERR", "message" => $otp]);
        //     }
        // }else{
        //     $checkotp = \Myhelper::otpValidate("ip", $user->mobile, $post->otp);
        //     if($checkotp == "failed"){
        //         return response()->json(['status' => 'ERR', 'message' => 'Please provide correct otp']);
        //     }
        // }

        $post["user_id"] = session("loginid");
        $action = \DB::table("api_whitelisted_ips")->insert([
            "ip"      => $post->ip,
            "user_id" => $post->user_id
        ]);

        if ($action) {
            return response()->json(['statuscode' => "TXN", "message" => "success"]);
        }else{
            return response()->json(['statuscode' => "ERR", "message" => "Task Failed, please try again"]);
        } 
    }

    public function tokenDelete(Request $post)
    {
        $delete = ApiCredential::where('id', $post->id)->where('user_id', \Auth::id())->delete();
        return response()->json(['status'=>$delete], 200);
    }

    public function ipDelete(Request $post)
    {
        $delete = \DB::table("api_whitelisted_ips")->where('id', $post->id)->where('user_id', \Auth::id())->delete();
        return response()->json(['status'=>$delete], 200);
    }

    public function download(Request $post)
    {
        $api = \DB::table("api_credentials")->where('id', $post->id)->where('user_id', \Auth::id())->first();

        $excelData[] = [
            "Api Key",
            "Partner Id"
        ];

        $excelData[] = [
            $api->api_key,
            $api->user_id
        ];
        
        $export = new ReportExport($excelData);
        return \Excel::download($export, 'nifipay-apicredentials.csv');
    }
}
