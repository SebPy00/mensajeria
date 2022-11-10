<?php

namespace App\Imports;

use App\Models\EnvioMensajesDetalle;
use Maatwebsite\Excel\Concerns\ToModel;

class EnvioMensajeDetalleImport implements ToModel
{
    /**
    * @param array $row
    *
    * @return \Illuminate\Database\Eloquent\Model|null
    */
    public function model(array $row)
    {
        return new EnvioMensajesDetalle([
            'idenviomensaje'     => $row[0],
            'nrotelefono'    => $row[1],
            'enviado'    => $row[2],
            'intentos'    => $row[3],
            'ci' =>$row[4],
        ]);
    }
}
