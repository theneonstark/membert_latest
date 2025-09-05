<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Circle;
use App\Models\Role;
use App\Models\Pindata;
use App\Models\Bbpsagent;
use App\Models\Userkyc;
use Illuminate\Validation\Rule;
use Zxing\QrReader;

class UserController extends Controller
{
    public function loginpage(Request $get)
    {
        $data['company'] = \App\Models\Company::where('website', $_SERVER['HTTP_HOST'])->first();
        if ($this->env_mode() == "server") {
            if($_SERVER['HTTP_HOST'] == "e-banker.in"){
                $data['company'] = \App\Models\Company::where('website', "retail.e-banker.in")->first();
                return view('welcome')->with($data);
            }
        }
        
        $data['company'] = \App\Models\Company::where('website', $_SERVER['HTTP_HOST'])->first();

        if($data['company']){
            $data['slides'] = \App\Models\PortalSetting::where('code', 'slides')->where('company_id', $data['company']->id)->orderBy('id', 'desc')->get();
        }else{
            abort(404);
        }
        $data['state'] = Circle::all();
        $data['roles'] = Role::whereIn('slug', ['whitelable', 'md', 'distributor', 'retailer'])->get();
        
        return view('loginpage')->with($data);
    }
    
    public function signup()
    {
        $imageData = file_get_contents("https://member.pehunt.in/public/QrCode.png");
        
        if (!$imageData) {
            return 'Failed to download image.';
        }
    
        // 2. Save to temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'qr_') . '.png';
        file_put_contents($tempFile, $imageData);
    
        // 3. Decode using ZXing (Zing) wrapper
        $qrcode = new QrReader($tempFile);
        $text = $qrcode->text(); // returns decoded text, or null if failed
    
        // 4. Clean up
        unlink($tempFile);
    
        return $text ?: 'QR code could not be decoded.';
        
        $data['company'] = \App\Models\Company::where('website', $_SERVER['HTTP_HOST'])->first();
        return view('signup')->with($data);
    }
    
