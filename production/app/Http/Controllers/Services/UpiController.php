<?php

namespace App\Http\Controllers\Services;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Collectionreport;
use App\Models\Qrreport;
use App\Models\User;
use App\Models\Report;
use App\Models\Api;
use Carbon\Carbon;
use Firebase\JWT\JWT;

class UpiController extends Controller
{
    protected $admin, $api;
    public function __construct()
    {
        $this->admin  = User::whereHas('role', function ($q){
            $q->where("slug", "admin");
        })->first();
        $this->api = Api::where('code', 'payin_upi')->first();
    }

    public function encrypt($text, $key, $type)
    {
        $iv = "0123456789abcdef";
        $size =16;
        $pad = $size - (strlen($text) % $size);
        $padtext = $text . str_repeat(chr($pad) , $pad);
        $crypt = openssl_encrypt($padtext,"AES-256-CBC", base64_decode($key),
        OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING,$iv);
        return base64_encode($crypt);
    }

    function decrypt($crypt, $key)
    {
        $iv = "0123456789abcdef";
        $crypt = base64_decode($crypt);
        $padtext = openssl_decrypt($crypt,"AES-256-CBC", base64_decode($key), OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv); 
        $pad = ord($padtext
                   [
                       strlen($padtext) - 1]);
        if ($pad > strlen($padtext)) return false;
        
        if (strspn($padtext, $padtext
                   [
                       strlen($padtext) - 1], strlen($padtext) - $pad) != $pad)
        {
            $text = "Error";
        }
    
        $text = substr($padtext, 0, -1 * $pad);
        return $text;
    }

    public function create(Request $post)
    {
        $rules = array(
            'apitxnid' => "required|unique:collectionreports,apitxnid",
            'amount'   => 'required|numeric|min:100|max:5000',
            'callback' => 'required',
            'name'     => 'required',
            'mobile'   => 'required',
            'email'    => 'required',
        );

        if ($this->payinstatus() == "off" && $post->user_id != 2) {
            return response()->json([
                'statuscode' => "ERR",
                "message"    => "Service Under Maintenance"
            ]);
        }

        if (!\App\Helpers\Permission::can('upi_service', $post->user_id)) {
            return response()->json([
                'statuscode' => "ERR",
                "message"    => "Service Not Allowed"
            ]);
        }
        
        $validate = \App\Helpers\Permission::FormValidator($rules, $post->all());
        if($validate != "no"){
            return $validate;
        }

        do {
            $post['txnid'] = "PGPETXN".rand(1111111, 9999999);
        } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);

        $user = User::where('id', $post->user_id)->first();
        if($post->amount > 1 && $post->amount <= 250){
            $provider = Provider::where('recharge1', 'qrcharge')->first();
        }else{
            $provider = Provider::where('recharge1', 'qrcharge2')->first();
        }

        if(!$provider){
            return response()->json(['statuscode' => "ERR", "message" => "Operator Not Found"]);
        }

        if($provider->status == 0){
            return response()->json(['statuscode' => "ERR", "message" => "Operator Down For Sometime"]);
        }

        if(!$provider->api || $provider->api->status == 0){
            return response()->json(['statuscode' => "ERR", "message" => "Service Down For Sometime"]);
        }
        
        if($user->payin_daily_limit && ($user->payin_daily_used > $user->payin_daily_limit)){
            return response()->json(['statuscode' => "ERR", "message" => "Daily Limit Consumed"]);
        }

