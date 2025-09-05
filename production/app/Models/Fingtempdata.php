<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Fingtempdata extends Model
{
	protected $fillable = ['fingpayTransactionId', 'cdPkId', 'merchantTranId', 'mobileNumber', 'beneficiaryName', 'accountNumber', 'transactiontype', 'user_id', 'deviceIMEI', 'transactionAmount'];
}
