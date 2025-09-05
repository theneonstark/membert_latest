<?php

namespace App\Http\Controllers\Services;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Provider;
use App\Models\Report;
use App\Models\Tempreport;
use App\Models\User;
use App\Models\Api;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Firebase\JWT\JWT;

class PayoutController extends Controller
{
    public function create(Request $post)
    { 
        if ($this->payoutstatus() == "off" && $post->user_id != 69) {
            return response()->json([
                'statuscode' => "ERR",
                "message"    => "Service Under Maintenance"
            ]);
        }

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
            'amount'   => 'required|numeric|min:100|max:50000',
            'webhook'  => 'required',
            'latitude' => 'required',
            'longitude'=> 'required',
            'apitxnid' => "required|unique:reports,apitxnid"
        );

        $validate = \App\Helpers\Permission::FormValidator($rules, $post->all());
        if($validate != "no"){
            return $validate;
        }
        
        $post["ifsc"] = strtoupper($post->ifsc);

        $post["paymode"] = 5;
        if($post->amount > 0 && $post->amount <= 499){
            $provider = Provider::where('recharge1', 'payout1')->first();
        }elseif($post->amount > 499 && $post->amount <= 999){
            $provider = Provider::where('recharge1', 'payout2')->first();
        }elseif($post->amount > 999 && $post->amount <= 1999){
            $provider = Provider::where('recharge1', 'payout5')->first();
        }else{
            $provider = Provider::where('recharge1', 'payout3')->first();
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
            case 'collectswift_payout':
                do {
                    $post['txnid'] = date("ymdhis").rand(1111111, 9999999);
                } while (Report::where("txnid", "=", $post->txnid)->first() instanceof Report);
                break;
                
            case 'zentexpay_payout':
                do {
                    $post['txnid'] = "PHNT".rand(11111111, 99999999);
                } while (Report::where("txnid", "=", $post->txnid)->first() instanceof Report);
                break;
                
            default : 
                do {
                    $post['txnid'] = "PGPETXN".rand(1111111, 9999999);
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
            'option8'    => $post->latitude."/".$post->longitude,
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
            case "apiwala_payout":
                $url = "https://apiwala.in/production/api/payment/order/create";
                $parameter = [
                    'partner_id' => $api->username,
                    'mode'     => 'IMPS',
                    'name'     => $post->name,
                    'account'  => $post->account,
                    'bank'     => $post->bank,
                    'ifsc'     => $post->ifsc,
                    'mobile'   => $post->mobile,
                    'amount'   => $post->amount,
                    'webhook'  => "https://member.pehunt.in/production/api/webhook/apiwala/payout",
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
                $query  = json_encode($parameter);
                break;
                
            case 'zentexpay_payout':
                $header = [
                    "Content-Type: application/json",
                    "Authorization: eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6MjAsInVzZXJfdHlwZSI6InBheWluX3BheW91dCIsImlhdCI6MTc0ODMzMjk4MSwiZXhwIjoxNzQ4NDE5MzgxfQ.n7z8ZzOfcAIcKW6OZ9dEJbXxhb3rO40LreGpZsaxlAg"
                ];

                $url = "https://api.zentexpay.in/api/payments/payout";
                $parameter = [
                    'beneficiary_name' => $post->name,
                    'account_number'   => $post->account,
                    'request_type'     => "IMPS",
                    'account_ifsc'  => $post->ifsc,
                    'bank_name'     => $post->bank,
                    'amount'        => $post->amount,
                    'reference_id'  => $post->txnid,
                ];

                $method = "POST";
                $query  = json_encode($parameter);
                break;
                            
            // case 'indiplixpayout':
            // case 'indiplixpayout_nikat':
            //     $url  = "https://demo.nikatby.in/payoutpass.php";
            //     $parameter = [
            //         "name"    => $post->name,
            //         "phone"   => $post->mobile,
            //         "email"   => $user->email,
            //         "account" => $post->account,
            //         "ifsc"    => $post->ifsc,
            //     ];
                
            //     $header = array();
                
            //     $result   = \App\Helpers\Permission::curl($url, 'POST', $parameter, $header, "yes", "Qr", $post->txnid); 
            //     $response = json_decode($result['response']);
            //     if($response->customer_reference_number){
            //         $url = "https://demo.nikatby.in/payoutsend.php";
            //         $payload =  [
            //             "iat" => Carbon::now()->timestamp,
            //             "exp" => Carbon::now()->addMinute()->timestamp,
            //             "amount"    => $post->amount,
            //             "apiRefNum" => $post->txnid,
            //             "customer_reference_number" => $response->customer_reference_number
            //         ];
        
            //         $token = JWT::encode($payload, "fO8d2yHFZUtbVUULQTiMLwgopRoYppHwePiXcchFVqw");
            //         $query  = [
            //             "token" => $token
            //         ];
            //         $header = array(
            //         );
            //     }else{
            //         User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
            //         Report::where('id', $report->id)->update([
            //             'status' => "failed", 
            //             'refno'  => isset($response->message) ? $response->message : "Payout Failed",
            //             'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
            //         ]);
            //         return response()->json([
            //             'statuscode'=> 'TXF', 
            //             'message'   => isset($response->message) ? $response->message : "Payout Failed",
            //             "apitxnid"  => $post->apitxnid, 
            //             "txnid"     => $post->txnid,
            //             "bankutr"   => isset($response->message) ? $response->message : "Payout Failed", 
            //         ]);
            //     }
            //     break;
                            
            case 'indiplixpayout':
            case 'indiplixpayout_nikat':
                $url  = "https://demo.nikatby.in/payoutsend.php";
                $parameter = [
                    "name"    => $post->name,
                    "account" => $post->account,
                    "ifsc"    => $post->ifsc,
                    "iat" => Carbon::now()->timestamp,
                    "exp" => Carbon::now()->addMinute()->timestamp,
                    "amount"    => $post->amount,
                    "apiRefNum" => $post->txnid,
                ];
                $method = "POST";
                $header = array();
                
                $token = JWT::encode($parameter, "fO8d2yHFZUtbVUULQTiMLwgopRoYppHwePiXcchFVqw");
                $query  = [
                    "token" => $token
                ];
                break;
                
            case 'ekopayout-nikatby':
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
                
            case 'groscope_payout':
                $header = array(
                    'Content-Type: application/json',
                    'X-Client-IP: 91.203.133.62',
                    'X-Auth-Token: 9LqM4sK5JfJKC3QSElYvyZSa8EbEftuKCluEYtgDncdBGF0B42',
                );
                
                $url = "https://login.groscope.com/api/payout?X-Client-IP=91.203.133.62&X-Auth-Token=9LqM4sK5JfJKC3QSElYvyZSa8EbEftuKCluEYtgDncdBGF0B42";
                $parameter = [
                    'bank_name' => $post->bank,
                    'account_name' => $post->name,
                    'account_holder_name' => $post->name,
                    'account_number'      => $post->account,
                    'ifsc_code'    => $post->ifsc,
                    'payment_mode' => "IMPS",
                    'amount'       => $post->amount,
                ];

                $method = "POST";
                $query  = json_encode($parameter);
                break;
                
            case 'collectswift_payout':
                $url = "https://api.collectswift.com/apiAdmin/v1/payout/generatePayOut";
                $parameter = [
                    "authToken"    => "b540cc99f3c595741a0e746e298391beae80390b0156e05ddc1f7dbbe4a1d1bb",
                    "userName"     => "C1745662385424",
                    "amount"       => (int)$post->amount,
                    "trxId"        => $post->txnid,
                    "accountHolderName" => $post->name,
                    "accountNumber" => $post->account,
                    "bankName" => $post->bank,
                    "ifscCode" => $post->ifsc,
                    "mobileNumber" => $user->mobile
                ];
                
                $header = array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                );

                $method = "POST";
                $query  = json_encode($parameter);
                break;
                
            case 'groscopenew_payout':
                $url     = "https://login.groscope.in/v1/service/payout/contacts";
                $header  = [
                    'content-type: application/json',
                    'Authorization: Basic UEFZU184ODM4MmFkOGNjNzViM2RkMjAzOTY2NTc4NTcwNTg5NzQ6NWE1MjM2NGY1YzA2ZjEyZmVmYjMxZTQ1OWIxMDVkZjAyMDM5NjY1Nzg1NzA3MjQwOQ=='
                ];
    
                $parameter = [
                    'firstName' => $post->name,
                    'lastName'  => $post->name,
                    'accountNumber' => $post->account,
                    'bankName' => $post->bank,
                    'ifsc'     => $post->ifsc,
                    'amount'   => $post->amount,
                    'email'    => $user->email,
                    'mobile'   => $user->mobile,
                    'accountType' => "bank_account",
                    'type'     => "customer",
                    'referenceId' => $post->txnid
                ];

                $method = "POST";
                $query  = json_encode($parameter);
                $result = \App\Helpers\Permission::curl($url, "POST", $query, $header, "yes", 'Payout', $post->txnid);

                if($result['response'] == ""){
                    User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                    Report::where('id', $report->id)->update([
                        'status' => "failed", 
                        'refno'  => "Failed",
                        'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                    ]);
                    return response()->json([
                        'statuscode'=> 'TXF', 
                        'message'   => "failed",
                        "apitxnid"  => $post->apitxnid, 
                        "txnid"     => $post->txnid,
                        "bankutr"   => "failed", 
                    ]);
                }
                $response = json_decode($result['response'], true);
                
                if(isset($response["message"]["contact_id"][0]) || isset($response["data"]["contactId"])){
                    $method = "POST";

                    if(isset($response["data"]["contactId"])){
                        $cid = $response["data"]["contactId"];
                    }else{
                        $cid = $response["message"]["contact_id"][0];
                    }
                    $url     = "https://login.groscope.in/v1/service/payout/orders";
                    $header  = [
                        'content-type: application/json',
                        'Authorization: Basic UEFZU184ODM4MmFkOGNjNzViM2RkMjAzOTY2NTc4NTcwNTg5NzQ6NWE1MjM2NGY1YzA2ZjEyZmVmYjMxZTQ1OWIxMDVkZjAyMDM5NjY1Nzg1NzA3MjQwOQ=='
                    ];
        
                    $parameter = [
                        'contactId' => $cid,
                        'amount'  => $post->amount,
                        'purpose' => "others",
                        'mode'    => "IMPS",
                        'udf1'    => "",
                        'udf2'    => "",
                        'clientRefId' => $post->txnid
                    ];
                    $query  = json_encode($parameter);
                }else{
                    User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                    Report::where('id', $report->id)->update([
                        'status' => "failed", 
                        'refno'  => isset($response["message"]) ? $response["message"] : "Payout Failed",
                        'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                    ]);
                    return response()->json([
                        'statuscode'=> 'TXF', 
                        'message'   => isset($response["message"]) ? $response["message"] : "Payout Failed",
                        "apitxnid"  => $post->apitxnid, 
                        "txnid"     => $post->txnid,
                        "bankutr"   => isset($response["message"]) ? $response["message"] : "Payout Failed", 
                    ]);
                }
                break;
                
            case 'camlenio_payout':
                $url = "https://partner.camlenio.com/api/v1/payout/payoutprocess";

                $parameter = [
                    'address' => $user->address,
                    'email'   => $user->email,
                    'mobile_number' => $post->mobile,
                    'name'          => $post->name,
                    'account_number'=> $post->account,
                    'ifsc_code'     => $post->ifsc,
                    'payment_type'  => "3",
                    'amount'        => $post->amount,
                    'merchant_order_id' => $post->txnid,
                ];
                
                $message ="POST\napi/v1/payout/payoutprocess\n\n".json_encode($parameter)."\n".time()."\n";
                
                $header = [
                    "Content-Type: application/json",
                    "User-Agent: team testing",
                    "ApiKey: 9516f27d93c4eb89167012e29fa46d0afa90fb705cec23019bb060ec0101ad21",
                    "SecretKey: 53dbd3b24df8bbca1a5b6250a18fa23685da2c2a42411a7ca7cf2bee892c0b8a361127cbd6db1bc6f1ef0f97ce87866e",
                    "UserId: 2703468451",
                    "signature: ".hash_hmac('sha512', $message, "53dbd3b24df8bbca1a5b6250a18fa23685da2c2a42411a7ca7cf2bee892c0b8a361127cbd6db1bc6f1ef0f97ce87866e"),
                ];

                $method = "POST";
                $query  = json_encode($parameter);
                break;

            case 'waayupayout':
                $header = array(
                    "content-type: application/json",
                    "accept: application/jso"
                );
                
                $url = $api->url."payout/payout";

                $parameter = [
                    'clientId'   => $api->username,
                    'secretKey'  => $api->password,
                    'beneficiaryName' => $post->name,
                    'accountNo'      => $post->account,
                    'transferMode'   => $post->mode,
                    'ifscCode'       => $post->ifsc,
                    'vpa'            => "",
                    'amount'         => $post->amount,
                    'clientOrderId'  => $post->txnid,
                    'number'         => $user->mobile,
                ];

                $method = "POST";
                $query  = json_encode($parameter);
                break;

            case 'ezywallet_payout':
                $header = array(
                    'Token: eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJhZXBzX2tleSI6IjliMmZhYjllMGZiNzI1NWUxMmY4NjAwNSIsImFlcHNfaXYiOiJjNWEwYjhhZjY0NTBiYTUwODdjZDgyMTc4OGFmIn0.uM_r_q7-_wuMK1kdO5V3v4qNWfBUCI7FnF9IeqkCX6A'
                );
                
                $url = "https://dashboard.ezywallet.in/api/v1/upi/upiPayoutAuth";
                $parameter = [
                    'account_holder_name' => $post->name,
                    'account_number'      => $post->account,
                    'requesttype' => $post->mode,
                    'ifsc_code'   => $post->ifsc,
                    'bank_name'   => $post->bank,
                    'amount'      => $post->amount,
                    'transaction_id' => $post->txnid,
                    'mobile_number'  => $user->mobile,
                    'email' => $user->email,
                ];

                $method = "POST";
                $query  = $parameter;
                break;

            case 'zanithpay_payout':
                $header = array(
                    'Content-Type: application/json'
                );
                
                $url = "https://api.zanithpay.com/apiAdmin/v1/payout/generatePayOut";
                $parameter = [
                    "authToken" => "f9d3db45032a4e11aaa2dd63a60622adb26a2d6d8e80c8e4e81f80298c91c294",
                    "userName"  => "M1732958599592",
                    'accountHolderName' => $post->name,
                    'accountNumber'      => $post->account,
                    'ifscCode'    => $post->ifsc,
                    'bankName'    => $post->bank,
                    'amount'      => $post->amount,
                    'trxId'       => $post->txnid,
                    'mobileNumber' => $user->mobile
                ];

                $method = "POST";
                $query  = json_encode($parameter);
                break;

            case 'paynits_payout':
                $header = array(
                    'Content-Type: application/json',
                    'Authorization: '.$api->username
                );
                
                $url = "https://gateway.suvikapay.com/api/v6/doPayout";
                $parameter = [
                    'beneficiary_name' => $post->name,
                    'account_number'   => $post->account,
                    'requesttype'   => $post->mode,
                    'account_ifsc'  => $post->ifsc,
                    'bankname'      => $post->bank,
                    'amount'        => $post->amount,
                    'reference'     => $post->txnid,
                ];

                $method = "POST";
                $query  = json_encode($parameter);
                break;

            case 'walletpayout':
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
                    'bankutr'    => $utr
                ]);
                break;

            case 'iserveupayout':
                $url = $api->url."w1w2-payout/w1/cashtransfer";
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

            case 'payonedigitalpayout':
                $url = "https://tech.payonedigital.in/api/v1/payout/initiate";
                $parameter = [
                    "amount"   => $post->amount,
                    "refid"    => $post->txnid,
                    "fname"    => $post->name,
                    "lname"    => $post->name,
                    "mobile"   => $post->mobile,
                    "email"    => $post->email
                ];
                
                $header = array();
                
                $query = [
                    "username" => "weblead2011@gmail.com",
                    "password" => "7042342976"
                ];
                
                $url = $url."?".http_build_query($parameter);
                $method = "POST";
                break;

            case 'techmondopayout':
                $url = "https://tech.mondomoney.co.in/api/v1/payout/initiate";
                $parameter = [
                    "account_no"   => $post->account,
                    "ifsc"   => $post->ifsc,
                    "amount"   => $post->amount,
                    "refid"    => $post->txnid,
                    "account_holder_name"    => $post->name,
                    "user_mobile_number"   => $post->mobile,
                    "payout_mode"   => "IMPS"
                ];
                
                $header = array();
                
                $query = [
                    "username" => "pgpeservices@gmail.com",
                    "password" => "9234875743"
                ];
                
                $url = $url."?".http_build_query($parameter);
                $method = "POST";
                break;

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
                        'refno'  => "failed",
                        'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
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
            
            default:
                $url = $api->url."payment/order/create";
                $parameter = [
                    'partner_id' => $api->username,
                    'mode'     => 'IMPS',
                    'name'     => $post->name,
                    'account'  => $post->account,
                    'bank'     => $post->bank,
                    'ifsc'     => $post->ifsc,
                    'mobile'   => $post->mobile,
                    'amount'   => $post->amount,
                    'webhook'  => "https://login.pgpe.in/production/api/webhook/unpepayout",
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
                $query  = json_encode($parameter);
                break;
        }
        
        if($this->env_mode() == "server"){
            $result = \App\Helpers\Permission::curl($url, $method, $query, $header, "yes", "report", $post->txnid);
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
                'bankutr'    => $post->txnid,
                'txnid'      => $post->txnid
            ]);
        }else{
            switch ($api->code) {
                
                case "apiwala_payout":
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
                            "apitxnid"   => $post->apitxnid, 
                            'txnid'      => $post->txnid,
                            'bankutr'    => isset($response->bankutr)? $response->bankutr : $post->txnid
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => isset($response->bankutr)? $response->bankutr : "", 
                            'payid'  => isset($response->txnid)? $response->txnid : ""
                        ]);

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
                    
                case 'zentexpay_payout':
                    $response = json_decode($result['response']);
                    if(isset($response->success) && $response->success !== true){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status'  => "failed", 
                            'refno'   => isset($reponse->message) ? $reponse->message : "Failed",
                            'closing' => \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($reponse->message) ? $reponse->message : "Failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($reponse->message) ? $reponse->message : "Failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => "NA",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => "pending", 
                        ]);
                    }
                    break;
                    
