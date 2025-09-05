<?php

namespace App\Http\Controllers\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use App\Models\User;
use App\Models\Fundreport;
use App\Exports\ReportExport;

class StatementController extends Controller
{
    public function export(Request $post, $type)
    {
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '-1');
        
        $parentData = \Myhelper::getParents(session("loginid"));

        switch ($type) {
            case 'fundrequest':
            case 'fundaddmoney':
                $table = "fundreports";
                $query = \DB::table($table)
                        ->leftJoin('users as user', 'user.id', '=', $table.'.user_id')
                        ->leftJoin('users as sender', 'sender.id', '=', $table.'.credited_by')
                        ->leftJoin('fundbanks as fundbank', 'fundbank.id', '=', $table.'.fundbank_id')
                        ->orderBy($table.'.id', 'desc');

                if($type == "fundreport"){
                    if(!empty($post->agent) && in_array($post->agent, $parentData)){
                        $query->where($table.'.user_id', $post->agent);
                    }else{
                        if(\Myhelper::hasRole(['whitelable', 'md', 'distributor','retaillite', 'retailer', 'apiuser'])){
                            $query->whereIntegerInRaw($table.'.user_id', $parentData);
                        }
                    }
                }else{
                    $query->where($table.'.user_id', session("loginid"));
                }

                $dateFilter = 1;

                if($type == "fundrequest"){
                    $dateFilter = 0;
                }

                if(!empty($post->searchtext)){
                    $serachDatas = ['ref_no', 'amount', 'id'];
                    $query->where( function($q) use($request, $serachDatas, $table){
                        foreach ($serachDatas as $value) {
                            $q->orWhere($table.".".$value , $post->searchtext);
                        }
                    });
                    $dateFilter = 0;
                }

                if(isset($post->product) && !empty($post->product) && $post->product != '' && $post->product != null){
                    $query->where($table.'.type', $post->product);
                    $dateFilter = 0;
                }

                if(isset($post->status) && $post->status != '' && $post->status != null){
                    $query->where($table.'.status', $post->status);
                    $dateFilter = 0;
                }

                if((isset($post->fromdate) && !empty($post->fromdate)) && (isset($post->todate) && !empty($post->todate))){
                    if($post->fromdate == $post->todate){
                        $query->whereDate($table.'.created_at','=', Carbon::createFromFormat('Y-m-d', $post->fromdate)->format('Y-m-d'));
                    }else{
                        $query->whereBetween($table.'.created_at', [Carbon::createFromFormat('Y-m-d', $post->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $post->todate)->addDay(1)->format('Y-m-d')]);
                    }
                }elseif($dateFilter && isset($post->fromdate) && !empty($post->fromdate)){
                    $query->whereDate($table.'.created_at','=', Carbon::createFromFormat('Y-m-d', $post->fromdate)->format('Y-m-d'));
                }

                $titles = ['Id', 'Date' , 'Amount', 'Ref No', 'Payment Bank Name', 'Payment Acoount Number', 'Pay Date', 'Status', 'Requested Via Name', 'Requested Via Mobile', 'Approved By Name', 'Approved By Mobile'];

                $selectData[] = $table.".id";
                $selectData[] = $table.".created_at";
                $selectData[] = $table.".amount";
                $selectData[] = $table.".ref_no";
                $selectData[] = 'fundbank.name as bankname';
                $selectData[] = 'fundbank.account as bankaccount';
                $selectData[] = $table.".paydate";
                $selectData[] = $table.".status";
                $selectData[] = 'sender.name as sendername';
                $selectData[] = 'sender.mobile as sendermobile';
                $selectData[] = 'user.name as username';
                $selectData[] = 'user.mobile as usermobile';
                $excelData = $query->select($selectData)->get()->toArray();

                $exportData[] = $titles;
                $exportData[] = json_decode(json_encode($excelData), true);
                
                $export = new ReportExport($exportData);
                return \Excel::download($export, $type.date('d-m-Y').'.csv');
                break;

            case 'fundall':
                $table = "reports";
                $query = \DB::table($table)->leftJoin('users as user', 'user.id', '=', $table.'.user_id')
                        ->leftJoin('users as sender', 'sender.id', '=', $table.'.credit_by')
                        ->leftJoin('apis as api', 'api.id', '=', $table.'.api_id')
                        ->where('api.code', 'fund')
                        ->orderBy($table.'.id', 'desc');

                if(!empty($post->agent) && in_array($post->agent, $parentData)){
                    $query->where($table.'.user_id', $post->agent);
                }else{
                    $query->where($table.'.user_id', session("loginid"));
                }

                $dateFilter = 1;

                if(!empty($post->searchtext)){
                    $serachDatas = ['amount', 'number', 'mobile','credit_by'];
                    $query->where( function($q) use($request, $serachDatas, $table){
                        foreach ($serachDatas as $value) {
                            $q->orWhere($table.".".$value , $post->searchtext);
                        }
                    });
                    $dateFilter = 0;
                }

                if(isset($post->product) && !empty($post->product) && $post->product != '' && $post->product != null){
                    $query->where($table.'.type', $post->product);
                    $dateFilter = 0;
                }

                if(isset($post->status) && $post->status != '' && $post->status != null){
                    $query->where($table.'.status', $post->status);
                    $dateFilter = 0;
                }

                if((isset($post->fromdate) && !empty($post->fromdate)) && (isset($post->todate) && !empty($post->todate))){
                    if($post->fromdate == $post->todate){
                        $query->whereDate($table.'.created_at','=', Carbon::createFromFormat('Y-m-d', $post->fromdate)->format('Y-m-d'));
                    }else{
                        $query->whereBetween($table.'.created_at', [Carbon::createFromFormat('Y-m-d', $post->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $post->todate)->addDay(1)->format('Y-m-d')]);
                    }
                }elseif($dateFilter && isset($post->fromdate) && !empty($post->fromdate)){
                    $query->whereDate($table.'.created_at','=', Carbon::createFromFormat('Y-m-d', $post->fromdate)->format('Y-m-d'));
                }

                $titles = ['Order Id', 'Date', 'Payment Type', 'Amount', 'Ref No', 'Status', 'Remarks', 'Requested Via Name', 'Requested Via Mobile', 'Approved By Name', 'Approved By Mobile'];

                $selectData[] = $table.".id";
                $selectData[] = $table.".created_at";
                $selectData[] = $table.".product";
                $selectData[] = $table.".amount";
                $selectData[] = $table.".refno";
                $selectData[] = $table.".status";
                $selectData[] = $table.".remark";
                $selectData[] = 'user.name as username';
                $selectData[] = 'user.mobile as usermobile';
                $selectData[] = 'sender.name as sendername';
                $selectData[] = 'sender.mobile as sendermobile';
                $excelData = $query->select($selectData)->get()->toArray();

                $exportData[] = $titles;
                $exportData[] = json_decode(json_encode($excelData), true);
                
                $export = new ReportExport($exportData);
                return \Excel::download($export, $type.date('d-m-Y').'.csv');

                break;
        }
    }
}
