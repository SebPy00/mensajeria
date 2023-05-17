<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Timbrado extends Model
{
    use HasFactory;

    protected $table = 'fac_timbrado';
    public $timestamps = false;
    protected $primaryKey = 'regnro';

}
