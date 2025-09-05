<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;
use Spatie\ArrayToXml\ArrayToXml;
use Zxing\QrReader;

class AuthController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */

    public function billcheck(Request $post)
    {
        $parameter = '<billPaymentRequest>
            <agentId>sdfsdfsd</agentId>
            <billerAdhoc>asfsaf</billerAdhoc>
            <agentDeviceInfo>
                <ip>101.53.145.140</ip>
                <initChannel>AGT</initChannel>
                <mac>01-23-45-67-89-ab</mac>
            </agentDeviceInfo>
            
            <customerInfo>
                <customerMobile>asfasfas</customerMobile>
                <customerEmail></customerEmail>
                <customerAdhaar></customerAdhaar>
                <customerPan></customerPan>
            </customerInfo>
        <billerId>asfasfsaf</billerId><inputParams>';
        
        $parameter .= '</inputParams>';
        $responsedata = \App\Helpers\Permission::decrypt("e3a631b01feef3958a6f725e28c61e6f9fd855dd653f7c5bda1e75c88c0ec58ba42cd2edea184359dcf0526228f185a12541c63af428103070c576542e6d2dba9904c6cc7de90a28c7025e0cad5ba9fca273b4c514f1fdb37c6b76ce325c350578eecb2359badebadaefdee031f0636181a5a9340b72abce22082593509340ac4a0ebd1d929a1d4f9e0ab51cb016a2368ce20685d488f29a989aa5a51aee6a9208860c55ef46d5640bb28155e28f2c8b7a2409fe5f8a61527c4a81ddb3ed50dbf94064ea32ebdc99c5d8d21cd90bddc2b846457aa1e125839e567165cfa9689eebb59c38eaa0ac475d769aca248e1aaba8d4da609483003a64b5d81ac9f04d293ef24467b44cbfda1ce1c0624c01f134361373ebf7a0a8cd915a856bcb257e9dae31d3786c96b9773dc8230591bec7ef42a09722f724e79870ae05d00811beaa0a49e362b86545f141ef496bf2c2074d2fdae907a3acc6a8b492f85334bcc040ef2db832e8d137b3a733b202db5020d070954fee8ca8992f4fa30806adc36d344eb0131c85b35b079b9f7bae2dbb5e003bb9ecea9030a3e3de389cfcaffb9806da7d79513a05a563fb85c31e6828ec1e9139f138cd0511108dba23b55a5cb1830383a648ce50791595ff5f2a14d170f100b80ee36c2c9587f4506eda3ad2f371aba901f3fcb0eefde816e39a64d0b880a0f2537aab3fd07f513b4e6262824b93113055c24896e87b2bf143897533efc71659c8bc547a60dc9efa5004c1dc68e6bd8c55e8f06b17e5b981924f93d71b21352be0da41f7f58bc3ba88d7c4a8c5deb86c419da2c374821869d752ed7e4b277467b58bd75b737046b3daadab1dc09d71b330a66c765387a2946726821d709eec6229c892d9c2b52853de88c14805f56cbef8c7cec342a48c08264dc295d572806ae3b24edff82f6af501cd611558199f538ee27a03280cb36abdf1e445910173565c8283eaed1058f78b14c245e6813e88a4e8b40d1ad1f3f8ed7b0e8db4f88aff81cdc5c8e0da7bb455441c5fc04c509ba6ca910670583a3a8af39dc4b119a9841df6a6b41ec0b3f73937f3ee837dcbde646bed709bff7a0291af5869057560df9d8bdd7e0d65844e37b2293e46e398b8021cfd21641b0ae042b11da154f7", "7495D5534230571447B6FA30C271D8A9");
        
        $xml      = simplexml_load_string($responsedata);
        $billerresponse = json_decode(json_encode((array) $xml), true);
        
        $parameter .= str_replace('<?xml version="1.0"?>', "", ArrayToXml::convert($billerresponse['billerResponse'], 'billerResponse'));
        $parameter .= str_replace('<?xml version="1.0"?>', "", ArrayToXml::convert($billerresponse['additionalInfo'], 'additionalInfo'));
        
        $parameter .= '
        <amountInfo>
            <amount>100</amount>
            <currency>356</currency>
            <custConvFee>0</custConvFee>
            <amountTags></amountTags>';

        $parameter .= '</amountInfo>
        <paymentMethod>
            <paymentMode>Cash</paymentMode>
            <quickPay>N</quickPay>
            <splitPay>N</splitPay>
        </paymentMethod>
        <paymentInfo>
            <info>
            <infoName>Remarks</infoName>
            <infoValue>Received</infoValue>
            </info>
        </paymentInfo>';
        $parameter .= '</billPaymentRequest>';
        
        dd($parameter);
    }
    
    public function getip(Request $post)
    {
        $output['statuscode'] = "TXN";
        $output['message']    = "Ip Found Successfully";
        $output['ip']         = $post->ip();
        return response()->json($output);
    }
    
    public function getcode(Request $post)
    {
        if (extension_loaded('gd')) {
            echo "GD Library is installed.";
            $gdInfo = gd_info();
            print_r($gdInfo);
        } else {
            echo "GD Library is not installed.";
        }

        $qrcode = new QrReader("153787841.png");
        dd($qrcode);
    }

    public function getbalance(Request $post)
    {
        $user = User::where('id',$post->user_id)->first(['mainwallet', 'qrwallet', 'collectionwallet']);
        
        if($user){
            $output['statuscode']  = "TXN";
            $output['message'] = "Balance Fetched Successfully";
            $output['balance'] = [
                "mainwallet" => round($user->mainwallet, 2),
                "qrwallet"   => round($user->qrwallet, 2),
                "collectionwallet" => round($user->collectionwallet, 2),
            ];
        }else{
            $output['statuscode'] = "ERR";
            $output['message'] = "User details not matched";
        }
        return response()->json($output);
    }
}
