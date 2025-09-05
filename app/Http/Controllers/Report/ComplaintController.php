<?php

namespace App\Http\Controllers\Report;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\Models\Complaint;
use App\Models\Complaintsubject;

class ComplaintController extends Controller
{
    public function index($type, $id="none", $product="none")
    {
        if($id != "none"){
            $data['id'] = $id;
        }
        if($product != "none"){
            $data['product'] = $product;
        }
        
        switch ($type) {
            case 'contact':
                return view("complaints.contact");
                break;

            case 'faq':
                $data['videos'] = \App\Models\PortalSetting::where('code', 'dashboardvideos')->orderBy('id', 'desc')->get();
                return view("complaints.fqa")->with($data);
                break;
            
            default:
                $data["subjects"] = Complaintsubject::get();
                return view("complaints.bbps")->with($data);
                break;
        }
    }

    public function getSubject(Request $post)
    {
        $subjects = Complaintsubject::where("product", $post->product)->get();
        if ($subjects) {
            return response()->json(['statuscode' => "TXN", "data" => $subjects]);
        }else{
            return response()->json(['statuscode' => "ERR", "message" => "Task Failed, please try again"]);
        }
    }

    public function store(Request $post)
    {
        $rules = array(
            'transaction_id' => 'required',
            'product' => 'required',
            'subject' => 'required'
        );
        
        $validator = \Validator::make($post->all(), $rules);
        if ($validator->fails()) {
            foreach ($validator->errors()->messages() as $key => $value) {
                $error = $value[0];
            }
            return response()->json(['statuscode' => "ERR", "message" => $error]);
        }   
        
        if(!$post->has("user_id")){
            $post['user_id'] = session("loginid");
        }

        $action = Complaint::updateOrCreate(['id'=> $post->id], $post->all());
        if ($action) {
            return response()->json(['statuscode' => "TXN"]);
        }else{
            return response()->json(['statuscode' => "ERR", "message" => "Task Failed, please try again"]);
        }
    }
}
