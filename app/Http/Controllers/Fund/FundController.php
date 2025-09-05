<?php

namespace App\Http\Controllers\Fund;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Settlementreport;
use App\Models\Fundreport;
use App\Models\Report;
use App\Models\Fundbank;
use App\Models\Collectionreport;
use App\Models\Paymode;
use App\Models\Api;
use App\Models\Provider;
use App\Models\PortalSetting;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Firebase\JWT\JWT;

class FundController extends Controller
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
        $data['type'] = $type;
        $file = $type;
        switch ($type) {
            case 'addmoney':
                $permission = 'fund_request';
                $file = "addmoney";
                break;

            case 'qrcode':
                $permission = 'fund_request';
                $file = "upi";
                break;

            case 'upi':
                $permission = 'fund_report';
                $file = "upireport";
                break;

            case 'upirequest':
                $permission = 'fund_request';
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

        switch ($type) {
            case 'addmoney':
                $data['banks'] = Fundbank::where('status', '1')->get();
                $data['paymodes'] = Paymode::where('status', '1')->get();
                break;
        }

        return view('fund.'.$file)->with($data);
    }

    public function transaction(Request $post)
    {
        if ($this->fundapi->status == "0") {
            return response()->json(["status" => "ERR" , "message" => "Service Down For Sometime"]);
        }

        $provider = Provider::where('recharge1', 'fund')->first();
        $post['provider_id'] = $provider->id;
        $user = User::find($post->user_id);

        switch ($post->transactionType) {
            case 'movetowallet':
                $rules = array(
                    'amount' => 'required|numeric|min:1000',
                );
        
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    foreach ($validator->errors()->messages() as $key => $value) {
                        $error = $value[0];
                    }
                    return response()->json(['status' => "ERR", "message" => $error]);
                }

                $payee  = \Auth::user();
                $user   = \Auth::user();

                $debitbalance = "collectionwallet";
                $product = "settlement";
                $debittable   = Collectionreport::query();

                $creditbalance = "mainwallet";
                $credittable   = Report::query();

                $settlecount = \DB::table("collectionreports")->whereDate("created_at", date("Y-m-d"))->where("user_id", $user->id)->where("product", $product)->where("status", "success")->count();

                if($settlecount >= $payee->settlecount){
                    return response()->json(['status'=>"ERR", 'message' => "Settlement Limit Exceeded"]);
                }

                if($payee[$debitbalance] - $user->wallet_hold_amount < $post->amount){
                    return response()->json(['status'=>"ERR", 'message' => "Insufficient wallet balance"]);
                }

                $debit = [
                    'number'  => $payee->mobile,
                    'mobile'  => $payee->mobile,
                    'provider_id' => $post->provider_id,
                    'api_id'  => $provider->api_id,
                    'amount'  => $post->amount,
                    'txnid'   => "WTR".date('Ymdhis'),
                    'remark'  => $post->remark,
                    'refno'   => $post->refno,
                    'status'  => 'success',
                    'user_id' => $payee->id,
                    'credit_by' => $user->id,
                    'rtype'   => 'main',
                    'via'     => 'portal',
                    'balance' => $payee[$debitbalance],
                    'trans_type' => 'debit',
                    'product' => $product
                ];

                $debit['option5'] = "fund";
                $debit['option1'] = "wallet";

                $credit = [
                    'number' => $user->mobile,
                    'mobile' => $user->mobile,
                    'provider_id' => $post->provider_id,
                    'api_id' => $provider->api_id,
                    'amount' => $post->amount,
                    'txnid'  => "WTR".date('Ymdhis'),
                    'remark' => $post->remark,
                    'refno'  => $post->refno,
                    'status' => 'success',
                    'user_id'   => $user->id,
                    'credit_by' => $payee->id,
                    'rtype' => 'main',
                    'via'   => $post->via,
                    'balance'    => $user[$creditbalance],
                    'trans_type' => 'credit',
                    'product'    => $product
                ];

                $request = \DB::transaction(function () use($debit, $credit, $creditbalance, $credittable, $debitbalance, $debittable) {
                    $debit["balance"] = $this->getAccBalance($debit["user_id"], $debitbalance);
                    User::where('id', $debit['user_id'])->decrement($debitbalance, $debit['amount']);
                    $debit["closing"] = $this->getAccBalance($debit["user_id"], $debitbalance);
                    $debitReport = $debittable->create($debit);

                    $credit["balance"] = $this->getAccBalance($credit["user_id"], $creditbalance);
                    User::where('id', $credit['user_id'])->increment($creditbalance, $credit['amount']);
                    $credit["closing"] = $this->getAccBalance($credit["user_id"], $creditbalance);
                    $creditReport = $credittable->create($credit);
                    return true;
                });

                if($request){
                    return response()->json(['status'=>"TXN", 'message' => "Fund Transfer successfully"]);
                }else{
                    return response()->json(['status'=>"ERR", 'message' => "Something went wrong."]);
                }
                break;
                
            case 'settlementRequest':
                $user   = \Auth::user();
                $rules = array(
                    'amount' => 'required|numeric|min:'.$user->minimum_settlement,
                );
        
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    foreach ($validator->errors()->messages() as $key => $value) {
                        $error = $value[0];
                    }
                    return response()->json(['status' => "ERR", "message" => $error]);
                }
                
                $pendingAmt = \DB::table("settlementreports")->where("user_id", $user->id)->where("status", "pending")->sum("amount");
                if($user->collectionwallet - $user->wallet_hold_amount - $pendingAmt < $post->amount){
                    return response()->json(['status'=>"ERR", 'message' => "Insufficient wallet balance"]);
                }

                $post["user_id"] = \Auth::id();
                $request = Settlementreport::create($post->all());

                if($request){
                    return response()->json(['status'=>"TXN", 'message' => "Settlement Request Initiated successfully"]);
                }else{
                    return response()->json(['status'=>"ERR", 'message' => "Something went wrong."]);
                }
                break;

            case 'request':
                if(!\Myhelper::can('fund_request', $post->user_id)){
                    return response()->json(['status'=>"ERR", 'message' => "Permission not allowed"]);
                }

                $post['ref_no'] = preg_replace('/[^A-Za-z0-9]/', '', $post->ref_no);
                $rules = array(
                    'fundbank_id' => 'required|numeric',
                    'paymode'     => 'required',
                    'amount'      => 'required|numeric|min:100',
                    'ref_no'      => 'required|unique:fundreports,ref_no',
                    'paydate'     => 'required'
                );
        
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    foreach ($validator->errors()->messages() as $key => $value) {
                        $error = $value[0];
                    }
                    return response()->json(['status' => "ERR", "message" => $error]);
                }

                $post['credited_by'] = $this->admin->id;
                $post['status'] = "pending";
                $post['create_time'] = $user->id."-".date('ymdhis');
                if($post->hasFile('payslips')){
                    $filename ='payslip'.$post->user_id.date('ymdhis').".".$post->file('payslips')->guessExtension();
                    $post->file('payslips')->move(public_path('deposit_slip/'), $filename);
                    $post['payslip'] = $filename;
                }
                $action = Fundreport::create($post->all());

                if($action){
                    return response()->json(['status'=>"TXN", 'message' => "Transaction Successfull"]);
                }else{
                    return response()->json(['status'=>"ERR", 'message' => "Something went wrong, please try again."]);
                }
                break;
            
            case 'addmoney':
                $userkey = \DB::table("api_credentials")->where("user_id", \Auth::id())->first();
                $url     = "https://member.pehunt.in/production/api/collection/order/create";
                $header = [  
                    'accept: application/json',  
                    'api-key: '.$userkey->api_key,  
                    'content-type: application/json',  
                ];

                $parameter = [
                    'partner_id' => $userkey->user_id,
                    'apitxnid'   => $user->id."testupi".date("ymdhis"),
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'mobile' => $user->mobile,
                    'amount'     => $post->amount,
                    'callback'   => 'SELF'
                ];

                $query    = json_encode($parameter);
                $result   = \Myhelper::curl($url, "POST", $query, $header, "yes", 'AddMoney', $parameter["apitxnid"]);
                $response = json_decode($result['response']);

                if(isset($response->statuscode) && $response->statuscode == "TXN"){
                    $return['status']  = "TXN";
                    $return['qr_link'] = $response->upi_string;
                    $return['payment_link'] = isset($response->payment_link) ? $response->payment_link : '';
                    $return['txnid']   = $parameter["apitxnid"];
                }else{
                    $return['status']  = "error";
                    $return['message'] = $response->message;
                }
                return response()->json($return);
                break;
            
            case 'geturl':
                $userkey = \DB::table("api_credentials")->where("user_id", \Auth::id())->first();
                $url     = "https://member.pehunt.in/production/api/collection/order/paymenturl";
                $header = [  
                    'accept: application/json',  
                    'api-key: '.$userkey->api_key,  
                    'content-type: application/json',  
                ];

                $parameter = [
                    'partner_id' => $userkey->user_id,
                    'apitxnid'   => $user->id."testupi".date("ymdhis"),
                    'name'   => $user->name,
                    'email'  => $user->email,
                    'mobile' => $user->mobile,
                    'amount'     => $post->amount,
                    'callback'   => 'SELF'
                ];

                $query    = json_encode($parameter);
                $result   = \Myhelper::curl($url, "POST", $query, $header, "yes", 'AddMoney', $parameter["apitxnid"]);
                $response = json_decode($result['response']);

                if(isset($response->statuscode) && $response->statuscode == "TXN"){
                    $return['status']  = "TXN";
                    $return['payment_link'] = isset($response->payment_link) ? $response->payment_link : '';
                    $return['txnid']   = $parameter["apitxnid"];
                }else{
                    $return['status']  = "error";
                    $return['message'] = $response->message;
                }
                return response()->json($return);
                break;
            
            case 'upistatus':
                $report = \DB::table("qrreports")->where("apitxnid", $post->txnid)->where("product", "payin")->where("status", "success")->first();
                if($report){
                    $return['status'] = "TXN";
                    $return['refno']  = $report->refno;
                }else{
                    $return['status'] = "TUP";
                }
                return response()->json($return);
                break;

            case 'banklist':
                $qrapi = Api::where('code', 'paysprintpayin')->first();
                $payload =  [
                    "timestamp" => time(),
                    "client_secret" => $qrapi->password,
                    "requestid"     => $post->user_id.Carbon::now()->timestamp
                ];

                $AES_ENCRYPTION_IV  = $qrapi->optional2;
                $AES_ENCRYPTION_KEY = $qrapi->optional1;
                $cipher = openssl_encrypt(json_encode($payload,true), 'AES-256-CBC', $AES_ENCRYPTION_KEY, $options=OPENSSL_RAW_DATA, $AES_ENCRYPTION_IV);
                $token  = base64_encode($cipher);

                $header = array(
                    "Token: ".$token,
                    "client-id: ".base64_encode($qrapi->username)
                );
                
                $parameter = [
                    "bankId" => 3,
                    "apiId" => "20242",
                    "type" => "collection"
                ];

                $result = \Myhelper::curl("https://api.sprintnxt.in/api/v2/UPIService/UPI", "POST", $parameter, $header, "no", 'Vpa', $payload["requestid"]);
                
                \DB::table('rp_log')->insert([
                    'ServiceName' => "Bank-List",
                    'header'      => json_encode($header),
                    'body'        => json_encode($parameter),
                    'response'    => $result['response'],
                    'url'         => "https://uatnxtgen.sprintnxt.in/api/v2/UPIService/UPI",
                    'created_at'  => date('Y-m-d H:i:s')
                ]);
                break;

            case 'checkvpa':
                $qrapi = Api::where('code', 'paysprintpayin')->first();
                $payload =  [
                    "timestamp" => time(),
                    "client_secret" => $qrapi->password,
                    "requestid"     => $post->user_id.Carbon::now()->timestamp
                ];

                $AES_ENCRYPTION_IV  = $qrapi->optional2;
                $AES_ENCRYPTION_KEY = $qrapi->optional1;
                $cipher = openssl_encrypt(json_encode($payload,true), 'AES-256-CBC', $AES_ENCRYPTION_KEY, $options=OPENSSL_RAW_DATA, $AES_ENCRYPTION_IV);
                $token  = base64_encode($cipher);

                $header = array(
                    "Token: ".$token,
                    "client-id: ".base64_encode($qrapi->username)
                );
                
                $parameter = [
                    "bankId" => 3,
                    "apiId"  => "20243",
                    "vpa"    => "pgpe"
                ];

                $result =\Myhelper::curl("https://uatnxtgen.sprintnxt.in/api/v2/UPIService/UPI", "POST", $parameter, $header, "no", 'Vpa', $payload["requestid"]);
                
                \DB::table('rp_log')->insert([
                    'ServiceName' => "Bank-List",
                    'header'      => json_encode($header),
                    'body'        => json_encode($parameter),
                    'response'    => $result['response'],
                    'url'         => "https://uatnxtgen.sprintnxt.in/api/v2/UPIService/UPI",
                    'created_at'  => date('Y-m-d H:i:s')
                ]);
                break;

            case 'getvpa':
                $qrapi = Api::where('code', 'paysprintpayin')->first();
                $payload =  [
                    "timestamp" => time(),
                    "client_secret" => $qrapi->password,
                    "requestid"     => $post->user_id.Carbon::now()->timestamp
                ];

                $AES_ENCRYPTION_IV  = $qrapi->optional2;
                $AES_ENCRYPTION_KEY = $qrapi->optional1;
                $cipher = openssl_encrypt(json_encode($payload,true), 'AES-256-CBC', $AES_ENCRYPTION_KEY, $options=OPENSSL_RAW_DATA, $AES_ENCRYPTION_IV);
                $token  = base64_encode($cipher);

                $header = array(
                    "Token: ".$token,
                    "client-id: ".base64_encode($qrapi->username)
                );
                
                $parameter = [
                    "bankId" => 3,
                    "apiId"  => "20246",
                    "mobileNumber" => ""
                ];

                $result = \Myhelper::curl("https://uatnxtgen.sprintnxt.in/api/v2/UPIService/UPI", "POST", $parameter, $header, "no", 'Vpa', $payload["requestid"]);
                
                \DB::table('rp_log')->insert([
                    'ServiceName' => "Get Vpa",
                    'header'      => json_encode($header),
                    'body'        => json_encode($parameter),
                    'response'    => $result['response'],
                    'url'         => "https://uatnxtgen.sprintnxt.in/api/v2/UPIService/UPI",
                    'created_at'  => date('Y-m-d H:i:s')
                ]);

                if($result['response'] == ""){
                    return response()->json(['statuscode'=> 'ERR', 'message'=> "Something went wrong"]);
                }
                break;

            case 'qrcode':
                $user   = User::where('id', \Auth::id())->first();
                $rules = array(
                    'mobile'   => 'required',
                    'address'  => 'required',
                    'city'     => 'required',
                    'state'    => 'required',
                    'pincode'  => 'required',
                    'merchant_name' => 'required',
                    'pan'      => 'required'
                );
        
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    return response()->json(['errors'=>$validator->errors()], 422);
                }

                do {
                    $post['refid'] = $this->transcode().'UPI'.rand(111111111111, 999999999999);
                } while (Qrcode::where("refid", "=", $post->refid)->first() instanceof Qrcode);

                $post['user_id'] = \Auth::id();
                $qrapi = Api::where('code', 'paysprintpayin')->first();
                $payload =  [
                    "timestamp" => time(),
                    "client_secret" => $qrapi->password,
                    "requestid"     => $post->user_id.Carbon::now()->timestamp
                ];

                $AES_ENCRYPTION_IV  = $qrapi->optional2;
                $AES_ENCRYPTION_KEY = $qrapi->optional1;
                $cipher = openssl_encrypt(json_encode($payload,true), 'AES-256-CBC', $AES_ENCRYPTION_KEY, $options=OPENSSL_RAW_DATA, $AES_ENCRYPTION_IV);
                $token  = base64_encode($cipher);

                $header = array(
                    "Token: ".$token,
                    "client-id: ".base64_encode($qrapi->username)
                );
                
                $parameter = array(
                    'txnReferance' => $post->refid,
                    'apiId' => 20249,
                    'account_id' => "989823121122",
                    'payeeVPA'   => "ps1.sdpay@fin",
                    'txnNote'   => "test",
                    'bankId' => "3",
                );

                $result = \Myhelper::curl("https://uatnxtgen.sprintnxt.in/api/v2/UPIService/UPI", "POST", $parameter, $header, "no", 'Vpa', $payload["requestid"]);
                \DB::table('rp_log')->insert([
                    'ServiceName' => "Create-VPA",
                    'header'      => json_encode($header),
                    'body'        => json_encode($parameter),
                    'response'    => $result['response'],
                    'url'         => "https://uatnxtgen.sprintnxt.in/api/v2/UPIService/UPI",
                    'created_at'  => date('Y-m-d H:i:s')
                ]);
                break;
            
            case 'qrcode_dynamic':
                $user   = User::where('id', \Auth::id())->first();
                do {
                    $post['refid'] = $this->transcode().'UPI'.rand(111111111111, 999999999999);
                } while (Qrcode::where("refid", "=", $post->refid)->first() instanceof Qrcode);

                $post['user_id'] = \Auth::id();
                $qrcode = Qrcode::where("user_id", $post->user_id)->first();

                $qrapi = Api::where('code', 'paysprintpayin')->first();
                $payload =  [
                    "timestamp" => time(),
                    "client_secret" => $qrapi->password,
                    "requestid"     => $post->user_id.Carbon::now()->timestamp
                ];

                $AES_ENCRYPTION_IV  = $qrapi->optional2;
                $AES_ENCRYPTION_KEY = $qrapi->optional1;
                $cipher = openssl_encrypt(json_encode($payload,true), 'AES-256-CBC', $AES_ENCRYPTION_KEY, $options=OPENSSL_RAW_DATA, $AES_ENCRYPTION_IV);
                $token  = base64_encode($cipher);

                $header = array(
                    "Token: ".$token,
                    "client-id: ".base64_encode($qrapi->username)
                );

                $parameter = array(
                    'txnReferance' => $post->refid,
                    'apiId' => 20248,
                    'ExpiryTime' => "1440",
                    'payeeVPA'   => "ps1.sdpay@fin",
                    'mobile'     => $user->mobile,
                    'amount' => $post->amount,
                    'bankId' => "3",
                    'txnNote' => "Pay For 1 Rupees"
                );

                $result = \Myhelper::curl("https://uatnxtgen.sprintnxt.in/api/v2/UPIService/UPI", "POST", $parameter, $header, "no", 'Vpa', $payload["requestid"]);
                \DB::table('rp_log')->insert([
                    'ServiceName' => "Create-VPA",
                    'header'      => json_encode($header),
                    'body'        => json_encode($parameter),
                    'response'    => $result['response'],
                    'url'         => "https://uatnxtgen.sprintnxt.in/api/v2/UPIService/UPI",
                    'created_at'  => date('Y-m-d H:i:s')
                ]);

                if($result['response'] == ""){
                    return response()->json(['statuscode'=> 'ERR', 'message'=> "Something went wrong"]);
                }

                $response = json_decode($result['response']);
                
                if(isset($response->details->UPIRefID)){
                    $post['txnReferance'] = $response->details->merchantId;
                    $post['UPIRefID'] = $response->details->UPIRefID;
                    $post['status']   = "success";
                    $post['qr_lLink'] = $response->details->intent_url;
                    $post['vpa'] = $response->details->payeeVPA;
                    return response()->json(['statuscode'=> 'TXN', 'message'=> $response->message, "qr_lLink" => $response->details->intent_url]);
                    
                }else{
                    return response()->json(['statuscode'=> 'TXF', 'message'=> $response->message]);
                }
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

        switch ($type) {
            case 'fundrequest':
            case 'fundreport':
            case 'fundaddmoney':
                $table = "fundreports";
                $query = \DB::table($table)
                        ->leftJoin('users as user', 'user.id', '=', $table.'.user_id')
                        ->leftJoin('users as sender', 'sender.id', '=', $table.'.credited_by')
                        ->leftJoin('fundbanks as fundbank', 'fundbank.id', '=', $table.'.fundbank_id')
                        ->orderBy($table.'.id', 'desc');

                if($type == "fundreport"){
                    if(!empty($request->agent) && in_array($request->agent, $parentData)){
                        $query->where($table.'.user_id', $request->agent);
                    }else{
                        if(\Myhelper::hasRole(['whitelable', 'md', 'distributor','retaillite', 'retailer', 'apiuser'])){
                            $query->whereIn($table.'.user_id', $parentData);
                        }
                    }
                }elseif($type == "fundaddmoney"){
                    $query->where($table.'.user_id', $userid);
                }else{
                    $query->where($table.'.credited_by', $userid)->where($table.'.status', "pending");
                }

                $dateFilter = 1;

                if($type == "fundrequest"){
                    $dateFilter = 0;
                }

                if(!empty($request->searchtext)){
                    $serachDatas = ['ref_no', 'amount', 'id'];
                    $query->where( function($q) use($request, $serachDatas, $table){
                        foreach ($serachDatas as $value) {
                            $q->orWhere($table.".".$value , $request->searchtext);
                        }
                    });
                    $dateFilter = 0;
                }

                if(isset($request->product) && !empty($request->product) && $request->product != '' && $request->product != null){
                    $query->where($table.'.type', $request->product);
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

                $selects = ['type', 'fundbank_id', 'ref_no', 'paydate', 'remark', 'status', 'user_id', 'credited_by', 'paymode', 'amount', 'id', 'created_at', 'updated_at'];

                foreach ($selects as $select) {
                    $selectData[] = $table.".".$select;
                }

                $selectData[] = 'user.name as username';
                $selectData[] = 'user.mobile as usermobile';
                $selectData[] = 'sender.name as sendername';
                $selectData[] = 'sender.mobile as sendermobile';
                $selectData[] = 'fundbank.name as bankname';
                $selectData[] = 'fundbank.branch as bankbranch';
                $selectData[] = 'fundbank.account as bankaccount';

                $exportData = $query->select($selectData);

                if($request->has("length")){
                    $exportData->skip($request['start'])->take($request['length']);
                }

                $data = array(
                    "draw"            => intval($request['draw']),
                    "recordsTotal"    => intval($exportData->count()),
                    "recordsFiltered" => intval($exportData->count()),
                    "data"            => $exportData->get()
                );
                break;

            case 'fundall':
            case 'fundvirtual':
            case 'fundvirtualall':
                $table = "reports";
                $query = \DB::table($table)->leftJoin('users as user', 'user.id', '=', $table.'.user_id')
                        ->leftJoin('users as sender', 'sender.id', '=', $table.'.credit_by')
                        ->leftJoin('apis as api', 'api.id', '=', $table.'.api_id')
                        ->where('api.code', 'fund')
                        ->orderBy($table.'.id', 'desc');

                if($type == "fundvirtual"){
                    $query->where($table.'.option4', "virtualload")->where($table.'.user_id', session("loginid"));
                }elseif($type == "fundvirtualall"){
                    $query->where($table.'.option4', "virtualload");
                    if(\Myhelper::hasRole(['whitelable', 'md', 'distributor','retaillite', 'retailer', 'apiuser'])){
                        $query->where($table.'.user_id', session("loginid"));
                    }
                }

                if(!empty($request->agent) && in_array($request->agent, $parentData)){
                    $query->where($table.'.user_id', $request->agent);
                }else{
                    if($type != "fundvirtualall"){
                        $query->where($table.'.user_id', session("loginid"));
                    }
                }

                $dateFilter = 1;

                if(!empty($request->searchtext)){
                    $serachDatas = ['amount', 'number', 'mobile','credit_by'];
                    $query->where( function($q) use($request, $serachDatas, $table){
                        foreach ($serachDatas as $value) {
                            $q->orWhere($table.".".$value , $request->searchtext);
                        }
                    });
                    $dateFilter = 0;
                }

                if(isset($request->product) && !empty($request->product) && $request->product != '' && $request->product != null){
                    $query->where($table.'.product', "fund ". $request->product);
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

                $selects = ['number','api_id','amount','refno','status','user_id','credit_by','product', 'created_at', 'updated_at', 'id', 'remark'];

                foreach ($selects as $select) {
                    $selectData[] = $table.".".$select;
                }

                $selectData[] = 'user.name as username';
                $selectData[] = 'user.mobile as usermobile';
                $selectData[] = 'sender.name as sendername';
                $selectData[] = 'sender.mobile as sendermobile';
                $selectData[] = 'api.name as apiname';

                $exportData = $query->select($selectData);
                if($request->has("length")){
                    $exportData->skip($request['start'])->take($request['length']);
                }

                $data = array(
                    "draw"            => intval($request['draw']),
                    "recordsTotal"    => intval($exportData->count()),
                    "recordsFiltered" => intval($exportData->count()),
                    "data"            => $exportData->get()
                );
                break;
        }
        echo json_encode($data);
    }
}
