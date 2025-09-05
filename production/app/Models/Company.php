<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = ['companyname', 'website', 'status', 'type', 'logo', 'senderid', 'smsuser', 'smspwd'];

	public function getUpdatedAtAttribute($value)
    {
        return date('d M y - h:i A', strtotime($value));
    }

    public function getCreatedAtAttribute($value)
    {
        return date('d M y - h:i A', strtotime($value));
    }

    public function setWebsiteAttribute($value)
    {
        $this->attributes['website'] = str_replace("https://", '', str_replace("http://", '', str_replace("www.", '', $value)));
    }
}