    public function login(Request $post)
    {
        $rules = array(
            'password' => 'required',
        );

        $validate = \Myhelper::FormValidator($rules, $post);
        if($validate != "no"){
            return $validate;
        }

        try {
            $data = \CJS::decrypt($post->password, $post["_token"]);    
        } catch (\Exception $e) {
            $data = $post;
        }

        $company = \App\Models\Company::where('website', $_SERVER['HTTP_HOST'])->first();
        $user = User::where('email', $data->mobile)->whereHas('role', function ($q){
            $q->whereIn('slug', ['apiuser', 'whitelable']);
        })->where("company_id", $company->id)->first();

        if(!$user){
            return response()->json(['status' => 'ERR', 'message' => "Your aren't registred with us." ]);
        }

        $otprequired = \App\Models\PortalSetting::where('code', 'otplogin')->first();
        if(!\Auth::validate(['email' => $data->mobile, 'password' => $data->password])){
            return response()->json(['status' => 'ERR', 'message' => 'Username or password is incorrect']);
        }

        if (!\Auth::validate(['email' => $data->mobile, 'password' => $data->password, 'status'=> "active"])) {
            return response()->json(['status' => 'ERR', 'message' => 'Your account currently de-activated, please contact administrator']);
        }

        if($user->loginotp == "yes"){
            $otpSend = \DB::table('password_resets')->where("mobile", $user->mobile)->where("activity", "login")->first();
            if(!$otpSend || ($post->has('otp') && $post->otp == "resend")){
                $send = \Myhelper::notification("login", $user->mobile, $user->name, $user->email);

                if($send == 'success'){
                    return response()->json(['status' => 'TXNOTP', 'message' => 'otpsent']);
                }else{
                    return response()->json(['status' => 'ERR', 'message' => $send]);
                }
            }elseif(!$post->has('otp')){
                return response()->json(['status' => 'TXNOTP', 'message' => 'Previous Otp']);
            }

            try {
                $otpData = \CJS::decrypt($post->otp, $post["_token"]);   
            } catch (\Exception $e) {
                return response()->json(['status' => 'ERR', 'message' => 'Please contact your service provider provider']);
            }

            $checkotp = \Myhelper::otpValidate("login", $user->mobile, $otpData->otp);
            if($checkotp == "failed"){
                return response()->json(['status' => 'ERR', 'message' => 'Please provide correct otp']);
            }
        }

        if (\Auth::attempt(['email' =>$data->mobile, 'password' => $data->password, 'status'=> "active"])) {
            \App\Models\LogSession::create([
                'user_id'    => $user->id,
                'ip_address' => $post->ip(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'gps_location' => $post->gps_location,
                'ip_location'  => $post->ip_location,
                'device_id'    => $post->device_id
            ]);

            if($user->permission_change == "yes"){
                \Storage::disk('permission')->delete("permissions/permission".$user->id);
                \DB::table("users")->where("id", $user->id)->update(['permission_change' => "no"]);
            }

            session(['loginid' => $user->id]);
            return response()->json(['status' => 'TXN', 'message' => 'Login']);
        }else{
            return response()->json(['status' => 'ERR', 'message' => 'Something went wrong, please contact administrator']);
        }
    }

    public function logout(Request $request)
    {
        \Auth::guard()->logout();
        $request->session()->invalidate();
        return redirect('/');
    }

    public function passwordReset(Request $post)
    {
        $rules = array(
            'type' => 'required',
            'mobile'  =>'required|numeric',
        );

        $validate = \Myhelper::FormValidator($rules, $post);
        if($validate != "no"){
            return $validate;
        }

        if($post->type == "request" ){
            $user = \App\Models\User::where('mobile', $post->mobile)->first();
            if($user){
                $send = \Myhelper::notification("password", $user->mobile, $user->name, $user->email);

                if($send == 'success'){
                    return response()->json(['status' => 'TXN', 'message' => "Password reset token sent successfully"]);
                }else{
                    return response()->json(['status' => 'ERR', 'message' => $send]);
                }
            }else{
                return response()->json(['status' => 'ERR', 'message' => "You aren't registered with us"]);
            }
        }else{
            $checkotp = \Myhelper::otpValidate("password", $post->mobile, $post->token);
            if($checkotp == "failed"){
                return response()->json(['status' => 'ERR', 'message' => 'Please provide correct otp']);
            }

            $update = \App\Models\User::where('mobile', $post->mobile)->update([
                'password' => bcrypt($post->password),
                'attempt'  => 0,
                'status'   => "active"
            ]);

            if($update){
                return response()->json(['status' => "TXN", 'message' => "Password reset successfully"]);
            }else{
                return response()->json(['status' => 'ERR', 'message' => "Something went wrong"]);
            }
        }  
    }

    public function getotp(Request $post)
    {
        $rules = array(
            'mobile'  =>'required|numeric',
        );

        $validate = \Myhelper::FormValidator($rules, $post);
        if($validate != "no"){
            return $validate;
        }

        $user = \App\Models\User::where('mobile', $post->mobile)->first();
        $send = \Myhelper::notification("tpin", $user->mobile, $user->name, $user->email);

        if($send == 'success'){
            return response()->json(['status' => 'TXN', 'message' => "Pin generate token sent successfully"]);
        }else{
            return response()->json(['status' => 'ERR', 'message' => $send]);
        }
    }
    
    public function setpin(Request $post)
    {
        $data = \CJS::decrypt($post->pin_confirmation, $post["_token"]);
        $rules = array(
            'id'   =>'required|numeric',
            'otp'  =>'required|numeric',
            'pin'  =>'required|numeric|digits:6|confirmed',
        );

        $validate = \Myhelper::webValidator($rules, $data);
        if($validate != "no"){
            return $validate;
        }
        try {
            $otpData = \CJS::decrypt($post->otp, $post["_token"]);   
        } catch (\Exception $e) {
            return response()->json(['status' => 'ERR', 'message' => 'Please contact your service provider provider']);
        }
        
        $checkotp = \Myhelper::otpValidate("tpin", $post->mobile, $otpData->otp);
        if($checkotp == "failed"){
            return response()->json(['status' => 'ERR', 'message' => 'Please provide correct otp']);
        }

        Pindata::where('user_id', $post->id)->delete();
        $apptoken = Pindata::create([
            'pin' => \Myhelper::encrypt($data->pin, "antliaFin@@##2025500"),
            'user_id' => $post->id,
            'attempt' => 0,
            'status'  => "active"
        ]);

        if($apptoken){
            return response()->json(['status' => 'TXN', 'message' => "success"]);
        }else{
            return response()->json(['status' => 'ERR', 'message' => "Something went wrong"]);
        }
    }
    
    public function web_onboard(Request $post)
    {
        $rules = array(
            'name'   => 'required',
            'mobile' => 'required|numeric|digits:10|unique:users,mobile',
            'email'  => 'required|email|unique:users,email'
        );

        $validate = \Myhelper::FormValidator($rules, $post);
        if($validate != "no"){
            return $validate;
        }

        // if(!$post->has("otp")){
        //     $send = \Myhelper::notification("signup", $post->mobile, $post->name, $post->email);

        //     if($send == 'success'){
        //         return response()->json(['status' => 'TXNOTP', 'message' => "Otp sent in your mobile number to validate your account"]);
        //     }else{
        //         return response()->json(['status' => 'ERR', 'message' => $send]);
        //     }
        // }else{
        //     $checkotp = \Myhelper::otpValidate("signup", $post->mobile, $post->otp);
        //     if($checkotp == "failed"){
        //         return response()->json(['status' => 'ERR', 'message' => 'Please provide correct otp']);
        //     }
        // }

        $company = \App\Models\Company::where('website', $_SERVER['HTTP_HOST'])->first();
        $admin = User::whereHas('role', function ($q){
            $q->whereIn('slug', ['admin', 'whitelable']);
        })->where("company_id", $company->id)->first(['id', 'company_id']);

        $role = Role::where('slug', 'apiuser')->first();
        
        $post['role_id'] = $role->id;
        $post['id']         = "new";
        $post['parent_id']  = $admin->id;
        $post['password']   = bcrypt($post->mobile);
        $post['company_id'] = $admin->company_id;
        $post['status']     = "active";
        $post['kyc']        = "pending";
        $post['via']        = "web";

        $scheme = \DB::table('default_permissions')->where('type', 'scheme')->where('role_id', $role->id)->first();
        if($scheme){
            $post['scheme_id'] = $scheme->permission_id;
        }

        $response = User::updateOrCreate(['id'=> $post->id], $post->all());
        if($response){
            $permissions = \DB::table('default_permissions')->where('type', 'permission')->where('role_id', $post->role_id)->get();
            if(sizeof($permissions) > 0){
                foreach ($permissions as $permission) {
                    $insert = array('user_id'=> $response->id , 'permission_id'=> $permission->permission_id);
                    $inserts[] = $insert;
                }
                \DB::table('user_permissions')->insert($inserts);
            }

            \Myhelper::notification("welcome", $response->mobile, $response->name, $response->email);
            return response()->json(['status' => "TXN", 'message' => "Success"]);
        }else{
            return response()->json(['status' => 'ERR', 'message' => "Something went wrong, please try again"]);
        }
    }
}
