<?php

namespace App\Helpers;
use Request;
use App\Models\Activity as LogActivityModel;

class LogActivity
{
    public static function addToLog($subject, $data="")
    {
    	$log = [];
    	$log['subject'] = $subject;
        $log['data']    = json_encode($data);
    	$log['url']     = Request::fullUrl();
    	$log['method']  = Request::method();
    	$log['ip']      = Request::ip();
        $log['gps_location'] = $data->gps_location;
        $log['ip_location']  = $data->ip_location;
    	$log['agent']   = Request::header('user-agent');
    	
    	if(isset($data->user_id)){
    	    $log['user_id'] = $data->user_id;
    	}else{
    	    $log['user_id'] = auth()->check() ? auth()->user()->id : 0;
    	}
    	LogActivityModel::create($log);
    }

    public static function logActivityLists()
    {
    	return LogActivityModel::latest()->get();
    }
}