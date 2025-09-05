<?php

namespace App\Http\Controllers\PortalSetting;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Scheme;
use App\Models\Company;
use App\Models\Provider;
use App\Models\Commission;
use App\Models\Companydata;
use App\Models\User;

class ResourceController extends Controller
{
    public function index($type)
    {
        switch ($type) {
            case 'commission':
                $product = [
                    'collection' => ['collection'],
                    "payout"     => ['payout']
                ];

                foreach ($product as $key => $value) {
                    $data['commission'][$key] = \App\Models\Commission::where('scheme_id', \Auth::user()->scheme_id)->whereHas('provider', function ($q) use($value){
                        $q->whereIn('type' , $value);
                    })->get();
                }
                break;

            case 'companyprofile':
                $permission = "change_company_profile";
                $data['company'] = Company::where('id', \Auth::user()->company_id)->first();
                $data['companydata'] = Companydata::where('company_id', \Auth::user()->company_id)->first();
                break;

            case 'scheme':
                $permission = "scheme_manager";
                $data['charge']['collection']= Provider::where('type', 'collection')->where('status', "1")->orderBy('name', 'asc')->get();
                $data['charge']['qr'] = Provider::where('type', 'qr')->where('status', "1")->orderBy('name', 'asc')->get();
                $data['charge']['payout']    = Provider::where('type', 'payout')->where('status', "1")->orderBy('name', 'asc')->get();
                $data['charge']['upipay']    = Provider::where('type', 'upipay')->where('status', "1")->orderBy('name', 'asc')->get();
                break;
            
            default:
                abort(404);
                break;
        }
        $data['type'] = $type;

        return view("resource.".$type)->with($data);
    }

    public function update(Request $post)
    {
        switch ($post->actiontype) {
            case 'company':
                $permission = ["company_manager", "change_company_profile"];
                break;

            case 'companydata':
                $permission = "change_company_profile";
                break;
        }

        if (isset($permission) && !\Myhelper::can($permission)) {
            return response()->json(['status' => "Permission Not Allowed"], 400);
        }

        switch ($post->actiontype) {
            case 'scheme':
                $rules = array(
                    'name'    => 'sometimes|required|unique:schemes,name' 
                );
                
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    return response()->json(['errors'=>$validator->errors()], 422);
                }
                $post['user_id'] = \Auth::id();
                $action = Scheme::updateOrCreate(['id'=> $post->id], $post->all());
                if ($action) {
                    return response()->json(['status' => "success"], 200);
                }else{
                    return response()->json(['status' => "Task Failed, please try again"], 200);
                }
                break;

            case 'company':
                $rules = array(
                    'companyname'    => 'sometimes|required'
                );

                if($post->file('logos')){
                    $rules['file'] = 'sometimes|required|mimes:jpg,JPG,jpeg,png|max:500';
                }
                
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    return response()->json(['errors'=>$validator->errors()], 422);
                }
                if($post->id != 'new'){
                    $company = Company::find($post->id);
                }
                
                if($post->hasFile('file')){
                    try {
                        unlink(public_path('/').$company->logo);
                    } catch (\Exception $e) {
                    }
                    $post['logo'] = asset('public')."/".$post->file('file')->store('logos');
                }

                $action = Company::updateOrCreate(['id'=> $post->id], $post->all());
                if ($action) {
                    return response()->json(['status' => "success"], 200);
                }else{
                    return response()->json(['status' => "Task Failed, please try again"], 200);
                }
                break;

            case 'companydata':
                $rules = array(
                    'company_id'    => 'required'
                );
                
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    return response()->json(['errors'=>$validator->errors()], 422);
                }

                $action = Companydata::updateOrCreate(['company_id'=> $post->company_id], $post->all());
                if ($action) {
                    return response()->json(['status' => "success"], 200);
                }else{
                    return response()->json(['status' => "Task Failed, please try again"], 200);
                }
                break;
            
            case 'commission':
                $rules = array(
                    'scheme_id'    => 'sometimes|required|numeric' 
                );
                
                $validator = \Validator::make($post->all(), $rules);
                if ($validator->fails()) {
                    return response()->json(['errors'=>$validator->errors()], 422);
                }

                foreach ($post->slab as $key => $value) {
                    $provider = Commission::where("slab", $post->slab[$key])->where("scheme_id", \Auth::user()->scheme_id)->first();

                    if($provider){
                        if($provider->provider->recharge1 == "payin_commission"){
                            if($provider->apiuser < $post->apiuser[$key]){
                                $update[$value] = "Commission value should be less than ". $provider->apiuser;
                            }else{
                                $update[$value] = Commission::updateOrCreate([
                                    'scheme_id' => $post->scheme_id,
                                    'slab'      => $post->slab[$key]
                                ],[
                                    'scheme_id' => $post->scheme_id,
                                    'slab'      => $post->slab[$key],
                                    'type'      => $provider->type,
                                    'apiuser'   => $post->apiuser[$key],
                                ]);
                            }
                        }else{
                            if($provider->apiuser > $post->apiuser[$key]){
                                $update[$value] = "Charge value should be greater than ". $provider->apiuser;
                            }else{
                                $update[$value] = Commission::updateOrCreate([
                                    'scheme_id' => $post->scheme_id,
                                    'slab'      => $post->slab[$key]
                                ],[
                                    'scheme_id' => $post->scheme_id,
                                    'slab'      => $post->slab[$key],
                                    'type'      => $provider->type,
                                    'apiuser'   => $post->apiuser[$key],
                                ]);
                            }
                        }
                    }
                }
                return response()->json(['status'=>$update], 200);
                break;
            
            default:
                # code...
                break;
        }
    }

    public function getCommission(Request $post , $type)
    {
        return Commission::where('scheme_id', $post->scheme_id)->get()->toJson();
    }
}
