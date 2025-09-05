<?php
namespace App\Helpers;
 
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Api;

class Dmt {
    public static function avenueAccountVerify($mobile, $beneaccount, $bankcode, $beneifsc){
        $api = Api::where('code', 'bank_avenue')->first();
        $txnid = "ANTACCVER".Str::random(14).sprintf("%012d", substr(date("Y"), -1).date("z").date('Hs'));
        $url   = $api->url."dmt/dmtServiceReq/xml?";
        $parameter = "<dmtServiceRequest>
                        <requestType>VerifyBankAcct</requestType>
                        <agentId>CC01AV34AGTU00000011</agentId>
                        <initChannel>AGT</initChannel>
                        <senderMobileNumber>$mobile</senderMobileNumber>
                        <bankCode>$bankcode</bankCode>
                        <bankAccountNumber>$beneaccount</bankAccountNumber>
                        <ifsc>$beneifsc</ifsc>
                    </dmtServiceRequest>";
        $encrypt_xml_data = \App\Helpers\Permission::encrypt($parameter, $api->password);

        $data['accessCode'] = $api->username;
        $data['requestId']  = $txnid;
        $data['encRequest'] = $encrypt_xml_data;
        $data['ver'] = "1.0";
        $data['instituteId'] = $api->optional1;
        $parameters  = http_build_query($data);

        $result = \App\Helpers\Permission::curl($url, "POST", $parameters, [], "no", "no", "no");
        $response_data = \App\Helpers\Permission::decrypt($result['response'], $api->password);
        $xml      = simplexml_load_string($response_data, "SimpleXMLElement", LIBXML_NOCDATA);
        $response = json_decode(json_encode($xml), true);

        if(isset($response['responseCode']) && $response['responseCode'] == '000'){

            \DB::table("dmt_accounts")->insert([
                "name"    => $response['impsName'],
                "account" => $beneaccount,
                "bank"    => $bankcode,
                "ifsc"    => $beneifsc,
                "created_at" => date("Y-m-d H:i:s"),
                "updated_at" => date("Y-m-d H:i:s")
            ]);

            return ['statuscode' => 'TXN', 'message' => $response['impsName'], "utr" => $response['uniqueRefId'], "txnid" => $txnid];
        }elseif(isset($response['errorInfo']) && isset($response['errorInfo']['error']['errorCode'])){
            return ['statuscode' => 'TXR', 'message' => $response['errorInfo']['error']['errorMessage']];
        }else{
            return ['statuscode'=> 'TXR', 'message'=> 'Transaction Error'];
        }
    }
}