<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferenciasCPH extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'cph.referencias';
    public $timestamps = false;
}
