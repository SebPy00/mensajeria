<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GestionesAlarmasPY extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'alarmaspy.gestiones';
    public $timestamps = false;
}
