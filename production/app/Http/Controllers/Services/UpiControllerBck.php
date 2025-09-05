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

    public function create(Request $post)
    {
        $rules = array(
            'apitxnid' => "required|unique:collectionreports,apitxnid",
            'amount'   => 'required|numeric|min:1',
            'callback' => 'required',
        );

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
            $post['txnid'] = "PEUNIPAYAPI".rand(1111111, 9999999);
        } while (Qrreport::where("txnid", "=", $post->txnid)->first() instanceof Qrreport);

        $user = User::where('id', $post->user_id)->first();
        if($post->amount > 1 && $post->amount <= 9999){
            $provider = Provider::where('recharge1', 'qrcharge')->first();
        }elseif($post->amount > 9999 && $post->amount <= 20000){
            $provider = Provider::where('recharge1', 'qrcharge2')->first();
        }else{
            $provider = Provider::where('recharge1', 'qrcharge3')->first();
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

        // if($post->charge == 0){
        //     return response()->json(['statuscode' => "ERR", "message" => "Contact Administrator, Charges Not Set"]);
        // }

        $post['gst']    = ($post->charge * 18)/100;  

        if(\App\Helpers\Permission::getAccBalance($user->id, "qrwallet") < ($post->charge + $post->gst)){
            return response()->json(['statuscode' => "TXF", "message" => "Insufficient Wallet balance"]);
        }

        switch ($api->code) {
            case 'ekopayin':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Qrreport::where("txnid", "=", $post->txnid)->first() instanceof Qrreport);

                $encodedKey = base64_encode("150782ee-3cea-4f00-81ad-466a1346fd78");
                $secret_key_timestamp = "".round(microtime(true) * 1000);
                $signature  = hash_hmac('SHA256', $secret_key_timestamp, $encodedKey, true);
                $secret_key = base64_encode($signature);

                $header = array(
                    "Content-Type: application/json",
                    "developer_key: 49af8a451c10fff8404501c907757e02",
                    "secret-key: ".$secret_key,
                    "secret-key-timestamp: ".$secret_key_timestamp 
                );
                
                $url = "https://api.eko.in:25002/ekoicici/v2/customer/createcustomer";
                $parameter = [
                    'initiator_id' => "7982724172",
                    'name'      => $user->name,
                    'sender_id' => $post->txnid,
                    'email'     => $user->email,
                ];
                $query  = json_encode($parameter);

                $result   = \App\Helpers\Permission::curl($url, 'POST', $query, $header, "yes", "Qr", $post->txnid);
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
                        'api_id'  => $provider->api_id,
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
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        User::where('id', $insert['user_id'])->decrement("qrwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        return Qrreport::create($insert);
                    });
                    
                    if($report){
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

            case 'ekopayin-wordpay':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Qrreport::where("txnid", "=", $post->txnid)->first() instanceof Qrreport);

                $encodedKey = base64_encode("83a56078-933d-49e6-9c68-f2702a3cd78a");
                $secret_key_timestamp = "".round(microtime(true) * 1000);
                $signature  = hash_hmac('SHA256', $secret_key_timestamp, $encodedKey, true);
                $secret_key = base64_encode($signature);

                $header = array(
                    "Content-Type: application/json",
                    "developer_key: de7075758c45226ace8e48dc82addbd1",
                    "secret-key: ".$secret_key,
                    "secret-key-timestamp: ".$secret_key_timestamp 
                );
                
                $url = "https://api.eko.in:25002/ekoicici/v2/customer/createcustomer";
                $parameter = [
                    'initiator_id' => "9234875743",
                    'name'      => $user->name,
                    'sender_id' => $post->txnid,
                    'email'     => $user->email,
                ];
                $query  = json_encode($parameter);

                $result   = \App\Helpers\Permission::curl($url, 'POST', $query, $header, "yes", "Qr", $post->txnid);
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
                        'api_id'  => $provider->api_id,
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
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        User::where('id', $insert['user_id'])->decrement("qrwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        return Qrreport::create($insert);
                    });
                    
                    if($report){
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

            case 'ekopayin-atmaadhaar':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Qrreport::where("txnid", "=", $post->txnid)->first() instanceof Qrreport);

                $encodedKey = base64_encode("30595098-b497-41b4-85a5-70b7a710e72b");
                $secret_key_timestamp = "".round(microtime(true) * 1000);
                $signature  = hash_hmac('SHA256', $secret_key_timestamp, $encodedKey, true);
                $secret_key = base64_encode($signature);

                $header = array(
                    "Content-Type: application/json",
                    "developer_key: bdc9fe5778a5670640bf9fbe980fe94c",
                    "secret-key: ".$secret_key,
                    "secret-key-timestamp: ".$secret_key_timestamp 
                );
                
                $url = "https://api.eko.in:25002/ekoicici/v2/customer/createcustomer";
                $parameter = [
                    'initiator_id' => "9234863988",
                    'name'      => $user->name,
                    'sender_id' => $post->txnid,
                    'email'     => $user->email,
                ];
                $query  = json_encode($parameter);

                $result   = \App\Helpers\Permission::curl($url, 'POST', $query, $header, "yes", "Qr", $post->txnid);
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
                        'api_id'  => $provider->api_id,
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
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        User::where('id', $insert['user_id'])->decrement("qrwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        return Qrreport::create($insert);
                    });
                    
                    if($report){
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
            
            case 'ekopayin-page':
                do {
                    $post['txnid'] = rand(1111111111, 9999999999);
                } while (Qrreport::where("txnid", "=", $post->txnid)->first() instanceof Qrreport);

                $encodedKey = base64_encode("63b63cc7-e747-4440-a85f-7084448cab5d");
                $secret_key_timestamp = "".round(microtime(true) * 1000);
                $signature  = hash_hmac('SHA256', $secret_key_timestamp, $encodedKey, true);
                $secret_key = base64_encode($signature);

                $header = array(
                    "Content-Type: application/json",
                    "developer_key: e9cce8c161ae1b7077332881c53ee227",
                    "secret-key: ".$secret_key,
                    "secret-key-timestamp: ".$secret_key_timestamp 
                );
                
                $url = "https://api.eko.in:25002/ekoicici/v2/customer/createcustomer";
                $parameter = [
                    'initiator_id' => "8505875775",
                    'name'      => $user->name,
                    'sender_id' => $post->txnid,
                    'email'     => $user->email,
                ];
                $query  = json_encode($parameter);

                $result   = \App\Helpers\Permission::curl($url, 'POST', $query, $header, "yes", "Qr", $post->txnid);
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
                        'api_id'  => $provider->api_id,
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
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        User::where('id', $insert['user_id'])->decrement("qrwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        return Qrreport::create($insert);
                    });
                    
                    if($report){
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
            
            case 'walletpayin':
                $url  = "http://103.205.64.251:8080/clickncashapi/rest/auth/generateToken";
                
                $parameter = [
                    "username" => "Suchit",
                    "password" => "Vc9JSjIYYSGJ"
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
                            'api_id'  => $provider->api_id,
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
                            $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                            User::where('id', $insert['user_id'])->decrement("qrwallet", $insert["amount"] + $insert["gst"]);
                            $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                            return Qrreport::create($insert);
                        });
                        
                        if($report){
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

            case 'unpepayin':
                $url = $api->url."payin/order/create";
                $parameter = [
                    "partner_id" => $api->username,
                    "callback"   => "https://api.peunique.com/production/api/webhook/unpe",
                    "amount"     => $post->amount,
                    "txnid"      => $post->txnid,
                ];

                $header = array(
                    "Cache-Control: no-cache",
                    "Content-Type: application/json",
                    "api-key: ".$api->password
                );

                $method = "POST";
                $data["body"] = \App\Helpers\Permission::unpeencrypt($parameter, $api->optional1, $api->optional2);
                $query  = json_encode($data);

                $result   = \App\Helpers\Permission::curl($url, 'POST', $query, $header, "yes", "Qr", $post->txnid);
                $response = json_decode($result['response']);

                if(isset($response->statuscode) && $response->statuscode == "TXN"){
                    $post['type']  = "upigateway";
                    $post['refId'] = $post->upi_tr;
                    $post['upi_string'] = $response->upi_string;
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->upi_string);  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $provider->api_id,
                        'amount'  => $post->charge,
                        'gst'     => $post->gst,
                        'txnid'   => $post->txnid,
                        'apitxnid'=> $post->apitxnid,
                        'option1' => $post->amount,
                        'payid'   => $post->upi_tr,
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
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        User::where('id', $insert['user_id'])->decrement("qrwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        return Qrreport::create($insert);
                    });
                    
                    if($report){
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
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message : "Something went wrong"]);
                }
                break;

            case 'edhaapayin':
                $url  = "https://api.edhaapay.in/collection/upi/v1/login";
                $parameter = [
                    "merchantId" => $api->username,
                    "password"   => $api->password
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'MERCHANT-KEY: '.$api->optional1
                );

                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->refid); 
                $response = json_decode($result['response']);

                if(isset($response->token)){
                    $url  = "https://api.edhaapay.in/collection/upi/v1/getUpiLink";
                    $parameter = [
                        "merchantId"    => $api->username,
                        "emailAddress"  => $user->email,
                        "mobileNumber"  => $user->mobile,
                        "merchantTxnId" => $post->txnid,
                        "name"   => $user->name,
                        "amount" => $post->amount
                    ];
                    $hashString = $api->username.$response->token.$post->txnid.$post->amount;

                    $hash = hash_hmac('sha256', $hashString, $api->optional2, true);
                    $base64_hash = base64_encode($hash);

                    $header = array(
                        'Content-Type: application/json',
                        'MERCHANT-KEY: '.$api->optional1,
                        'TOKEN: '.$response->token,
                        "HASH: ".$base64_hash
                    );
                
                    $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->refid); 
                    $response = json_decode($result['response']);
        
                    if(isset($response->intentData->intentUri)){
                        $post['type']  = "upigateway";
                        $post['refId'] = $post->txnid;
                        $post['upi_string'] = $response->intentData->intentUri;
                        $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode($response->intentData->intentUri);
                        
                        $insert = [
                            'number'  => $user->mobile,
                            'mobile'  => $user->mobile,
                            'provider_id' => $provider->id,
                            'api_id'  => $provider->api_id,
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
                            $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                            User::where('id', $insert['user_id'])->decrement("qrwallet", $insert["amount"] + $insert["gst"]);
                            $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                            return Qrreport::create($insert);
                        });
                        
                        if($report){
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
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message) ? $response->message : "Something went wrong"]);
                }
                break;

            case 'indicpayin':
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
                
                //return response()->json([$url, $header, $body, $parameter, $response]);
    
                if(isset($response->status) && $response->status == "SUCCESS"){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->txnid;
                    $post['upi_string'] = base64_decode($response->qr);
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode(base64_decode($response->qr));  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $provider->api_id,
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
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        User::where('id', $insert['user_id'])->decrement("qrwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        return Qrreport::create($insert);
                    });
                    
                    if($report){
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
            
            case 'pinwalletpayin':
                $url  = "https://app.huntood.com/api/DyupiV2/V4/GenerateUPI";
                $parameter = [
                    "Email"  => $user->email,
                    "Phone"  => $user->mobile,
                    "ReferenceId" => $post->txnid,
                    "Name"   => $user->name,
                    "amount" => $post->amount
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'AuthKey: '.$api->username,
                    'IPAddress: 91.203.133.62'
                );
            
                $result   = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), $header, "yes", "Qr", $post->refid); 
                $response = json_decode($result['response']);
    
                if(isset($response->data->status) && $response->data->status == "SUCCESS"){
                    $post['type']  = "upigateway";
                    $post['refId'] = $response->data->walletTransactionId;
                    $post['upi_string'] = str_replace(" ", "", $response->data->qr);
                    $post['upi_string_image'] = "https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=".urlencode(str_replace(" ", "", $response->data->qr));  
                    
                    $insert = [
                        'number'  => $user->mobile,
                        'mobile'  => $user->mobile,
                        'provider_id' => $provider->id,
                        'api_id'  => $provider->api_id,
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
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        User::where('id', $insert['user_id'])->decrement("qrwallet", $insert["amount"] + $insert["gst"]);
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                        return Qrreport::create($insert);
                    });
                    
                    if($report){
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
            
            default:
                $parameter = array(
                    'token' => $api->username,
                    'txnid' => $post->txnid,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'mobile'=> $user->mobile,
                    'amount'=> $post->amount,
                    'type'  => 'dynamic',
                    'callback' => "https://api.peunique.com/production/api/webhook/payin"
                );

                $url = "https://api.gameupi.com/v/qr/collection";
                $result = \App\Helpers\Permission::curl($url, 'POST', json_encode($parameter), array("Content-Type: application/json"), 'yes', "Collection",  $post->txnid);
                
                if($result['response'] != ''){
                    $response = json_decode($result['response']);
                    if(isset($response->upi_string)){
                        $post['type']  = "upigateway";
                        $post['refId'] = $response->upi_tr;
                        $post['upi_string'] = $response->upi_string;
                        
                        $insert = [
                            'number'  => $user->mobile,
                            'mobile'  => $user->mobile,
                            'provider_id' => $provider->id,
                            'api_id'  => $provider->api_id,
                            'amount'  => $post->charge,
                            'gst'     => $post->gst,
                            'txnid'   => $post->txnid,
                            'option1' => $post->amount,
                            'apitxnid'=> $post->apitxnid,
                            'payid'   => $response->upi_tr,
                            "refno"   => "UPI INTENT FEE",
                            'status'  => 'pending',
                            'user_id' => $user->id,
                            'credit_by'   => $user->id,
                            'rtype'       => 'main',
                            'trans_type'  => "debit",
                            'product'     => "qrcode",
                            'remark'      => $post->callback,
                            "description" => $response->upi_string
                        ];

                        $report = \DB::transaction(function () use($insert){
                            $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                            User::where('id', $insert['user_id'])->decrement("qrwallet", $insert["amount"] + $insert["gst"]);
                            $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "qrwallet");
                            return Qrreport::create($insert);
                        });
                        
                        if($report){
                            return response()->json([
                                'statuscode' => "TXN",
                                "message"    => "Success",
                                "txnid"      => $post->txnid,
                                "upi_tr"     => $post->refId,
                                "upi_string" => $post->upi_string
                            ]);
                        }else{
                            return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                        }
                    }else{
                        return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
                    }
                }else{
                    return response()->json(['statuscode' => "TXF", "message" => "Something went wrong"]);
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

        $upiload = Qrreport::where("apitxnid", $post->apitxnid)->where("user_id", $post->user_id)->first();
        if(!$upiload){
            return response()->json(['statuscode' => "TNF", 'message' => "Transaction Not Found"]);
        }

        $upiloadReport = Collectionreport::where("apitxnid", $post->apitxnid)->where("user_id", $post->user_id)->first();
        if(!$upiloadReport){
            return response()->json(['statuscode' => "TNF", 'message' => "Payment Not Received Yet"]);
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

    public function webhook(Request $post)
    {
        $log = \DB::table('log_webhooks')->insert([
            'txnid'      => $post->apitxnid, 
            'product'    => 'Payin', 
            'response'   => json_encode($post->all()),
            "created_at" => date("Y-m-d H:i:s")
        ]);
            
        if($post->status == "success"){
            $payinReport = Qrreport::where("txnid", $post->apitxnid)->where("status", "pending")->where("product", "qrcode")->first();

            if($payinReport){
                if($post->amount > 0 && $post->amount <= 199){
                    $provider = Provider::where('recharge1', 'qrcollection1')->first();
                }elseif($post->amount > 199 && $post->amount <= 499){
                    $provider = Provider::where('recharge1', 'qrcollection2')->first();
                }elseif($post->amount > 499 && $post->amount <= 999){
                    $provider = Provider::where('recharge1', 'qrcollection3')->first();
                }else{
                    $provider = Provider::where('recharge1', 'qrcollection4')->first();
                }

                $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $payinReport->user_id)->first();
                if($serviceApi  && $payinReport->remark != "SELF"){
                    $api = Api::find($serviceApi->api_id);
                }else{
                    $api = Api::find($provider->api_id);
                }

                if($api->code == "collect"){
                    $userid = $this->admin->id;
                }else{
                    $userid = $payinReport->user_id;
                }
                
                $post['charge'] = \App\Helpers\Permission::getCommission($post->amount, $payinReport->user->scheme_id, $provider->id, $payinReport->user->role->slug);
                $post['gst']    = ($post->charge * 18)/100;

                $insert = [
                    'number'  => $post->payid,
                    'option1' => $post->payment_mode,
                    'mobile'  => $payinReport->user->mobile,
                    'provider_id' => $provider->id,
                    'api_id'  => $provider->api->id,
                    'amount'  => $post->amount,
                    'charge'  => $post->charge,
                    'gst'     => $post->gst,
                    'txnid'   => $payinReport->txnid,
                    'apitxnid'   => $payinReport->apitxnid,
                    'payid'   => $post->txnid,
                    'refno'   => $post->utr,
                    'status'  => 'success',
                    'transfer_mode' => 'callback',
                    'user_id' => $userid,
                    'credit_by'   => $payinReport->user_id,
                    'rtype'       => 'main',
                    'create_time' => $post->utr,
                    'trans_type'  => "credit",
                    'product'     => "payin"
                ];

                if($payinReport->remark == "SELF"){
                    $wallet = "qrwallet";
                    $table  = "qrreports";
                    $reportTable = Qrreport::query();
                }else{
                    $wallet = "collectionwallet";
                    $table  = "collectionreports";
                    $reportTable = Collectionreport::query();
                }

                try {
                    $report = \DB::transaction(function () use($insert, $api, $wallet, $table, $reportTable, $payinReport){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        User::where('id', $insert['user_id'])->increment($wallet, $insert["amount"]  - ($insert["charge"] + $insert["gst"]));
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        if($api->code != "collect"){
                            \DB::table("qrreports")->where('id', $payinReport->id)->where("product", "qrcode")->update([
                                "status"  => "success",
                                "refno"   => $insert["refno"]
                            ]);
                        }
                        return $reportTable->create($insert);
                    });

                    if($api->code != "collect"){
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

                    if($api->code != "collect"){
                        $webhook_payload["amount"] = $post->amount;
                        $webhook_payload["status"] = "success";
                        $webhook_payload["statuscode"] = "TXN";
                        $webhook_payload["txnid"]  = $payinReport->txnid;
                        $webhook_payload["apitxnid"]  = $payinReport->apitxnid;
                        $webhook_payload["utr"]    = $post->utr;
                        $webhook_payload["payment_mode"] = $post->payid;
                        $webhook_payload["payid"]  = $payinReport->id;
                        $webhook_payload["message"]= $post->payment_mode;
                        $response = \App\Helpers\Permission::curl($payinReport->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");

                        \DB::table('log_webhooks')->where('txnid', $post->client_txn_id)->update([
                            'url' => $payinReport->remark."?".http_build_query($webhook_payload), 
                            'callbackresponse' => json_encode($response)
                        ]);
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
    }

    public function indicpay(Request $data)
    {
        //$sting = json_decode(, true);
        $kyeArray = array_keys($data->all());
        $post  = json_decode($kyeArray[0]);

        $log = \DB::table('log_webhooks')->insert([
            'txnid'      => $post->txnid, 
            'product'    => 'indicpay', 
            'response'   => json_encode($data->all()),
            "created_at" => date("Y-m-d H:i:s")
        ]);
            
        if($post->status == "SUCCESS"){
            $payinReport = Qrreport::where("txnid", $post->txnid)->where("status", "pending")->where("product", "qrcode")->first();

            if($payinReport){
                if($post->amount > 0 && $post->amount <= 199){
                    $provider = Provider::where('recharge1', 'qrcollection1')->first();
                }elseif($post->amount > 199 && $post->amount <= 499){
                    $provider = Provider::where('recharge1', 'qrcollection2')->first();
                }elseif($post->amount > 499 && $post->amount <= 999){
                    $provider = Provider::where('recharge1', 'qrcollection3')->first();
                }else{
                    $provider = Provider::where('recharge1', 'qrcollection4')->first();
                }

                $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $payinReport->user_id)->first();
                if($serviceApi  && $payinReport->remark != "SELF"){
                    $api = Api::find($serviceApi->api_id);
                }else{
                    $api = Api::find($provider->api_id);
                }

                if($api->code == "collect"){
                    $userid = $this->admin->id;
                }else{
                    $userid = $payinReport->user_id;
                }
                
                $data['charge'] = \App\Helpers\Permission::getCommission($post->amount, $payinReport->user->scheme_id, $provider->id, $payinReport->user->role->slug);
                $data['gst']    = ($data->charge * 18)/100;

                $insert = [
                    'number'  => $post->rrn,
                    'mobile'  => $payinReport->user->mobile,
                    'provider_id' => $provider->id,
                    'api_id'  => $provider->api->id,
                    'amount'  => $post->amount,
                    'charge'  => $data->charge,
                    'gst'     => $data->gst,
                    'txnid'   => $payinReport->txnid,
                    'apitxnid'   => $payinReport->apitxnid,
                    'payid'   => $post->txnid,
                    'refno'   => $post->rrn,
                    'status'  => 'success',
                    'transfer_mode' => 'callback',
                    'user_id' => $userid,
                    'credit_by'   => $payinReport->user_id,
                    'rtype'       => 'main',
                    'create_time' => $post->rrn,
                    'trans_type'  => "credit",
                    'product'     => "payin"
                ];

                if($payinReport->remark == "SELF"){
                    $wallet = "qrwallet";
                    $table  = "qrreports";
                    $reportTable = Qrreport::query();
                }else{
                    $wallet = "collectionwallet";
                    $table  = "collectionreports";
                    $reportTable = Collectionreport::query();
                }

                try {
                    $report = \DB::transaction(function () use($insert, $api, $wallet, $table, $reportTable, $payinReport){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        User::where('id', $insert['user_id'])->increment($wallet, $insert["amount"]  - ($insert["charge"] + $insert["gst"]));
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        if($api->code != "collect"){
                            \DB::table("qrreports")->where('id', $payinReport->id)->where("product", "qrcode")->update([
                                "status"  => "success",
                                "refno"   => $insert["refno"]
                            ]);
                        }
                        return $reportTable->create($insert);
                    });

                    if($api->code != "collect"){
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

                    if($api->code != "collect"){
                        $webhook_payload["amount"] = $post->amount;
                        $webhook_payload["status"] = "success";
                        $webhook_payload["statuscode"] = "TXN";
                        $webhook_payload["txnid"]  = $payinReport->txnid;
                        $webhook_payload["apitxnid"]  = $payinReport->apitxnid;
                        $webhook_payload["utr"]    = $post->rrn;
                        $webhook_payload["payment_mode"] = $payinReport->payid;
                        $webhook_payload["payid"]  = $payinReport->id;
                        $webhook_payload["message"]= "Upi Payment";
                        $response = \App\Helpers\Permission::curl($payinReport->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");

                        \DB::table('log_webhooks')->where('txnid', $post->txnid)->update([
                            'url' => $payinReport->remark."?".http_build_query($webhook_payload), 
                            'callbackresponse' => json_encode($response)
                        ]);
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
    }
    
    public function pinwallet(Request $data)
    {
        $mydata = json_decode(json_encode($data->all()), true);
        $data   = $mydata["Data"];

        if($mydata["event"] == "DynamicUPIQR"){
            $txnid = $data['ApiUserReferenceId'];
        }else{
            $txnid = $data['upiid'];
        }

        $log = \DB::table('log_webhooks')->insert([
            'txnid'      => $txnid, 
            'product'    => 'Payin', 
            'response'   => json_encode($mydata),
            "created_at" => date("Y-m-d H:i:s")
        ]);

        if(($mydata["event"] == "DynamicUPIQR" || $mydata["event"] == "DynamicUPI") &&  $data['TxnStatus'] == "SUCCESS"){
            $payinReport = Qrreport::where("txnid", $txnid)->where("status", "pending")->where("product", "qrcode")->first();

            if($payinReport){
                if($data["PayerAmount"] > 0 && $data["PayerAmount"] <= 199){
                    $provider = Provider::where('recharge1', 'qrcollection1')->first();
                }elseif($data["PayerAmount"] > 199 && $data["PayerAmount"] <= 499){
                    $provider = Provider::where('recharge1', 'qrcollection2')->first();
                }elseif($data["PayerAmount"] > 499 && $data["PayerAmount"] <= 999){
                    $provider = Provider::where('recharge1', 'qrcollection3')->first();
                }else{
                    $provider = Provider::where('recharge1', 'qrcollection4')->first();
                }

                $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $payinReport->user_id)->first();
                if($serviceApi  && $payinReport->remark != "SELF"){
                    $api = Api::find($serviceApi->api_id);
                }else{
                    $api = Api::find($provider->api_id);
                }

                if($api->code == "collect"){
                    $userid = $this->admin->id;
                }else{
                    $userid = $payinReport->user_id;
                }
                
                $data['charge'] = \App\Helpers\Permission::getCommission($data['PayerAmount'], $payinReport->user->scheme_id, $provider->id, $payinReport->user->role->slug);
                $data['gst']    = ($data['charge'] * 18)/100;

                $insert = [
                    'number'  => $data['PayerMobile'],
                    'option1' => $data['PayerName'],
                    'mobile'  => $payinReport->user->mobile,
                    'provider_id' => $provider->id,
                    'api_id'  => $provider->api->id,
                    'amount'  => $data['PayerAmount'],
                    'charge'  => $data['charge'],
                    'gst'     => $data['gst'],
                    'txnid'   => $payinReport->txnid,
                    'apitxnid'   => $payinReport->apitxnid,
                    'payid'   => $data['WalletTransactionId'],
                    'refno'   => $data['BankRRN'],
                    'status'  => 'success',
                    'transfer_mode' => 'callback',
                    'user_id' => $userid,
                    'credit_by'   => $payinReport->user_id,
                    'rtype'       => 'main',
                    'create_time' => $data['BankRRN'],
                    'trans_type'  => "credit",
                    'product'     => "payin"
                ];

                if($payinReport->remark == "SELF"){
                    $wallet = "qrwallet";
                    $table  = "qrreports";
                    $reportTable = Qrreport::query();
                }else{
                    $wallet = "collectionwallet";
                    $table  = "collectionreports";
                    $reportTable = Collectionreport::query();
                }

                try {
                    $report = \DB::transaction(function () use($insert, $api, $wallet, $table, $reportTable, $payinReport){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        User::where('id', $insert['user_id'])->increment($wallet, $insert["amount"]  - ($insert["charge"] + $insert["gst"]));
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        if($api->code != "collect"){
                            \DB::table("qrreports")->where('id', $payinReport->id)->where("product", "qrcode")->update([
                                "status"  => "success",
                                "refno"   => $insert["refno"]
                            ]);
                        }
                        return $reportTable->create($insert);
                    });

                    if($api->code != "collect"){
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

                    if($api->code != "collect"){
                        $webhook_payload["amount"] = $data['PayerAmount'];
                        $webhook_payload["status"] = "success";
                        $webhook_payload["statuscode"] = "TXN";
                        $webhook_payload["txnid"]  = $payinReport->txnid;
                        $webhook_payload["apitxnid"]  = $payinReport->apitxnid;
                        $webhook_payload["utr"]    = $insert["refno"];
                        $webhook_payload["payment_mode"] = $insert["option1"];
                        $webhook_payload["payid"]  = $payinReport->id;
                        $webhook_payload["message"]= $insert["option1"];
                        $response = \App\Helpers\Permission::curl($payinReport->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");

                        \DB::table('log_webhooks')->where('txnid', $txnid)->update([
                            'url' => $payinReport->remark."?".http_build_query($webhook_payload), 
                            'callbackresponse' => json_encode($response)
                        ]);
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
    }
    
    public function edhaapay(Request $post)
    {
        $mydata = json_decode(json_encode($post->all()), true);
        $data   = $mydata["txnDetails"];
        $txnid  = $data['merchantTxnId'];
        
        $log = \DB::table('log_webhooks')->insert([
            'txnid'      => $txnid, 
            'product'    => 'edhaapay', 
            'response'   => json_encode($mydata),
            "created_at" => date("Y-m-d H:i:s")
        ]);

        if($mydata["txnStatus"] == "SUCCESS"){
            $payinReport = Qrreport::where("txnid", $txnid)->where("status", "pending")->where("product", "qrcode")->first();

            if($payinReport){
                if($data["amount"] > 0 && $data["amount"] <= 199){
                    $provider = Provider::where('recharge1', 'qrcollection1')->first();
                }elseif($data["amount"] > 199 && $data["amount"] <= 499){
                    $provider = Provider::where('recharge1', 'qrcollection2')->first();
                }elseif($data["amount"] > 499 && $data["amount"] <= 999){
                    $provider = Provider::where('recharge1', 'qrcollection3')->first();
                }else{
                    $provider = Provider::where('recharge1', 'qrcollection4')->first();
                }

                $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $payinReport->user_id)->first();
                if($serviceApi  && $payinReport->remark != "SELF"){
                    $api = Api::find($serviceApi->api_id);
                }else{
                    $api = Api::find($provider->api_id);
                }

                if($api->code == "collect"){
                    $userid = $this->admin->id;
                }else{
                    $userid = $payinReport->user_id;
                }
                
                $data['charge'] = \App\Helpers\Permission::getCommission($data["amount"], $payinReport->user->scheme_id, $provider->id, $payinReport->user->role->slug);
                $data['gst']    = ($data['charge'] * 18)/100;

                $insert = [
                    'number'  => $data['bankReferenceId'],
                    'mobile'  => $payinReport->user->mobile,
                    'provider_id' => $provider->id,
                    'api_id'  => $provider->api->id,
                    'amount'  => $data["amount"],
                    'charge'  => $data['charge'],
                    'gst'     => $data['gst'],
                    'txnid'   => $payinReport->txnid,
                    'apitxnid'   => $payinReport->apitxnid,
                    'payid'   => $data['bankReferenceId'],
                    'refno'   => $data['utrNo'],
                    'status'  => 'success',
                    'transfer_mode' => 'callback',
                    'user_id' => $userid,
                    'credit_by'   => $payinReport->user_id,
                    'rtype'       => 'main',
                    'create_time' => $data['utrNo'],
                    'trans_type'  => "credit",
                    'product'     => "payin"
                ];

                if($payinReport->remark == "SELF"){
                    $wallet = "qrwallet";
                    $table  = "qrreports";
                    $reportTable = Qrreport::query();
                }else{
                    $wallet = "collectionwallet";
                    $table  = "collectionreports";
                    $reportTable = Collectionreport::query();
                }

                try {
                    $report = \DB::transaction(function () use($insert, $api, $wallet, $table, $reportTable, $payinReport){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        User::where('id', $insert['user_id'])->increment($wallet, $insert["amount"]  - ($insert["charge"] + $insert["gst"]));
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        if($api->code != "collect"){
                            \DB::table("qrreports")->where('id', $payinReport->id)->where("product", "qrcode")->update([
                                "status"  => "success",
                                "refno"   => $insert["refno"]
                            ]);
                        }
                        return $reportTable->create($insert);
                    });

                    if($api->code != "collect"){
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

                    if($api->code != "collect"){
                        $webhook_payload["amount"] = $data["amount"];
                        $webhook_payload["status"] = "success";
                        $webhook_payload["statuscode"] = "TXN";
                        $webhook_payload["txnid"]  = $payinReport->txnid;
                        $webhook_payload["apitxnid"]  = $payinReport->apitxnid;
                        $webhook_payload["utr"]    = $insert["refno"];
                        $webhook_payload["payment_mode"] = $insert["option1"];
                        $webhook_payload["payid"]  = $payinReport->id;
                        $webhook_payload["message"]= $insert["option1"];
                        $response = \App\Helpers\Permission::curl($payinReport->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");

                        \DB::table('log_webhooks')->where('txnid', $txnid)->update([
                            'url' => $payinReport->remark."?".http_build_query($webhook_payload), 
                            'callbackresponse' => json_encode($response)
                        ]);
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
    }

    public function unpe(Request $post)
    {
        $log = \DB::table('log_webhooks')->insert([
            'txnid'      => $post->txnid, 
            'product'    => 'Payin', 
            'response'   => json_encode($post->all()),
            "created_at" => date("Y-m-d H:i:s")
        ]);
            
        if($post->status == "success"){
            $payinReport = Qrreport::where("txnid", $post->txnid)->where("status", "pending")->where("product", "qrcode")->first();

            if($payinReport){
                if($post->amount > 0 && $post->amount <= 199){
                    $provider = Provider::where('recharge1', 'qrcollection1')->first();
                }elseif($post->amount > 199 && $post->amount <= 499){
                    $provider = Provider::where('recharge1', 'qrcollection2')->first();
                }elseif($post->amount > 499 && $post->amount <= 999){
                    $provider = Provider::where('recharge1', 'qrcollection3')->first();
                }else{
                    $provider = Provider::where('recharge1', 'qrcollection4')->first();
                }

                $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $payinReport->user_id)->first();
                if($serviceApi  && $payinReport->remark != "SELF"){
                    $api = Api::find($serviceApi->api_id);
                }else{
                    $api = Api::find($provider->api_id);
                }

                if($api->code == "collect"){
                    $userid = $this->admin->id;
                }else{
                    $userid = $payinReport->user_id;
                }
                
                $post['charge'] = \App\Helpers\Permission::getCommission($post->amount, $payinReport->user->scheme_id, $provider->id, $payinReport->user->role->slug);
                $post['gst']    = ($post->charge * 18)/100;

                $insert = [
                    'number'  => $post->payid,
                    'option1' => $post->payment_mode,
                    'mobile'  => $payinReport->user->mobile,
                    'provider_id' => $provider->id,
                    'api_id'  => $provider->api->id,
                    'amount'  => $post->amount,
                    'charge'  => $post->charge,
                    'gst'     => $post->gst,
                    'txnid'   => $payinReport->txnid,
                    'apitxnid'   => $payinReport->apitxnid,
                    'payid'   => $post->txnid,
                    'refno'   => $post->utr,
                    'status'  => 'success',
                    'transfer_mode' => 'callback',
                    'user_id' => $userid,
                    'credit_by'   => $payinReport->user_id,
                    'rtype'       => 'main',
                    'create_time' => $post->utr,
                    'trans_type'  => "credit",
                    'product'     => "payin"
                ];

                if($payinReport->remark == "SELF"){
                    $wallet = "qrwallet";
                    $table  = "qrreports";
                    $reportTable = Qrreport::query();
                }else{
                    $wallet = "collectionwallet";
                    $table  = "collectionreports";
                    $reportTable = Collectionreport::query();
                }

                try {
                    $report = \DB::transaction(function () use($insert, $api, $wallet, $table, $reportTable, $payinReport){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        User::where('id', $insert['user_id'])->increment($wallet, $insert["amount"]  - ($insert["charge"] + $insert["gst"]));
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        if($api->code != "collect"){
                            \DB::table("qrreports")->where('id', $payinReport->id)->where("product", "qrcode")->update([
                                "status"  => "success",
                                "refno"   => $insert["refno"]
                            ]);
                        }
                        return $reportTable->create($insert);
                    });

                    if($api->code != "collect"){
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

                    if($api->code != "collect"){
                        $webhook_payload["amount"] = $post->amount;
                        $webhook_payload["status"] = "success";
                        $webhook_payload["statuscode"] = "TXN";
                        $webhook_payload["txnid"]  = $payinReport->txnid;
                        $webhook_payload["apitxnid"]  = $payinReport->apitxnid;
                        $webhook_payload["utr"]    = $post->utr;
                        $webhook_payload["payment_mode"] = $post->payid;
                        $webhook_payload["payid"]  = $payinReport->id;
                        $webhook_payload["message"]= $post->payment_mode;
                        $response = \App\Helpers\Permission::curl($payinReport->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");

                        \DB::table('log_webhooks')->where('txnid', $post->txnid)->update([
                            'url' => $payinReport->remark."?".http_build_query($webhook_payload), 
                            'callbackresponse' => json_encode($response)
                        ]);
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
    }

    public function walletpay(Request $post)
    {
        if($post->status == "SUCCESS"){
            $log = \DB::table('log_webhooks')->insert([
                'txnid'      => $post->txnId, 
                'product'    => 'Walletpay', 
                'response'   => json_encode($post->all()),
                "created_at" => date("Y-m-d H:i:s")
            ]);
            
            $payinReport = Qrreport::where("txnid", $post->txnId)->where("status", "pending")->where("product", "qrcode")->first();

            if($payinReport){
                if($post->amount > 0 && $post->amount <= 199){
                    $provider = Provider::where('recharge1', 'qrcollection1')->first();
                }elseif($post->amount > 199 && $post->amount <= 499){
                    $provider = Provider::where('recharge1', 'qrcollection2')->first();
                }elseif($post->amount > 499 && $post->amount <= 999){
                    $provider = Provider::where('recharge1', 'qrcollection3')->first();
                }else{
                    $provider = Provider::where('recharge1', 'qrcollection4')->first();
                }

                $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $payinReport->user_id)->first();
                if($serviceApi  && $payinReport->remark != "SELF"){
                    $api = Api::find($serviceApi->api_id);
                }else{
                    $api = Api::find($provider->api_id);
                }

                if($api->code == "collect"){
                    $userid = $this->admin->id;
                }else{
                    $userid = $payinReport->user_id;
                }
                
                $post['charge'] = \App\Helpers\Permission::getCommission($post->amount, $payinReport->user->scheme_id, $provider->id, $payinReport->user->role->slug);
                $post['gst']    = ($post->charge * 18)/100;

                $insert = [
                    'number'  => $post->payid,
                    'option1' => $post->payment_mode,
                    'mobile'  => $payinReport->user->mobile,
                    'provider_id' => $provider->id,
                    'api_id'  => $provider->api->id,
                    'amount'  => $post->amount,
                    'charge'  => $post->charge,
                    'gst'     => $post->gst,
                    'txnid'   => $payinReport->txnid,
                    'apitxnid'   => $payinReport->apitxnid,
                    'payid'   => $post->orderId,
                    'refno'   => $post->utr,
                    'option1' => $post->name,
                    'status'  => 'success',
                    'transfer_mode' => 'callback',
                    'user_id' => $userid,
                    'credit_by'   => $payinReport->user_id,
                    'rtype'       => 'main',
                    'create_time' => $post->utr,
                    'trans_type'  => "credit",
                    'product'     => "payin"
                ];

                if($payinReport->remark == "SELF"){
                    $wallet = "qrwallet";
                    $table  = "qrreports";
                    $reportTable = Qrreport::query();
                }else{
                    $wallet = "collectionwallet";
                    $table  = "collectionreports";
                    $reportTable = Collectionreport::query();
                }

                try {
                    $report = \DB::transaction(function () use($insert, $api, $wallet, $table, $reportTable, $payinReport){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        User::where('id', $insert['user_id'])->increment($wallet, $insert["amount"]  - ($insert["charge"] + $insert["gst"]));
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        if($api->code != "collect"){
                            \DB::table("qrreports")->where('id', $payinReport->id)->where("product", "qrcode")->update([
                                "status"  => "success",
                                "refno"   => $insert["refno"]
                            ]);
                        }
                        return $reportTable->create($insert);
                    });

                    if($api->code != "collect"){
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

                    if($api->code != "collect"){
                        $webhook_payload["amount"] = $post->amount;
                        $webhook_payload["status"] = "success";
                        $webhook_payload["statuscode"] = "TXN";
                        $webhook_payload["txnid"]  = $payinReport->txnid;
                        $webhook_payload["apitxnid"]  = $payinReport->apitxnid;
                        $webhook_payload["utr"]    = $post->utr;
                        $webhook_payload["payment_mode"] = $post->orderId;
                        $webhook_payload["payid"]  = $payinReport->id;
                        $webhook_payload["message"]= $post->name;
                        $response = \App\Helpers\Permission::curl($payinReport->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");

                        \DB::table('log_webhooks')->where('txnid', $post->client_txn_id)->update([
                            'url' => $payinReport->remark."?".http_build_query($webhook_payload), 
                            'callbackresponse' => json_encode($response)
                        ]);
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
    }

    public function ekopayin(Request $post)
    {
        if($post->tx_status == 0){
            $log = \DB::table('log_webhooks')->insert([
                'txnid'      => $post->client_ref_id, 
                'product'    => 'Ekopayin', 
                'response'   => json_encode($post->all()),
                "created_at" => date("Y-m-d H:i:s")
            ]);
            
            $payinReport = Qrreport::where("txnid", $post->client_ref_id)->where("status", "pending")->where("product", "qrcode")->first();

            if($payinReport){
                if($post->amount > 0 && $post->amount <= 199){
                    $provider = Provider::where('recharge1', 'qrcollection1')->first();
                }elseif($post->amount > 199 && $post->amount <= 499){
                    $provider = Provider::where('recharge1', 'qrcollection2')->first();
                }elseif($post->amount > 499 && $post->amount <= 999){
                    $provider = Provider::where('recharge1', 'qrcollection3')->first();
                }else{
                    $provider = Provider::where('recharge1', 'qrcollection4')->first();
                }

                $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $payinReport->user_id)->first();
                if($serviceApi  && $payinReport->remark != "SELF"){
                    $api = Api::find($serviceApi->api_id);
                }else{
                    $api = Api::find($provider->api_id);
                }

                if($api->code == "collect"){
                    $userid = $this->admin->id;
                }else{
                    $userid = $payinReport->user_id;
                }
                
                $post['charge'] = \App\Helpers\Permission::getCommission($post->amount, $payinReport->user->scheme_id, $provider->id, $payinReport->user->role->slug);
                $post['gst']    = ($post->charge * 18)/100;

                $insert = [
                    'number'  => $post->payid,
                    'option1' => $post->payment_mode,
                    'mobile'  => $payinReport->user->mobile,
                    'provider_id' => $provider->id,
                    'api_id'  => $provider->api->id,
                    'amount'  => $post->amount,
                    'charge'  => $post->charge,
                    'gst'     => $post->gst,
                    'txnid'   => $payinReport->txnid,
                    'apitxnid'   => $payinReport->apitxnid,
                    'payid'   => $post->tid,
                    'refno'   => $post->bank_ref_num,
                    'option1' => $post->sender_name,
                    'status'  => 'success',
                    'transfer_mode' => 'callback',
                    'user_id' => $userid,
                    'credit_by'   => $payinReport->user_id,
                    'rtype'       => 'main',
                    'create_time' => $post->bank_ref_num,
                    'trans_type'  => "credit",
                    'product'     => "payin"
                ];

                if($payinReport->remark == "SELF"){
                    $wallet = "qrwallet";
                    $table  = "qrreports";
                    $reportTable = Qrreport::query();
                }else{
                    $wallet = "collectionwallet";
                    $table  = "collectionreports";
                    $reportTable = Collectionreport::query();
                }

                try {
                    $report = \DB::transaction(function () use($insert, $api, $wallet, $table, $reportTable, $payinReport){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        User::where('id', $insert['user_id'])->increment($wallet, $insert["amount"]  - ($insert["charge"] + $insert["gst"]));
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        if($api->code != "collect"){
                            \DB::table("qrreports")->where('id', $payinReport->id)->where("product", "qrcode")->update([
                                "status"  => "success",
                                "refno"   => $insert["refno"]
                            ]);
                        }
                        return $reportTable->create($insert);
                    });

                    if($api->code != "collect"){
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

                    if($api->code != "collect"){
                        $webhook_payload["amount"] = $post->amount;
                        $webhook_payload["status"] = "success";
                        $webhook_payload["statuscode"] = "TXN";
                        $webhook_payload["txnid"]  = $payinReport->txnid;
                        $webhook_payload["apitxnid"]  = $payinReport->apitxnid;
                        $webhook_payload["utr"]    = $post->bank_ref_num;
                        $webhook_payload["payment_mode"] = $post->tid;
                        $webhook_payload["payid"]  = $payinReport->id;
                        $webhook_payload["message"]= $post->sender_name;
                        $response = \App\Helpers\Permission::curl($payinReport->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");

                        \DB::table('log_webhooks')->where('txnid', $post->client_ref_id)->update([
                            'url' => $payinReport->remark."?".http_build_query($webhook_payload), 
                            'callbackresponse' => json_encode($response)
                        ]);
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
    }

    public function quntusEcollect(Request $post, $type)
    {
        $log = \DB::table('log_webhooks')->insert([
            'txnid'      => date("ymdhis"), 
            'product'    => 'quntusEcollect-'.$type, 
            'response'   => json_encode($post->all()),
            "created_at" => date("Y-m-d H:i:s")
        ]);

        if($type == "verify"){
            $data = json_decode(json_encode($post->all()), true);
            $data = $data["validate"];

            $account = \DB::table("virtual_accounts")->where("account", $data["bene_account_no"])->first();
            if(!$account){
                return response([
                    "validateResponse" => [
                        "decision" => "reject",
                        "reject_reason" => "Account Not Found"
                    ]
                ]);
            }

            $report = \DB::table("reports")->where("refno", $data["transfer_unique_no"])->first();

            if($report){
                return response([
                    "validateResponse" => [
                        "decision" => "reject",
                        "reject_reason" => "Account Not Found"
                    ]
                ]);
            }

            return response([
                "validateResponse" => [
                    "decision" => "pass"
                ]
            ]);
        }else{
            $data = json_decode(json_encode($post->all()), true);
            $data = $data["notify"];

            if($data["status"] == "CREDITED"){
                $account = \DB::table("virtual_accounts")->where("account", $data["bene_account_no"])->first();
                if(!$account){
                    return response([
                        "notifyResult" => [
                            "result" => "retry"
                        ]
                    ]);
                }

                $report = \DB::table("reports")->where("refno", $data["transfer_unique_no"])->first();

                if($report){
                    return response([
                        "notifyResult" => [
                            "result" => "retry"
                        ]
                    ]);
                }

                if($post->amount > 0 && $post->amount <= 500){
                    $provider = Provider::where('recharge1', 'virtual1')->first();
                }else{
                    $provider = Provider::where('recharge1', 'virtual2')->first();
                }

                $api = Api::find($provider->api_id);
                $userid = $account->user_id;
                $user = User::where("id", $userid)->first();
                
                $post['charge'] = \App\Helpers\Permission::getCommission($data["transfer_amt"], $user->scheme_id, $provider->id, "apiuser");
                $post['gst']    = ($post->charge * 18)/100;

                $insert = [
                    'number'  => $data["rmtr_account_no"],
                    'option1' => $data["rmtr_full_name"],
                    'option2' => $data["customer_code"],
                    'option3' => $data["bene_account_no"],
                    'option4' => $data["bene_full_name"],
                    'option5' => $data["bene_account_ifsc"],
                    'option6' => $data["transfer_type"],
                    'option7' => $data["credit_acct_no"],
                    'mobile'  => $user->mobile,
                    'provider_id' => $provider->id,
                    'api_id'  => $provider->api->id,
                    'amount'  => $data["transfer_amt"],
                    'charge'  => $post->charge,
                    'gst'     => $post->gst,
                    'txnid'   => $data["transfer_unique_no"],
                    'payid'   => $data["transfer_unique_no"],
                    'refno'   => $data["transfer_unique_no"],
                    'status'  => 'success',
                    'user_id' => $userid,
                    'credit_by'   => $userid,
                    'rtype'       => 'main',
                    'create_time' => $data["transfer_unique_no"],
                    'trans_type'  => "credit",
                    'product'     => "collect"
                ];

                $wallet = "mainwallet";
                $table  = "reports";
                $reportTable = Report::query();

                try {
                    $report = \DB::transaction(function () use($insert, $api, $wallet, $table, $reportTable){
                        $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        User::where('id', $insert['user_id'])->increment($wallet, $insert["amount"]  - ($insert["charge"] + $insert["gst"]));
                        $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], $wallet);
                        return $reportTable->create($insert);
                    });

                    return response([
                        "notifyResult" => [
                            "result" => "ok"
                        ]
                    ]);
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
    }
}