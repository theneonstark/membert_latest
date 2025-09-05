<?php

namespace App\Http\Controllers\PortalSetting;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Api;
use App\Models\Provider;
use App\Models\Qrreport;
use App\Models\User;
use App\Models\Collectionreport;
use App\Models\Complaint;
use Carbon\Carbon;

class ActionController extends Controller
{
    public $fundapi, $admin;

    public function __construct()
    {
        $this->fundapi = Api::where('code', 'fund')->first();
        $this->admin = User::whereHas('role', function ($q){
            $q->where('slug', 'admin');
        })->first();
    }

    public function complaint(Request $post)
    {
        $rules = array(
            'transaction_id' => 'required',
            'refno'  => 'required'
        );
        
        $validator = \Validator::make($post->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['errors'=>$validator->errors()], 422);
        }   

        $action = Complaint::updateOrCreate(['id'=> $post->id], [
            "transaction_id" =>$post->transaction_id,
            "product" => "UPI",
            "subject" => "Fund Not Credited"
        ]);

        if ($action) {
            return response()->json(['status' => "success", "message" => "success"]);
        }else{
            return response()->json(['status' => "ERR", "message" => "Task Failed, please try again"]);
        }

        //return response()->json(['status' => "error", "message" => "Complaint Not Allowed"]);
        
        // if($post->product == "oldqrcode"){
        //     $report = Qrreport::where('id', $post->transaction_id)->first();
        // }else{
        //     $report = Collectionreport::where('id', $post->transaction_id)->first();
        // }
        
        // if(!$report || !in_array($report->status , ['pending', "failed"])){
        //     return response()->json(['status' => "error", "message" => "Complaint Not Allowed"]);
        // }
        // $payinReport = \DB::table("collectionreports")->where('refno', $post->refno)->where('user_id', $report->user_id)->first();

        // if($payinReport && $payinReport->status == "success"){
        //     if($post->product == "oldqrcode"){
        //         Qrreport::where('id', $post->transaction_id)->update(["status" => "success"]);
        //     }else{
        //         Collectionreport::where('id', $post->transaction_id)->update(["status" => "success"]);
        //     }
            
        //     return response()->json(['status' => "error", "message" => "Fund Already Credited, Check your ladger with bank refernce ". $post->refno]);
        // }

        // $payinReport = \DB::table("collectionreports")->where('refno', $post->refno)->where('user_id', $this->admin->id)->first();
        // if(!$payinReport){
        //     return response()->json(['status' => "error", "message" => "Transaction not found, You can raise charge back"]);
        // }

        // $reversedAmount = \DB::table('collectionreports')->whereDate('created_at', Carbon::createFromFormat("Y-m-d H:i:s", $payinReport->created_at)->format("Y-m-d"))->where('credit_by', $report->user_id)->where('user_id', $this->admin->id)->select([
        //         \DB::raw("sum(case when collectionreports.product = 'payin' and collectionreports.status = 'success' then collectionreports.amount else 0 end) as successamt"),
        //         \DB::raw("sum(case when collectionreports.product = 'payin' and collectionreports.status = 'reversed' then collectionreports.amount else 0 end) as reversedamt"),
        //     ])->first();

        // $totalAmt = (40 * ($reversedAmount->successamt + $reversedAmount->reversedamt))/100;
        // if($totalAmt <= ($reversedAmount->reversedamt + $payinReport->amount)){
        //     return response()->json(['status' => "error", "message" => "Transaction not found, You can raise charge back"]);
        // }

        // if($payinReport->status == "success"){
        //     if($payinReport->amount > 0 && $payinReport->amount <= 199){
        //         $provider = Provider::where('recharge1', 'qrcollection1')->first();
        //     }elseif($payinReport->amount > 199 && $payinReport->amount <= 499){
        //         $provider = Provider::where('recharge1', 'qrcollection2')->first();
        //     }elseif($payinReport->amount > 499 && $payinReport->amount <= 999){
        //         $provider = Provider::where('recharge1', 'qrcollection3')->first();
        //     }else{
        //         $provider = Provider::where('recharge1', 'qrcollection4')->first();
        //     }

