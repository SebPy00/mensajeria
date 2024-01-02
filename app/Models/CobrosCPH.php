<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CobrosCPH extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'cph.cobros';
   // public $timestamps = false;
}