        $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $user->id)->first();
        if($serviceApi){
            $api = Api::find($serviceApi->api_id);
        }else{
            $api = Api::find($provider->api_id);
        }

        if($post->amount > 0 && $post->amount <= 250){
            $upiprovider = Provider::where('recharge1', 'qrcollection1')->first();
        }else{
            $upiprovider = Provider::where('recharge1', 'qrcollection2')->first();
        }

        $post['upicharge'] = \App\Helpers\Permission::getCommission($post->amount, $user->scheme_id, $upiprovider->id, "apiuser");
        if($post->upicharge == 0){
            return response()->json(['statuscode' => "ERR", "message" => "Contact Administrator, Charges Not Set"]);
        }

        $post['charge'] = \App\Helpers\Permission::getCommission(0, $user->scheme_id, $provider->id, "apiuser");
        $post['gst']    = ($post->charge * 18)/100;  

        if(\App\Helpers\Permission::getAccBalance($user->id, "collectionwallet") < ($post->charge + $post->gst)){
            return response()->json(['statuscode' => "TXF", "message" => "Insufficient Wallet balance"]);
        }

        switch ($api->code) {
            case 'jiffywalletpayin':
                do {
                    $post['txnid'] = "PPE_ORDER_".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url = "https://jiffywallet.in/Api/merchant/transaction/initiate";
                $parameter = [
                    "merchant_reference_id" => $post->txnid,
                    "currency" => "INR",
                    "service"  => "upi",
                    "MerchantId"=> "MT12213830",
                    "Password"  => "12345678",
                    "amount"         => $post->amount,
                    "service_details" => [
                        "upi" => [
                            "channel" => "UPI_INTENT"
                        ]
                    ],
                    "customer_details" => [
                        "customer_mobile" => $post->mobile,
                        "customer_name"   => $post->name,
                        "customer_email"  => $post->email
                    ],
                    "device_details" => [
                        "device_name" => "Desktop",
                        "device_id" => "123",
                        "device_ip" => $post->ip()
                    ],
                    "geo_location" => [
                        "latitude" => "17.3840",
                        "longitude" => "78.4564"
                    ],
                    "webhook_url" => "https://member.pehunt.in/production/api/webhook/jiffywallet/payin"
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    "AuthToken: MjDVtbLWPNLeWsWSPPe3j7wvSLCRdc2o",
                    "IpAddress: 91.203.133.62"
                );
                
                $body = json_encode($parameter);
                $result   = \App\Helpers\Permission::curl($url, 'POST', $body, $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
    
                if(isset($response->response->data->data->payload->url)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->response->data->data->apx_payment_id;
                    $post['txnid'] = $post->txnid;
                    $post['upi_string'] = $response->response->data->data->payload->url;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->response->data->data->payload->url);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'phonepepayin':
                do {
                    $post['txnid'] = "PPE_ORDER_".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url = "https://api.phonepe.com/apis/hermes/pg/v1/pay";
                $parameter = [
                    "merchantId" => "M22HW7W8TUDDE", 
                    "merchantTransactionId" => $post->txnid,
                    "merchantUserId" => "MUID123",
                    "amount"         => $post->amount*100,
                    "redirectMode"   => "REDIRECT", 
                    "mobileNumber"   => "9310842731",
                    "redirectUrl"    => url("api/webhook/phonepe/payin"),
                    "callbackUrl"    => url("api/webhook/phonepe/payin"),
                    "paymentInstrument" => [
                        "type" => "PAY_PAGE"
                    ]
                ];

                $encode  = base64_encode(json_encode($parameter));
                $saltKey = "0c8fa2c0-7b69-4cdc-9d7c-7c49fb736f38";
                $saltIndex = 1;
        
                $string = $encode.'/pg/v1/pay'.$saltKey;
                $sha256 = hash('sha256',$string);
        
                $finalXHeader = $sha256.'###'.$saltIndex;
                $header = array(
                    'Content-Type: application/json',
                    "X-VERIFY:".$finalXHeader
                );
                $body = json_encode(['request' => $encode]);
                $result   = \App\Helpers\Permission::curl($url, 'POST', $body, $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
    
                if(isset($response->success) && $response->success == 'true'&& $response->code=="PAYMENT_INITIATED"){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->txnId;
                    $post['txnid'] = $response->txnId;
                    $post['upi_string'] = $response->intentData;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->intentData);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'mondomoneypayin':
                do {
                    $post['txnid'] = "MM_ORDER_".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url = "https://tech.mondomoney.in/api/generate-token";
                
                $parameter = [
                    "email" => $api->username,
                    "password" => $api->password
                ];
                
                $result   = \App\Helpers\Permission::curl($url, 'POST', $parameter, [], "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->token)){
                    $url  = "https://tech.mondomoney.in/api/initiate-dynamic-qrcode";
                    $parameter = [
                        "amount" => $post->amount,
                        "refid"  => $post->txnid
                    ];
                    
                    $header = array(
                        'Accept: application/json',
                        'Authorization: Bearer '.$response->token
                    );
                
                    $result   = \App\Helpers\Permission::curl($url, 'POST', $parameter, $header, "yes", "Qr", $post->txnid); 
                    $response = json_decode($result['response']);

                    if(isset($response->status) && $response->status == "INITIATED"){
                        $post['type']  = "upigateway";
                        $post['refId'] = $response->txnId;
                        $post['txnid'] = $response->txnId;
                        $post['upi_string'] = $response->intentData;
                        $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->intentData);  
                        
                        $insert = [
                            'number'  => $user->mobile,
                            'mobile'  => $user->mobile,
                            'provider_id' => $provider->id,
                            'api_id'  => $api->id,
                            'amount'  => $post->charge,
                            'gst'     => $post->gst,
                            'txnid'   => $post->txnid,
                            'apitxnid'=> $post->apitxnid,
                            'option1' => $post->amount,
                            'payid'   => $post->refId,
                            "refno"   => "UPI INTENT FEE",
                            'status'  => 'pending',
                            'user_id' => $user->id,
                            'credit_by'   => $user->id,
                            'rtype'       => 'main',
                            'trans_type'  => "debit",
                            'product'     => "qrcode",
                            'remark'      => $post->callback,
                            "description" => $post->upi_string
                        ];
                        
                        $report = \DB::transaction(function () use($insert){
                            $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                            User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                            $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                            return Collectionreport::create($insert);
                        });
                        
                        if($report){
                            try {
                                \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                            } catch (\Exception $e) {
                                \DB::table('log_500')->insert([
                                    'line' => $e->getLine(),
                                    'file' => $e->getFile(),
                                    'log'  => $e->getMessage(),
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }
                        
                            return response()->json([
                                'statuscode' => "TXN",
                                "message"    => "Success",
                                "txnid"      => $post->txnid,
                                "upi_tr"     => $post->upi_tr,
                                "upi_string" => $post->upi_string,
                                "upi_string_image" => $post->upi_string_image
                            ]);
                        }else{
                            return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                        }
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message : "Something went wrong"]);
                }
                break;
                
            case 'payindiapayin':
                do {
                    $post['txnid'] = "MM_ORDER_".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://payindia.live/api/v1/payin/order/create";
                
                $parameter = array(
                    'amount'   => $post->amount,
                    'transaction_id' => $post->txnid,
                    'token'    => 'L4MSPBCKXBN2U73OUETXVAXQ1NOW3LU6AL3XTB6QN699C',
                    'callback' => 'https://member.pehunt.in/production/api/webhook/payindia/payin'
                );
                
                $result   = \App\Helpers\Permission::curl($url, 'POST', $parameter, [], "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->upi_string)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->transaction_id;
                    $post['txnid'] = $post->txnid;
                    $post['upi_string'] = $response->upi_string;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->upi_string);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refid,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'payonedigitalpayin':
                do {
                    $post['txnid'] = "MM_ORDER_".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://tech.payonedigital.in/api/v1/payin/initiate";
                
                $parameter = [
                    "amount"   => $post->amount,
                    "refid"    => $post->txnid,
                    "fname"    => $post->name,
                    "lname"    => $post->name,
                    "mobile"   => $post->mobile,
                    "email"    => $post->email
                ];
                
                $body = [
                    "username" => "weblead2011@gmail.com",
                    "password" => "7042342976"
                ];
                
                $result   = \App\Helpers\Permission::curl($url."?".http_build_query($parameter), 'POST', $body, [], "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->upi_link)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->refid;
                    $post['txnid'] = $post->txnid;
                    $post['upi_string'] = $response->upi_link;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->upi_link);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refid,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'indiplexpayin':
                do {
                    $post['txnid'] = "INPAYIN".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://demo.nikatby.in/pass.php";
                
                $parameter = [
                    "amount"   => (string)$post->amount,
                    "orderId"  => $post->txnid,
                    "sellerIdentifier" => "SINDIF9CA712E7A",
                    "expiryInMinutes"  => "10"
                ];
                
                $result   = \App\Helpers\Permission::curl($url, 'POST', $parameter, [], "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->data->sellerInfo->vpa)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->data->orderId;
                    $post['txnid'] = $post->txnid;
                    $post['upi_string'] = "upi://pay?mc=".$response->data->sellerInfo->mcc."&pa=".$response->data->sellerInfo->vpa."&pn=".$response->data->sellerInfo->payeeName."&tr=".$post->txnid."&am=".$post->amount;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($post->upi_string);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refid,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'techmondomoneypayin':
                do {
                    $post['txnid'] = "TM".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://tech.mondomoney.co.in/api/v1/payin/initiate";
                
                $parameter = [
                    "amount"   => $post->amount,
                    "refid"    => $post->txnid,
                    "fname"    => $post->name,
                    "lname"    => $post->name,
                    "mobile"   => $post->mobile,
                    "email"    => $post->email
                ];
                
                $body = [
                    "username" => "pgpeservices@gmail.com",
                    "password" => "9234875743"
                ];
                
                $header = array(
                    'Content-Type: application/json'
                );
                
                $result   = \App\Helpers\Permission::curl($url."?".http_build_query($parameter), 'POST', json_encode($body), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->upi_link)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->refid;
                    $post['txnid'] = $post->txnid;
                    $post['upi_string'] = $response->upi_link;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->upi_link);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refid,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'camleniopayin':
                do {
                    $post['txnid'] = "CM_ORDER_".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://partner.camlenio.com/api/initiate-payment";
                    
                $header = [
                    "Content-Type: application/json",
                    "User-Agent: team testing",
                    "ApiKey: 9516f27d93c4eb89167012e29fa46d0afa90fb705cec23019bb060ec0101ad21",
                    "SecretKey: 53dbd3b24df8bbca1a5b6250a18fa23685da2c2a42411a7ca7cf2bee892c0b8a361127cbd6db1bc6f1ef0f97ce87866e",
                    "UserId: 2703468451",
                ];
            
                $parameter = [
                    "amount" => (int)$post->amount,
                    "txnid"  => $post->txnid,
                    "payerName"    => $post->name,
                    "payerAddress" => $user->address,
                    "payerMobile"  => $post->mobile,
                    "payerEmail"   => "ps.".$post->email
                ];
                $body = json_encode($parameter);
                
                $result   = \App\Helpers\Permission::curl($url, 'POST', $body, $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->data->payload->default)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->cf_payment_id;
                    $post['txnid'] = $post->txnid;
                    $post['upi_string'] = str_replace(" ","", $response->data->payload->default);
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->data->payload->default);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refid,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'zentexpay_payin':
                do {
                    $post['txnid'] = "ZTX".rand(111111111, 999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://api.zentexpay.in/api/payments/payin";
                    
                $header = [
                    "Content-Type: application/json",
                    "Authorization: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6MjAsInVzZXJfdHlwZSI6InBheWluX3BheW91dCIsImlhdCI6MTc0ODMzMjk4MSwiZXhwIjoxNzQ4NDE5MzgxfQ.n7z8ZzOfcAIcKW6OZ9dEJbXxhb3rO40LreGpZsaxlAg"
                ];
                
                $parameter = [
                    "order_amount" => (int)$post->amount,
                    "reference_id"  => $post->txnid,
                    "name"    => $post->name,
                    "payerAddress" => $user->address,
                    "phone"  => $post->mobile,
                    "email"   => "ps.".$post->email
                ];
                $body = json_encode($parameter);
                
                $result   = \App\Helpers\Permission::curl($url, 'POST', $body, $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->result->payment_url)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->transaction_id;
                    $post['txnid'] = $post->txnid;
                    $post['upi_string'] = str_replace(" ","", $response->result->payment_url);
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->result->payment_url);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refid,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'easyfundpropayin':
                do {
                    $post['txnid'] = "EFP_".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://easyfundpro.com/api/payment/create";
                    
                $header = array(
                    'client: 608835170ec586a3a74dd7fd1abf0ce2',
                    'secret: 6508638acf923ffd42e1e2e6feb3abdba7ab5bd68cca8d9a4e676bbabccc803e',
                    'Content-Type: application/json'
                );
                
                $parameter = [
                    "amount" => (int)$post->amount,
                    "note"   => "Pay to wallet",
                    "return_url"     => "https://member.pehunt.in/production/api/webhook/easyfundpro/payin",
                    "customer_name"  => $post->name,
                    "customer_phone" => $post->mobile,
                    "customer_email" => $post->email
                ];
                $body = json_encode($parameter);
                
                $result   = \App\Helpers\Permission::curl($url, 'POST', $body, $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->data->upi_link)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->data->transaction_id;
                    $post['txnid'] = $response->data->order_id;
                    $post['upi_string'] = str_replace(" ","", $response->data->upi_link);
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->data->upi_link);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refid,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'openmartpayin':
                do {
                    $post['txnid'] = "OM_ORDER_".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://merchant.rudraxpay.com/api/pg/phonepe/initiate";
                    
                $header = [
                    "Content-Type: application/json"
                ];
                
                $parameter = [
                    "token"  => '$2y$10$7EJIgyavaD2TUOOUUn63t.a01ur6ay3hv.B0VabyZx.v4HFrNKgRm',
                    "userid" => "RXP10324",
                    "amount" => $post->amount,
                    "orderid"=> $post->txnid,
                    "callback_url" => "https://member.pehunt.in/production/api/webhook/openmart/payin",
                    "mobile" => $post->mobile
                ];
                
                $body     = json_encode($parameter);
                $result   = \App\Helpers\Permission::curl($url, 'POST', $body, $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->url)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $post->txnid;
                    $post['txnid'] = $post->txnid;
                    $post['upi_string'] = str_replace(" ","", $response->url);
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->url);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refid,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case "kwikpaisa":
                do {
                    $post['txnid'] = "KWK_ORDER_".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                    
                $app_id     = 'mid_aaf206d6bb825eb';   //// Update with your API Key
                $secret_key = 'key_bf1b726fc30ab324837af2f833dd4078';   //// Update with Your API secret key
                $url   = 'https://uat.api.kwikpaisa.com/order'; 
                
                /////  Order Data
                $order_id = $post->txnid;
                $order_amount = $post->amount;
                $order_currency = 'INR';
                $order_note = 'Payment for Product';
                $service_type = 'DIGITAL';
                $customer_name = $post->name;
                $customer_email = $post->email;
                $customer_phone = $post->mobile;
                $customer_address_line1 = $user->address;
                $customer_address_line2 = $user->address;
                $customer_address_city = $user->city;
                $customer_address_state = $user->state;
                $customer_address_country = 'IN';
                $customer_address_postal_code = $user->pincode;
                $return_url = "http://login.pgpe.in/production/api/webhook/kwipay/payin?order_id=$order_id";
                
                //// Checksum Login with Posted Data 
                $checkSumData = [
                  "app_id" => $app_id,
                  "order_id" => $order_id,
                  "order_amount" => $order_amount,
                  "order_currency" => $order_currency,
                  "order_note" => $order_note,
                  "service_type" => $service_type,
                  "customer_name" => $customer_name,
                  "customer_email" => $customer_email,
                  "customer_phone" => $customer_phone,
                  "customer_address_line1" => $customer_address_line1,
                  "customer_address_line2" => $customer_address_line2,
                  "customer_address_city" => $customer_address_city,
                  "customer_address_state" => $customer_address_state,
                  "customer_address_country" => $customer_address_country,
                  "customer_address_postal_code" => $customer_address_postal_code,
                  "return_url" => $return_url,
                ];
                
                ksort($checkSumData);
                $signatureData = "";
                foreach ($checkSumData as $key => $value){
                    $signatureData .= $key . $value;
                }
                
                $signature = hash_hmac('sha256', $signatureData, $secret_key, true);
                $order_checksum = base64_encode($signature);
                
                $order_data = [
                    "order_id"       => $order_id,
                    "order_amount"   => $order_amount,
                    "order_currency" => $order_currency,
                    "order_note"     => $order_note,
                    "service_type"   => $service_type,
                    "customer_name"  => $customer_name,
                    "customer_email" => $customer_email,
                    "customer_phone" => $customer_phone,
                    "customer_address_line1" => $customer_address_line1,
                    "customer_address_line2" => $customer_address_line2,
                    "customer_address_city"  => $customer_address_city,
                    "customer_address_state" => $customer_address_state,
                    "customer_address_country"     => $customer_address_country,
                    "customer_address_postal_code" => $customer_address_postal_code,
                    "return_url"     => $return_url,
                    "order_checksum" => $order_checksum
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'x-client-id: ' . $app_id,
                    'x-client-secret: ' . $secret_key,
                    'order-source: reset-api'
                );
                
                $method = "POST";
                $query  = json_encode($order_data);

                $result   = \App\Helpers\Permission::curl($url, 'POST', $query, $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->return_data->kwikX_order_id)){
                    $url = "https://uat.api.kwikpaisa.com/payorder";
                    $parameter = [
                        "kwikX_order_id" => $response->return_data->kwikX_order_id,
                        "payment_method" => [
                            "upi" => [
                                "channel" => "app"
                            ]
                        ]
                    ];
    
                    $header = array(
                        "x-client-id: ".$api->username,
                        "x-client-secret: ".$api->password,
                        "order-source: rest-api"
                    );
    
                    $method = "POST";
                    $query  = json_encode($parameter);
    
                    $result   = \App\Helpers\Permission::curl($url, 'POST', $query, $header, "yes", "Qr", $post->txnid); 
                    $response = json_decode($result['response']);
    
                    if(isset($response->return_data->payment_method->upi->deepLink)){
                        $post['type']  = "upigateway";
                        $post['refId'] = $response->return_data->kwikX_payment_id;
                        $post['upi_string'] = $response->return_data->payment_method->upi->deepLink;
                        $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->return_data->payment_method->upi->deepLink);  
                        
                        $insert = [
                            'number'  => $user->mobile,
                            'mobile'  => $user->mobile,
                            'provider_id' => $provider->id,
                            'api_id'  => $api->id,
                            'amount'  => $post->charge,
                            'gst'     => $post->gst,
                            'txnid'   => $post->txnid,
                            'apitxnid'=> $post->apitxnid,
                            'option1' => $post->amount,
                            'payid'   => $post->refId,
                            "refno"   => "UPI INTENT FEE",
                            'status'  => 'pending',
                            'user_id' => $user->id,
                            'credit_by'   => $user->id,
                            'rtype'       => 'main',
                            'trans_type'  => "debit",
                            'product'     => "qrcode",
                            'remark'      => $post->callback,
                            "description" => $post->upi_string
                        ];
                        
                        $report = \DB::transaction(function () use($insert){
                            $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                            User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                            $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                            return Collectionreport::create($insert);
                        });
                        
                        if($report){
                            try {
                                \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                            } catch (\Exception $e) {
                                \DB::table('log_500')->insert([
                                    'line' => $e->getLine(),
                                    'file' => $e->getFile(),
                                    'log'  => $e->getMessage(),
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }
                            
                            return response()->json([
                                'statuscode' => "TXN",
                                "message"    => "Success",
                                "txnid"      => $post->txnid,
                                "upi_tr"     => $post->refId,
                                "upi_string" => $post->upi_string,
                                "upi_string_image" => $post->upi_string_image
                            ]);
                        }else{
                            return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                        }
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'jeetoabhipayin':
                do {
                    $post['txnid'] = "JEETOPAYIN".rand(111111111111, 999999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url = "https://upiapi.jeetoabhi.in/api/BMAGPartner/upiintentaddmoney";
                
                $parameter = [
                  'user_id' => "8135",
                  'api_key' => "jd2as+TVI3Y57fcVlvBVUnI3sh1R9FwUTSH+dau7sCM=",
                  'clientRefId' => $post->txnid,
                  'amount'      => $post->amount,
                  'user_email'  => $post->email,
                  'user_mobile_number'   => $post->mobile,
                  'return_url' => 'https://login.pgpe.in/production/api/webhook/jeetoabhi/payin'
                ];
          
                $header = array(
                    'Content-Type: application/json',
                    'Authorization: Basic ODEzNTpqZDJhcytUVkkzWTU3ZmNWbHZCVlVuSTNzaDFSOUZ3VVRTSCtkYXU3c0NNPQ=='
                );

                $method = "POST";
                $query  = json_encode($parameter);

                $result   = \App\Helpers\Permission::curl($url, 'POST', $query, $header, "yes", "Qr", $post->txnid);
                $response = json_decode($result['response']);

                if(isset($response->result->intentURIData)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->result->paymentId;
                    $post['upi_string'] = $response->result->intentURIData;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->result->intentURIData);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'payupayin':
                do {
                    $post['txnid'] = "PYU".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url = "https://secure.payu.in/_payment";

          
                $parameter = [
                  'key'    => $api->username,
                  'txnid'  => $post->txnid,
                  'amount' => $post->amount,
                  'productinfo' => 'ecommerceproduct',
                  'firstname'   => $post->name,
                  'email' => $post->email,
                  'phone' => $post->mobile,
                  'surl'  => 'https://login.pgpe.in/production/api/webhook/payu/payin',
                  'furl'  => 'https://login.pgpe.in/production/api/webhook/payu/payin',
                  'hash'  => 'string',
                  'notifyurl' => 'https://login.pgpe.in/production/api/webhook/payu/payin',
                  'lastname'  => '',
                  'pg' => 'UPI',
                  'bankcode' => 'INTENT',
                  'udf1' => '',
                  'udf2' => '',
                  'udf3' => '',
                  'udf4' => '',
                  'udf5' => '',
                  'txn_s2s_flow'    => 4,
                  's2s_client_ip'   => $post->ip(),
                  's2s_device_info' => $post->ip()
                ];
          
                $parameter['hash'] = hash('sha512', $api->username.'|'.$parameter['txnid'].'|'.$parameter['amount'].'|'.$parameter['productinfo'].'|'.$parameter['firstname'].'|'.$parameter['email'].'|||||||||||'.$api->password);
                $header = array(
                    'Content-Type' => 'application/x-www-form-urlencoded'
                );

                $method = "POST";
                $query  = $parameter;

                $result   = \App\Helpers\Permission::curl($url, 'POST', $query, $header, "yes", "Qr", $post->txnid);
                $response = json_decode($result['response']);

                if(isset($response->result->intentURIData)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->result->paymentId;
                    $post['upi_string'] = $response->result->intentURIData;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->result->intentURIData);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'razorpayin':
                do {
                    $post['txnid'] = "RZU".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url = "https://api.razorpay.com/v1/payments/create/upi";
                
                $parameter = [
                   'currency'  => "INR",
                   'customer_id'  => "CUST_".$post->user_id,
                   'order_id'  => $post->txnid,
                   'amount' => $post->amount*100,
                   'description' => 'Gorocery And Ecommerce Payment',
                   'email'   => $post->email,
                   'contact' => $post->mobile,
                   'method'  => 'upi',
                   'ip' => $post->ip(),
                   'referer' => 'http',
                   'user_agent' => 'Mozilla/5.0',
                   'upi' => [
                        "flow" => "intent" 
                    ]
                ];
          
                $header = array(
                    "Content-Type: application/json", 
                    "Authorization: Basic ".base64_encode($api->username.":".$api->password)
                );

                $method = "POST";
                $query  = json_encode($parameter);

                $result   = \App\Helpers\Permission::curl($url, 'POST', $query, $header, "yes", "Qr", $post->txnid);
                $response = json_decode($result['response']);

                if(isset($response->result->intentURIData)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->result->paymentId;
                    $post['upi_string'] = $response->result->intentURIData;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->result->intentURIData);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'zanithpaypayin':
                do {
                    $post['txnid'] = "ZPG".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://api.rupaymaster.com/apiAdmin/v1/payin/generatePayment";
                $data = [
                    "memberId"    => "C1748587014163",
                    "trxPassword" => "CO1748587014163"
                ];
                
                $jsonToString   = json_encode($data);
                $sha256Generate = hash("sha256", $jsonToString);
        
                $parameter = [
                    "authToken" => "a510664427080a1fec1adf314fd9bd4b433898865d91c08c2b14297c8ad62d03",
                    "userName"  => "C1748587014163",
                    "amount"    => $post->amount,
                    "trxId"     => $post->txnid,
                    "name"      => $post->name,
                    "mobileNumber" => $user->mobile,
                    "email" => $user->email
                ];
                
                $header = array(
                    'Content-Type: application/json'
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->data->qr)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->data->trxID;
                    $post['upi_string'] = $response->data->qr;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->data->qr);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'collectshiftpayin':
                do {
                    $post['txnid'] = "ZPG".date("ymdhis").rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://api.collectswift.com/apiAdmin/v1/payin/generatePayment";
                $data = [
                    "memberId"    => "C1745662385424",
                    "trxPassword" => "CTX1745662385424"
                ];
        
                $parameter = [
                    "authToken"    => "b540cc99f3c595741a0e746e298391beae80390b0156e05ddc1f7dbbe4a1d1bb",
                    "userName"     => "C1745662385424",
                    "amount"       => (int)$post->amount,
                    "trxId"        => $post->txnid,
                    "name"         => $user->name,
                    "email"        => $user->email,
                    "mobileNumber" => $user->mobile
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->data->qr)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->data->trxID;
                    $post['upi_string'] = $response->data->qr;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->data->qr);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'ezywalletpayin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://dashboard.ezywallet.in/api/v1/upi/upiQrGenerateAuth";
                $parameter = [
                    "amount" => $post->amount,
                    "transaction_id"   => $post->txnid,
                    "name"   => $user->name,
                    "email"  => $user->email,
                    "mobile" => $user->mobile
                ];
                
                $header = array(
                    'Token: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhZXBzX2tleSI6IjliMmZhYjllMGZiNzI1NWUxMmY4NjAwNSIsImFlcHNfaXYiOiJjNWEwYjhhZjY0NTBiYTUwODdjZDgyMTc4OGFmIn0.uM_r_q7-_wuMK1kdO5V3v4qNWfBUCI7FnF9IeqkCX6A'
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', $parameter, $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->qr_string)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->OrderID;
                    $post['upi_string'] = $response->qr_string;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->qr_string);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'nodalpayin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://dk.nodalaccount.com/pg/v1/getIntent";
                $parameter = [
                    "amount"    => $post->amount,
                    "orderId"   => $post->txnid,
                    "name"      => $user->name,
                    "emailId"   => $user->email,
                    "mobileNo"  => $user->mobile,
                    "signature" => $post->signature,
                    "udf1" => "",
                    "udf2" => "",
                    "udf3" => "",
                    "udf4" => "",
                    "udf5" => "",
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'X-APIKEY: '.$api->username,
                    'X-APISECRET: '.$api->password,
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->data->redirect_url) && $response->data->redirect_url == "INITIATED"){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->data->transaction_id;
                    $post['upi_string'] = $data->redirect_url;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($data->redirect_url);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'indpayin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://merchant.indpay.in.net/payin_api.php";
                $parameter = [
                    "amount"     => $post->amount,
                    "order_id"   => $post->txnid,
                    "name"       => $user->name,
                    "email"      => $user->email,
                    "mobile"     => $user->mobile,
                    "user_token" => "805c539553b83355c3ed4259122390ca7f7fd8ef",
                    "api_password"    => "8caaf735a37f630567c834cfe04562adf76e854c",
                    "api_merchant_id" => "82703792",
                    "redirect_url"    => "https://login.pgpe.in/production/api/webhook/indpay/payin"
                ];
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', $parameter, [], "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->data->redirect_url) && $response->data->redirect_url == "INITIATED"){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->data->transaction_id;
                    $post['upi_string'] = $data->redirect_url;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($data->redirect_url);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'flowerpayin':
                do {
                    $post['txnid'] = "FLWP".rand(11111111111, 99999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://payin.flowerpay.in/api/offline-payin-api";
                $parameter = [
                    "amount"    => (float)$post->amount,
                    "order_id"  => $post->txnid
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'token-id: '.$api->username,
                    'secret-key: '.$api->password,
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->success) && $response->success == "Amount has been added."){
                    $post['type']  = "upigateway";
                    $post['refId'] = $post->txnid;
                    $post['upi_string'] = $response->payment_link; 
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string"   => "",
                            "payment_link" => $post->upi_string,
                            "upi_string_image" => ""
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'zunep_payin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://partner.zunep.com/api/generate_qr";
                
                // $post["name"] = \DB::table("name")->where("id", 1)->first()->name;
                // $namereport = \DB::table("collectionreports")->where("product", "qrcode")->orderBy("id", "desc")->first();
                // $name = \DB::table("name")->where("name", $namereport->option4)->first();
                // if($name){
                //     $name = \DB::table("name")->where("id", ($name->id+1))->first();
                //     if($name){
                //         $post["name"] =$name->name;
                //     }
                // }
                
                $parameter = [
                    "amount"    => $post->amount,
                    "orderId"   => $post->txnid,
                    "name"   => $post->name,
                    "email"  => $post->email,
                    "phone"  => $post->mobile,
                    "redirect_url" => "https://login.pgpe.in/production/api/webhook/zunep/payin"
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Token: '.$api->username
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->status) && $response->status == "SUCCESS"){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->txnid;
                    $post['upi_string'] = $response->qr_data;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->qr_data);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $post->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'option4' => $post->name,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->msg)?$response->msg:"Something went wrong"]);
                }
                break;
                
            case 'peuniquezunep_payin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://partner.zunep.com/api/generate_qr_premium";
                
                // $post["name"] = \DB::table("name")->where("id", 1)->first()->name;
                // $namereport = \DB::table("collectionreports")->where("product", "qrcode")->orderBy("id", "desc")->first();
                // $name = \DB::table("name")->where("name", $namereport->option4)->first();
                // if($name){
                //     $name = \DB::table("name")->where("id", ($name->id+1))->first();
                //     if($name){
                //         $post["name"] =$name->name;
                //     }
                // }
                
                $parameter = [
                    "amount"    => $post->amount,
                    "orderId"   => $post->txnid,
                    "name"   => $post->name,
                    "email"  => $post->email,
                    "phone"  => $post->mobile,
                    "redirect_url" => "https://login.pgpe.in/production/api/webhook/zunep/payin"
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Token: '.$api->username
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->status) && $response->status == "SUCCESS"){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->txnid;
                    $post['upi_string'] = $response->qr_data;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->qr_data);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $post->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'option4' => $post->name,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->msg)?$response->msg:"Something went wrong"]);
                }
                break;
                
            case 'paynits_payin':
                do {
                    $post['txnid'] = "PGPE".rand(11111111111, 99999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://gateway.suvikapay.com/api/v6/generateUpi";
                
                // $post["name"] = \DB::table("name")->where("id", 1)->first()->name;
                // $namereport = \DB::table("collectionreports")->where("product", "qrcode")->orderBy("id", "desc")->first();
                // $name = \DB::table("name")->where("name", $namereport->option4)->first();
                // if($name){
                //     $name = \DB::table("name")->where("id", ($name->id+1))->first();
                //     if($name){
                //         $post["name"] =$name->name;
                //     }
                // }
                
                $parameter = [
                    "order_amount" => $post->amount,
                    "order_id"     => $post->txnid,
                    "name"   => str_replace(" ", "", $post->name),
                    "email"  => $post->email,
                    "mobile" => $post->mobile
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Authorization: 38970c118b4501f4d85023c4a239fb3709b842b8'
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->data->payment_link)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->data->transactionId;
                    $post['upi_string'] = $response->data->payment_link;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->data->payment_link);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $post->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'option4' => $post->name,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'walletpayin':
                $url  = "http://103.205.64.251:8080/clickncashapi/rest/auth/generateToken";
                
                $parameter = [
                    "username" => $api->username,
                    "password" => $api->password
                ];
                
                $header = array(
                    'Content-Type: application/json'
                );
                
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->payload->token)){
                    $url  = "http://103.205.64.251:8080/clickncashapi/rest/auth/transaction/generate-upi";
                    $parameter = [
                        "amount" => $post->amount,
                        "option" => "INTENT"
                    ];
                    
                    $header = array(
                        'Content-Type: application/json',
                        'Authorization: Bearer '.$response->payload->token
                    );
                
                    $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                    $response = json_decode($result['response']);

                    if(isset($response->status) && $response->status == "INITIATED"){
                        $post['type']  = "upigateway";
                        $post['refId'] = $response->txnId;
                        $post['txnid'] = $response->txnId;
                        $post['upi_string'] = $response->intentData;
                        $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->intentData);  
                        
                        $insert = [
                            'number'  => $user->mobile,
                            'mobile'  => $user->mobile,
                            'provider_id' => $provider->id,
                            'api_id'  => $api->id,
                            'amount'  => $post->charge,
                            'gst'     => $post->gst,
                            'txnid'   => $post->txnid,
                            'apitxnid'=> $post->apitxnid,
                            'option1' => $post->amount,
                            'payid'   => $post->refId,
                            "refno"   => "UPI INTENT FEE",
                            'status'  => 'pending',
                            'user_id' => $user->id,
                            'credit_by'   => $user->id,
                            'rtype'       => 'main',
                            'trans_type'  => "debit",
                            'product'     => "qrcode",
                            'remark'      => $post->callback,
                            "description" => $post->upi_string
                        ];
                        
                        $report = \DB::transaction(function () use($insert){
                            $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                            User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                            $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                            return Collectionreport::create($insert);
                        });
                        
                        if($report){
                            try {
                                \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                            } catch (\Exception $e) {
                                \DB::table('log_500')->insert([
                                    'line' => $e->getLine(),
                                    'file' => $e->getFile(),
                                    'log'  => $e->getMessage(),
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }
                        
                            return response()->json([
                                'statuscode' => "TXN",
                                "message"    => "Success",
                                "txnid"      => $post->txnid,
                                "upi_tr"     => $post->upi_tr,
                                "upi_string" => $post->upi_string,
                                "upi_string_image" => $post->upi_string_image
                            ]);
                        }else{
                            return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                        }
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message : "Something went wrong"]);
                }
                break;
            
            case 'safexpayin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://uatcheckout.safexpay.com/ms-transaction-core-1-0/paymentRedirection/paymentRequest";
                $parameter["txn_details"] = [
                    "amount"   => $post->amount,
                    "order_number"  => $post->txnid,
                    "ag_id"    => "paygate",
                    "country"  => "IND",
                    "currency" => "INR",
                ];

                $parameter["cust_details"] = [
                    "first_name"    => $user->name,
                    "last_name"     => $user->name,
                    "email_id"      => $user->email,
                    "mobile_number" => $user->mobile
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Referer: https://login.pgpe.in'
                );

                $parameters = [
                    "me_id"    => $api->username,
                    "data"     => $this->encrypt(json_encode($parameter), $api->password, $api->optional1)
                ];
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameters), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                return response()->json([$url, $header, $parameter, $parameters, $response]);

                if(isset($response->response_code) && $response->response_code == "0001"){
                    $data = json_decode($this->decrypt($response->data, $api->password, $api->optional1));

                    $post['type']  = "upigateway";
                    $post['refId'] = $data->ag_ref;
                    $post['upi_string'] = $data->intent_url;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($data->intent_url);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => json_encode($data)."/".json_encode($parameter),
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
            
            case 'resolvitpayin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://resolvit.com/apiTest/upiIntent/qr";
                $parameter = [
                    "amount"   => $post->amount,
                    "orderId"  => $post->txnid,
                    "clientId" => $api->username,
                    "service_type" => "digital",
                    "store_id" => "PGP",
                    "description" => "PGPE",
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Authorization: '.$api->password
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->data->redirect_url) && $response->data->redirect_url == "INITIATED"){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->data->transaction_id;
                    $post['upi_string'] = $data->redirect_url;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($data->redirect_url);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;

            case 'ekopayin-pagepe':
            case 'ekopayin-nikayby':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);

                $encodedKey = base64_encode($api->optional1);
                $secret_key_timestamp = "".round(microtime(true) * 1000);
                $signature  = hash_hmac('SHA256', $secret_key_timestamp, $encodedKey, true);
                $secret_key = base64_encode($signature);

                $header = array(
                    "Content-Type: application/json",
                    "developer_key: ".$api->password,
                    "secret-key: ".$secret_key,
                    "secret-key-timestamp: ".$secret_key_timestamp 
                );
                
                $url = "https://api.eko.in:25002/ekoicici/v2/customer/createcustomer";
                $parameter = [
                    'initiator_id' => $api->username,
                    'name'      => $user->name,
                    'sender_id' => $post->txnid,
                    'email'     => $user->email,
                ];
                $query  = json_encode($parameter);

                $result   = \App\Helpers\Permission::curl($url, 'POST', $query, $header, "yes", "Qr", $post->txnid);
                
                // $url = "https://api.eko.in:25002/ekoicici/v1/user/service/activate";
                // $parameter = [
                //     'initiator_id' => $api->username,
                //     'user_code'    => $api->optional2,
                //     'service_code' => "45"
                // ];
                
                // $header = array(
                //     "Content-Type: application/x-www-form-urlencoded",
                //     "developer_key: ".$api->password,
                //     "secret-key: ".$secret_key,
                //     "secret-key-timestamp: ".$secret_key_timestamp 
                // );
                // $query  = http_build_query($parameter);

                // $result   = \App\Helpers\Permission::curl($url, 'PUT', $query, $header, "yes", "Qr", $post->txnid);
                
                $response = json_decode($result['response']);

                if(isset($response->data->qr_string)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $post->txnid;
                    $post['txnid'] = $response->data->utility_acc_no;
                    $post['upi_tr'] = $response->data->utility_acc_no;
                    $post['upi_string'] = $response->data->qr_string."&am=".$post->amount;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->data->qr_string."&am=".$post->amount);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
            
            case 'iservupayin':
                $url  = $api->url."api/upi/initiate-dynamic-transaction";
                $parameter = [
                    "virtualAddress"  => $api->optional1,
                    "clientRefId"  => $post->txnid,
                    "paymentMode"  => "INTENT",
                    "amount"    => $post->amount,
                    "channelId" => "WEBUSER",
                    "isWalletTopUp" => false,
                    "remarks"   => "Topup",
                    "merchantType"  => "AGGREGATE",
                    "requestingUserName" => "upitestret",
                ];

                $header = array(
                    "Content-Type: application/json",
                    "client_id: ".$api->username,
                    "client_secret: ".$api->password,
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->intentData) && $response->intentData != ""){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->txnId;
                    $post['txnid'] = $post->txnid;
                    $post['upi_string'] = str_replace(" ", "", $response->intentData);
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->intentData);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
            
            case 'indicpayinfino':
                $url  = "https://indicpay.in/api/btt/createorder";
                $parameter = [
                    "token"  => $api->username,
                    "txnid"  => $post->txnid,
                    "amount" => (float)$post->amount,
                    "return_url" => "https://api.atmaadhaar.com/production/api/webhook/cosmos/payin",
                    "name"  => $user->name,
                    "email" => $user->email,
                    "phone" => $user->mobile
                ];
                
                $header = array(
                    'Content-Type: application/json'
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->upi_url)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->txnid;
                    $post['txnid'] = $response->txnid;
                    $post['upi_string'] = str_replace(" ", "", $response->upi_url);
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->upi_url);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->upi_tr,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;

            case 'indicpayinyes':
                $url  = "https://indicpay.in/api/upi/get_dynamic_qr";
                $body = [
                    "token"  => $api->username,
                    "mid"    => $api->password,
                    "utxnid" => $post->txnid,
                    "amount" => $post->amount
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Token: '.$api->username,
                    'Iv: 4569856985632452'
                );
                
                $parameter = array(
                    "body" => openssl_encrypt(base64_encode(json_encode($body)), "AES-256-CBC", $api->password, 0, "4569856985632452"),
                    "hash" => hash('sha512', $api->username.$api->password.$post->txnid)
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->status) && $response->status == "SUCCESS"){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->txnid;
                    $post['upi_string'] = base64_decode($response->qr);
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode(base64_decode($response->qr));  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];

                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{  
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message) ? $response->message : "Something went wrong"]);
                }
                break;
            
            case 'indicpayincosmos':
                $url  = "https://indicpay.in/api/pv2/dynamic_qr";
                $body = [
                    "name"  => $user->name,
                    "email" => $user->email,
                    "phone" => $user->mobile,
                    "txnid" => $post->txnid,
                    "amount"=> $post->amount
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'token: '.$api->username,
                );
                
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($body), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->status) && $response->status == "INITIATE"){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->txnid;
                    $post['upi_string'] = $response->qrstring;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->qrstring);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];

                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{  
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message) ? $response->message : "Something went wrong"]);
                }
                break;
            
            case 'apiwala_nsdl':
                $url  = "https://apiwala.in/production/api/collection/order/nsdl";
                $body = [
                    "partner_id"  => $api->username,
                    "name"  => $user->name,
                    "email" => $user->email,
                    "mobile" => $user->mobile,
                    "apitxnid" => $post->txnid,
                    "amount"=> $post->amount,
                    "callback"=> "https://member.pehunt.in/production/api/webhook/apiwala/payin",
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'api-key: '.$api->password,
                );
                
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($body), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->upi_string)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->upi_tr;
                    $post['upi_string'] = $response->upi_string;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->upi_string);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];

                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{  
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message) ? $response->message : "Something went wrong"]);
                }
                break;
            
            case 'needu_payin':
                $url  = "https://needu.tech/api/v1/create-payment";
                $body = [
                    "lat"  => "28.0358",
                    "long" => "80.2589",
                    "api_ref_id" => $post->txnid,
                    "amount"     => $post->amount
                ];

                $header = array(
                    'Content-Type: application/json',
                    'Clientid: 9220747031',
                    'authkey: a3d7b7f21d58c832aab11051a8476df813817cbc0aae544a74bafaad58c8f7dd',
                );
                
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($body), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->data->upiIntend)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->data->txnId;
                    $post['upi_string'] = $response->data->upiIntend;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->data->upiIntend);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];

                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "upi_string" => $post->upi_string,
                            "upi_string_image" => $post->upi_string_image
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{  
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message) ? $response->message : "Something went wrong"]);
                }
                break;
        }
    }

    public function paymenturl(Request $post)
    {
        $rules = array(
            'apitxnid' => "required|unique:collectionreports,apitxnid",
            'amount'   => 'required|numeric|min:100',
            'callback' => 'required',
            'name'     => 'required',
            'mobile'   => 'required',
            'email'    => 'required',
        );

        if ($this->payinstatus() == "off" && $post->user_id != 69) {
            return response()->json([
                'statuscode' => "ERR",
                "message"    => "Service Under Maintenance"
            ]);
        }

        if (!\App\Helpers\Permission::can('upi_service', $post->user_id)) {
            return response()->json([
                'statuscode' => "ERR",
                "message"    => "Service Not Allowed"
            ]);
        }
        
        $validate = \App\Helpers\Permission::FormValidator($rules, $post->all());
        if($validate != "no"){
            return $validate;
        }

        do {
            $post['txnid'] = "PGPETXN".rand(1111111, 9999999);
        } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);

        $user = User::where('id', $post->user_id)->first();
        if($post->amount > 1 && $post->amount <= 999){
            $provider = Provider::where('recharge1', 'cqrcharge')->first();
        }elseif($post->amount > 999 && $post->amount <= 9999){
            $provider = Provider::where('recharge1', 'cqrcharge2')->first();
        }elseif($post->amount > 9999 && $post->amount <= 20000){
            $provider = Provider::where('recharge1', 'cqrcharge3')->first();
        }else{
            $provider = Provider::where('recharge1', 'cqrcharge4')->first();
        }

        if(!$provider){
            return response()->json(['statuscode' => "ERR", "message" => "Operator Not Found"]);
        }

        if($provider->status == 0){
            return response()->json(['statuscode' => "ERR", "message" => "Operator Down For Sometime"]);
        }

        if(!$provider->api || $provider->api->status == 0){
            return response()->json(['statuscode' => "ERR", "message" => "Service Down For Sometime"]);
        }

        $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $user->id)->first();
        if($serviceApi){
            $api = Api::find($serviceApi->api_id);
        }else{
            $api = Api::find($provider->api_id);
        }

        if($post->amount > 0 && $post->amount <= 199){
            $upiprovider = Provider::where('recharge1', 'qrcollection1')->first();
        }elseif($post->amount > 199 && $post->amount <= 499){
            $upiprovider = Provider::where('recharge1', 'qrcollection2')->first();
        }elseif($post->amount > 499 && $post->amount <= 999){
            $upiprovider = Provider::where('recharge1', 'qrcollection3')->first();
        }else{
            $upiprovider = Provider::where('recharge1', 'qrcollection4')->first();
        }

        $post['upicharge'] = \App\Helpers\Permission::getCommission($post->amount, $user->scheme_id, $upiprovider->id, "apiuser");
        if($post->upicharge == 0){
            return response()->json(['statuscode' => "ERR", "message" => "Contact Administrator, Charges Not Set"]);
        }

        $post['charge'] = \App\Helpers\Permission::getCommission(0, $user->scheme_id, $provider->id, "apiuser");
        $post['gst']    = ($post->charge * 18)/100;  

        if(\App\Helpers\Permission::getAccBalance($user->id, "collectionwallet") < ($post->charge + $post->gst)){
            return response()->json(['statuscode' => "TXF", "message" => "Insufficient Wallet balance"]);
        }

        switch ($api->code) {
            case 'groscopepayin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url = "https://login.groscope.com/api/upi-intent";
                $parameter = [
                    "payment_amount" => $post->amount,
                    'email' => $post->email,
                    "order_id" => $post->txnid,
                    "name"     => $user->name,
                    "mobile_no"=> $post->mobile,
                    "redirect_url" => "https://member.pehunt.in/production/api/webhook/groscope/payin"
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'X-Client-IP: 91.203.133.62',
                    'X-Auth-Token: 9LqM4sK5JfJKC3QSElYvyZSa8EbEftuKCluEYtgDncdBGF0B42',
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->data->url)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->data->order_id;
                    $post['upi_string'] = $response->data->url; 
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->txnid,
                            "payment_link" => $response->data->url
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'camleniopayin':
                do {
                    $post['txnid'] = "CM_ORDER_".rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://partner.camlenio.com/api/v1/payin/ordercreate";
                    
                $header = [
                    "Content-Type: application/json",
                    "User-Agent: team testing",
                    "ApiKey: 9516f27d93c4eb89167012e29fa46d0afa90fb705cec23019bb060ec0101ad21",
                    "SecretKey: 53dbd3b24df8bbca1a5b6250a18fa23685da2c2a42411a7ca7cf2bee892c0b8a361127cbd6db1bc6f1ef0f97ce87866e",
                    "UserId: 2703468451",
                ];
                
                $parameter = [
                    "amount"   => $post->amount,
                    "order_id" => $post->txnid,
                    "redirect_url"    => $post->name,
                    "customer_mobile" => $post->mobile,
                    "email"    => $post->email
                ];
                $body = json_encode($parameter);
                
                $result   = \App\Helpers\Permission::curl($url, 'POST', $body, $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);
                
                if(isset($response->result->payment_url)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->result->orderId;
                    $post['txnid'] = $post->txnid;
                    $post['upi_string'] = str_replace(" ","", $response->result->payment_url);
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->result->payment_url);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refid,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->txnid,
                            "payment_link" => $response->result->payment_url
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'neyopayin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://udaypay.com/api/createqrrun";
                $parameter = [
                    "amount" => $post->amount,
                    "transactionId" => $post->txnid,
                    "Apikey" => "19954"
                ];
                
                $header = array();
                $result   = \App\Helpers\Permission::curl($url, 'POST', $parameter, $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->daynamiclink)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $post->txnid;
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->txnid,
                            "payment_link" => $response->daynamiclink
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'kenzpayin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://kenzpay.com/api/create-order";
                $parameter = [
                    "amount"   => $post->amount,
                    "order_id" => $post->txnid,
                    "customer_mobile"  => $user->mobile,
                    "user_token" => "268bb7d2d63644f5b8e5c3c17fb8bccd",
                    "redirect_url" => "https://login.pgpe.in/production/api/webhook/kenzpay/payin",
                    "remark1" => "test",
                    "remark2" => "test"
                ];
                
                $header = array();
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', $parameter, $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->result->payment_url)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->result->orderId;
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "payment_link" => $response->result->payment_url
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'indpayin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://merchant.indpay.in.net/payin_api.php";
                $parameter = [
                    "amount"    => $post->amount,
                    "order_id"  => $post->txnid,
                    "name"      => $user->name,
                    "email"     => $user->email,
                    "mobile"    => $user->mobile,
                    "user_token" => "805c539553b83355c3ed4259122390ca7f7fd8ef",
                    "api_password" => "8caaf735a37f630567c834cfe04562adf76e854c",
                    "api_merchant_id" => "82703792",
                    "redirect_url" => "https://login.pgpe.in/production/api/webhook/indpay/payin"
                ];
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', $parameter, [], "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->result->payment_url)){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->result->orderId;
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "payment_link" => $response->result->payment_url
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
                
            case 'flowerpayin':
                do {
                    $post['txnid'] = "FLWP".rand(11111111111, 99999999999);
                } while (Collectionreport::where("txnid", "=", $post->txnid)->first() instanceof Collectionreport);
                
                $url  = "https://payin.flowerpay.in/api/offline-payin-api";
                $parameter = [
                    "amount"    => (float)$post->amount,
                    "order_id"  => $post->txnid
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'token-id: '.$api->username,
                    'secret-key: '.$api->password,
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->txnid); 
                $response = json_decode($result['response']);

                if(isset($response->success) && $response->success == "Amount has been added."){
                    $post['type']  = "upigateway";
                    $post['refId'] = $post->txnid;
                    $post['upi_string'] = $response->payment_link; 
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->refId,
                        "refno"   => "UPI INTENT FEE",
                        'status'  => 'pending',
                        'user_id' => $user->id,
                        'credit_by'   => $user->id,
                        'rtype'       => 'main',
                        'trans_type'  => "debit",
                        'product'     => "qrcode",
                        'remark'      => $post->callback,
                        "description" => $post->upi_string
                    ];
                    
                    $report = \DB::transaction(function () use($insert){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->decrement("collectionwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        return Collectionreport::create($insert);
                    });
                    
                    if($report){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        return response()->json([
                            'statuscode' => "TXN",
                            "message"    => "Success",
                            "txnid"      => $post->txnid,
                            "upi_tr"     => $post->refId,
                            "payment_link" => $post->upi_string
                        ]);
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message:"Something went wrong"]);
                }
                break;
        }
    }

    public function status(Request $post)
    {
        $rules = array(
            'apitxnid' => 'required',
        );

        $validate = \App\Helpers\Permission::FormValidator($rules, $post->all());
        if($validate != "no"){
            return $validate;
        }

        $upiload = Collectionreport::where("apitxnid", $post->apitxnid)->where("user_id", $post->user_id)->where("product", "qrcode")->first();
        if(!$upiload){
            return response()->json(['statuscode' => "TNF", 'message' => "Transaction Not Found"]);
        }

        $upiloadReport = Collectionreport::where("apitxnid", $post->apitxnid)->where("user_id", $post->user_id)->where("product", "payin")->first();
        if(!$upiloadReport){
            $callback["amount"] = $upiload->amount;
            $callback["status"] = $upiload->status;
            $callback["statuscode"] = "TXN";
            $callback["txnid"]  = $upiload->txnid;
            $callback["apitxnid"]  = $upiload->apitxnid;
            $callback["utr"]    = $upiload->refno;
            $callback["payment_mode"] = "Upi";
            $callback["payid"]  = $upiload->payid;
            $callback["message"]= "Transaction Status Fetched Successfully";
            return response()->json($callback);

        }

        $callback["amount"] = $upiloadReport->amount;
        $callback["status"] = $upiloadReport->status;
        $callback["statuscode"] = "TXN";
        $callback["txnid"]  = $upiloadReport->txnid;
        $callback["apitxnid"]  = $upiloadReport->apitxnid;
        $callback["utr"]    = $upiloadReport->refno;
        $callback["payment_mode"] = "Upi";
        $callback["payid"]  = $upiloadReport->payid;
        $callback["message"]= "Transaction Status Fetched Successfully";
        return response()->json($callback);
    }

    public function webhook(Request $post, $api)
    {
        $checkVia = "txnid";
        $update["status"] = "pending";
        try {
            switch ($api) {
                case 'eko':
                    if($post->tx_status == 0 || $post->tx_status == 5){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->client_ref_id, 
                            'product'    => 'Ekopayin', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);

                        $update["status"] = "success";
                        $update["txnid"]  = $post->client_ref_id;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = $post->bank_ref_num;
                        $update["sendername"] = $post->sender_name;
                        $update["payid"] = $post->tid;
                        $update["extra"] = $post->payment_mode;
                    }
                    break;
                    
                case 'apiwala':
                    if($post->status == "success"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->apitxnid, 
                            'product'    => 'Apiwala', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);

                        $update["status"] = "success";
                        $update["txnid"]  = $post->apitxnid;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = $post->utr;
                        $update["sendername"] = $post->payment_mode;
                        $update["payid"] = $post->payid;
                        $update["extra"] = $post->payment_mode;
                    }
                    break;

                case 'walletpay':
                    if($post->status == "SUCCESS"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->txnId, 
                            'product'    => 'Walletpay', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);

                        $update["status"] = "success";
                        $update["txnid"]  = $post->txnId;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = $post->utr;
                        $update["sendername"] = "UPI";
                        $update["payid"] = $post->orderId;
                        $update["extra"] = $post->payment_mode;
                    }
                    break;

                case 'indicpay':
                    if(!isset($post->status)){
                        $jsondata = json_encode($post->all());
                        
                        $kyeArray = array_keys($post->all());
                        $post     = json_decode($kyeArray[0]);
                        $amount = explode("_", $post->amount);
                        $myamount = $amount[0];
                    }else{
                        $jsondata = json_encode($post->all());
                        $myamount = $post->amount;
                    }

                    if($post->status == "SUCCESS"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->txnid, 
                            'product'    => 'indicpay', 
                            'response'   => $jsondata,
                            "created_at" => date("Y-m-d H:i:s")
                        ]);

                        $update["status"] = "success";
                        $update["txnid"]  = $post->txnid;
                        $update["amount"] = $myamount;
                        $update["utr"]    = $post->rrn;
                        $update["sendername"] = isset($post->payername) ? $post->payername : "Upi";
                        $update["payid"] = isset($post->vpa) ? $post->vpa : $post->txnid;;
                        $update["extra"] = "UPI";
                    }
                    break;

                case 'openmart':
                    if($post->status == "success"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->client_txn_id, 
                            'product'    => 'openmart', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $post->client_txn_id;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = $post->utr;
                        $update["sendername"] = "Upi";
                        $update["payid"] = $post->client_txn_id;;
                        $update["extra"] = "UPI";
                    }
                    break;

                case 'cosmos':
                    $log = \DB::table('log_webhooks')->insert([
                        'txnid'      => date("ymdhis"), 
                        'product'    => 'cosmospayin', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);
                    break;

                case 'jiffywallet':
                    $data = json_decode(json_encode($post->all()), true);
                    
                    if($data["data"]["status"] == "SUCCESS"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $data["data"]["client_ref_id"], 
                            'product'    => 'jiffywallet', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $data["data"]["client_ref_id"];
                        $update["amount"] = $data["data"]["amount"];
                        $update["utr"]    = $data["data"]["bank_reference"];
                        $update["sendername"] = "Upi";
                        $update["payid"] = $data["data"]["apx_payment_id"];
                        $update["extra"] = "UPI";
                    }
                    break;

                case 'collectswift':
                    if($post->status == "200"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->txnID, 
                            'product'    => 'collectswiftin', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);

                        $update["status"] = "success";
                        $update["txnid"]  = $post->txnID;
                        $update["amount"] = $post->payerAmount;
                        $update["utr"]    = $post->BankRRN;
                        $update["sendername"] = $post->payerName;
                        $update["payid"] = $post->payerVA;
                        $update["extra"] = $post->payerVA;
                    }
                    break;

                case 'resolvit':
                    $log = \DB::table('log_webhooks')->insert([
                        'txnid'      => date("ymdhis"), 
                        'product'    => 'resolvit', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);
                    
                    if($data["data"]["payment_status"] == "success"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $data["data"]["order_id"], 
                            'product'    => 'easyfundproin', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $data["data"]["order_id"];
                        $update["amount"] = $data["data"]["amount"];
                        $update["utr"]    = $data["data"]["utr"];
                        $update["sendername"] = "Upi";
                        $update["payid"] = $data["data"]["transaction_id"];
                        $update["extra"] = "UPI";
                    }
                    break;

                case 'zentexpay':
                    if($post->status == "completed"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->reference_id, 
                            'product'    => 'zentexpay', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $post->reference_id;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = $post->utr;
                        $update["sendername"] = "Upi";
                        $update["payid"] = $post->transaction_id;
                        $update["extra"] = "UPI";
                    }
                    break;

                case 'easyfundpro':
                    $data = json_decode(json_encode($post->all()), true);
                    if($data["data"]["payment_status"] == "success"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $data["data"]["order_id"], 
                            'product'    => 'easyfundproin', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $data["data"]["order_id"];
                        $update["amount"] = $data["data"]["amount"];
                        $update["utr"]    = $data["data"]["utr"];
                        $update["sendername"] = "Upi";
                        $update["payid"] = $data["data"]["transaction_id"];
                        $update["extra"] = "UPI";
                    }
                    break;

                case 'payindia':
                    if($post->status_code == "SUCCESS"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->transaction_id, 
                            'product'    => 'payindia', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $post->transaction_id;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = $post->rrn;
                        $update["sendername"] = "Upi";
                        $update["payid"] = $post->txnid;;
                        $update["extra"] = "UPI";
                    }
                    break;

                case 'zunep':
                    $checkVia = "payid";
                    $log = \DB::table('log_webhooks')->insert([
                        'txnid'      => $post->txnid, 
                        'product'    => 'zunep', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);
                    
                    if($post->status == "SUCCESS"){
                        $update["status"] = "success";
                        $update["txnid"]  = $post->txnid;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = isset($post->utr)?$post->utr : $post->rrn;
                        $update["sendername"] = isset($post->payername) ? $post->payername : "Upi";
                        $update["payid"] = isset($post->vpa) ? $post->vpa : $post->txnid;;
                        $update["extra"] = "UPI";
                    }
                    break;

                case 'indpay':
                case 'kenzpay':
                    $checkVia = "payid";
                    $log = \DB::table('log_webhooks')->insert([
                        'txnid'      => date("ymdhis"), 
                        'product'    => 'indpay', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);
                    
                    if($post->status == "SUCCESS"){
                        $update["status"] = "success";
                        $update["txnid"]  = $post->txnid;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = isset($post->utr)?$post->utr : $post->rrn;
                        $update["sendername"] = isset($post->payername) ? $post->payername : "Upi";
                        $update["payid"] = isset($post->vpa) ? $post->vpa : $post->txnid;;
                        $update["extra"] = "UPI";
                    }
                    break;
                    
                case 'neyopayin':
                case 'neyopay':
                    $checkVia = "txnid";
                    $jsondata = json_encode($post->all());
                    $kyeArray = array_keys($post->all());
                    $post     = json_decode($kyeArray[0]);
                    
                    if($post->txnstatus == "Success"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->partnerTxnId, 
                            'product'    => 'neyopay', 
                            'response'   => json_encode($post),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $post->partnerTxnId;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = $post->rrn;
                        $update["sendername"] = "Upi";
                        $update["payid"] = $post->transactionId;;
                        $update["extra"] = "UPI";
                    }
                    break;
                    
                case 'ezywallet':
                    $checkVia = "txnid";
                    if($post->status == "SUCCESS"){
                        $log = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->refid, 
                            'product'    => 'ezywallet', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $post->refid;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = $post->upi_txn_id;
                        $update["sendername"] = $post->customer_virtual_address;
                        $update["payid"] = $post->upi_txn_id;;
                        $update["extra"] = $post->customer_virtual_address;
                    }
                    break;

                case 'flowerpay':
                    $data = json_decode(json_encode($post->all()), true);
                    $checkVia = "payid";
                    $log = \DB::table('log_webhooks')->insert([
                        'txnid'      => $data["pay_in_offline"]["request_data"]["order_id"], 
                        'product'    => 'flowerpay', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);
                    
                    if($data["pay_in_offline"]["response_data"]["status"] == "success"){
                        $update["status"] = "success";
                        $update["txnid"]  = $data["pay_in_offline"]["request_data"]["order_id"];
                        $update["amount"] = $data["pay_in_offline"]["request_data"]["amount"];
                        $update["utr"]    = $data["pay_in_offline"]["response_data"]["rrn_no"];
                        $update["sendername"] = "Upi";
                        $update["payid"] = $data["pay_in_offline"]["response_data"]["credit_date"];
                        $update["extra"] = "UPI";
                    }
                    break;

                case 'paynits':
                    $log  = \DB::table('log_webhooks')->insert([
                        'txnid'      => date("ymdhis"), 
                        'product'    => 'paynits', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);
                    
                    $checkVia = "txnid";
                    $data = json_decode(json_encode($post->all()), true);
                    $data = json_decode(json_encode($data["data"]));
                    $log  = \DB::table('log_webhooks')->insert([
                        'txnid'      => $data->order_id, 
                        'product'    => 'paynits', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);
                    
                    if($post->event == "TRANSACTION_CREDIT"){
                        $update["status"] = "success";
                        $update["txnid"]  = $data->order_id;
                        $update["amount"] = $data->amount;
                        $update["utr"]    = $data->UTR;
                        $update["sendername"] = isset($data->name) ? $data->name : "Upi";
                        $update["payid"] = isset($data->payer_UPIID) ? $data->payer_UPIID : $data->order_id;
                        $update["extra"] = "UPI";
                    }
                    break;
                    
                case 'groscope':
                    $checkVia = "txnid";
                    $data = json_decode(json_encode($post->all()), true);
                    $data = json_decode(json_encode($data["data"]));
                    
                    if($data->status == "Success"){
                        $log  = \DB::table('log_webhooks')->insert([
                            'txnid'      => $data->order_id, 
                            'product'    => 'groscope', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $data->order_id;
                        $update["amount"] = $data->payment_amount;
                        $update["utr"]    = $data->rrn_no;
                        $update["sendername"] = isset($data->payer_upi_id) ? $data->payer_upi_id : "Upi";
                        $update["payid"] = isset($data->payment_id) ? $data->payment_id : $data->order_id;
                        $update["extra"] = "UPI";
                    }
                    break;
                
                case 'zanithpaypayin':
                    if($post->status === 200){
                        $log  = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->txnID, 
                            'product'    => 'zanithpaypayin', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $post->txnID;
                        $update["amount"] = $post->payerAmount;
                        $update["utr"]    = $post->BankRRN;
                        $update["sendername"] = isset($post->payerVA) ? $post->payerVA : "Upi";
                        $update["payid"] = $post->txnID;
                        $update["extra"] = isset($post->payerName) ? $post->payerName : "UPI";
                    }
                    break;
                
                case 'mizorpay':
                    $log  = \DB::table('log_webhooks')->insert([
                        'txnid'      => date("ymgdhis"), 
                        'product'    => 'mizorpay', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);
                    break;
                
                case 'indiplex':
                    $data = json_decode(json_encode($post->all()), true);
                    $data = json_decode(json_encode($data["transaction_data"]));
                    
                    if($data->gatewayResponseStatus === "SUCCESS"){
                        $log  = \DB::table('log_webhooks')->insert([
                            'txnid'      => $data->merchantRequestId, 
                            'product'    => 'indiplex', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $data->merchantRequestId;
                        $update["amount"] = $data->amount;
                        $update["utr"]    = $data->gatewayReferenceId;
                        $update["sendername"] = isset($data->payerName) ? $data->payerName : "Upi";
                        $update["payid"]  = $data->yppReferenceNumber;
                        $update["extra"]  = isset($data->payerVPA) ? $data->payerVPA : "UPI";
                    }
                    break;
                
                case 'camlenio':
                    $log  = \DB::table('log_webhooks')->insert([
                        'txnid'      => $post->order_id, 
                        'product'    => 'camlenio', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);
                    
                    if($post->status === "SUCCESS"){
                        $update["status"] = "success";
                        $update["txnid"]  = $post->order_id;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = $post->utr;
                        $update["sendername"] = isset($post->customer_mobile) ? $post->customer_mobile : "Upi";
                        $update["payid"] = $post->order_id;
                        $update["extra"] = isset($post->customer_mobile) ? $post->customer_mobile : "UPI";
                    }
                    break;
                
                case 'payonedigital':
                case 'techmondo':
                    if($post->status === "SUCCESS" && $post->type === "payin"){
                        $log  = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->refid, 
                            'product'    => $api, 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update["status"] = "success";
                        $update["txnid"]  = $post->refid;
                        $update["amount"] = $post->amount;
                        $update["utr"]    = $post->transaction_id;
                        $update["sendername"] = "Upi";
                        $update["payid"] = $post->transaction_id;
                        $update["extra"] = "UPI";
                    }
                    
                    if($post->status === "SUCCESS" && $post->type === "payout"){
                        $log  = \DB::table('log_webhooks')->insert([
                            'txnid'      => $post->refid, 
                            'product'    => 'payonedigital', 
                            'response'   => json_encode($post->all()),
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                        
                        $update['txnid']  = $post->refid;
                        if(isset($post->status) && strtolower($post->status) == "success"){
                            $update['status'] = "success";
                            $update['refno']  = $post->rrn;
                        }elseif(isset($post->status) && strtolower($post->status) == "failure"){
                            $update['status'] = "reversed";
                            $update['refno']  = $post->statusDesc;
                        }elseif(isset($post->status) && strtolower($post->status) == "failed"){
                            $update['status'] = "reversed";
                            $update['refno']  = $post->statusDesc;
                        }
                        
                        try {
                            if(isset($update['txnid'])){
                                $report = Report::where($checkvia, $update['txnid'])->first();
                                if($report){
                                    if(isset($update['status']) && $update['status'] != "pending"){
                                        if(in_array($report->status, ['success', 'pending', 'accept'])){
                                            $updates = Report::where('id', $report->id)->update($update);
                                            Tempreport::where('txnid', $report->txnid)->update($update);
                                            
                                            if($update['status'] == "reversed"){
                                                \App\Helpers\Permission::transactionRefund($report->id, "reports", "mainwallet");
                                            }
                                            
                                            $webhook_payload["amount"] = $report->amount;
                                            $webhook_payload["status"] = $update['status'];
                                            $webhook_payload["statuscode"] = "TXN";
                                            $webhook_payload["txnid"]  = $report->txnid;
                                            $webhook_payload["apitxnid"]  = $report->apitxnid;
                                            $webhook_payload["utr"]    = $update['refno'];
                                            $webhook_payload["payment_mode"] = "payout";
                                            $webhook_payload["payid"]  = $report->id;
                                            $webhook_payload["message"]= $update['refno'];
                                            $response = \App\Helpers\Permission::curl($report->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no");
                        
                                            \DB::table('log_webhooks')->where('txnid', $update['txnid'])->update([
                                                'url' => $report->remark."?".http_build_query($webhook_payload), 
                                                'callbackresponse' => json_encode($response)
                                            ]);
                                        }
                                    }
                                    return response()->json(["status" => true, "message" => "ok"]);
                                }else{
                                    return response()->json(["status" => false, "message" => "Not Matched"]);
                                }
                            }
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        return response()->json(["status" => false, "message" => "Not Matched"]);
                    }
                    break;
            }

            if($update["status"] == "success"){
                $payinReport = Collectionreport::where($checkVia, $update["txnid"])->whereIn("status", ["pending", "failed"])->where("product", "qrcode")->first();
                
                if($payinReport){
                    if($update["amount"] > 0 && $update["amount"] <= 250){
                        $provider = Provider::where('recharge1', 'qrcollection1')->first();
                    }else{
                        $provider = Provider::where('recharge1', 'qrcollection2')->first();
                    }

                    $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $payinReport->user_id)->first();
                    if($serviceApi  && $payinReport->remark != "SELF"){
                        $api = Api::find($serviceApi->api_id);
                    }else{
                        $api = Api::find($provider->api_id);
                    }

                    if($api->code == "collect"){
                        $userid = $this->admin->id;
                        $update["type"] = "local";
                    }else{
                        $userid = $payinReport->user_id;
                        $update["type"] = "live";
                    }
                    
                    if($payinReport->user->payin_daily_deduction == 0){
                        $update['charge'] = \App\Helpers\Permission::getCommission($update["amount"], $payinReport->user->scheme_id, $provider->id, $payinReport->user->role->slug);
                        $update['gst']    = ($update['charge'] * 18)/100;
                    }else{
                        $update['charge'] = 0;
                        $update['gst']    = 0; 
                    }

                    $insert = [
                        'number'  => $update["payid"],
                        'mobile'  => $payinReport->user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $api->id,
                        'amount'  => $update["amount"],
                        'charge'  => $update['charge'],
                        'gst'     => $update['gst'],
                        'txnid'   => $payinReport->txnid,
                        'apitxnid'   => $payinReport->apitxnid,
                        'payid'   => $update['payid'],
                        'refno'   => $update['utr'],
                        'option1' => $update['sendername'],
                        'option2' => $update['extra'],
                        'option3' => $update['type'],
                        'status'  => 'success',
                        'transfer_mode' => 'callback',
                        'user_id' => $userid,
                        'credit_by'   => $payinReport->user_id,
                        'rtype'       => 'main',
                        'create_time' => $update['utr'],
                        'trans_type'  => "credit",
                        'product'     => "payin"
                    ];

                    $report = \DB::transaction(function () use($insert, $payinReport){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        User::where('id', $insert['user_id'])->increment("collectionwallet", $insert["amount"] - ($insert["charge"] + $insert["gst"]));
                        User::where('id', $insert['user_id'])->increment("payin_daily_used", $insert["amount"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "collectionwallet");
                        
                        if($insert["option3"] != "local"){
                            \DB::table("collectionreports")->where('txnid', $insert["txnid"])->where("product", "qrcode")->update([
                                "status"  => "success",
                                "refno"   => $insert["refno"]
                            ]);
                        }
                        return Collectionreport::create($insert);
                    });

                    if($update["type"] == "live"){
                        try {
                            \App\Helpers\Permission::commission(Collectionreport::where("id", $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                    }
                    
                    if($update["type"] == "live"){
                        $webhook_payload["amount"] = $update["amount"];
                        $webhook_payload["status"] = "success";
                        $webhook_payload["statuscode"] = "TXN";
                        $webhook_payload["txnid"]  = $payinReport->txnid;
                        $webhook_payload["apitxnid"]   = $payinReport->apitxnid;
                        $webhook_payload["utr"]    = $update["utr"];
                        $webhook_payload["payment_mode"] = $update["extra"];
                        $webhook_payload["payid"]  = $payinReport->id;
                        $webhook_payload["message"]= $update["sendername"];
                        $response = \App\Helpers\Permission::curl($payinReport->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");

                        \DB::table('log_webhooks')->where('txnid', $update["txnid"])->update([
                            'url' => $payinReport->remark."?".http_build_query($webhook_payload), 
                            'callbackresponse' => json_encode($response)
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            \DB::table('log_500')->insert([
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'log'  => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}