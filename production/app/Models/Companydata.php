<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Companydata extends Model
{
    protected $fillable = ['news', 'notice', 'company_id', 'number', 'email', 'billnotice'];
    public $timestamps = false;
}
