<?php

namespace App\Http\Controllers\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CommonController extends Controller
{

    public function fetchData(Request $request, $type, $id=0, $returntype="all")
	{
		$request['return']     = 'all';
		$request['returntype'] = $returntype;
		switch ($type) {
			case 'apitoken':
				$request['table']  = '\App\Models\ApiCredential';
				$request['searchdata'] = ['user_id'];
				$request['select'] = ['api_key', 'id', 'user_id'];
				$request['order']  = ['id','DESC'];
				$request['parentData'] = [session("loginid")];
				$request['whereIn']    = 'user_id';
				break;

			case 'whitelistedip':
				$request['table']  = '\App\Models\ApiWhitelistedIp';
				$request['searchdata'] = ['user_id'];
				$request['select'] = ['ip', 'user_id', 'id'];
				$request['order']  = ['id','DESC'];
				$request['parentData'] = [session("loginid")];
				$request['whereIn']    = 'user_id';
				break;

			case 'apiuser':
				$request['table']= '\App\Models\User';
				$request['searchdata'] = ['name', 'mobile','email'];
				$request['select'] = 'all';
				$request['order'] = ['id','DESC'];
				$request['parentData'] = [\Auth::id()];
				$request['whereIn'] = 'parent_id';
			break;

			case 'resourcescheme':
				$request['table']= '\App\Models\Scheme';
				$request['searchdata'] = ['name', 'user_id'];
				$request['select'] = 'all';
				$request['order'] = ['id','DESC'];
				$request['parentData'] = [\Auth::id()];
				$request['whereIn'] = 'user_id';
				break;

			default:
				# code...
				break;
        }
        
		$request['where']=0;
		$request['type']= $type;
        
		try {
			$totalData = $this->getData($request, 'count');
		} catch (\Exception $e) {
			$totalData = 0;
		}

		if ((isset($request->searchtext) && !empty($request->searchtext)) ||
           	(isset($request->todate) && !empty($request->todate))       ||
           	(isset($request->product) && !empty($request->product))       ||
           	(isset($request->status) && $request->status != '')		  ||
           	(isset($request->agent) && !empty($request->agent))
         ){
	        $request['where'] = 1;
	    }

		try {
			$totalFiltered = $this->getData($request, 'count');
		} catch (\Exception $e) {
			$totalFiltered = 0;
		}

		try {
			$data = $this->getData($request, 'data');
		} catch (\Exception $e) {
			$data = [];
		}
		
		if ($request->return == "all" || $returntype =="all") {
			$json_data = array(
				"draw"            => intval( $request['draw'] ),
				"recordsTotal"    => intval( $totalData ),
				"recordsFiltered" => intval( $totalFiltered ),
				"data"            => $data
			);
			echo json_encode($json_data);
		}else{
			return response()->json($data);
		}
	}

	public function getData($request, $returntype)
	{ 
		$table = $request->table;
		$data = $table::query();
		$data->orderBy($request->order[0], $request->order[1]);

		if($request->parentData != 'all'){
			if(!is_array($request->whereIn)){
				$data->whereIn($request->whereIn, $request->parentData);
			}else{
				$data->where(function ($query) use($request){
					$query->where($request->whereIn[0] , $request->parentData)
					->orWhere($request->whereIn[1] , $request->parentData);
				});
			}
		}

		if(
			$request->type != "apitoken" &&
			$request->type != "resourcescheme" &&
			!in_array($request->type , ['apiuser']) &&
			$request->where != 1
        ){
            if(!empty($request->fromdate)){
                $data->whereDate('created_at', $request->fromdate);
            }
	    }

        switch ($request->type) {
			case 'apiuser':
				$data->whereHas('role', function ($q) use($request){
					$q->where('slug', $request->type);
				});
			break;
        }

		if ($request->where) {
	        if((isset($request->fromdate) && !empty($request->fromdate)) 
	        	&& (isset($request->todate) && !empty($request->todate))){
	        	    
	        	if(!in_array($request->type, ['websession', 'appsession'])){
		            if($request->fromdate == $request->todate){
		                $data->whereDate('created_at','=', Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'));
		            }else{
		                $data->whereBetween('created_at', [Carbon::createFromFormat('Y-m-d', $request->fromdate)->format('Y-m-d'), Carbon::createFromFormat('Y-m-d', $request->todate)->addDay(1)->format('Y-m-d')]);
		            }
	        	}
	        }

	        if(isset($request->status) && $request->status != '' && $request->status != null){
	        	switch ($request->type) {
					default:
	            		$data->where('status', $request->status);
					break;
				}
			}
			
			if(isset($request->agent) && !empty($request->agent)){
	        	switch ($request->type) {
					default:
						$data->whereIn('user_id', [$request->agent]);
					break;
				}
	        }

	        if(!empty($request->searchtext)){
	            $data->where( function($q) use($request){
	            	foreach ($request->searchdata as $value) {
                  		$q->orWhere($value,'like','%'.$request->searchtext.'%');
	            	}
				});
	        } 
      	}
		
		if ($request->return == "all" || $request->returntype == "all") {
			if($returntype == "count"){
				return $data->count();
			}else{
				if($request['length'] != -1){
					$data->skip($request['start'])->take($request['length']);
				}

				if($request->select == "all"){
					return $data->get();
				}else{
					return $data->select($request->select)->get();
				}
			}
		}else{
			if($request->select == "all"){
				return $data->first();
			}else{
				return $data->select($request->select)->first();
			}
		}
	}

	public function agentFilter($post)
	{
		if (in_array($post->agent, session('parentData'))) {
			return \Myhelper::getParents($post->agent);
		}else{
			return [];
		}
	}
}
