<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory;

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [];
    }
    
    protected $fillable = ['name','email','mobile','aadharmobile','password','remember_token','lockedwallet','role_id','parent_id','reference','company_id','scheme_id','status','address','shopname','gstin','city','state','pincode','pancard','aadharcard','kyc','resetpwd','account','bank','ifsc','account2','bank2','ifsc2','account3','bank3','ifsc3','apptoken','rrn','amount','tag','via','payin_daily_used'];

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