                case 'indiplixpayout':
                case 'indiplixpayout_nikat':
                    $response = json_decode($result['response']);
                    if(isset($response->status) && $response->status == false){
                            User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                            Report::where('id', $report->id)->update([
                                'status'  => "failed", 
                                'refno'   => $response->message,
                                'closing' => \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                            ]);
                    }else{
                        $data = JWT::decode($response->token, "fO8d2yHFZUtbVUULQTiMLwgopRoYppHwePiXcchFVqw", array('HS256'));
                        
                        if(isset($data->data->txnStatus) && $data->data->txnStatus != "IN_PROCESS"){
                            User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                            Report::where('id', $report->id)->update([
                                'status'  => "failed", 
                                'refno'   => "Failed",
                                'closing' => \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                            ]);
                            
                            return response()->json([
                                'statuscode'=> 'TXF', 
                                'message'   => "Failed",
                                "apitxnid"  => $post->apitxnid, 
                                "txnid"     => $post->txnid,
                                "bankutr"   => "Failed", 
                            ]);
                        }else{
                            Report::where('id', $report->id)->update([
                                'status' => "pending", 
                                'description' => $response->token."/".json_encode($data),
                                'refno'  => isset($data->data->bankRefId) ? $data->data->bankRefId : "",
                                'payid'  => isset($data->data->custRefNum) ? $data->data->custRefNum : ""
                            ]);
    
                            try {
                                \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                            } catch (\Exception $e) {}
    
                            return response()->json([
                                'statuscode'=> 'TXN', 
                                'message'   => 'Transaction Successfull',
                                "apitxnid"  => $post->apitxnid, 
                                "txnid"     => $post->txnid, 
                                "bankutr"   => isset($data->data->bankRefId) ? $data->data->bankRefId : "pending", 
                            ]);
                        }
                    }
                    break;
                    
