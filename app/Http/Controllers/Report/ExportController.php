<?php

namespace App\Http\Controllers\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Exports\ReportExport;
use App\Models\User;

class ExportController extends Controller
{
    public $admin;
    public function __construct()
    {
        $this->admin = User::whereHas('role', function ($q){
            $q->where('slug', 'admin');
        })->first();
    }

    public function export(Request $request, $type)
    {
        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', 600);
        $data   = [];
        $userid = session("loginid");
        $parentData = \Myhelper::getParents($userid);

        if($request->has("todate") && (empty($request->todate) || $request->todate == "" || $request->todate == "undefined")){
            $request['todate'] = $request->fromdate;
        }

        switch ($type) {
            case 'payout':
            case 'mypayout':
            case 'payin':
            case 'qrcode':
            case 'chargeback':
                switch($type){
                    case 'payout':
                    case 'mypayout':
                        $table = "reports";
                        //$reporttable = \DB::table("portal_settings")->where("code", "oldreporttable")->get();
                        break;

                    case 'payin':
                    case 'chargeback':
                        $table = "collectionreports";
                        //$reporttable = \DB::table("portal_settings")->where("code", "oldreporttable")->get();
                        break;

                    case 'qrcode':
                        $table = "qrreports";
                        //$reporttable = \DB::table("portal_settings")->where("code", "oldreporttable")->get();
                        break;
                }

                $query = \DB::table($table)
                        ->leftJoin('users', 'users.id', '=', $table.'.user_id')
                        ->leftJoin('apis', 'apis.id', '=', $table.'.api_id')
                        ->leftJoin('providers', 'providers.id', '=', $table.'.provider_id')
                        ->orderBy($table.'.id', 'desc')
                        ->where($table.'.rtype', "main")
                        ->whereIn($table.'.user_id', $parentData);;

                switch ($request->type) {
                    case 'payout':
                        $query->where($table.'.product', 'payout')->where("via", "api");
                        break;

                    case 'mypayout':
                        $query->where($table.'.product', 'payout')->where("via", "portal");
                        break;

                    case 'payin':
                        $query->where($table.'.product', 'payin')->where("credit_by", "!=", "1");
                        break;
                }

                if(isset($request->status) && $request->status != '' && $request->status != null){
                    $query->where($table.'.status', $request->status);
                    $dateFilter = 0;
                }
                $query->whereBetween($table.'.created_at', [Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')]);

                $selects = [
                    $table.'.id', 
                    'providers.name as providername',
                    $table.'.mobile', 
                    $table.'.number', 
                    $table.'.apitxnid' , 
                    $table.'.txnid' , 
                    $table.'.payid' , 
                    $table.'.refno' , 
                    $table.'.amount',
                    $table.'.charge', 
                    $table.'.gst', 
                    $table.'.profit', 
                    $table.'.tds'   , 
                    $table.'.status', 
                    $table.'.trans_type', 
                    $table.'.option1', 
                    $table.'.user_id', 
                    'users.name as username', 
                    'users.mobile as usermobile', 
                    $table.'.remark', 
                    $table.'.created_at'
                ];

                $selects[] = $table.'.api_id';
                $selects[] = 'apis.name as apiname';
                
                $titles = [
                    'Id', 
                    'Provider',
                    'Number',
                    'Mobile', 
                    'Api Txnid', 
                    'Txnid', 
                    'Payid', 
                    'Refno', 
                    'Amount', 
                    'Charge',
                    'Gst',
                    'Profit', 
                    'Tds', 
                    'Status',  
                    'Type',
                    'Product',
                    'Agent Id', 
                    'Agent Name', 
                    'Agent Mobile' , 
                    'Remark',
                    'Craete Time',
                ];

                $titles[] = "Api Id";
                $titles[] = 'Api Name';
                $exportData = $query->select($selects)->get()->toArray();
                break;

            case 'collectionwallet':
            case 'mainwallet':
            case 'qrwallet':
            case 'rrwallet':
                switch($type){
                    case 'collectionwallet':
                        $table = "collectionreports";
                        break;

                    case 'mainwallet':
                        $table = "reports";
                        break;

                    case 'qrwallet':
                        $table = "qrreports";
                        break;

                    case 'rrwallet':
                        $table = "rrreports";
                        break;
                }

                $query = \DB::table($table)
                ->leftJoin('users', 'users.id', '=', $table.'.user_id')
                ->leftJoin('apis', 'apis.id', '=', $table.'.api_id')
                ->leftJoin('users as sender', 'sender.id', '=', $table.'.credit_by')
                ->leftJoin('providers', 'providers.id', '=', $table.'.provider_id')
                ->orderBy($table.'.id', 'desc');

                if((isset($request->fromdate) && !empty($request->fromdate)) && (isset($request->todate) && !empty($request->todate))){
                    $query->whereBetween($table.'.created_at', [Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')]);
                }
                $query->whereIn($table.'.user_id', $parentData);

                $selects = [
                    $table.'.id', 
                    $table.'.created_at',
                    $table.'.user_id', 
                    'users.name as username', 
                    'users.mobile as usermobile', 
                    'sender.name as sendername', 
                    'sender.mobile as sendermobile', 
                    'providers.name as providername',
                    $table.'.number', 
                    $table.'.txnid' , 
                    $table.'.refno' , 
                    $table.'.trans_type', 
                    $table.'.balance', 
                    $table.'.amount',
                    $table.'.charge', 
                    $table.'.profit', 
                    $table.'.tds'   , 
                    $table.'.closing'   
                ];

                $titles = [
                    'Id', 
                    'Date',
                    'Agent Id', 
                    'Agent Name', 
                    'Agent Mobile' , 
                    'Sender Name', 
                    'Sender Mobile' , 
                    'Provider',
                    'Number', 
                    'Txnid', 
                    'Refno', 
                    'Sttatement Type',
                    'Opening Balance',
                    'Amount', 
                    'Charge',
                    'Profit', 
                    'Tds', 
                    'Closing Balance'
                ];
                $exportData = $query->select($selects)->get()->toArray();
                break;

            case 'tdsreport':
                if(empty($request->todate)){
                    $request['todate'] = $request->fromdate;
                }

                $exportData = array();
                $aepsData = \DB::table('aepsreports')
                    ->leftJoin('users', 'users.id', '=', 'aepsreports.user_id')
                    ->whereBetween('aepsreports.created_at', [Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')])
                    ->where('aepsreports.status', 'success')
                    ->groupBy('aepsreports.user_id')
                    ->select(\DB::raw('users.id as userid, users.name as username, users.mobile as usermobile,users.pancard as userpan, sum(tds) as tds'));

                $matmData  = \DB::table('microatmreports')
                    ->leftJoin('users', 'users.id', '=', 'microatmreports.user_id')
                    ->whereBetween('microatmreports.created_at', [Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')])
                    ->where('microatmreports.status', 'success')
                    ->groupBy('microatmreports.user_id')
                    ->select(\DB::raw('users.id as userid, users.name as username, users.mobile as usermobile,users.pancard as userpan, sum(tds) as tds'));

                $exportData = \DB::table('reports')
                    ->leftJoin('users', 'users.id', '=', 'reports.user_id')
                    ->whereBetween('reports.created_at', [Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')])
                    ->where('reports.status', 'success')
                    ->groupBy('reports.user_id')
                    ->select(\DB::raw('users.id as userid, users.name as username, users.mobile as usermobile,users.pancard as userpan, sum(tds) as tds'))->unionAll($aepsData)->unionAll($matmData)->get()->toArray();

                $titles = ['Agent Id', 'Agent Name', 'Agent Mobile', 'Agent Pancard', 'Tds'];
                break;
        }

        $excelData[] = $titles;
        $excelData[] = json_decode(json_encode($exportData), true);
        
        $export = new ReportExport($excelData);
        return \Excel::download($export, $type.'.csv');
    }
}
