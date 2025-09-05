<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name','email','mobile','aadharmobile','password','remember_token','lockedwallet','role_id','parent_id','reference','company_id','scheme_id','status','address','shopname','gstin','city','state','pincode','pancard','aadharcard','kyc','resetpwd','qrcode', 'merchant_name', 'vpa'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
    */
    protected $hidden = [
        'password', 'remember_token'
    ];

    public $with = ['role', 'company'];
    protected $appends = ['parents'];

    public function role(){
        return $this->belongsTo('App\Models\Role');
    }
    
    public function company(){
        return $this->belongsTo('App\Models\Company');
    }

    public function getParentsAttribute() {
        $user = User::where('id', $this->parent_id)->first(['id', 'name', 'mobile', 'role_id']);
        if($user){
            return $user->name." (".$user->id.")<br>".$user->mobile."<br>".$user->role->name;
        }else{
            return "Not Found";
        }
    }

    public function getUpdatedAtAttribute($value)
    {
        return date('d M y - h:i A', strtotime($value));
    }
    
    public function getMainwalletAttribute($value)
    {
        return round($value, 2);
    }
    
    public function getaepswalletAttribute($value)
    {
        return round($value, 2);
    }
    
    public function getmatmwalletAttribute($value)
    {
        return round($value, 2);
    }

    public function getCreatedAtAttribute($value)
    {
        return date('d M y - h:i A', strtotime($value));
    }
}
