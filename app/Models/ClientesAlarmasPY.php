<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientesAlarmasPY extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'alarmaspy.datos_clientes';
    public $timestamps = false;

    public function asignacion() {
        return $this->belongsTo(AsignacionAlarmas::class, 'nro_documento', 'nro_documento');
    }
}
