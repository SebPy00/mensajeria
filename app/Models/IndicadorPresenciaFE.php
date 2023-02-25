<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IndicadorPresenciaFE extends Model
{
    use HasFactory;
    protected $table = 'factura_electronica_indicador_presencia';
    public $timestamps = false;
}
