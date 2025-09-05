<?php

namespace App\Http\Controllers\Server;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Aepsreport;
use App\Models\Aepsfundrequest;
use App\Models\Microatmfundrequest;
use App\Models\Microatmreport;
use App\Models\Api;
use App\Models\Provider;
use App\Models\Fingagent;

class CronController extends Controller
{
    public function sessionClear()
  	{
	    \DB::table('sessions')->where('last_activity' , '<', time()- 1800)->delete();
  	}
  	
  	public function passwordClear()
  	{
	    \DB::table('password_resets')->where('activity', "!=", "login")->where('last_activity' , '<', time()-1800)->delete();
  	}
  	
  	public function loginOtpClear()
  	{
	    \DB::table('password_resets')->where('activity', "login")->delete();
  	}

  	public function otpClear()
  	{
  		User::where('otpverify', '!=', 'yes')->update(['otpverify' => "yes", 'otpresend' => 0]);
  	}

  	public function aepsauth()
  	{
  		\DB::table('fingagents')->whereupdate(['auth' => "need"]);
  	}
}
