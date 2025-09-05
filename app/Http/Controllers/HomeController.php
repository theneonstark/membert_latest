<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Circle;
use App\Models\User;
use App\Models\Report;
use App\Models\Aepsreport;
use App\Models\Userkyc;
use App\Models\Userbank;
use App\Models\VirtualAccount;
use App\Models\Api;
use Carbon\Carbon;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
     
    public function index(Request $post)
    {
        $user = User::find(session("loginid"));
        $profile = \App\Models\Userkyc::where("user_id", session("loginid"))->first();

        if($profile && $profile->profile){
            session(["profile"  => $profile->profile]); 
        }else{
            session(["profile"  => asset('')."public/profiles/user.png"]); 
        }
        
        try {
            session(["companyid" => $user->company_id]);
            session(["companyname" => $user->company->companyname]);
            
        } catch (\Exception $e) {
            \DB::table('log_500')->insert([
                'line' => session("loginid"),
                'file' => $e->getFile(),
                'log'  => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }

        session(["logo" => $user->company->logo]); 
        session(["kyc"  => $user->kyc]); 

        $data['slides'] = \App\Models\PortalSetting::where('code', 'dashboardslides')->where('company_id', $user->company_id)->orderBy('id', 'desc')->get();
        $data['videos'] = \App\Models\PortalSetting::where('code', 'dashboardvideos')->orderBy('id', 'desc')->get();
        $data['state']  = Circle::all();
        
        $data['mainwallet']  = $user->mainwallet;
        $data['aepswallet'] = $user->aepswallet;

        $notice = \DB::table('companydatas')->where('company_id', $user->company_id)->first(['notice', 'news', 'number', 'email']);
        if($notice){
            $data['notice'] = $notice->notice;
            $data['news'] = $notice->news;
            $data['supportnumber'] = $notice->number;
            $data['supportemail']  = $notice->email;
            session(["news" => $notice->news]);
        }else{
            $data['notice'] = "";
            $data['news']   = "";
            $data['supportnumber'] = "";
            $data['supportemail']  = "";
            session(["news" => ""]);
        }

        $pincheck = \DB::table('portal_settings')->where('code', "pincheck")->first();
        if($pincheck){
            if($pincheck->value == "yes"){
                if(!\Myhelper::can('pin_check')){
                    session(["pincheck" => $pincheck->value]);
                }else{
                    session(["pincheck" => "no"]);
                }
            }
        }else{
            session(["pincheck" => "no"]);
        }

        $data["virtual"] = \DB::table("virtual_accounts")->where("user_id", session("loginid"))->first();
        if(!$data["virtual"]){
            do {
                $account = "QUINT2119893".rand(1111111, 9999999);
            } while (VirtualAccount::where("account", "=", $account)->first() instanceof VirtualAccount);

            
            \DB::table("virtual_accounts")->insert([
                "user_id" => session("loginid"),
                "account" => $account
            ]);
            $data["virtual"] = \DB::table("virtual_accounts")->where("user_id", session("loginid"))->first();
        }

        $data['reports'] = \DB::table("reports")->where('user_id', session("loginid"))->whereDate("created_at", date("Y-m-d"))->where("status", "success")->where("rtype", "main")->orderBy("id", "desc")->take(10)->get(["product", "amount", "txnid", "trans_type", 'created_at']);
        return view('home')->with($data);
    }

    public function getbalance(Request $post)
    {
        $user = \App\Models\User::find($post->user_id);
        $data['apibalance']   = 0;
        $data['mainwallet']   = $user->mainwallet;
        $data['collectionwallet'] = $user->collectionwallet;
        $data['qrwallet'] = $user->qrwallet;
        $data['cbwallet'] = $user->cbwallet;
        $data['lockedwallet'] = $user->lockedwallet;

        $data['downlinemainwallet'] = round(User::whereIntegerInRaw('id',  \Myhelper::getParents($post->user_id))->where('id', '!=', $post->user_id)->sum('mainwallet'), 2);
        $data['downlinecollectionwallet'] = round(User::whereIntegerInRaw('id',  \Myhelper::getParents($post->user_id))->where('id', '!=', $post->user_id)->sum('collectionwallet'), 2);
        $data['downlineqrwallet'] = round(User::whereIntegerInRaw('id',  \Myhelper::getParents($post->user_id))->where('id', '!=', $post->user_id)->sum('qrwallet'), 2);
        $data['downlinecbwallet'] = round(User::whereIntegerInRaw('id',  \Myhelper::getParents($post->user_id))->where('id', '!=', $post->user_id)->sum('cbwallet'), 2);

        return response()->json($data);
    }

    public function onboarding(Request $post)
    {
        $data["user"]     =  User::find(session("loginid"));
        $data["userbank"] =  Userbank::where("user_id", session("loginid"))->where("type", "primary")->first();
        return view('onboarding')->with($data);
    }

    public function invoice($id)
    {
        $data["invoice"] =  \DB::table("invoices")->where("id", $id)->where("user_id", session("loginid"))->first();

        if(!$data["invoice"]){
            abort(404);
        }

        $data["from"] = Carbon::createFromFormat("Y-M", $data["invoice"]->year."-".$data["invoice"]->month)->startOfMonth()->format("d-M-Y");
        $data["to"]   = Carbon::createFromFormat("Y-M", $data["invoice"]->year."-".$data["invoice"]->month)->endOfMonth()->format("d-M-Y");

        //dd($data);
        return view('invoice')->with($data);
    }

    public function complete_kyc(Request $post)
    {
        $post["user_id"]    = session("loginid");
        switch ($post->step) {
            case '1':
                $rules = array(
                    'pancard'    => 'required',
                    'aadharcard' => 'required'
                );
                break;

            case '2':
                $rules = [
                    'pancards'     => 'required|mimes:png,jpg,JPG,jpeg|max:1024',
                    'aadharfronts' => 'required|mimes:png,jpg,JPG,jpeg|max:1024',
                    'aadharbacks'  => 'required|mimes:png,jpg,JPG,jpeg|max:1024'
                ];
                break;

            case '3':
                $rules = array(
                    'bank'    => 'required',
                    'account' => 'required',
                    'ifsc'    => 'required',
                );
                break;            
            default:
                return response()->json(['status' => "ERR", "message" => "Permission Not Allowed"]);
                break;
        }
        
        $validator = \Validator::make($post->all(), array_reverse($rules));
        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $key => $value) {
                $error = $value[0];
            }
            return response()->json(array(
                'status'  => 'ERR',
                'message' => $error
            ));
        }

        $post["kyc"] = "pending";
        switch ($post->step) {
            case '1':
                $update = User::updateOrCreate(['id'=> session("loginid")], $post->all());
                break;

            case '2':
                $data = file_get_contents($post->file("pancards"));
                $post['pancard'] = 'data:image/png;base64, ' . base64_encode($data);

                $data = file_get_contents($post->file("aadharfronts"));
                $post['aadharfront'] = 'data:image/png;base64, ' . base64_encode($data);

                $data = file_get_contents($post->file("aadharbacks"));
                $post['aadharback'] = 'data:image/png;base64, ' . base64_encode($data);

                $update = Userkyc::updateOrCreate(['user_id'=> $post->user_id], $post->all());
                break;

            case '3':
                $update = Userbank::updateOrCreate(['user_id'=> $post->user_id], $post->all());
                break;
        }

        if($update){
            User::where('id', \Auth::user()->id)->update([
               'step' => $post->step,
               'kyc'  => $post->kyc
            ]);
            return response()->json(['status' => "TXN", "message" => "Profile updated success, wait for approval"]);
        }else{
            return response()->json(['status' => "ERR", "message" => "Something went wrong, try again"]);
        }
    }

    public function statics(Request $post)
    {
        if(\Myhelper::hasRole("apiuser")){
            $userid = session("loginid");
        }else{
            $userid = $post->userid;
        }

        $query = \DB::table('reports')
        ->where('rtype', 'main')
        ->where('status', 'success')
        ->whereBetween('created_at', [Carbon::now()->subDays(7)->format('Y-m-d'), Carbon::now()->addDay(1)->format('Y-m-d')]);
        $query->where('user_id', session("loginid"));

        $data['main'] = $query->orderBy('created_at', "asc")              
        ->groupBy(\DB::raw('Date(created_at)'))
        ->select([
            'created_at',
            \DB::raw("sum(case when reports.product = 'payout' then reports.amount else 0 end) as payoutsales"),
            \DB::raw("sum(case when reports.product = 'recharge' then reports.amount else 0 end) as rechargesales"),
            \DB::raw("sum(case when reports.product = 'billpay' then reports.amount else 0 end) as billpaysales"),
            \DB::raw("sum(case when reports.product = 'aeps' then reports.amount else 0 end) as aepssales"),
            \DB::raw("sum(case when reports.product = 'dmt' then reports.amount else 0 end) as dmtsales"),
        ])->get();

        $query = \DB::table('collectionreports')
        ->where('rtype', 'main')
        ->where('status', 'success')
        ->whereBetween('created_at', [Carbon::now()->subDays(7)->format('Y-m-d'), Carbon::now()->addDay(1)->format('Y-m-d')]);
        $query->where('user_id', session("loginid"));

        $data['collection'] = $query->orderBy('created_at', "asc")              
        ->groupBy(\DB::raw('Date(created_at)'))
        ->select([
            'created_at',
            \DB::raw("sum(case when collectionreports.product = 'payin'  then collectionreports.amount else 0 end) as payinsales")
        ])->get();

        return response()->json($data);      
    }
}