                case 'collectswift_payout':
                    $data = json_decode($result['response']);
                    if(isset($data->data->status) && $data->data->status == "0"){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status'  => "failed", 
                            'refno'   => "Failed",
                            'closing' => \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => "Failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => "Failed", 
                        ]);
                    }elseif(isset($data->message) && $data->message == "Failed"){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status'  => "failed", 
                            'refno'   => $data->data,
                            'closing' => \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => "Failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => "Failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => isset($data->data->optxid) ? $data->data->optxid : "",
                            'payid'  => isset($data->data->optxid) ? $data->data->optxid : ""
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($data->data->optxid) ? $data->data->optxid : "pending", 
                        ]);
                    }
                    break;
                    
                case 'groscope_payout':
                    $response = json_decode($result['response']);
                    if(isset($response->statusCode) && $response->statusCode === "FAILED"){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->status_msg) ? $response->status_msg : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->status_msg) ? $response->status_msg : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->status_msg) ? $response->status_msg : "failed", 
                        ]);
                    }elseif(isset($response->status) && $response->status === false){
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
                            'refno'  => isset($response->utr) ? $response->utr : "",
                            'payid'  => isset($response->txn_id) ? $response->txn_id : ""
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->utr) ? $response->utr : "pending", 
                        ]);
                    }
                    break;
                    
                case 'groscopenew_payout':
                    $response = json_decode($result['response']);
                    if(isset($response->status) && $response->status != "SUCCESS"){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->status_msg) ? $response->status_msg : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->Message) ? $response->Message : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->Message) ? $response->Message : "failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => "NA",
                            'payid'  => isset($response->data->orderRefId) ? $response->data->orderRefId : ""
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => "NA", 
                        ]);
                    }
                    break;
                    
                case 'zanithpay_payout':
                    $response = json_decode($result['response']);
                    if(isset($response->data->status) && $response->data->status == "2"){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->status_msg) ? $response->status_msg : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->status_msg) ? $response->status_msg : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->status_msg) ? $response->status_msg : "failed", 
                        ]);
                    }elseif(isset($response->message) && $response->message === "Failed"){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->data) ? $response->data : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->data) ? $response->data : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->data) ? $response->data : "failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => "N/A"
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TUP', 
                            'message'   => 'Transaction Under Process',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => "N/A", 
                        ]);
                    }
                    break;
                    
                case 'ezywallet_payout':
                    $response = json_decode($result['response']);
                    if(isset($response->txn_status) && $response->txn_status === "FAILED"){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->status_msg) ? $response->status_msg : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->status_msg) ? $response->status_msg : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->status_msg) ? $response->status_msg : "failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->utr) ? $response->utr : ""
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->utr) ? $response->utr : "pending", 
                        ]);
                    }
                    break;
                    
                case 'camlenio_payout':
                    $response = json_decode($result['response']);
                    if(isset($response->Txn_Status) && $response->Txn_Status === "Failed"){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->message) ? $response->message : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->message) ? $response->message : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->message) ? $response->message : "failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->Ref_No) ? $response->Ref_No : ""
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->Ref_No) ? $response->Ref_No : "pending", 
                        ]);
                    }
                    break;
                    
                case 'paynits_payout':
                    $response = json_decode($result['response']);
                    if(isset($response->status) && $response->status === false){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->message) ? $response->message : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->message) ? $response->message : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->message) ? $response->message : "failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => isset($response->data->payout_ref) ? $response->data->payout_ref : "",
                            'payid'  => isset($response->data->payout_ref) ? $response->data->payout_ref : ""
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->data->payout_ref) ? $response->data->payout_ref : "pending", 
                        ]);
                    }
                    break;
                    
                case 'indicpaypayout':
                    $response = json_decode($result['response']);
                    if(isset($response->status) && in_array($response->status, ["failed", "failure", "ERROR"])){
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
                    
                case 'payonedigitalpayout':
                    $response = json_decode($result['response']);
                    if(isset($response->response_code) && $response->response_code != 1){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->message) ? $response->message : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->message) ? $response->message : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->message) ? $response->message : "failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => "NA",
                            'payid'  => isset($response->payout_ref) ? $response->payout_ref : ""
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => "pending", 
                        ]);
                    }
                    break;
                    
                case 'techmondopayout':
                    $response = json_decode($result['response']);
                    if(isset($response->payout_status) && $response->payout_status == "failed"){
                        User::where('id', $user->id)->increment('mainwallet', $post->amount + ($post->charge + $post->gst));
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => isset($response->message) ? $response->message : "Failed",
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => isset($response->message) ? $response->message : "failed",
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => isset($response->message) ? $response->message : "failed", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->transaction_id) ? $response->transaction_id : ""
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->transaction_id) ? $response->transaction_id : "" 
                        ]);
                    }
                    break;

                case 'ekopayout':
                case 'ekopayout-pagepe':
                case 'ekopayout-nikatby':
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
                            $refno = "NA";
                            if(isset($response->data->bank_ref_num) && $response->data->bank_ref_num != ""){
                                $refno = $response->data->bank_ref_num;
                            }
                            
                            Report::where('id', $report->id)->update([
                                'status' => "success", 
                                'refno'  => $refno,
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
                                \DB::table("log_callbacks")->insert(["request" => $report->remark."?".http_build_query($webhook_payload)]);
                                // $myresponse = \App\Helpers\Permission::curl($report->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");
            
                                // \DB::table('log_webhooks')->insert([
                                //     'url' => $report->remark."?".http_build_query($webhook_payload), 
                                //     'callbackresponse' => json_encode($myresponse)
                                // ]);
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
                            $refno = "NA";
                            if(isset($response->data->bank_ref_num) && $response->data->bank_ref_num != ""){
                                $refno = $response->data->bank_ref_num;
                            }
                            
                            Report::where('id', $report->id)->update([
                                'status' => "pending", 
                                'refno'  => $refno,
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

                case "waayupayout":
                    $response = json_decode($result['response']);
                    if(isset($response->statusCode) && $response->statusCode == 0){
                        User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);

                        $msg = isset($response->message) ? $response->message : "Failed";
                        if(isset($response->message) && $response->message == "Low Balance"){
                            $msg = "Service Down";
                        }

                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => $msg,
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => $msg,
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => $msg
                        ]);
                    }elseif(isset($response->status) && $response->status == 0){
                        User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                        
                        $msg = isset($response->message) ? $response->message : "Failed";
                        if(isset($response->message) && $response->message == "Low Balance"){
                            $msg = "Service Down";
                        }
                        
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => $msg,
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => $msg,
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => $msg
                        ]);
                    }elseif(isset($response->status) && $response->status == 400){
                        User::where('id', $user->id)->increment('mainwallet', $post->debitAmount);
                        
                        $msg = isset($response->title) ? $response->title : "Failed";
                        if(isset($response->title) && $response->title == "Low Balance"){
                            $msg = "Service Down";
                        }
                        
                        Report::where('id', $report->id)->update([
                            'status' => "failed", 
                            'refno'  => $msg,
                            'closing'=> \App\Helpers\Permission::getAccBalance($insert['user_id'], "mainwallet")
                        ]);
                        return response()->json([
                            'statuscode'=> 'TXF', 
                            'message'   => $msg,
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid,
                            "bankutr"   => $msg
                        ]);
                    }elseif(isset($response->status) && $response->status == 1){
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->utr) ? $response->utr : $post->txnid,
                            'payid'  => isset($response->orderId) ? $response->orderId : "",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->utr) ? $response->utr : "pending", 
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => isset($response->utr) ? $response->utr : $post->txnid,
                            'payid'  => isset($response->orderId) ? $response->orderId : "",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->utr) ? $response->utr : "pending", 
                        ]);
                    }
                    break;

                case "walletpayout":
                    $response = json_decode($result['response']);
                    if(isset($response->status) && in_array($response->status, ["FAILED", "ERROR"])){

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
                            'refno'  => isset($response->utr) ? $response->utr : $post->txnid,
                            'payid'  => isset($response->txnId) ? $response->txnId : "",
                        ]);

                        try {
                            \App\Helpers\Permission::commission(Report::where('id', $report->id)->first());
                        } catch (\Exception $e) {}

                        return response()->json([
                            'statuscode'=> 'TXN', 
                            'message'   => 'Transaction Successfull',
                            "apitxnid"  => $post->apitxnid, 
                            "txnid"     => $post->txnid, 
                            "bankutr"   => isset($response->utr) ? $response->utr : "pending", 
                        ]);
                    }
                    break;

                case "iserveupayout":
                    $response = json_decode($result['response']);
                    if(isset($response->status) && in_array($response->status, ["FAILED"])){
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
                    }elseif(isset($response->status) && in_array($response->status, ["SUCCESS"])){
                        Report::where('id', $report->id)->update([
                            'status' => "success", 
                            'refno'  => isset($response->rrn) ? $response->rrn : "success",
                            'payid'  => isset($response->transactionId) ? $response->transactionId : "success",
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
                            "bankutr"   => isset($response->rrn) ? $response->rrn : "success", 
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
                            "apitxnid"   => $post->apitxnid, 
                            'txnid'      => $post->txnid,
                            'bankutr'    => isset($response->bankutr)? $response->bankutr : $post->txnid
                        ]);
                    }else{
                        Report::where('id', $report->id)->update([
                            'status' => "pending", 
                            'refno'  => isset($response->bankutr)? $response->bankutr : "", 
                            'payid'  => isset($response->txnid)? $response->txnid : ""
                        ]);

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
            }
        }
    }
    
    // public function create(Request $post)
    // {
    //     if ($this->payoutstatus() == "off" && $post->user_id != 69) {
    //         return response()->json([
    //             'statuscode' => "ERR",
    //             "message"    => "Service Under Maintenance"
    //         ]);
    //     }

    //     if (!\App\Helpers\Permission::can('payout_service', $post->user_id)) {
    //         return response()->json(['statuscode' => "ERR", "message" => "Permission Not Allowed"]);
    //     }

    //     $rules = array(
    //         'mode'     => 'required',
    //         'name'     => 'required',
    //         'account'  => 'required',
    //         'bank'     => 'required',
    //         'ifsc'     => 'required',
    //         'mobile'   => 'required',
    //         'amount'   => 'required',
    //         'webhook'  => 'required',
    //         'latitude' => 'required',
    //         'longitude'=> 'required',
    //         'apitxnid' => "required|unique:reports,apitxnid"
    //     );

    //     $validate = \App\Helpers\Permission::FormValidator($rules, $post->all());
    //     if($validate != "no"){
    //         return $validate;
    //     }

    //     if($post->amount > 0 && $post->amount <= 499){
    //         $provider = Provider::where('recharge1', 'payout1')->first();
    //     }elseif($post->amount > 499 && $post->amount <= 999){
    //         $provider = Provider::where('recharge1', 'payout2')->first();
    //     }elseif($post->amount > 999 && $post->amount <= 1999){
    //         $provider = Provider::where('recharge1', 'payout5')->first();
    //     }elseif($post->amount > 1999 && $post->amount <= 25000){
    //         $provider = Provider::where('recharge1', 'payout3')->first();
    //     }else{
    //         $provider = Provider::where('recharge1', 'payout4')->first();
    //     }

    //     if(!$provider){
    //         return response()->json(['statuscode' => "ERR", "message" => "Operator Not Found"]);
    //     }

    //     if($provider->status == 0){
    //         return response()->json(['statuscode' => "ERR", "message" => "Operator Down For Sometime"]);
    //     }

    //     if(!$provider->api || $provider->api->status == 0){
    //         return response()->json(['statuscode' => "ERR", "message" => "Service Down For Sometime"]);
    //     }

    //     $user = User::find($post->user_id);
    //     if($user->status != "active"){
    //         return response()->json(['statuscode' => "ERR", "message" => "Your account has been blocked."]);
    //     }

    //     $post['charge'] = \App\Helpers\Permission::getCommission($post->amount, $user->scheme_id, $provider->id, "apiuser");
    //     if($post->charge == 0){
    //         return response()->json(['statuscode' => "ERR", "message" => "Contact Administrator, Charges Not Set"]);
    //     }

    //     $totalPending = \DB::table("tempreports")->where("status", "accept")->where("user_id", $user->id)->sum("amount");
    //     if(\App\Helpers\Permission::getAccBalance($user->id, "mainwallet") - \App\Helpers\Permission::getAccBalance($user->id, "lockedwallet") - $totalPending < $post->amount + $post->charge){
    //         return response()->json(['statuscode' => "ERR", "message" => 'Insufficient Wallet Balance']);
    //     }

    //     $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $user->id)->first();
    //     if($serviceApi && $post->webhook != "SELF"){
    //         $api = Api::find($serviceApi->api_id);
    //     }else{
    //         $api = Api::find($provider->api_id);
    //     }

    //     switch ($api->code) {
    //         case 'zentexpay_payout':
    //             do {
    //                 $post['txnid'] = "PHNT".rand(11111111, 99999999);
    //             } while (Report::where("txnid", "=", $post->txnid)->first() instanceof Report);
    //             break;
                
    //         default : 
    //             do {
    //                 $post['txnid'] = "PGPEBULK".rand(11111111, 99999999);
    //             } while (Report::where("txnid", "=", $post->txnid)->first() instanceof Report);
    //             break;
    //     }

    //     $post['charge'] = \App\Helpers\Permission::getCommission($post->amount, $user->scheme_id, $provider->id, "apiuser");
    //     $post['gst']    = ($post->charge * $provider->api->gst)/100;

    //     $insert = [
    //         'number'     => $post->account,
    //         'mobile'     => $user->mobile,
    //         'provider_id'=> $provider->id,
    //         'api_id'     => $api->id,
    //         'amount'     => $post->amount,
    //         'charge'     => $post->charge,
    //         'gst'        => $post->gst,
    //         'txnid'      => $post->txnid,
    //         'apitxnid'   => $post->apitxnid,
    //         'option1'    => $post->name,
    //         'option2'    => $post->bank,
    //         'option3'    => $post->ifsc,
    //         'option4'    => $post->mode,
    //         'remark'     => $post->webhook,
    //         'status'     => 'accept',
    //         'user_id'    => $user->id,
    //         'credit_by'  => $user->id,
    //         'rtype'      => 'main',
    //         'via'        => "api",
    //         'trans_type' => 'debit',
    //         'product'    => 'payout',
    //         'create_time'=> $post->apitxnid,
    //         "option7"    => $post->ip(),
    //         'option8'    => $post->latitude."/".$post->longitude,
    //     ];

    //     try {
    //         $report = Tempreport::create($insert);
    //     } catch (\Exception $e) {
    //         \DB::table('log_500')->insert([
    //             'line' => $e->getLine(),
    //             'file' => $e->getFile(),
    //             'log'  => $e->getMessage(),
    //             'created_at' => date('Y-m-d H:i:s')
    //         ]);
    //         $report = false;
    //     }

    //     if (!$report){
    //         return response()->json(['statuscode' => "ERR", "message" => 'Transaction Failed, please try again.']);
    //     }

    //     return response()->json([
    //         'statuscode' => 'ACCEPT', 
    //         'message'    => 'Transaction Accepted', 
    //         'bankutr'    => "processed",
    //         'txnid'      => $post->txnid,
    //         'apitxnid'   => $post->apitxnid
    //     ]);
    // }

    public function bulkpayout(Request $post)
    {
        if ($this->payoutstatus() == "off" && $post->user_id != 69) {
            return response()->json([
                'statuscode' => "ERR",
                "message"    => "Service Under Maintenance"
            ]);
        }

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

        if($post->amount > 0 && $post->amount <= 499){
            $provider = Provider::where('recharge1', 'payout1')->first();
        }elseif($post->amount > 499 && $post->amount <= 999){
            $provider = Provider::where('recharge1', 'payout2')->first();
        }elseif($post->amount > 999 && $post->amount <= 1999){
            $provider = Provider::where('recharge1', 'payout5')->first();
        }elseif($post->amount > 1999 && $post->amount <= 25000){
            $provider = Provider::where('recharge1', 'payout3')->first();
        }else{
            $provider = Provider::where('recharge1', 'payout4')->first();
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

        $totalPending = \DB::table("tempreports")->where("status", "accept")->where("user_id", $user->id)->sum("amount");
        if(\App\Helpers\Permission::getAccBalance($user->id, "mainwallet") - \App\Helpers\Permission::getAccBalance($user->id, "lockedwallet") - $totalPending < $post->amount + $post->charge){
            return response()->json(['statuscode' => "ERR", "message" => 'Insufficient Wallet Balance']);
        }

        $serviceApi = \DB::table("service_managers")->where("provider_id", $provider->id)->where("user_id", $user->id)->first();
        if($serviceApi && $post->webhook != "SELF"){
            $api = Api::find($serviceApi->api_id);
        }else{
            $api = Api::find($provider->api_id);
        }

        switch ($api->code) {
            case 'zentexpay_payout':
                do {
                    $post['txnid'] = "PHNT".rand(11111111, 99999999);
                } while (Report::where("txnid", "=", $post->txnid)->first() instanceof Report);
                break;
                
            default : 
                do {
                    $post['txnid'] = "PGPEBULK".rand(11111111, 99999999);
                } while (Report::where("txnid", "=", $post->txnid)->first() instanceof Report);
                break;
        }

        $post['charge'] = \App\Helpers\Permission::getCommission($post->amount, $user->scheme_id, $provider->id, "apiuser");
        $post['gst']    = ($post->charge * $provider->api->gst)/100;

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
            'create_time'=> $post->apitxnid,
            "option7"    => $post->ip(),
            'option8'    => $post->latitude."/".$post->longitude,
        ];

        try {
            $report = Tempreport::create($insert);
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

        return response()->json([
            'statuscode' => 'ACCEPT', 
            'message'    => 'Transaction Accepted', 
            'bankutr'    => "processed",
            'txnid'      => $post->txnid,
            'apitxnid'   => $post->apitxnid
        ]);
    }

    public function upipayment(Request $post)
    { 
        if ($this->payoutstatus() == "off" && $post->user_id != 69) {
            return response()->json([
                'statuscode' => "ERR",
                "message"    => "Service Under Maintenance"
            ]);
        }
        
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

        $url = $api->url."payment/order/upi";
        $parameter = [
            'partner_id' => $api->username,
            'mode'     => 'IMPS',
            'name'     => $post->name,
            'vpa'      => $post->vpa,
            'mobile'   => $post->mobile,
            'amount'   => $post->amount,
            'webhook'  => "https://login.pgpe.in/production/api/webhook/unpepayout",
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
        $query  = json_encode($parameter);
        
        if($this->env_mode() == "server"){
            $result = \App\Helpers\Permission::curl($url, $method, $query, $header, "yes", "report", $post->txnid);
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
                    "apitxnid"   => $post->apitxnid, 
                    'txnid'      => $post->txnid,
                    'bankutr'    => isset($response->bankutr)? $response->bankutr : $post->txnid
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
            $upiload = Tempreport::where("apitxnid", $post->apitxnid)->where("user_id", $post->user_id)->first();
            if(!$upiload){
                return response()->json(['statuscode' => "TNF", 'message' => "Transaction Not Found"]);
            }
        }

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

    public function webhook(Request $post, $api)
    {
        $update["status"] = "pending";
        $checkvia = "txnid";
        switch ($api) {
            case 'walletpay':
                if($post->status != "PROCESSING"){
                    $log = \DB::table('log_webhooks')->insert([
                        'txnid'      => $post->orderId, 
                        'product'    => 'walletpayout', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);

                    $update['txnid']  = $post->orderId;
                    if(isset($post->status) && strtolower($post->status) == "success"){
                        $update['status'] = "success";
                        $update['refno']  = $post->utr;
                        $update['payid']  = $post->txnId;
                    }elseif(isset($post->status) && in_array(strtolower($post->status), ["failed", "failure"])){
                        $update['status'] = "reversed";
                        $update['refno']  = $post->msg;
                        $update['payid']  = $post->txnId;
                    }
                }
                break;
                
            case 'zanithpay':
                $log = \DB::table('log_webhooks')->insert([
                    'txnid'      => $post->txnid, 
                    'product'    => 'zanithpay', 
                    'response'   => json_encode($post->all()),
                    "created_at" => date("Y-m-d H:i:s")
                ]);

                $update['txnid']  = $post->txnid;
                if(isset($post->status) && strtolower($post->status) == "success"){
                    $update['status'] = "success";
                    $update['refno']  = $post->rrn;
                    $update['payid']  = $post->optxid;
                }elseif(isset($post->status) && in_array(strtolower($post->status), ["failed", "failure"])){
                    $update['status'] = "reversed";
                    $update['refno']  = $post->msg;
                    $update['payid']  = $post->txnId;
                }
                break;

            case 'waayupay':
                if(isset($post->Status) && in_array($post->Status, [1, 0])){
                    $log = \DB::table('log_webhooks')->insert([
                        'txnid'      => $post->ClientOrderId, 
                        'product'    => 'waayupay', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);

                    $update['txnid']  = $post->ClientOrderId;
                    if(isset($post->Status) && $post->Status == 1){
                        $update['status'] = "success";
                        $update['refno']  = $post->UTR;
                        $update['payid']  = $post->OrderId;
                    }elseif(isset($post->Status) && $post->Status == 0){
                        $update['status'] = "reversed";
                        $update['refno']  = $post->Message;
                        $update['payid']  = $post->OrderId;
                    }
                }
                break;

            case 'iserveu':
                if($post->status != "INPROGRESS"){
                    $log = \DB::table('log_webhooks')->insert([
                        'txnid'      => $post->ClientRefID, 
                        'product'    => 'iserveuPayout', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);

                    $update['txnid']  = $post->ClientRefID;

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
                }
                break;

            case 'indiplex':
                $log = \DB::table('log_webhooks')->insert([
                    'txnid'      => date("ymdhis"), 
                    'product'    => 'indiplexpayout', 
                    'response'   => json_encode($post->all()),
                    "created_at" => date("Y-m-d H:i:s")
                ]);
                
                $data = JWT::decode($post->token, "4bi7Zz7uDmcsWZSIsEePSbt7TV614Kdo_a700Ps6eXs", array('HS256'));
                return response()->json(["status" => true, "message" => "success", "OTP" => $data->OTP]);
                break;

            case 'openmart':
                if(isset($post->status) && $post->status == "success"){
                    $log = \DB::table('log_webhooks')->insert([
                        'txnid'      => $post->transaction_id, 
                        'product'    => 'openmartpayout', 
                        'response'   => json_encode($post->all()),
                        "created_at" => date("Y-m-d H:i:s")
                    ]);

                    $update['txnid']  = $post->transaction_id;
                    if(isset($post->status) && $post->status == "success"){
                        $update['status'] = "success";
                        $update['refno']  = $post->utr;
                    }elseif(isset($post->status) && $post->status == "failed"){
                        $update['status'] = "reversed";
                        $update['refno']  = $post->status;
                    }
                }
                break;

            case 'eko':
                $log = \DB::table('log_webhooks')->insert([
                    'txnid'      => date("ymdhis"), 
                    'product'    => 'indicpay', 
                    'response'   => json_encode($post->all()),
                    "created_at" => date("Y-m-d H:i:s")
                ]);
                break;

            case 'zentexpay':
                $log = \DB::table('log_webhooks')->insert([
                    'txnid'      => date("ymdhis"), 
                    'product'    => 'zentexpayout', 
                    'response'   => json_encode($post->all()),
                    "created_at" => date("Y-m-d H:i:s")
                ]);
                break;

            case 'collectswift':
                $log = \DB::table('log_webhooks')->insert([
                    'txnid'      => $post->txnid, 
                    'product'    => 'collectswiftpayout', 
                    'response'   => json_encode($post->all()),
                    "created_at" => date("Y-m-d H:i:s")
                ]);
                
                $update['txnid']  = $post->txnid;

                if(isset($post->status) && strtolower($post->status) == "success"){
                    $update['status'] = "success";
                    $update['refno']  = $post->rrn;
                    $update['payid']  = $post->optxid;
                }elseif(isset($post->status) && strtolower($post->status) == "failure"){
                    $update['status'] = "reversed";
                    $update['refno']  = $post->rrn;
                }elseif(isset($post->status) && strtolower($post->status) == "failed"){
                    $update['status'] = "reversed";
                    $update['refno']  = $post->rrn;
                }
                break;

            case 'paynits':
                $data = json_decode(json_encode($post->all()), true);
                $data = json_decode(json_encode($data["data"]));
                
                $log = \DB::table('log_webhooks')->insert([
                    'txnid'      => $data->reference, 
                    'product'    => 'paynitspayout', 
                    'response'   => json_encode($post->all()),
                    "created_at" => date("Y-m-d H:i:s")
                ]);
                
                $checkvia = "txnid";
                $update['txnid']  = $data->reference;
                if(isset($data->status) && strtolower($data->status) == "success"){
                    $update['status'] = "success";
                    $update['refno']  = $data->UTR;
                }elseif(isset($data->status) && strtolower($data->status) == "failure"){
                    $update['status'] = "reversed";
                    $update['refno']  = $data->message;
                }elseif(isset($data->status) && strtolower($data->status) == "refunded"){
                    $update['status'] = "reversed";
                    $update['refno']  = $data->message;
                }elseif(isset($data->status) && strtolower($data->status) == "failed"){
                    $update['status'] = "reversed";
                    $update['refno']  = $data->message;
                }
                break;

            case 'groscope':
                $data = json_decode(json_encode($post->all()), true);
                $data = json_decode(json_encode($data["data"]));
                
                $log = \DB::table('log_webhooks')->insert([
                    'txnid'      => $data->transaction_id, 
                    'product'    => 'groscope', 
                    'response'   => json_encode($post->all()),
                    "created_at" => date("Y-m-d H:i:s")
                ]);
                
                $checkvia = "payid";
                $update['txnid']  = $data->transaction_id;
                if(isset($data->status) && strtolower($data->status) == "success"){
                    $update['status'] = "success";
                    $update['refno']  = $data->utr_number;
                }elseif(isset($data->status) && strtolower($data->status) == "failure"){
                    $update['status'] = "reversed";
                    $update['refno']  = "Failed";
                }elseif(isset($data->status) && strtolower($data->status) == "refunded"){
                    $update['status'] = "reversed";
                    $update['refno']  = "Failed";
                }elseif(isset($data->status) && strtolower($data->status) == "failed"){
                    $update['status'] = "reversed";
                    $update['refno']  = "Failed";
                }
                break;

            case 'ezywallet':
                $log = \DB::table('log_webhooks')->insert([
                    'txnid'      => $post->transaction_id, 
                    'product'    => 'ezywalletpayout', 
                    'response'   => json_encode($post->all()),
                    "created_at" => date("Y-m-d H:i:s")
                ]);

                $update['txnid']  = $post->transaction_id;

                if(isset($post->status) && strtolower($post->status) == "success"){
                    $update['status'] = "success";
                    $update['refno']  = $post->utr;
                }elseif(isset($post->status) && strtolower($post->status) == "failure"){
                    $update['status'] = "reversed";
                    $update['refno']  = $post->message;
                }elseif(isset($post->status) && strtolower($post->status) == "failed"){
                    $update['status'] = "reversed";
                    $update['refno']  = $post->message;
                }
                break;

            case 'camlenio':
                $log = \DB::table('log_webhooks')->insert([
                    'txnid'      => $post->order_id, 
                    'product'    => 'camlenio', 
                    'response'   => json_encode($post->all()),
                    "created_at" => date("Y-m-d H:i:s")
                ]);
                $update['txnid']  = $post->order_id;

                if(isset($post->status) && strtolower($post->status) == "success"){
                    $update['status'] = "success";
                    $update['refno']  = $post->utr;
                }elseif(isset($post->status) && strtolower($post->status) == "failure"){
                    $update['status'] = "reversed";
                    $update['refno']  = $post->message;
                }elseif(isset($post->status) && strtolower($post->status) == "failed"){
                    $update['status'] = "reversed";
                    $update['refno']  = $post->message;
                }
                break;
                
            case 'laraware':
                $log = \DB::table('log_webhooks')->insert([
                    'txnid'      => $post->transaction_id, 
                    'product'    => 'larawarepayout', 
                    'response'   => json_encode($post->all()),
                    "created_at" => date("Y-m-d H:i:s")
                ]);
                
                $update['txnid']  = $post->transaction_id;

                if(isset($post->status) && strtolower($post->status) == "success"){
                    $update['status'] = "success";
                    $update['refno']  = $post->utr;
                }elseif(isset($post->status) && strtolower($post->status) == "failure"){
                    $update['status'] = "reversed";
                    $update['refno']  = $post->message;
                }elseif(isset($post->status) && strtolower($post->status) == "failed"){
                    $update['status'] = "reversed";
                    $update['refno']  = $post->message;
                }
                break;
                
            default:
                $log = \DB::table('log_webhooks')->insert([
                    'txnid'      => $post->apitxnid, 
                    'product'    => 'apiwalapayout', 
                    'response'   => json_encode($post->all()),
                    "created_at" => date("Y-m-d H:i:s")
                ]);
                
                $update['txnid']  = $post->apitxnid;

                if(isset($post->status) && strtolower($post->status) == "success"){
                    $update['status'] = "success";
                    $update['refno']  = $post->utr;
                }elseif(isset($post->status) && strtolower($post->status) == "reversed"){
                    $update['status'] = "reversed";
                    $update['refno']  = $post->message;
                }elseif(isset($post->status) && strtolower($post->status) == "failed"){
                    $update['status'] = "reversed";
                    $update['refno']  = $post->message;
                }
                break;
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
}
