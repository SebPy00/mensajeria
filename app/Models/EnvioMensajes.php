<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;


class EnvioMensajes extends Model
{
    use HasFactory;

    protected $table = 'envio_mensajes';

    public function detalles() {

        return $this->hasMany(EnvioMensajesDetalle::class, 'idenviomensaje');
    }

}
