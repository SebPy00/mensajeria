<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CobrosVistaCPH extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'cph.basecobros';
   // public $timestamps = false;
}
