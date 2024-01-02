<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionMensajes extends Model
{
    use HasFactory;

    protected $table = 'configuracion_mensajes';
    
    public $timestamps = false;
}