        //     $post['charge'] = \Myhelper::getCommission($payinReport->amount, $report->user->scheme_id, $provider->id, $report->user->role->slug);
        //     $post['gst']    = ($post->charge * 18)/100;

        //     $insert = [
        //         'number'  => $payinReport->number,
        //         'option1' => $payinReport->option1,
        //         'mobile'  => $payinReport->mobile,
        //         'provider_id' => $payinReport->provider_id,
        //         'api_id'  => $payinReport->api_id,
        //         'amount'  => $payinReport->amount,
        //         'charge'  => $post->charge,
        //         'gst'     => $post->gst,
        //         'txnid'   => $payinReport->txnid,
        //         'apitxnid'=> $payinReport->apitxnid,
        //         'payid'   => $payinReport->id,
        //         'refno'   => $payinReport->refno,
        //         'status'  => 'success',
        //         'transfer_mode' => 'callback',
        //         'user_id' => $report->user_id,
        //         'credit_by'   => $report->user_id,
        //         'rtype'       => 'main',
        //         'create_time' => "DIRECT".$payinReport->refno,
        //         'trans_type'  => "credit",
        //         'product'     => "payin"
        //     ];

        //     try {
        //         $report = \DB::transaction(function () use($insert, $report, $payinReport, $post){
        //             if($post->product == "oldqrcode"){
        //                 \DB::table("qrreports")->where('id', $report->id)->where("product", "qrcode")->update([
        //                     "status"  => "success"
        //                 ]);
        //             }else{
        //                 \DB::table("collectionreports")->where('id', $report->id)->where("product", "qrcode")->update([
        //                     "status"  => "success"
        //                 ]);
        //             }
                    
        //             $insert["balance"] = \Myhelper::getAccBalance($insert['user_id'], "collectionwallet");
        //             User::where('id', $insert['user_id'])->increment("collectionwallet", $insert["amount"]  - ($insert["charge"] + $insert["gst"]));
        //             $insert["closing"] = \Myhelper::getAccBalance($insert['user_id'], "collectionwallet");
        //             Collectionreport::where('id', $payinReport->id)->where('user_id', $this->admin->id)->update(["status" => "reversed"]);
        //             return Collectionreport::create($insert);
        //         });

        //         if($report->api->code != "collect"){
        //             try {
        //                 \Myhelper::commission(Collectionreport::where("id", $report->id)->first());
        //             } catch (\Exception $e) {
        //                 \DB::table('log_500')->insert([
        //                     'line' => $e->getLine(),
        //                     'file' => $e->getFile(),
        //                     'log'  => $e->getMessage(),
        //                     'created_at' => date('Y-m-d H:i:s')
        //                 ]);
        //             }
        //         }

        //         if($report->api->code != "collect"){
        //             $webhook_payload["amount"] = $payinReport->amount;
        //             $webhook_payload["status"] = "success";
        //             $webhook_payload["statuscode"] = "TXN";
        //             $webhook_payload["txnid"]  = $report->txnid;
        //             $webhook_payload["apitxnid"]  = $report->apitxnid;
        //             $webhook_payload["utr"]    = $post->utr;
        //             $webhook_payload["payment_mode"] = $post->payid;
        //             $webhook_payload["payid"]  = $payinReport->id;
        //             $webhook_payload["message"]= "UPI";
        //             $response = \Myhelper::curl($report->remark."?".http_build_query($webhook_payload), "GET", "", [], "no", "no", "no", "no");

        //             \DB::table('log_webhooks')->insert([
        //                 'url' => $report->remark."?".http_build_query($webhook_payload), 
        //                 'callbackresponse' => json_encode($response)
        //             ]);
        //         }
        //     } catch (\Exception $e) {
        //         \DB::table('log_500')->insert([
        //             'line' => $e->getLine(),
        //             'file' => $e->getFile(),
        //             'log'  => $e->getMessage(),
        //             'created_at' => date('Y-m-d H:i:s')
        //         ]);
        //     }
        // }
    }
}
