<?php

namespace App\Http\Controllers\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class DataController extends Controller
{
    public function fetchData(Request $request)
    {
        $data = [];
        $parentData = \Myhelper::getParents(session("loginid"));
        if(!$request->has("fromdate")){
            $request['fromdate'] = date("Y-m-d");
        }

        if(!$request->has("todate")){
            $request['todate'] = date("Y-m-d");
        }
        
        switch ($request->type) {
            case 'businessstatics':
                $id = session("loginid");

                $data['collectionsales'] = \DB::table('collectionreports')->whereBetween('created_at', [
                        Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), 
                        Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')
                    ])->select([
                    \DB::raw("sum(case when product = 'payin' and status = 'success' and rtype = 'main' and user_id = '".$id."' and credit_by != '1' then 1 else 0 end) as upicount"),
                    \DB::raw("sum(case when product = 'payin' and status = 'success' and rtype = 'main' and user_id = '".$id."' and credit_by != '1' then amount else 0 end) as upiamt"),
                    \DB::raw("sum(case when product = 'payin' and status = 'success' and rtype = 'main' and user_id = '".$id."' and credit_by != '1' then charge else 0 end) as upicharge"),
                    \DB::raw("sum(case when product = 'payin' and status = 'success' and rtype = 'main' and user_id = '".$id."' and credit_by != '1' then gst else 0 end) as upigst"),
                    \DB::raw("sum(case when product = 'payin' and status = 'success' and rtype = 'main' and user_id = '".$id."' and credit_by != '1' then (charge + gst) else 0 end) as upitotal"),
                    
                    \DB::raw("sum(case when product = 'payin' and status = 'chargeback' and rtype = 'main' and user_id = '".$id."' then 1 else 0 end) as chargebackcount"),
                    \DB::raw("sum(case when product = 'payin' and status = 'chargeback' and rtype = 'main' and user_id = '".$id."' then amount else 0 end) as chargebackamt"),
                    \DB::raw("sum(case when product = 'payin' and status = 'chargeback' and rtype = 'main' and user_id = '".$id."' then charge else 0 end) as chargebackcharge"),
                    \DB::raw("sum(case when product = 'payin' and status = 'chargeback' and rtype = 'main' and user_id = '".$id."' then gst else 0 end) as chargebackgst"),
                    \DB::raw("sum(case when product = 'payin' and status = 'chargeback' and rtype = 'main' and user_id = '".$id."' then (charge + gst) else 0 end) as chargebacktotal"),
                ])->first();

                $data['qrsales'] = \DB::table('qrreports')->whereBetween('created_at' , [
                        Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), 
                        Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')
                    ])->select([
                    \DB::raw("sum(case when status IN ('success', 'pending') and product = 'qrcode' and user_id = '".$id."' then 1 else 0 end) as qrcount"),
                    \DB::raw("sum(case when status IN ('success', 'pending') and product = 'qrcode' and user_id = '".$id."' then option1 else 0 end) as qramt"),
                    \DB::raw("sum(case when status IN ('success', 'pending') and product = 'qrcode' and user_id = '".$id."' then amount else 0 end) as qrcharge"),
                    \DB::raw("sum(case when status IN ('success', 'pending') and product = 'qrcode' and user_id = '".$id."' then gst else 0 end) as qrgst"),
                    \DB::raw("sum(case when status IN ('success', 'pending') and product = 'qrcode' and user_id = '".$id."' then (amount + gst) else 0 end) as qrtotal")
                ])->first();

