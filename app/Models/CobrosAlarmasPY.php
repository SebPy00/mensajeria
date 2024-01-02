<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CobrosAlarmasPY extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'alarmaspy.datos_cobros';
    public $timestamps = false;
}
