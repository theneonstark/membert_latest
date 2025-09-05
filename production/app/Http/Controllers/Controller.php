<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Carbon\Carbon;

class Controller extends BaseController
{
    public function getAccBalance($id, $wallet)
    {
        $mywallet = \DB::table('users')->where('id', $id)->first([$wallet]);

        $mywallet = (array)$mywallet;
        return $mywallet[$wallet];
    }

    public function env_mode()
    {
        $code = \DB::table('portal_settings')->where('code', 'envmode')->first(['value']);
        if($code){
           return $code->value;
        }else{
            return "local";
        }
    }

    public function transcode()
    {
        $code = \DB::table('portal_settings')->where('code', 'transactioncode')->first(['value']);
        if($code){
           return $code->value;
        }else{
            return "none";
        }
    }

    public function payoutstatus()
    {
        $code = \DB::table('portal_settings')->where('code', 'payoutstatus')->first(['value']);
        if($code){
           return $code->value;
        }else{
            return "off";
        }
    }

    public function payinstatus()
    {
        $code = \DB::table('portal_settings')->where('code', 'payinstatus')->first(['value']);
        if($code){
           return $code->value;
        }else{
            return "off";
        }
    }

    public function threewayreconstore($recon)
    {
        try {
            $setdate = Carbon::createFromFormat('d/m/Y H:i:s', $recon['txndate'])->format('d-m-Y');
            $requestbody =[
                "merchantTransactionId" => $recon['txnid'],
                "fingpayTransactionId"  => $recon['fpTransactionId'],
                "transactionRrn"  => $recon['bankRRN'],
                "responseCode"    => $recon['responseCode'],
                "transactionDate" => $setdate,
                "serviceType"     => $recon['transactionType']
            ];

            $headerbody = json_encode($requestbody)."antliatspdfacc3ad13c31e267874102eb75818541815f60d99e19d96182feed095c12f2f0";
            $requestheader = [                 
                'txnDate:'.$setdate,   
                'trnTimestamp:'.$recon['txndate'],
                'hash:'.base64_encode(hash("sha256", $headerbody, True)),         
                'superMerchantId:'.$recon['superMerchantId'],
                'superMerchantLoginId:antliad',
                'Content-Type: text/plain'       
            ];

            \DB::table("threewayrecon")->insert([
                "txnid"    => $recon['txnid'],
                "body"     => json_encode($requestbody),
                "header"   => json_encode($requestheader),
                "status"   => "pending",
                "product"  => $recon['product'],
                "via"  => $recon['via'],
                "env"  => $recon['env']
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
