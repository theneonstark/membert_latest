<?php
namespace App\Http\Controllers\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index($type="aeps", $id=0)
    {
        $data['type'] = $type;
        $data['id'] = $id;

        switch ($type) {
            case 'ladger':
                return view('statement.ladger')->with($data);
                break;

            case 'invoice':
                return view('statement.invoice')->with($data);
                break;
            
            default:
                return view('statement.transaction')->with($data);
                break;
        }
    }

    public function fetchData(Request $request)
    {
        if($request->has('user_id')){
            $userid = $request->user_id;
        }else{
            $userid = session("loginid");
        }

        $data = [];
        $parentData = \Myhelper::getParents($userid);

        switch ($request->type) {
            case 'payout':
            case 'bulkpayout':
            case 'mypayout':
            case 'upipay':
            case 'payin':
            case 'qrcode':
            case 'oldqrcode':
            case 'chargeback':
            case 'recharge':
            case 'billpay':
            case 'dmt':
            case 'aeps':
            case 'virtual':
                switch($request->type){
                    case 'payout':
                    case 'upipay':
                    case 'mypayout':
                    case 'recharge':
                    case 'billpay':
                    case 'dmt':
                    case 'aeps':
                    case 'virtual':
                        $tables = "reports";
                        //$reporttables = \DB::table("portal_settings")->where("code", "oldreporttable")->get();
                        break;

                    case 'payin':
                    case 'chargeback':
                    case 'qrcode':
                        $tables = "collectionreports";
                        //$reporttables = \DB::table("portal_settings")->where("code", "oldreporttable")->get();
                        break;

                    case 'oldqrcode':
                        $tables = "qrreports";
                        //$reporttables = \DB::table("portal_settings")->where("code", "oldreporttable")->get();
                        break;

                    case 'bulkpayout':
                        $tables = "tempreports";
                        //$reporttables = \DB::table("portal_settings")->where("code", "oldreporttable")->get();
                        break;
                }
                
                // foreach ($reporttables as $reporttable) {
                //     $dates = explode("/", $reporttable->value);
                //     if(Carbon::createFromFormat('Y-m-d', $dates[0])->format('Y-m-d') <= $request->fromdate && 
                //             Carbon::createFromFormat('Y-m-d', $dates[1])->format('Y-m-d') >= $request->fromdate){
                //         $tables = $reporttable->name;
                //     }
                // }

                $query = \DB::table($tables)->leftJoin('users', 'users.id', '=', $tables.'.user_id')
                        ->leftJoin('apis', 'apis.id', '=', $tables.'.api_id')
                        ->leftJoin('providers', 'providers.id', '=', $tables.'.provider_id')
                        ->orderBy($tables.'.id', 'desc')
                        ->where($tables.'.rtype', "main");

                switch ($request->type) {
                    case 'recharge':
                    case 'billpay':
                    case 'dmt':
                    case 'aeps':
                        $query->where($tables.'.product', 'recharge');
                        break;

                    case 'virtual':
                        $query->where($tables.'.product', 'collect');
                        break;

                    case 'payout':
                        $query->where($tables.'.product', 'payout');
                        break;

                    case 'upipay':
                        $query->where($tables.'.product', 'upipay');
                        break;

                    case 'mypayout':
                        $query->where($tables.'.product', 'payout');
                        break;

                    case 'payin':
                        $query->where($tables.'.product', 'payin')->where("credit_by", "!=", "1");
                        break;

                    case 'qrcode':
                        $query->where($tables.'.product', 'qrcode');
                        break;

                    case 'chargeback':
                        $query->where($tables.'.status', 'chargeback');
                        break;
                }
                $query->whereIn($tables.'.user_id', $parentData);

                $dateFilter = 1;
                if(!empty($request->searchtext)){
                    $serachDatas = ['number', 'txnid', 'apitxnid', 'refno'];
                    $query->where( function($q) use($request, $serachDatas, $tables){
                        foreach ($serachDatas as $value) {
                            $q->orWhere($tables.".".$value , 'like', '%'.$request->searchtext.'%');
                        }
                    });
                    $dateFilter = 0;
                }

                if(isset($request->product) && !empty($request->product)){
                    $query->where($tables.'.api_id', $request->product);
                    $dateFilter = 0;
                }
                
                if(isset($request->status) && $request->status != '' && $request->status != null){
                    $query->where($tables.'.status', $request->status);
                    $dateFilter = 0;
                }

                if((isset($request->fromdate) && !empty($request->fromdate)) && (isset($request->todate) && !empty($request->todate))){
                    if($request->fromdate == $request->todate){
                        $query->whereDate($tables.'.created_at','=', Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'));
                    }else{
                        $query->whereBetween($tables.'.created_at', [Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')]);
                    }
                }elseif($dateFilter && isset($request->fromdate) && !empty($request->fromdate)){
                    $query->whereDate($tables.'.created_at','=', Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'));
                }

                $selects = ['id','mobile' ,'number', 'apitxnid', 'txnid', 'api_id', 'amount', 'profit', 'charge','tds', 'gst', 'payid', 'refno', 'balance', 'status', 'rtype', 'trans_type', 'user_id', 'credit_by', 'created_at', 'product', 'remark','option1', 'option3', 'option2', 'option5','closing'];

                $selectData = [];
                foreach ($selects as $select) {
                    $selectData[] = $tables.".".$select;
                }

                $selectData[] = 'users.name as username';
                $selectData[] = 'users.mobile as usermobile';
                $selectData[] = 'users.shopname as usershop';
                $selectData[] = 'apis.name as apiname';
                $selectData[] = 'providers.name as providername';

                $exportData = $query->select($selectData);  
                $count = intval($exportData->count());

                if(isset($request['length'])){
                    $exportData->skip($request['start'])->take($request['length']);
                }

                $data = array(
                    "table"           => $tables,
                    "draw"            => intval($request['draw']),
                    "recordsTotal"    => $count,
                    "recordsFiltered" => $count,
                    "data"            => $exportData->get()
                );
                break;
            
            case 'collectionwallet':
            case 'mainwallet':
            case 'qrwallet':
            case 'rrwallet':
                switch($request->type){
                    case 'collectionwallet':
                        $tables = "collectionreports";
                        break;

                    case 'mainwallet':
                        $tables = "reports";
                        break;

                    case 'qrwallet':
                        $tables = "qrreports";
                        break;

                    case 'rrwallet':
                        $tables = "rrreports";
                        break;
                }

                $query = \DB::table($tables)->leftJoin('users', 'users.id', '=', $tables.'.credit_by')
                        ->leftJoin('providers', 'providers.id', '=', $tables.'.provider_id')
                        ->orderBy($tables.'.id', 'desc');
                $query->where($tables.'.user_id', $userid);

                $dateFilter = 1;
                if(!empty($request->searchtext)){
                    $serachDatas = ['number', 'txnid', 'apitxnid', 'refno'];
                    $query->where( function($q) use($request, $serachDatas, $tables){
                        foreach ($serachDatas as $value) {
                            $q->orWhere($tables.".".$value , 'like', '%'.$request->searchtext.'%');
                        }
                    });
                    $dateFilter = 0;
                }

                if((isset($request->fromdate) && !empty($request->fromdate)) && (isset($request->todate) && !empty($request->todate))){
                    if($request->fromdate == $request->todate){
                        $query->whereDate($tables.'.created_at','=', Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'));
                    }else{
                        $query->whereBetween($tables.'.created_at', [Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')]);
                    }
                }elseif($dateFilter && isset($request->fromdate) && !empty($request->fromdate)){
                    $query->whereDate($tables.'.created_at','=', Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'));
                }

                $selects = ['id','mobile' ,'number', 'apitxnid', 'txnid', 'api_id', 'amount', 'profit', 'charge','tds', 'gst', 'payid', 'refno', 'balance', 'status', 'rtype', 'trans_type', 'user_id', 'credit_by', 'created_at', 'product', 'remark','option1', 'option3', 'option2', 'option5','closing'];

                $selectData = [];
                foreach ($selects as $select) {
                    $selectData[] = $tables.".".$select;
                }

                $selectData[] = 'users.name as username';
                $selectData[] = 'users.shopname as usershop';
                $selectData[] = 'users.mobile as usermobile';
                $selectData[] = 'providers.name as providername';

                
                $exportData = $query->select($selectData);   
                $count = intval($exportData->count());

                if(isset($request['length'])){
                    $exportData->skip($request['start'])->take($request['length']);
                }

                $data = array(
                    "table"           => $tables,
                    "draw"            => intval($request['draw']),
                    "recordsTotal"    => $count,
                    "recordsFiltered" => $count,
                    "data"            => $exportData->get()
                );
                break;
                
            case 'complaint':
                $table = "complaints";
                $query = \DB::table($table)
                        ->leftJoin('users as user', 'user.id', '=', $table.'.user_id')
                        ->orderBy($table.'.id', 'desc');

                $query->whereIn($table.'.user_id', $parentData);
                switch($request->type){
                    case 'pendingComplaint':
                        $query->where($table.".status", "pending");
                        break;
                }

                if(!empty($request->searchtext)){
                    $serachDatas = ['transaction_id'];
                    $query->where( function($q) use($request, $serachDatas, $table){
                        foreach ($serachDatas as $value) {
                            $q->orWhere($table.".".$value , $request->searchtext);
                        }
                    });
                }

                $selects = ['*'];

                foreach ($selects as $select) {
                    $selectData[] = $table.".".$select;
                }
                $selectData[] = 'user.name as username';
                $selectData[] = 'user.mobile as usermobile';
                $exportData   = $query->select($selectData);
                $count = intval($query->count());

                if(isset($request['length'])){
                    $exportData->skip($request['start'])->take($request['length']);
                }

                $data = array(
                    "table"           => $table,
                    "draw"            => intval($request['draw']),
                    "recordsTotal"    => $count,
                    "recordsFiltered" => $count,
                    "data"            => $exportData->get()
                );
                break;
                
            case 'invoice':
                $table = "invoices";
                $query = \DB::table($table)
                        ->leftJoin('users as user', 'user.id', '=', $table.'.user_id')
                        ->orderBy($table.'.id', 'desc')
                        ->where($table.".user_id", $userid);

                // if((isset($request->fromdate) && !empty($request->fromdate)) && (isset($request->todate) && !empty($request->todate))){
                //     if($request->fromdate == $request->todate){
                //         $query->whereDate($tables.'.created_at','=', Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'));
                //     }else{
                //         $query->whereBetween($tables.'.created_at', [Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')]);
                //     }
                // }elseif(isset($request->fromdate) && !empty($request->fromdate)){
                //     $query->whereDate($tables.'.created_at','=', Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'));
                // }

                $selects = ['*'];

                foreach ($selects as $select) {
                    $selectData[] = $table.".".$select;
                }
                $selectData[] = 'user.name as username';
                $selectData[] = 'user.mobile as usermobile';
                $exportData   = $query->select($selectData);
                $count = intval($query->count());

                if(isset($request['length'])){
                    $exportData->skip($request['start'])->take($request['length']);
                }

                $data = array(
                    "table"           => $table,
                    "draw"            => intval($request['draw']),
                    "recordsTotal"    => $count,
                    "recordsFiltered" => $count,
                    "data"            => $exportData->get()
                );
                break;
        }
        echo json_encode($data);
    }
}
