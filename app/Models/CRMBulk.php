<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CRMBulk extends Model
{
    use HasFactory;
    protected $table = 'crm_bulk';
    public $timestamps = false;
}
