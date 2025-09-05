<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Matmorder extends Model
{
	protected $fillable = ['transactionType', 'transactionAmount', 'imei', 'mobileNumber', 'status', 'user_id', 'remark', 'apitxnid', 'latitude', 'longitude', 'merchantLoginId'];
}