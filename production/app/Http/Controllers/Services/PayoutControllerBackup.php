<?php

namespace App\Http\Controllers\Services;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Report;
use App\Models\User;
use App\Models\Api;
use Carbon\Carbon;
use Illuminate\Support\Str;

class PayoutControllerBackup extends Controller
{
    public function create(Request $post)
    { 
        if (!\App\Helpers\Permission::can('payout_service', $post->user_id)) {
            return response()->json(['statuscode' => "ERR", "message" => "Permission Not Allowed"]);
        }

        $rules = array(
            'mode'     => 'required',
            'name'     => 'required',
            'account'  => 'required',
            'bank'     => 'required',
            'ifsc'     => 'required',
            'mobile'   => 'required',
            'amount'   => 'required',
            'webhook'  => 'required',
            'latitude' => 'required',
            'longitude'=> 'required',
            'apitxnid' => "required|unique:reports,apitxnid"
        );

        $validate = \App\Helpers\Permission::FormValidator($rules, $post->all());
        if($validate != "no"){
            return $validate;
        }

        if($post->mode == "IMPS"){
            $post["paymode"] = 5;
            if($post->amount > 0 && $post->amount <= 499){
                $provider = Provider::where('recharge1', 'payout1')->first();
            }elseif($post->amount > 499 && $post->amount <= 999){
                $provider = Provider::where('recharge1', 'payout2')->first();
            }elseif($post->amount > 999 && $post->amount <= 2000){
                $provider = Provider::where('recharge1', 'payout5')->first();
            }elseif($post->amount > 2001 && $post->amount <= 25000){
                $provider = Provider::where('recharge1', 'payout3')->first();
            }else{
                $provider = Provider::where('recharge1', 'payout4')->first();
            }
        }elseif($post->mode == "NEFT"){
            $post["paymode"] = 4;
            $provider = Provider::where('recharge1', 'payoutneft')->first();
        }elseif($post->mode == "RTGS"){
            $post["paymode"] = 13;
            $provider = Provider::where('recharge1', 'payoutrtgs')->first();
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

        $user = User::find($post->user_id);
        if($user->status != "active"){
            return response()->json(['statuscode' => "ERR", "message" => "Your account has been blocked."]);
        }

        $post['charge'] = \App\Helpers\Permission::getCommission($post->amount, $user->scheme_id, $provider->id, "apiuser");

        if($post->charge == 0){
            return response()->json(['statuscode' => "ERR", "message" => "Contact Administrator, Charges Not Set"]);
        }

        if(\App\Helpers\Permission::getAccBalance($user->id, "mainwallet") - \App\Helpers\Permission::getAccBalance($user->id, "lockedwallet") < $post->amount + $post->charge){
            return response()->json(['statuscode' => "ERR", "message" => 'Insufficient Wallet Balance']);
        }

        $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $user->id)->first();
        if($serviceApi && $post->webhook != "SELF"){
            $api = Api::find($serviceApi->api_id);
        }else{
            $api = Api::find($provider->api_id);
        }

        switch ($api->code) {
            case "pinwalletpayout" : 
                do {
                    $post['txnid'] = rand(111111111111, 999999999999);
                } while (Report::where("txnid", "=", $post->txnid)->first() instanceof Report);
                break;

            case "quintasapyout" : 
                do {
                    $date = substr(date("Y"), -1).(date("z")+1).date('Hi');
                    
                    $post['txnid'] = strtoupper(Str::random((35 - strlen($date)))).$date;
                } while (Report::where("txnid", "=", $post->txnid)->first() instanceof Report);
                break;

            default : 
                do {
                    $post['txnid'] = "PEUNIPAYAPI".rand(1111111, 9999999);
                } while (Report::where("txnid", "=", $post->txnid)->first() instanceof Report);
                break;
        }

        $post['charge'] = \App\Helpers\Permission::getCommission($post->amount, $user->scheme_id, $provider->id, "apiuser");
        $post['gst']    = ($post->charge * $provider->api->gst)/100;
        $post['debitAmount'] = $post->amount + ($post->charge + $post->gst);

        $insert = [
            'number'     => $post->account,
            'mobile'     => $user->mobile,
            'provider_id'=> $provider->id,
            'api_id'     => $api->id,
            'amount'     => $post->amount,
            'charge'     => $post->charge,
            'gst'        => $post->gst,
            'txnid'      => $post->txnid,
            'apitxnid'   => $post->apitxnid,
            'option1'    => $post->name,
            'option2'    => $post->bank,
            'option3'    => $post->ifsc,
            'option4'    => $post->mode,
            'remark'     => $post->webhook,
            'status'     => 'accept',
            'user_id'    => $user->id,
            'credit_by'  => $user->id,
            'rtype'      => 'main',
            'via'        => "api",
            'trans_type' => 'debit',
            'product'    => 'payout',
            'create_time'=> $user->id.date('ymdhis'),
            "option7" => $post->ip()
        ];

        try {
            $report = \DB::transaction(function () use($insert, $post) {

                $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet");
                User::where('id', $insert['user_id'])->decrement("mainwallet", $post->debitAmount);
                $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet");

                return Report::create($insert);
            });
        } catch (\Exception $e) {
            \DB::table('log_500')->insert([
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'log'  => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $report = false;
        }

        if (!$report){
            return response()->json(['statuscode' => "ERR", "message" => 'Transaction Failed, please try again.']);
        }

        $method = "POST";
        switch ($api->code) {
            case 'indicpaypayout':
                $url = "https://indicpay.in/api/encryption";
                $parameter = [
                    "token"   => $api->username,
                    "mid"     => $api->password,
                    "txnid"   => $post->txnid,
                    "account" => $post->account,
                    "mode"    => $post->mode,
                    "amount"  => $post->amount,
                    "ifsc"    => strtoupper($post->ifsc),
                    "name"    => $post->name
                ];

                $iv = "1234567890987654";
                $body = [
                    "base64encodedata" => base64_encode(json_encode($parameter)),
                    "mid" => $api->password,
                    "iv"  => $iv
                ];

                $header = array(
                    'Content-Type: application/json'
                );

                $result   = \App\Helpers\Permission::curl($url, "POST", json_encode($body), $header, "no", "Bene", $post->account);
                if($result['response'] == ""){
                    Report::where('id', $report->id)->update([
                        'status' => "failed", 
                        'refno'  => "failed"
                    ]);

                    User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                    return response()->json(['statuscode' => "TXF", "message" => "Transaction Failed"]);
                }

                $url  = "https://indicpay.in/api/payout/dopayout";
                $hash = hash('sha512', $api->username.",".$api->password.",".$post->txnid);
                $body = [
                    'body' => $result['response'],
                    'hash' => $hash
                ];

                $header = array(
                    'Iv: '.$iv,
                    'Token: '.$api->username,
                    'Mid: '.$api->password,
                    'Content-Type: application/json'
                );

                $query = json_encode($body);
                break;

            case 'atmoondpspayout':
                $url = "https://api.haodapayments.com/api/v3/bank/payout";
                $header = array(
                    "x-client-id: ".$api->username,
                    "x-client-secret: ".$api->password,
                    "Content-Type: application/json"
                );

                $parameters = [
                    "narration"      => $post->txnid,
                    "requesttype"    => $post->mode,
                    "amount"         => (float)$post->amount,
                    "account_number" => $post->account,
                    "confirm_acc_number" => $post->account,
                    "account_ifsc" => strtoupper($post->ifsc),
                    "beneficiary_name"   => $post->name,
                    "bankname"     => $post->bank
                ];
                $query  = json_encode($parameters);
                break;
            
            case 'quintasapyout':
                $url = $api->url."/payout/domesticPayments";
                $header = array(
                    "partnerId: ".$api->username,
                    "consumersecret: ".$api->password,
                    "consumerkey: ".$api->optional1,
                    "Content-Type: application/json"
                );
                
                $parameters = [
                    "reqId"  => $post->txnid,
                    "name"   => "YPAY",
                    "sub_service_name" => "IMPS",
                    "amount" => (float)$post->amount,
                    "creditorAccountNo" => $post->account,
                    "creditorIFSC"   => strtoupper($post->ifsc),
                    "creditorName"   => $post->name,
                    "creditorEmail"  => $user->email,
                    "creditorMobile" => $post->mobile,
                    "paymentType"    => "IMPS",
                    "instructionIdentification" => $post->txnid,
                    "address"        => [
                        "address_1"  => $user->address,
                        "address_2"  => $user->city,
                        "state"      => $user->state,
                        "city"       => $user->city,
                        "pin"        => $user->pincode
                    ]
                ];
                $query  = json_encode($parameters);
                break;
        
            case 'ekopayout-pagepe':
            case 'ekopayout':
                $encodedKey = base64_encode($api->optional1);
                $secret_key_timestamp = "".round(microtime(true) * 1000);
                $signature  = hash_hmac('SHA256', $secret_key_timestamp, $encodedKey, true);
                $secret_key = base64_encode($signature);

                $header = array(
                    "Content-Type: application/x-www-form-urlencoded",
                    "developer_key: ".$api->password,
                    "secret-key: ".$secret_key,
                    "secret-key-timestamp: ".$secret_key_timestamp 
                );
                
                $url = "https://api.eko.in:25002/ekoicici/v1/agent/user_code:".$api->optional2."/settlement";

                $parameter = [
                    'initiator_id'   => $api->username,
                    'recipient_name' => $post->name,
                    'account'        => $post->account,
                    'payment_mode'   => $post->paymode,
                    'ifsc'           => $post->ifsc,
                    'service_code'   => 45,
                    'amount'         => $post->amount,
                    'client_ref_id'  => $post->txnid,
                    'sender_name'    => $user->name,
                ];

                $method = "POST";
                $query  = http_build_query($parameter);
                break;

            case 'walletpayout':
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

                if(!isset($response->payload->token)){
                    User::where('id', $post->user_id)->increment('mainwallet', $post->debitAmount);
                    return response()->json(['statuscode' => "ERR", "message" => 'Transaction Failed, please try again.']);
                }

                $url = "http://103.205.64.251:8080/clickncashapi/rest/auth/transaction/payOut";

                $parameter = [
                    'fundTransferType' => 'IMPS',
                    'beneName'     => $post->name,
                    'beneAccountNo'=> $post->account,
                    'beneBankName' => $post->bank,
                    'beneifsc'     => $post->ifsc,
                    'benePhoneNo'  => $post->mobile,
                    'amount'    => $post->amount,
                    'latlong'   => $post->latitude.",".$post->longitude,
                    'clientReferenceNo' => $post->txnid,
                    'custName'  => $user->name,
                    'custMobNo' => $user->mobile,
                    'pincode'   => $user->pincode,
                    'custIpAddress' => $post->ip(),
                    'paramA' => "",  
                    "paramB" => ""
                ];

                $header = array(
                    "Content-Type: application/json",
                    'Authorization: Bearer '.$response->payload->token
                );

                $method = "POST";
                $query  = json_encode($parameter);
                break;

            case 'iserveupayout':
                $url = $api->url."staging/w1w2-payout/w1/cashtransfer";
                $parameter = [
                    'fundTransferType' => 'IMPS',
                    'beneName'     => $post->name,
                    'beneAccountNo'=> $post->account,
                    'beneBankName' => $post->bank,
                    'beneifsc'     => $post->ifsc,
                    'benePhoneNo'  => (int)$post->mobile,
                    'amount'    => (float)$post->amount,
                    'latlong'   => $post->latitude.",".$post->longitude,
                    'clientReferenceNo' => $post->txnid,
                    'custName'  => $user->name,
                    'custMobNo' => $user->mobile,
                    'pincode'   => (int)$user->pincode,
                    'custIpAddress' => $post->ip(),
                    'paramA' => "",  
                    "paramB" => ""
                ];

                $header = array(
                    "Content-Type: application/json",
                    "client_id: ".$api->username,
                    "client_secret: ".$api->password,
                );

                $method = "POST";
                $query  = json_encode($parameter);
                break;

            case 'unpepayout':
                $url = $api->url."payout/order/create";
                $parameter = [
                    'partner_id' => $api->username,
                    'mode'     => 'IMPS',
                    'name'     => $post->name,
                    'account'  => $post->account,
                    'bank'     => $post->bank,
                    'ifsc'     => $post->ifsc,
                    'mobile'   => $post->mobile,
                    'amount'   => $post->amount,
                    'webhook'  => "https://api.peunique.com/production/api/webhook/unpepayout",
                    'latitude' => $post->latitude,
                    'longitude'=> $post->longitude,
                    'apitxnid' => $post->txnid
                ];

                $header = array(
                    "Cache-Control: no-cache",
                    "Content-Type: application/json",
                    "api-key: ".$api->password
                );

                $method = "POST";
                $data["body"] = \App\Helpers\Permission::unpeencrypt($parameter, $api->optional1, $api->optional2);
                $query  = json_encode($data);
                break;

            case 'frenzopayout':
                $url = "https://api.frenzopay.com/api/v1/payout/beneficiary";
                $parameter = [
                    "account_number" => $post->account,
                    "address" => $user->address,
                    "allowed_payment_types" => [
                        1,3,4
                    ],
                    "email" => $user->email,
                    "ifsc_code" => strtoupper($post->ifsc),
                    "mobile_number" => $post->mobile,
                    "name" => $post->name
                ];

                $queryString = "";
                $timestamp = floor(microtime(true) * 1000);
                $message   = "POST\n/api/v1/payout/beneficiary\n".$queryString."\n".json_encode($parameter)."\n".$timestamp."\n";
                $signature = hash_hmac('sha512', $message, $api->password);
                    
                $header = array(
                    'Content-Type: application/json',
                    'access_key: '.$api->username,
                    'signature: '.$signature,
                    'X-Timestamp: '.$timestamp
                );

                $result   = \App\Helpers\Permission::curl($url, "POST", json_encode($parameter), $header, "yes", "Bene", $post->account);
                $response = json_decode($result['response']);

                if($result['response'] == ""){
                    return response()->json(['statuscode' => "TUP", "message" => "Transaction Under Process"]);
                }

                if((isset($response->duplicate_beneficiary_id)) || (isset($response->status) && $response->status === "Active")){
                    
                    if(isset($response->duplicate_beneficiary_id)){
                        $post["id"] = $response->duplicate_beneficiary_id;
                    }else{
                        $post["id"] = $response->id;
                    }
                    $url  = "https://api.frenzopay.com/api/v1/payout/";
            
                    if($post->mode == "IMPS"){
                        $post["payment_method"] = "3";
                    }elseif($post->mode == "NEFT"){
                        $post["payment_method"] = "1";
                    }elseif($post->mode == "RTGS"){
                        $post["payment_method"] = "4";
                    }
                    $parameters = [
                        "beneficiary_id" => $post->id,
                        "amount"         => $post->amount*100,
                        "payment_method" => $post->payment_method,
                    ];

                    $queryString = "";
                    $timestamp = floor(microtime(true) * 1000);
                    $message   = "POST\n/api/v1/payout/\n".$queryString."\n".json_encode($parameters)."\n".$timestamp."\n";
                    $signature = hash_hmac('sha512', $message, $api->password);

                    $header = array(
                        'Content-Type: application/json',
                        'access_key: '.$api->username,
                        'signature: '.$signature,
                        'X-Timestamp: '.$timestamp,
                    );
                    $query = json_encode($parameters);
                }else{
                    Report::where('id', $report->id)->update([
                        'status' => "failed", 
                        'refno'  => "failed"
                    ]);

                    User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message : "Something went wrong"]);
                }
                break;

            case 'pinwalletpayout':
                // $url = "https://app.pinwallet.in/api/token/create";
                // $parameter = [
                //     "userName" => $api->password,
                //     "password" => $api->username
                // ];
                    
                // $header = array(
                //     'Content-Type: application/json',
                //     'Accept: application/json'
                // );

                // $result   = \App\Helpers\Permission::curl($url, "POST", $query, $header, "no", "payout", $post->txnid);
                // $response = json_decode($result['response']);

                // if($result['response'] == ""){
                //     return response()->json(['statuscode' => "TUP", "message" => "Transaction Under Process"]);
                // }

                // if($response->responseCode === 200){
                    $url  = "https://app.huntood.com/api/payout/v1/dotransaction";
                    $header = array(
                        'Content-Type: application/json',
                        'AuthKey: '.$api->username,
                        'IPAddress: 216.48.180.140'
                    );

                    $parameters = [
                        "BenificiaryName" => $post->name,
                        "Amount"          => (float)$post->amount,
                        "BenificiaryAccount" => $post->account,
                        "BenificiaryIfsc" => $post->ifsc,
                        "Latitude"  => "28.5355",
                        "Longitude" => "77.3910",
                        "TransactionId" => $post->txnid,
                    ];
                    $query = json_encode($parameters);
                // }else{
                //     User::where('id', $post->user_id)->increment('mainwallet', $post->debitAmount);
                //     return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message : "Something went wrong"]);
                // }
                break;

            case 'payout':
                $utrcode = \DB::table("portal_settings")->where("code", "utrcode")->first();
                do {
                    $utr = $utrcode->value.rand(11111111, 99999999);
                } while (Report::where("refno", $utr)->first() instanceof Report);

                Report::where('id', $report->id)->update([
                    'status'  => "success", 
                    'refno'   => $utr,
                    'option7' => "failed"
                ]);
                
                try {
                    \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                } catch (\Exception $e) {}

                return response()->json([
                    'statuscode' => 'TXN', 
                    'message'    => 'Transaction Successfull', 
                    'txnid'      => $post->txnid,
                    'refno'      => $utr
                ]);
                break;

            case "acosapipayout":
                $method = "GET";
                $header = array(
                    "Accept: application/json",
                    "Content-Type: application/json"
                );

                $parameters = array(
                    'Apitoken'    => $api->username,
                    'AccountName' => $post->name,
                    'AccountNo'  => $post->account,
                    'Mobile'   => $post->mobile,
                    'Email'    => $user->email,
                    'IFSC'     => strtoupper($post->ifsc),
                    'ClientId' => $post->txnid,
                    'CustId'   => $user->id,
                    'Amount'   => $post->amount,
                    'Mode'     => $post->mode,
                    'TransactionMode' => "web"
                );

                $query  = http_build_query($parameters);
                $url    = "https://acosapi.com/payout/payouttobank.aspx?".$query;
                $query  = "";
                $header = array();
                break;

            default:
                $url = $api->url."transaction";

                $header = array(
                    "Accept: application/json",
                    "Content-Type: application/json"
                );

                $parameters = array(
                    'token'    => $api->username,
                    'name'     => $post->name,
                    'bank'     => $post->bank,
                    'account'  => $post->account,
                    'mobile'   => $post->mobile,
                    'ifsc'     => strtoupper($post->ifsc),
                    'apitxnid' => $post->txnid,
                    'callback' => $post->webhook,
                    'amount'   => $post->amount
                );
                $query = json_encode($parameters);
                break;
        }
        
        if($this->env_mode() == "server"){
            $result = \App\Helpers\Permission::curl($url, $method, $query, $header, "yes", "payout", $post->txnid);
        }else{
            $result = [
                'error' => true,
                'response' => '' 
            ];
        }

        if($result['error'] || $result['response'] == ''){
            return response()->json([
                'statuscode' => 'TUP', 
                'message'    => 'Transaction Under Process', 
                'bankutr'      => $post->txnid,
                'txnid'      => $post->txnid
            ]);
        }else{
            switch ($api->code) {
                case 'indicpaypayout':
                    $response = json_decode($result['response']);
                    if(isset($response->status) && in_array($response->status, ["failed", "failure"])){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->msg) ? $response->msg : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->msg) ? $response->msg : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->msg) ? $response->msg : "failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->rrn) ? $response->rrn : "",
                            'payid'  => isset($response->txnid) ? $response->txnid : ""
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->rrn) ? $response->rrn : "pending", 
                        ]);
                    }
                    break;

                case 'atmoondpspayout':
                    $response = json_decode($result['response']);
                    if(isset($response->status_code) && $response->status_code != "200"){
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->message) ? $response->message : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);

                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => $response->status,
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->message) ? $response->message : "failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->reference) ? $response->reference : "",
                            'payid'  => isset($response->payout_id) ? $response->payout_id : ""
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->data->referenceNo) ? $response->data->referenceNo : "pending", 
                        ]);
                    }
                    break;

                case 'quintasapyout':
                    $response = json_decode($result['response']);
                    if(isset($response->success) && $response->success == false){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->message) ? $response->message : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => $response->status,
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->message) ? $response->message : "failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->data->referenceNo) ? $response->data->referenceNo : "",
                            'payid'  => isset($response->data->quintus_transaction_id) ? $response->data->quintus_transaction_id : ""
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->data->referenceNo) ? $response->data->referenceNo : "pending", 
                        ]);
                    }
                    break;
                
                case 'ekopayout':
                case 'ekopayout-pagepe':
                    $response = json_decode($result['response']);
                    if(isset($response->status) && $response->status != 0){

                        User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->message) ? $response->message : "failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => $response->message,
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->message) ? $response->message : "failed"
                        ]);
                    }else{
                        if(isset($response->data->tx_status) && !in_array($response->data->tx_status, ["0", "2", "5"])){

                            User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                            Report::where('id', $report->id)->update([
                                'status' => "failed", 
                                'refno'  => isset($response->data->txstatus_desc) ? $response->data->txstatus_desc : "failed",
                                'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                            ]);
                            return response()->json([
                                'statuscode'=> 'TXF', 
                                'message'   => $response->data->txstatus_desc,
                                "apitxnid"  => $post->apitxnid, 
                                "txnid"     => $post->txnid,
                                "bankutr"   => isset($response->data->txstatus_desc) ? $response->data->txstatus_desc : "failed"
                            ]);
                        }elseif(isset($response->data->tx_status) && in_array($response->data->tx_status, ["0"])){
                            Report::where('id', $report->id)->update([
                                'status' => "success", 
                                'refno'  => isset($response->data->bank_ref_num) ? $response->data->bank_ref_num : "pending",
                                'payid'  => isset($response->data->tid) ? $response->data->tid : "pending",
                            ]);

                            try {
                                \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                            } catch (\Exception $e) {
                                \DB::table('log_500')->insert([
                                    'line' => $e->getLine(),
                                    'file' => $e->getFile(),
                                    'log'  => $e->getMessage(),
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }

                            try {
                                $webhook_payload["amount"] = $report->amount;
                                $webhook_payload["status"] = "success";
                                $webhook_payload["statuscode"]= "TXN";
                                $webhook_payload["txnid"]    = $report->txnid;
                                $webhook_payload["apitxnid"] = $report->apitxnid;
                                $webhook_payload["utr"]      = isset($response->data->bank_ref_num) ? $response->data->bank_ref_num : $report->id;
                                $webhook_payload["payment_mode"] = "payout";
                                $webhook_payload["payid"]  = $report->id;
                                $webhook_payload["message"]= "Transaction Successfull";
                                $myresponse = \App\Helpers\Permission::curl($report->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");
            
                                \DB::table('log_webhooks')->insert([
                                    'url' => $report->remark."?".http_build_query($webhook_payload), 
                                    'callbackresponse' => json_encode($myresponse)
                                ]);
                            } catch (\Exception $e) {
                            }

                            return response()->json([
                                'statuscode'=> 'TXN', 
                                'message'   => 'Transaction Successfull',
                                "apitxnid"  => $post->apitxnid, 
                                "txnid"     => $post->txnid, 
                                "bankutr"   => isset($response->data->bank_ref_num) ? $response->data->bank_ref_num : "pending", 
                            ]);
                        }else{
                            Report::where('id', $report->id)->update([
                                'status' => "pending", 
                                'refno'  => isset($response->data->bank_ref_num) ? $response->data->bank_ref_num : "pending",
                                'payid'  => isset($response->data->tid) ? $response->data->tid : "pending",
                            ]);

                            try {
                                \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                            } catch (\Exception $e) {
                                \DB::table('log_500')->insert([
                                    'line' => $e->getLine(),
                                    'file' => $e->getFile(),
                                    'log'  => $e->getMessage(),
                                    'created_at' => date('Y-m-d H:i:s')
                                ]);
                            }

                            return response()->json([
                                'statuscode'=> 'TUP', 
                                'message'   => 'Transaction Under Process',
                                "apitxnid"  => $post->apitxnid, 
                                "txnid"     => $post->txnid, 
                                "bankutr"   => isset($response->data->bank_ref_num) ? $response->data->bank_ref_num : "pending", 
                            ]);
                        }
                    }
                    break;

                case "frenzopayout":
                    $response = json_decode($result['response']);
                    if(isset($response->data->status) && in_array($response->data->status, ["Failed","Reversed","Rejected"])){

                        User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->message) ? $response->message : "failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => $response->message,
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->message) ? $response->message : "failed"
                        ]);
                    }elseif(isset($response->data->status) && in_array($response->data->status, ["Success"])){
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->data->bank_reference_id) ? $response->data->bank_reference_id : "pending",
                            'payid'  => isset($response->data->id) ? $response->data->id : "pending",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->data->bank_reference_id) ? $response->data->bank_reference_id : "pending", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => isset($response->data->bank_reference_id) ? $response->data->bank_reference_id : "pending",
                            'payid'  => isset($response->data->id) ? $response->data->id : "pending",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        return response()->json([
                            'statuscode'=> 'TUP', 
                            'message'   => 'Transaction Under Process',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->data->bank_reference_id) ? $response->data->bank_reference_id : "pending", 
                        ]);
                    }
                    break;

                case "iserveupayout":
                    $response = json_decode($result['response']);
                    if(isset($response->status) && !in_array($response->status, ["FAILED"])){

                        User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => $response->statusDesc,
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => $response->statusDesc,
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->statusDesc) ? $response->statusDesc : "failed"
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => isset($response->rrn) ? $response->rrn : "pending",
                            'payid'  => isset($response->transactionId) ? $response->transactionId : "pending",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        return response()->json([
                            'statuscode'=> 'TUP', 
                            'message'   => 'Transaction Under Process',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->rrn) ? $response->rrn : "pending", 
                        ]);
                    }
                    break;

                case "walletpayout":
                    $response = json_decode($result['response']);
                    if(isset($response->status) && !in_array($response->status, ["FAILED", "ERROR"])){

                        User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->msg) ? $response->msg : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->msg) ? $response->msg : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->msg) ? $response->msg : "failed"
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->rrn) ? $response->rrn : "pending",
                            'payid'  => isset($response->transactionId) ? $response->transactionId : "pending",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->rrn) ? $response->rrn : "pending", 
                        ]);
                    }
                    break;

                case "pinwalletpayout":
                    $response = json_decode($result['response']);
                    if(isset($response->data->status) && $response->data->status == "SUCCESS"){
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->data->rrn) ? $response->data->rrn : "success",
                            'payid'  => isset($response->data->pinwalletTransactionId) ? $response->data->pinwalletTransactionId : "success",
                        ]);
                        
                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => "Transaction Successfull",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->data->rrn) ? $response->data->rrn : "success", 
                        ]);
                    }elseif(isset($response->data->status) && $response->data->status == "FAILURE"){

                        User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->data->message) ? $response->data->message : "failed",
                            'payid'  => isset($response->data->pinwalletTransactionId) ? $response->data->pinwalletTransactionId : "failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->data->message) ? $response->data->message : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->data->rrn) ? $response->data->rrn : "failed"
                        ]);
                    }elseif(isset($response->responseCode) && !in_array($response->responseCode, [200])){

                        User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => $response->message,
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => $response->message,
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->message) ? $response->message : "failed"
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => isset($response->data->rrn) ? $response->data->rrn : "pending",
                            'payid'  => isset($response->data->pinwalletTransactionId) ? $response->data->pinwalletTransactionId : "pending",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        return response()->json([
                            'statuscode'=> 'TUP', 
                            'message'   => 'Transaction Under Process',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->data->rrn) ? $response->data->rrn : "pending", 
                        ]);
                    }
                    break;

                case "acosapi":
                    $response = json_decode($result['response']);
                    if(isset($response->Status) && in_array($response->Status, ["Failure"])){

                        User::where('id', $post->user_id)->increment('mainwallet', $post->debitAmount);
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => $response->Message,
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode' => 'TXF', 
                            'message'    => $response->Message,
                            'bankutr'    => $response->Message,
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid
                        ]);
                    }else{

                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->OperatorRef) ? $response->OperatorRef :$response->Message,
                            'payid'  => isset($response->Id) ? $response->Id : "",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        return response()->json([
                            'statuscode' => 'TXN', 
                            'message'    => 'Transaction Successfull',
                            "apitxnid"   => $post->apitxnid, 
                            "txnid"      => $post->txnid,
                            'bankutr'    => isset($response->Message)? $response->Message : $post->txnid
                        ]);
                    }
                    break;

                case 'unpepayout':
                    $response = json_decode($result['response']);
                    if(isset($response->statuscode) && in_array($response->statuscode, ["TXF","ERR"])){

                        User::where('id', $post->user_id)->increment('mainwallet', $post->debitAmount);
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => $response->message,
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode' => 'TXF', 
                            'message'    => $response->message,
                            'bankutr'    => $response->message,
                            "apitxnid"   => $post->apitxnid, 
                            'txnid'      => $post->txnid
                        ]);
                    }elseif(isset($response->statuscode) && in_array($response->statuscode, ["TXN"])){
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => $response->refno, 
                            'payid'  => $response->txnid
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        return response()->json([
                            'statuscode' => 'TXN', 
                            'message'    => 'Transaction Successfull', 
                            "apitxnid"   => $post->apitxnid, 
                            'txnid'      => $post->txnid,
                            'bankutr'    => isset($response->refno)? $response->refno : $post->txnid
                        ]);
                    }else{
                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode' => 'TUP', 
                            'message'    => 'Transaction Under Process', 
                            "apitxnid"   => $post->apitxnid, 
                            'bankutr' => $post->txnid,
                            'txnid' => $post->txnid
                        ]);
                    }
                    break;

                default:
                    $response = json_decode($result['response']);
                    if(isset($response->statuscode) && in_array($response->statuscode, ["TXF","ERR"])){
                        User::where('id', $post->user_id)->increment('mainwallet', $post->debitAmount);
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => $response->message,
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode' => 'TXF', 
                            'message'    => $response->message,
                            'bankutr'    => $response->message,
                            "apitxnid"   => $post->apitxnid, 
                            'txnid'      => $post->txnid
                        ]);
                    }elseif(isset($response->statuscode) && in_array($response->statuscode, ["TXN"])){
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => $response->bankutr, 
                            'payid'  => $response->txnid
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        return response()->json([
                            'statuscode' => 'TXN', 
                            'message'    => 'Transaction Successfull', 
                            'txnid'      => $post->txnid,
                            "apitxnid"   => $post->apitxnid, 
                            'bankutr'    => isset($response->bankutr)? $response->bankutr : $post->txnid
                        ]);
                    }else{
                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        return response()->json([
                            'statuscode' => 'TUP', 
                            'message'    => 'Transaction Under Process', 
                            'bankutr'    => $post->txnid,
                            "apitxnid"   => $post->apitxnid, 
                            'txnid' => $post->txnid
                        ]);
                    }
                    break;
            }
        }
    }

    public function upipayment(Request $post)
    { 
        if (!\App\Helpers\Permission::can('upi_service', $post->user_id)) {
            return response()->json(['statuscode' => "ERR", "message" => "Permission Not Allowed"]);
        }

        $rules = array(
            'mode'     => 'required',
            'name'     => 'required',
            'vpa'      => 'required',
            'mobile'   => 'required',
            'amount'   => 'required',
            'webhook'  => 'required',
            'latitude' => 'required',
            'longitude'=> 'required',
            'apitxnid' => "required|unique:reports,apitxnid"
        );

        $validate = \App\Helpers\Permission::FormValidator($rules, $post->all());
        if($validate != "no"){
            return $validate;
        }

        $post["paymode"] = 2;
        if($post->amount > 0 && $post->amount <= 499){
            $provider = Provider::where('recharge1', 'upipayout1')->first();
        }elseif($post->amount > 499 && $post->amount <= 999){
            $provider = Provider::where('recharge1', 'upipayout2')->first();
        }elseif($post->amount > 999 && $post->amount <= 25000){
            $provider = Provider::where('recharge1', 'upipayout3')->first();
        }else{
            $provider = Provider::where('recharge1', 'upipayout4')->first();
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

        $user = User::find($post->user_id);
        if($user->status != "active"){
            return response()->json(['statuscode' => "ERR", "message" => "Your account has been blocked."]);
        }

        $post['charge'] = \App\Helpers\Permission::getCommission($post->amount, $user->scheme_id, $provider->id, "apiuser");
        if(\App\Helpers\Permission::getAccBalance($user->id, "mainwallet") - \App\Helpers\Permission::getAccBalance($user->id, "lockedwallet") < $post->amount + $post->charge){
            return response()->json(['statuscode' => "ERR", "message" => 'Insufficient Wallet Balance']);
        }

        $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $user->id)->first();
        if($serviceApi && $post->webhook != "SELF"){
            $api = Api::find($serviceApi->api_id);
        }else{
            $api = Api::find($provider->api_id);
        }

        do {
            $post['txnid'] = "PEUNIPAYAPI".rand(1111111, 9999999);
        } while (Report::where("txnid", "=", $post->txnid)->first() instanceof Report);

        $post['charge'] = \App\Helpers\Permission::getCommission($post->amount, $user->scheme_id, $provider->id, "apiuser");
        $post['gst']    = ($post->charge * $provider->api->gst)/100;
        $post['debitAmount'] = $post->amount + ($post->charge + $post->gst);

        $insert = [
            'number'     => $post->vpa,
            'mobile'     => $user->mobile,
            'provider_id'=> $provider->id,
            'api_id'     => $api->id,
            'amount'     => $post->amount,
            'charge'     => $post->charge,
            'gst'        => $post->gst,
            'txnid'      => $post->txnid,
            'apitxnid'   => $post->apitxnid,
            'option1'    => $post->name,
            'option2'    => $post->bank,
            'option3'    => $post->ifsc,
            'option4'    => $post->mode,
            'remark'     => $post->webhook,
            'status'     => 'accept',
            'user_id'    => $user->id,
            'credit_by'  => $user->id,
            'rtype'      => 'main',
            'via'        => "api",
            'trans_type' => 'debit',
            'product'    => 'upipay',
            'create_time'=> $user->id.date('ymdhis'),
            "option7" => $post->ip()
        ];

        try {
            $report = \DB::transaction(function () use($insert, $post) {

                $insert["balance"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet");
                User::where('id', $insert['user_id'])->decrement("mainwallet", $post->debitAmount);
                $insert["closing"] = \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet");

                return Report::create($insert);
            });
        } catch (\Exception $e) {
            \DB::table('log_500')->insert([
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'log'  => $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
            $report = false;
        }

        if (!$report){
            return response()->json(['statuscode' => "ERR", "message" => 'Transaction Failed, please try again.']);
        }

        $method = "POST";
        switch ($api->code) {
            default:
                $url = "https://api.frenzopay.com/api/v1/payout/beneficiary";
                $parameter = [
                    "vpa" => $post->vpa,
                    "address" => $user->address,
                    "allowed_payment_types" => [
                        2
                    ],
                    "email" => $user->email,
                    "mobile_number" => $post->mobile,
                    "name" => $post->name
                ];

                $queryString = "";
                $timestamp = floor(microtime(true) * 1000);
                $message   = "POST\n/api/v1/payout/beneficiary\n".$queryString."\n".json_encode($parameter)."\n".$timestamp."\n";
                $signature = hash_hmac('sha512', $message, $api->password);
                    
                $header = array(
                    'Content-Type: application/json',
                    'access_key: '.$api->username,
                    'signature: '.$signature,
                    'X-Timestamp: '.$timestamp
                );

                $result   = \App\Helpers\Permission::curl($url, "POST", json_encode($parameter), $header, "yes", "Bene", $post->account);
                $response = json_decode($result['response']);

                if($result['response'] == ""){
                    return response()->json(['statuscode' => "TUP", "message" => "Transaction Under Process"]);
                }

                if((isset($response->duplicate_beneficiary_id)) || (isset($response->status) && $response->status === "Active")){
                    
                    if(isset($response->duplicate_beneficiary_id)){
                        $post["id"] = $response->duplicate_beneficiary_id;
                    }else{
                        $post["id"] = $response->id;
                    }
                    $url  = "https://api.frenzopay.com/api/v1/payout/";
            
                   $post["payment_method"] = "2";
                    $parameters = [
                        "beneficiary_id" => $post->id,
                        "amount"         => $post->amount*100,
                        "payment_method" => $post->payment_method,
                    ];

                    $queryString = "";
                    $timestamp = floor(microtime(true) * 1000);
                    $message   = "POST\n/api/v1/payout/\n".$queryString."\n".json_encode($parameters)."\n".$timestamp."\n";
                    $signature = hash_hmac('sha512', $message, $api->password);

                    $header = array(
                        'Content-Type: application/json',
                        'access_key: '.$api->username,
                        'signature: '.$signature,
                        'X-Timestamp: '.$timestamp,
                    );
                    $query = json_encode($parameters);
                }else{
                    Report::where('id', $report->id)->update([
                        'status' => "failed", 
                        'refno'  => "failed"
                    ]);

                    User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                    return response()->json(['statuscode' => "TXF", "message" => isset($response->message)?$response->message : "Something went wrong"]);
                }
                break;
        }
        
        if($this->env_mode() == "server"){
            $result = \App\Helpers\Permission::curl($url, $method, $query, $header, "yes", "payout", $post->txnid);
        }else{
            $result = [
                'error' => true,
                'response' => '' 
            ];
        }

        if($result['error'] || $result['response'] == ''){
            return response()->json([
                'statuscode' => 'TUP', 
                'message'    => 'Transaction Under Process', 
                'bankutr'      => $post->txnid,
                'txnid'      => $post->txnid
            ]);
        }else{
            switch ($api->code) {
                default:
                    $response = json_decode($result['response']);
                    if(isset($response->data->status) && in_array($response->data->status, ["Failed","Reversed","Rejected"])){

                        User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->acquirer_message) ? $response->acquirer_message : "failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->acquirer_message) ? $response->acquirer_message : "Failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->acquirer_message) ? $response->acquirer_message : "Failed"
                        ]);
                    }elseif(isset($response->data->status) && in_array($response->data->status, ["Success"])){
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->data->bank_reference_id) ? $response->data->bank_reference_id : "pending",
                            'payid'  => isset($response->data->id) ? $response->data->id : "pending",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->data->bank_reference_id) ? $response->data->bank_reference_id : "pending", 
                        ]);
                    }elseif(isset($response->success) && $response->success === false){
                        User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => "failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => "Failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => "Failed"
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => isset($response->data->bank_reference_id) ? $response->data->bank_reference_id : "pending",
                            'payid'  => isset($response->data->id) ? $response->data->id : "pending",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {
                            \DB::table('log_500')->insert([
                                'line' => $e->getLine(),
                                'file' => $e->getFile(),
                                'log'  => $e->getMessage(),
                                'created_at' => date('Y-m-d H:i:s')
                            ]);
                        }

                        return response()->json([
                            'statuscode'=> 'TUP', 
                            'message'   => 'Transaction Under Process',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->data->bank_reference_id) ? $response->data->bank_reference_id : "pending", 
                        ]);
                    }
                    break;
            }
        }
    }

    public function ascoapiPayout(Request $post)
    {
        $log = \DB::table('log_webhooks')->insert([
            'txnid'      => $post->ClientId, 
            'product'    => 'AsocPayout', 
            'response'   => json_encode($post->all()),
            "created_at" => date("Y-m-d H:i:s")
        ]);

        $report = Report::where('txnid', $post->ClientId)->first();

        if($report){
            if(isset($post->Status) && strtolower($post->Status) == "success"){
                $update['status'] = "success";
                $update['refno']  = isset($post->OperatorRef) ? $post->OperatorRef : $report->refno;
            }elseif(isset($post->Status) && strtolower($post->Status) == "failure"){
                $update['status'] = "reversed";
                $update['refno']  = isset($post->OperatorRef) ? $post->OperatorRef : $report->refno;
            }else{
                $update['status'] = "pending";
                $update['refno']  = isset($post->OperatorRef) ? $post->OperatorRef : 'pending';
            }

            if(isset($update['status']) && $update['status'] != "pending"){
                if(in_array($report->status, ['success', 'pending'])){
                    $updates = Report::where('txnid', $post->ClientId)->update($update);
                    
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
                    $response = \App\Helpers\Permission::curl($report->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");

                    \DB::table('log_webhooks')->where('txnid', $post->ClientId)->update([
                        'url' => $report->remark."?".http_build_query($webhook_payload), 
                        'callbackresponse' => json_encode($response)
                    ]);
                }
            }
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

        $upiload = Report::where("apitxnid", $post->apitxnid)->where("user_id", $post->user_id)->first();
        if(!$upiload){
            return response()->json(['statuscode' => "TNF", 'message' => "Transaction Not Found"]);
        }

        switch ($upiload->api->code) {
            case 'apisevapayout':
                $url = $upiload->api->url."query";

                $header = array(
                    "Accept: application/json",
                    "Content-Type: application/json"
                );

                $parameters = array(
                    'token'    => $upiload->api->username,
                    'apitxnid' => $upiload->txnid
                );
                $query = json_encode($parameters);
                $result = \App\Helpers\Permission::curl($url, "POST", $query, $header, "no", "payout", $post->txnid);
                
                $response = json_decode($result['response']);
                if(isset($response->txn_status) && in_array($response->txn_status, ["success"])){
                    Report::where('id', $upiload->id)->update(['refno' => $response->bankutr]);
                }
                break;
        }
        
        $upiload = Report::where("apitxnid", $post->apitxnid)->where("user_id", $post->user_id)->first();
        $callback["amount"] = $upiload->amount;
        $callback["status"] = $upiload->status;
        $callback["statuscode"] = "TXN";
        $callback["txnid"]  = $upiload->txnid;
        $callback["apitxnid"]  = $upiload->apitxnid;
        $callback["utr"]    = $upiload->refno;
        $callback["payid"]  = $upiload->payid;
        $callback["message"]= "Transaction Status Fetched Successfully";
        return response()->json($callback);
    }

    public function frenzopayout(Request $post)
    {
        try {
            $data = json_decode(json_encode($post->all()), true);
            $log = \DB::table('log_webhooks')->insert([
                'txnid'      => $data["data"]["object"]["id"], 
                'product'    => 'frenzopayout', 
                'response'   => json_encode($post->all()),
                "created_at" => date("Y-m-d H:i:s")
            ]);
    
            $report = Report::where('payid', $data["data"]["object"]["id"])->first();
    
            if($report){
                if(isset($data["data"]["object"]["status"]) && strtolower($data["data"]["object"]["status"]) == "success"){
                    $update['status'] = "success";
                    $update['refno']  = isset($data["data"]["object"]["bank_reference_id"]) ? $data["data"]["object"]["bank_reference_id"] : $report->refno;
                }elseif(isset($data["data"]["object"]["status"]) && strtolower($data["data"]["object"]["status"]) == "failure"){
                    $update['status'] = "reversed";
                    $update['refno']  = isset($data["data"]["object"]["bank_reference_id"]) ? $data["data"]["object"]["bank_reference_id"] : $report->refno;
                }else{
                    $update['status'] = "pending";
                    $update['refno']  = isset($data["data"]["object"]["bank_reference_id"]) ? $data["data"]["object"]["bank_reference_id"] : 'pending';
                }
    
                if(isset($update['status']) && $update['status'] != "pending"){
                    if(in_array($report->status, ['success', 'pending', 'accept'])){
                        $updates = Report::where('id', $report->id)->update($update);
                        
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
                        $response = \App\Helpers\Permission::curl($report->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");
    
                        \DB::table('log_webhooks')->where('txnid', $data["data"]["object"]["id"])->update([
                            'url' => $report->remark."?".http_build_query($webhook_payload), 
                            'callbackresponse' => json_encode($response)
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            \DB::table('log_500')->insert([
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'log'  =>  $e->getMessage(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    public function quntuspayout(Request $post)
    {
        try {
            $data = json_decode(json_encode($post->all()), true);
            $log = \DB::table('log_webhooks')->insert([
                'txnid'      => $data["data"]["quintus_transaction_id"], 
                'product'    => 'quntusopayout', 
                'response'   => json_encode($post->all()),
                "created_at" => date("Y-m-d H:i:s")
            ]);
    
            $report = Report::where('payid', $data["data"]["quintus_transaction_id"])->first();
    
            if($report){
                if(isset($data["data"]["status"]) && strtolower($data["data"]["status"]) == "success"){
                    $update['status'] = "success";
                    $update['refno']  = isset($data["data"]["referenceNo"]) ? $data["data"]["referenceNo"] : "NA";
                }elseif(isset($data["data"]["status"]) && strtolower($data["data"]["status"]) == "failure"){
                    $update['status'] = "reversed";
                    $update['refno']  = isset($data["data"]["referenceNo"]) ? $data["data"]["referenceNo"] : "NA";
                    $update['description'] = isset($data["data"]["remark"]) ? $data["data"]["remark"] : "NA";
                }else{
                    $update['status'] = "pending";
                    $update['refno']  = isset($data["data"]["referenceNo"]) ? $data["data"]["referenceNo"] : 'pending';
                }
    
                if(isset($update['status']) && $update['status'] != "pending"){
                    if(in_array($report->status, ['success', 'pending', 'accept'])){
                        $updates = Report::where('id', $report->id)->update($update);
                        
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
                        $response = \App\Helpers\Permission::curl($report->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");
    
                        \DB::table('log_webhooks')->where('txnid', $data["data"]["quintus_transaction_id"])->update([
                            'url' => $report->remark."?".http_build_query($webhook_payload), 
                            'callbackresponse' => json_encode($response)
                        ]);
                    }
                }

                return response()->json(["status" => true, "message" => "ok"]);
            }else{
                return response()->json(["status" => false, "message" => "Not Matched"]);
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
}
