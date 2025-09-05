<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DmtBeneficiary extends Model
{
    protected $fillable = ['benename', 'beneaccount', 'beneifsc', 'benebank', 'benebankid', 'benemobile', 'rid', 'mobile', 'status', 'rdmt', 'pdmt', 'bdmt'];
}
