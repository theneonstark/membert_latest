<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BusinessOwner extends Model
{
    protected $fillable = ['user_id','firm_type','firm_name','firm_pan_available','firm_pancard','firm_gst','firm_gst_status','firm_bank','firm_accountname','firm_gst_doc','firm_account','firm_ifsc','firm_pancard_doc', 'no_director', 'director_pancards', 'director_names', 'director_aadharcards', 'director_banks', 'director_accounts', 'director_ifscs', 'director_accountnames'];
}
