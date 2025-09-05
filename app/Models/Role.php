<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $fillable = ['name', 'slug'];
    public $appends = ['scheme']; 

	public function getUpdatedAtAttribute($value)
    {
        return date('d M y - h:i A', strtotime($value));
    }

    public function getCreatedAtAttribute($value)
    {
        return date('d M y - h:i A', strtotime($value));
    }

    public function getSchemeAttribute($value)
    {
        $scheme  = \DB::table('default_permissions')->where('type', 'scheme')->where('role_id', $this->id)->first();
        if($scheme){
            return $scheme->permission_id;
        }else{
            return "";
        }
    }
}
