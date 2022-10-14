<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FrecuenciaEnvioCorreo extends Model
{
    use HasFactory;

    protected $table = 'frecuencia_envio_empresa';

    public function empresa()
    {
        return $this->belongsTo(CodigoEmpresa::class, 'codemp');
    }

    public function frecuencia()
    {
        return $this->belongsTo(Frecuencia::class, 'idfrecuencia');
    }

}
