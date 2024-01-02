<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CountryCodes extends Model
{
    use HasFactory;

    use HasFactory;
   // protected $connection = 'servicios';
    protected $table = 'country_codes';
    public $timestamps = false;
}