                $data['payoutsales'] = \DB::table('reports')->whereBetween('created_at' , [
                        Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), 
                        Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')
                    ])->select([
                        \DB::raw("sum(case when status = 'success' and product = 'payout' and user_id = '".$id."' then 1 else 0 end) as payoutcount"),
                        \DB::raw("sum(case when status = 'success' and product = 'payout' and user_id = '".$id."' then amount else 0 end) as payoutamt"),
                        \DB::raw("sum(case when status = 'success' and product = 'payout' and user_id = '".$id."' then charge else 0 end) as payoutcharge"),
                        \DB::raw("sum(case when status = 'success' and product = 'payout' and user_id = '".$id."' then gst else 0 end) as payoutgst"),
                        \DB::raw("sum(case when status = 'success' and product = 'payout' and user_id = '".$id."' then (charge + gst) else 0 end) as payouttotal"),

                        \DB::raw("sum(case when status = 'success' and product = 'upipay' and user_id = '".$id."' then 1 else 0 end) as upipaycount"),
                        \DB::raw("sum(case when status = 'success' and product = 'upipay' and user_id = '".$id."' then amount else 0 end) as upipayamt"),
                        \DB::raw("sum(case when status = 'success' and product = 'upipay' and user_id = '".$id."' then charge else 0 end) as upipaycharge"),
                        \DB::raw("sum(case when status = 'success' and product = 'upipay' and user_id = '".$id."' then gst else 0 end) as upipaygst"),
                        \DB::raw("sum(case when status = 'success' and product = 'upipay' and user_id = '".$id."' then (charge + gst) else 0 end) as upipaytotal"),
                        
                        \DB::raw("sum(case when status = 'success' and option7 = 'failed' and user_id = '".$id."' then 1 else 0 end) as selfpayoutcount"),
                        \DB::raw("sum(case when status = 'success' and option7 = 'failed' and user_id = '".$id."' then amount else 0 end) as selfpayoutamt"),
                        \DB::raw("sum(case when status = 'success' and option7 = 'failed' and user_id = '".$id."' then charge else 0 end) as selfpayoutcharge"),
                        \DB::raw("sum(case when status = 'success' and option7 = 'failed' and user_id = '".$id."' then gst else 0 end) as selfpayoutgst"),
                        \DB::raw("sum(case when status = 'success' and option7 = 'failed' and user_id = '".$id."' then (charge + gst) else 0 end) as selfpayouttotal"),
                        
                        \DB::raw("sum(case when status = 'reversed' and option7 = 'failed' and user_id = '".$id."' then 1 else 0 end) as selfpayoutbackcount"),
                        \DB::raw("sum(case when status = 'reversed' and option7 = 'failed' and user_id = '".$id."' then amount else 0 end) as selfpayoutbackamt"),
                        \DB::raw("sum(case when status = 'reversed' and option7 = 'failed' and user_id = '".$id."' then charge else 0 end) as selfpayoutbackcharge"),
                        \DB::raw("sum(case when status = 'reversed' and option7 = 'failed' and user_id = '".$id."' then gst else 0 end) as selfpayoutbackgst"),
                        \DB::raw("sum(case when status = 'reversed' and option7 = 'failed' and user_id = '".$id."' then (charge + gst) else 0 end) as selfpayoutbacktotal"),

                        \DB::raw("sum(case when status = 'success' and product = 'collect' and user_id = '".$id."' then 1 else 0 end) as virtualcount"),
                        \DB::raw("sum(case when status = 'success' and product = 'collect' and user_id = '".$id."' then amount else 0 end) as virtualamt"),
                        \DB::raw("sum(case when status = 'success' and product = 'collect' and user_id = '".$id."' then charge else 0 end) as virtualcharge"),
                        \DB::raw("sum(case when status = 'success' and product = 'collect' and user_id = '".$id."' then gst else 0 end) as virtualgst"),
                        \DB::raw("sum(case when status = 'success' and product = 'collect' and user_id = '".$id."' then (charge + gst) else 0 end) as virtualtotal"),
                        
                        \DB::raw("sum(case when status = 'success' and product IN ('payout') and user_id = '".$id."' then 1 else 0 end) as payoutsuccess"),
                        \DB::raw("sum(case when status = 'pending' and product IN ('payout') and user_id = '".$id."' then 1 else 0 end) as payoutpending"),
                        \DB::raw("sum(case when status = 'failed' and product IN ('payout') and user_id = '".$id."' then 1 else 0 end) as payoutfailed"),
                        \DB::raw("sum(case when status IN ('success', 'pending') and product IN ('payout') and user_id = '".$id."' then 1 else 0 end) as payoutcounttotal"),
                        
                        \DB::raw("sum(case when status = 'success' and product IN ('upipay') and user_id = '".$id."' then 1 else 0 end) as upipaysuccess"),
                        \DB::raw("sum(case when status = 'pending' and product IN ('upipay') and user_id = '".$id."' then 1 else 0 end) as upipaypending"),
                        \DB::raw("sum(case when status = 'failed' and product IN ('upipay') and user_id = '".$id."' then 1 else 0 end) as upipayfailed"),
                        \DB::raw("sum(case when status IN ('success', 'pending') and product IN ('upipay') and user_id = '".$id."' then 1 else 0 end) as upipaycounttotal")
                ])->first();
                break;

        }
        return response()->json($data);
    }
}
