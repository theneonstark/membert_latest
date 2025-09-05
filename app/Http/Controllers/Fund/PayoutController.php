<?php

namespace App\Http\Controllers\Fund;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Aepsfundrequest;
use App\Models\Report;
use App\Models\Api;
use App\Models\Provider;
use App\Models\Aepsreport;
use App\Models\PortalSetting;
use App\Models\Microatmfundrequest;
use App\Models\Microatmreport;
use App\Models\Collectionreport;
use App\Models\Userbank;
use App\Models\AepsPayoutBankChange;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class PayoutController extends Controller
{
    public $fundapi, $admin;

    public function __construct()
    {
        $this->fundapi = Api::where('code', 'fund')->first();
        $this->admin = User::whereHas('role', function ($q){
            $q->where('slug', 'admin');
        })->first();
    }

    public function index($type, $action="none")
    {
        $data = [];
        $data['action'] = $action;
        $data['type']   = $type;
        $data['user']   = \Auth::user();
        switch ($type) {
            case 'payout':
                switch ($action) {
                    case 'initiate':
                        $permission = 'my_payout';
                        $file = "mypayout";
                        break;

                    default:
                        abort(404);
                        break;
                }
                break;

            case 'collection':
                switch ($action) {
                    case 'initiate':
                        $data["collectionwallet"]          = $data['user']->collectionwallet;
                        $data["lockcollectionwallet"]      = \DB::table("collectionreports")->where("product", "payin")->where("status", "success")->where("user_id", \Auth::id())->whereDate('created_at', Carbon::now()->format('Y-m-d'))->sum("amount");
                        $data["availablecollectionwallet"] = $data['user']->collectionwallet - \DB::table("collectionreports")->where("product", "payin")->where("user_id", \Auth::id())->where("status", "success")->whereDate('created_at', Carbon::now()->format('Y-m-d'))->sum("amount");
                        $data['banks'] = Userbank::where('user_id', session("loginid"))->get();
                        $permission = 'collection_fund_request';
                        $file = "collectionmoney";
                        break;

                    default:
                        abort(404);
                        break;
                }
                break;
            default:
                abort(404);
                break;
        }

        if (isset($permission) && !\Myhelper::can($permission)) {
            abort(403);
        }

        if ($this->fundapi->status == "0") {
            abort(503);
        }

        return view('fund.'.$file)->with($data);
    }

    public function transaction(Request $post)
    {
        if ($this->fundapi->status == "0") {
            return response()->json(['status' => "ERR" , "message" => "This function is down"]);
        }

        $user = User::where('id', $post->user_id)->first();
        switch ($post->type) {
            case 'collectionwallet':
                return response()->json(['status' => "ERR" , "message" => "Settlement Down For Sometime"]);
                $rules = array(
                    'amount'    => 'required|numeric|min:10|max:500000'
                );
        
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    foreach ($validator->errors()->messages() as $key => $value) {
                        $error = $value[0];
                    }
                    return response()->json(['status'=>'ERR', 'message'=> $error]);
                }
                
                if($post->type == "wallet"){
                    $provider = Provider::where('recharge1', 'aepsfund')->first();
                }elseif($post->type == "matmwallet"){
                    $provider = Provider::where('recharge1', 'microatmfund')->first();
                }elseif($post->type == "collectionwallet"){
                    $provider = Provider::where('recharge1', 'collectionfund')->first();
                }

                if($provider->status == '0'){
                    return response()->json(['status' => "ERR" , "message" => "Settlement Down For Sometime"]);
                }

                if($provider->api->status == '0'){
                    return response()->json(['status' => "ERR" , "message" => "Settlement Down For Sometime"]);
                }

                if($post->type == "wallet"){
                    $reportTable  = Aepsreport::query();
                    $wallet = "aepswallet"; 
                }elseif($post->type == "matmwallet"){
                    $reportTable  = Microatmreport::query();
                    $wallet = "matmwallet"; 
                }elseif($post->type == "collectionwallet"){
                    $reportTable  = Collectionreport::query();
                    $wallet = "collectionwallet"; 

                    $blockAmount = \DB::table("collectionreports")->where("product", "payin")->where("user_id", $user->id)->where("status", "success")->whereDate('created_at', Carbon::now()->format('Y-m-d'))->sum("amount");

                    if($user[$wallet] - $blockAmount < ($post->amount + $post->charge)){
                        return response()->json(['status' => "ERR" , "message" => "Your limit will be open in T+1"]);
                    }
                }
                
                if($user[$wallet] - $user->lockedwallet < $post->amount){
                    return response()->json(['status' => "ERR" , "message" => "Low wallet balance to make this request"]);
                }

                $post['create_time'] = Carbon::now()->toDateTimeString();
                $post['txnid']       = "WTR".date('Ymdhis');

                $debit = [
                    'number'  => "Wallet",
                    'mobile'  => $user->mobile,
                    'provider_id' => $provider->id,
                    'api_id'  => $provider->api_id,
                    'amount'  => $post->amount,
                    'txnid'   => $post->txnid,
                    'payid'   => $post->txnid,
                    'refno'   => ucfirst($post->type)." Fund Transfer",
                    'description' =>  ucfirst($post->type)." Fund Transfer",
                    'remark'  => $post->remark,
                    'option1' => "wallet",
                    'option4' => $post->option4,
                    'option5' => "fund",
                    'status'  => "success",
                    'user_id' => $user->id,
                    'credit_by' => $user->id,
                    'rtype'   => 'main',
                    'via'     => $post->via,
                    'balance' => $user[$wallet],
                    'trans_type' => 'debit',
                    'product'    => "payout",
                    'create_time'=> $post->create_time,
                    "option7" => $post->ip()."/".$_SERVER['HTTP_USER_AGENT'],
                    "option8" => $post->gps_location
                ];

                $credit = [
                    'number'  => "Wallet",
                    'mobile'  => $user->mobile,
                    'provider_id' => $provider->id,
                    'api_id'  => $provider->api_id,
                    'amount'  => $post->amount,
                    'txnid'   => $post->txnid,
                    'payid'   => $post->txnid,
                    'refno'   => ucfirst($post->type)." Fund Recieved",
                    'description' =>  ucfirst($post->type)." Fund Recieved",
                    'remark'  => $post->remark,
                    'option1' => $post->type,
                    'option5' => "fund",
                    'status'  => 'success',
                    'user_id' => $user->id,
                    'credit_by' => $user->id,
                    'rtype'   => 'main',
                    'via'     => $post->via,
                    'balance' => $user->mainwallet,
                    'trans_type' => 'credit',
                    "option7" => $post->ip()."/".$_SERVER['HTTP_USER_AGENT'],
                    "option8" => $post->gps_location,
                    'product'    => "fund transfer",
                    'create_time'=> $post->create_time,
                    "option7" => $post->ip()."/".$_SERVER['HTTP_USER_AGENT'],
                    "option8" => $post->gps_location
                ];

                try {
                    $load = \DB::transaction(function () use($debit, $credit, $post, $user, $reportTable, $wallet) {
                        $debit["balance"] = $this->getAccBalance($debit["user_id"], $wallet);
                        User::where('id', $user->id)->decrement($wallet, $post->amount);
                        $debit["closing"] = $this->getAccBalance($debit["user_id"], $wallet);
                        $debitReport = $reportTable->create($debit);

                        $credit["balance"] = $this->getAccBalance($credit["user_id"], "mainwallet");
                        User::where('id', $user->id)->increment("mainwallet", $post->amount);
                        $credit["closing"] = $this->getAccBalance($credit["user_id"], "mainwallet");
                        $creditReport = Report::create($credit);
                        return true;
                    });
                } catch (\Exception $e) {
                    $load = false;
                }

                if($load){
                    return response()->json(['status' => "TXN" , "message" => "Transaction Successfull", 'txnid' => $post->txnid]);
                }else{
                    return response()->json(['status' => "ERR" , "message" => "Transaction Failed"]);
                }
                break;
                
            case 'mypayout':
                $rules = array(
                    'amount'  => 'required|numeric|min:10|max:500000',
                    'account' => 'required',
                    'bank'    => 'required',
                    'ifsc'    => 'required',
                    'name'    => 'required',
                );
                    
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    foreach ($validator->errors()->messages() as $key => $value) {
                        $error = $value[0];
                    }
                    return response()->json(['status'=>'ERR', 'message'=> $error]);
                }

                if($user->account == "" || $user->account == null){
                    User::where('id', $user->id)->update(['account' => $post->account, 'bank' => $post->bank, 'ifsc' => $post->ifsc]);
                }else{
                    $post['name']    = $user->name;
                    $post['account'] = $user->account;
                    $post['bank']    = $user->bank;
                    $post['ifsc']    = $user->ifsc;
                }

                $userkey = \DB::table("api_credentials")->where("user_id", \Auth::id())->first();
                $url     = "https://member.pehunt.in/production/api/payment/order/create";
                $header  = [
                    'accept: application/json',  
                    'api-key: '.$userkey->api_key,  
                    'content-type: application/json',  
                ];

                if($user->id == "4"){
                    $webhook = "test";
                }else{
                    $webhook = "test";
                }
                $parameter = [
                    'partner_id' => $userkey->user_id,
                    'mode'     => 'IMPS',
                    'name'     => $post->name,
                    'account'  => $post->account,
                    'bank'     => $post->bank,
                    'ifsc'     => $post->ifsc,
                    'mobile'   => $user->mobile,
                    'amount'   => $post->amount,
                    'webhook'  => $webhook,
                    'latitude' => '28.5355',
                    'longitude'=> '77.3910',
                    'apitxnid' => $user->id."testpayout".date("ymdhis")
                ];

                $query    = json_encode($parameter);
                $result   = \Myhelper::curl($url, "POST", $query, $header, "yes", 'AddMoney', $parameter["apitxnid"]);
                $response = json_decode($result['response']);

                if(isset($response->statuscode) && in_array($response->statuscode, ["TXF", 'ERR'])){
                    $message = $response->message;
                    if($response->message == "Insufficient Wallet Balance"){
                        $message = "Service Provider Downtime";
                    }

                    return response()->json([
                        'statuscode'=> 'TXF', 
                        'message'   => $message,
                        "bankutr"   => $message, 
                    ]);
                }elseif(isset($response->statuscode) && in_array($response->statuscode, ["TXN"])){
                    return response()->json([
                        'statuscode'=> 'TXN', 
                        'message'   => "Transaction Successfull",
                        "bankutr"   => $response->bankutr
                    ]);
                }else{
                    return response()->json([
                        'statuscode'=> 'TUP', 
                        'message'   => 'Transaction Under Process',
                        "bankutr"   => isset($response->txnid) ? $response->txnid : "pending", 
                    ]);
                }
                return response()->json($return);
                break;
                
            case 'bulkpayout':
                $rules = array(
                    'amount'  => 'required|numeric|min:10|max:500000',
                    'account' => 'required',
                    'bank'    => 'required',
                    'ifsc'    => 'required',
                    'name'    => 'required',
                );
                    
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    foreach ($validator->errors()->messages() as $key => $value) {
                        $error = $value[0];
                    }
                    return response()->json(['status'=>'ERR', 'message'=> $error]);
                }

                if($user->account == "" || $user->account == null){
                    User::where('id', $user->id)->update(['account' => $post->account, 'bank' => $post->bank, 'ifsc' => $post->ifsc]);
                }else{
                    $post['name']    = $user->name;
                    $post['account'] = $user->account;
                    $post['bank']    = $user->bank;
                    $post['ifsc']    = $user->ifsc;
                }

                $userkey = \DB::table("api_credentials")->where("user_id", \Auth::id())->first();
                $url     = "https://login.pgpe.in/production/api/payment/order/bulkpay";
                $header  = [
                    'accept: application/json',  
                    'api-key: '.$userkey->api_key,  
                    'content-type: application/json',  
                ];

                if($user->id == "4"){
                    $webhook = "test";
                }else{
                    $webhook = "SELF";
                }
                $parameter = [
                    'partner_id' => $userkey->user_id,
                    'mode'     => 'IMPS',
                    'name'     => $post->name,
                    'account'  => $post->account,
                    'bank'     => $post->bank,
                    'ifsc'     => $post->ifsc,
                    'mobile'   => $user->mobile,
                    'amount'   => $post->amount,
                    'webhook'  => $webhook,
                    'latitude' => '28.5355',
                    'longitude'=> '77.3910',
                    'apitxnid' => $user->id."testpayout".date("ymdhis")
                ];

                $query    = json_encode($parameter);
                $result   = \Myhelper::curl($url, "POST", $query, $header, "no", 'AddMoney', $parameter["apitxnid"]);
                $response = json_decode($result['response']);

                if(isset($response->statuscode) && in_array($response->statuscode, ["TXF", 'ERR'])){
                    $message = $response->message;
                    if($response->message == "Insufficient Wallet Balance"){
                        $message = "Service Provider Downtime";
                    }

                    return response()->json([
                        'statuscode'=> 'TXF', 
                        'message'   => $message,
                        "bankutr"   => $message, 
                    ]);
                }elseif(isset($response->statuscode) && in_array($response->statuscode, ["TXN"])){
                    return response()->json([
                        'statuscode'=> 'TXN', 
                        'message'   => "Transaction Successfull",
                        "bankutr"   => $response->bankutr
                    ]);
                }else{
                    return response()->json([
                        'statuscode'=> 'TUP', 
                        'message'   => 'Transaction Under Process',
                        "bankutr"   => isset($response->txnid) ? $response->txnid : "pending", 
                    ]);
                }
                return response()->json($return);
                break;
                
            case 'upipayout':
                $rules = array(
                    'amount'  => 'required|numeric|min:10|max:500000',
                    'account' => 'required',
                    'name'    => 'required',
                );
                    
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    foreach ($validator->errors()->messages() as $key => $value) {
                        $error = $value[0];
                    }
                    return response()->json(['status'=>'ERR', 'message'=> $error]);
                }

                $userkey = \DB::table("api_credentials")->where("user_id", \Auth::id())->first();
                $url     = "https://login.pgpe.in/production/api/payment/order/upi";
                $header  = [
                    'accept: application/json',  
                    'api-key: '.$userkey->api_key,  
                    'content-type: application/json',  
                ];

                if($user->id == "4"){
                    $webhook = "test";
                }else{
                    $webhook = "SELF";
                }
                $parameter = [
                    'partner_id' => $userkey->user_id,
                    'mode'     => 'UPI',
                    'name'     => $post->name,
                    'vpa'  => $post->account,
                    'mobile'   => $user->mobile,
                    'amount'   => $post->amount,
                    'webhook'  => $webhook,
                    'latitude' => '28.5355',
                    'longitude'=> '77.3910',
                    'apitxnid' => $user->id."testpayout".date("ymdhis")
                ];

                $query    = json_encode($parameter);
                $result   = \Myhelper::curl($url, "POST", $query, $header, "no", 'AddMoney', $parameter["apitxnid"]);
                $response = json_decode($result['response']);

                if(isset($response->statuscode) && in_array($response->statuscode, ["TXF", 'ERR'])){
                    $message = $response->message;
                    if($response->message == "Insufficient Wallet Balance"){
                        $message = "Service Provider Downtime";
                    }

                    return response()->json([
                        'statuscode'=> 'TXF', 
                        'message'   => $message,
                        "bankutr"   => $message, 
                    ]);
                }elseif(isset($response->statuscode) && in_array($response->statuscode, ["TXN"])){
                    return response()->json([
                        'statuscode'=> 'TXN', 
                        'message'   => "Transaction Successfull",
                        "bankutr"   => $response->bankutr
                    ]);
                }else{
                    return response()->json([
                        'statuscode'=> 'TUP', 
                        'message'   => 'Transaction Under Process',
                        "bankutr"   => isset($response->txnid) ? $response->txnid : "pending", 
                    ]);
                }
                return response()->json($return);
                break;
        }
    }

    public function fetchData(Request $request, $type)
    {
        if($request->has('user_id')){
            $userid = $request->user_id;
        }else{
            $userid = session("loginid");
        }

        $data = [];
        $parentData = \Myhelper::getParents($userid);
        $exportData = "";

        switch ($type) {
            case 'collectionreport':
            case 'collectioninitiate':
                $table = "collectionreports";
                $query = \DB::table($table)->leftJoin('users as user', 'user.id', '=', $table.'.user_id')
                        ->leftJoin('apis as api', 'api.id', '=', $table.'.api_id')
                        ->orderBy($table.'.id', 'desc')
                        ->whereIn($table.'.product', ['payout', 'fund return'])
                        ->whereIn($table.'.option1', ["bank", "wallet"]);

                if($type == "collectionreport"){
                    if(!empty($request->agent) && in_array($request->agent, $parentData)){
                        $query->where($table.'.user_id', $request->agent);
                    }else{
                        if(\Myhelper::hasRole(['whitelable', 'md', 'distributor','retaillite', 'retailer', 'apiuser'])){
                            $query->whereIn($table.'.user_id', $parentData);
                        }
                    }
                }else{
                    $query->where($table.'.user_id', $userid);
                }
                
                $dateFilter = 1;

                if(!empty($request->searchtext)){
                    $serachDatas = ['number', 'txnid', 'refno', 'amount', 'id'];
                    $query->where( function($q) use($request, $serachDatas, $table){
                        foreach ($serachDatas as $value) {
                            $q->orWhere($table.".".$value , $request->searchtext);
                        }
                    });
                    $dateFilter = 0;
                }

                if(isset($request->product) && !empty($request->product) && $request->product != '' && $request->product != null){
                    $query->where($table.'.option1', $request->product);
                    $dateFilter = 0;
                }

                if(isset($request->status) && $request->status != '' && $request->status != null){
                    $query->where($table.'.status', $request->status);
                    $dateFilter = 0;
                }

                if((isset($request->fromdate) && !empty($request->fromdate)) && (isset($request->todate) && !empty($request->todate))){
                    if($request->fromdate == $request->todate){
                        $query->whereDate($table.'.created_at','=', Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'));
                    }else{
                        $query->whereBetween($table.'.created_at', [Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')]);
                    }
                }elseif($dateFilter && isset($request->fromdate) && !empty($request->fromdate)){
                    $query->whereDate($table.'.created_at','=', Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'));
                }

                $selects = ['id','mobile' ,'number', 'apitxnid', 'txnid', 'api_id', 'amount', 'profit', 'charge','tds', 'gst', 'payid', 'refno', 'balance', 'status', 'rtype', 'trans_type', 'user_id', 'credit_by', 'created_at', 'product', 'remark','option1', 'option3', 'option2', 'option5','closing'];

                foreach ($selects as $select) {
                    $selectData[] = $table.".".$select;
                }

                $selectData[] = 'user.name as username';
                $selectData[] = 'user.mobile as usermobile';
                $selectData[] = 'api.name as apiname';

                $exportData = $query->select($selectData);
                $count = intval($exportData->count());

                if(isset($request['length'])){
                    $exportData->skip($request['start'])->take($request['length']);
                }

                $data = array(
                    "draw"            => intval($request['draw']),
                    "recordsTotal"    => $count,
                    "recordsFiltered" => $count,
                    "data"            => $exportData->get()
                );
                break;
            
            default:
                $table = "reports";
                $query = \DB::table($table);
                $query->where('user_id', $userid)->where('option7', $type);
                
                $dateFilter = 1;
                $selects = ['option7', "option3", "option2", "option4", "number", "amount", "status", "option6"];

                foreach ($selects as $select) {
                    $selectData[] = $table.".".$select;
                }

                $exportData = $query->select($selectData);
                $count      = intval($exportData->count());

                if(isset($request['length'])){
                    $exportData->skip($request['start'])->take($request['length']);
                }

                $data = array(
                    "draw"            => intval($request['draw']),
                    "recordsTotal"    => $count,
                    "recordsFiltered" => $count,
                    "data"            => $exportData->get()
                );
                break;
        }
        echo json_encode($data);
    }
}
