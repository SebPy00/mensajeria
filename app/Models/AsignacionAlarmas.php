<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AsignacionAlarmas extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'alarmaspy.asignacion';
    public $timestamps = false;


}
