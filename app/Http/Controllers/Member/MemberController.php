<?php

namespace App\Http\Controllers\Member;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Circle;
use App\Models\Scheme;
use App\Models\Company;
use App\Models\Provider;
use App\Models\Utiid;
use App\Models\Permission;
use App\Models\User;
use App\Models\Commission;
use App\Models\Packagecommission;
use App\Models\Package;
use App\Models\Userkyc;

class MemberController extends Controller
{
    public $admin;

    public function __construct()
    {
        $this->admin = User::whereHas('role', function ($q){
            $q->where('slug', 'admin');
        })->first();
    }

    public function index($type, $action="view")
    {
        if($action != 'view' && $action != 'create'){
            abort(404);
        }

        $data['role'] = Role::where('slug', $type)->first();
        $data['roles'] = [];
        if($action == "create"){
            $roles = Role::whereIn('slug', ["apiuser"])->get();

            foreach ($roles as $role) {
                if(\Myhelper::can('create_'.$role->slug)){
                    $data['roles'][] = $role;
                }
            }
        }
        
        $data['type']   = $type;
        $data['state']  = Circle::all();
        $data['scheme'] = Scheme::where("user_id", session("loginid"))->get();

        if($action == "view"){
            return view('member.index')->with($data);
        }else{
            return view('member.create')->with($data);
        }
    }

    public function kycManager()
    {
        if(!\Myhelper::can("userkyc_manager")){
            abort(403);
        }
        $data['scheme'] = Scheme::get();
        $types = array(
            'Admin Activity'     => "admin",
            'Resource'    => 'resource',
            'Setup Tools' => 'setup',
            'Member'      => 'member',
            'Member Setting'=> 'memberaction',
            'Member Report' => 'memberreport',
            'Wallet Fund'   => 'fund',
            'Wallet Fund Report' => 'fundreport',
            'Aeps Fund'     => 'aepsfund',
            'Aeps Fund Report'   => 'aepsfundreport',
            'Agents List'   => 'idreport',
            'Portal Services'    => 'service',
            'User Setting'  => 'setting',
            'Transactions'  => 'report',
            'Transactions Status'=> 'reportstatus',
        );

        foreach ($types as $key => $value) {
            $data['permissions'][$key] = Permission::where('type', $value)->orderBy('id', 'ASC')->get();
        }

        return view('member.kyc')->with($data);
    }

    public function create(\App\Http\Requests\Member $post)
    {
        $role = Role::where('id', $post->role_id)->first();

        if(!in_array($role->slug, ["apiuser"])){
            return response()->json(['status' => "Role not allowed"],200);
        }
        
        if(!\Myhelper::can('create_'.$role->slug)){
            return response()->json(['status' => "Permission not allowed"],200);
        }

        $post['id']  = "new";
        $post['parent_id']  = \Auth::user()->id;
        $post['via'] = "portal";
        $post['password']   = bcrypt($post->mobile);
        $post['company_id'] = \Auth::user()->company_id;

        $response = User::updateOrCreate(['id'=> $post->id], $post->all());
        if($response){
            Userkyc::create([
                "user_id" => $response->id
            ]);
            
            $permissions = \DB::table('default_permissions')->where('type', 'permission')->where('role_id', $post->role_id)->get();
            if(sizeof($permissions) > 0){
                foreach ($permissions as $permission) {
                    $insert = array('user_id'=> $response->id , 'permission_id'=> $permission->permission_id);
                    $inserts[] = $insert;
                }
                \DB::table('user_permissions')->insert($inserts);
            }
            return response()->json(['status'=>'success'], 200);
        }else{
            return response()->json(['status'=>'fail'], 400);
        }
    }

    public function getCommission(Request $post)
    {
        $product = [
            "collection" => ['collection'],
            "payout"     => ['payout'],
        ];

        foreach ($product as $key => $value) {
            $data['commission'][$key] = \App\Models\Commission::where('scheme_id', $post->scheme_id)->whereHas('provider', function ($q) use($value){
                $q->whereIn('type' , $value);
            })->get();
        }
        return response()->json(view('member.commission')->with($data)->render());
    }
}
