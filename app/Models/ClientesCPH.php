<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientesCPH extends Model
{
    use HasFactory;
    protected $connection = 'servicios';
    protected $table = 'cph.clientes';

    //columnas que se pueden llenar masivamente
    // protected $fillable = [
    //     'cod_cliente', 'nro_documento', 'nom_cliente', 'tellab', 'telefono', 'cel_alternativo',
    //     'cel_lab', 'cel_part', 'operacion', 'segmento', 'producto', 'tasa', 'total_saldo',
    //     'saldo_cuota', 'moratorio', 'punitorio', 'gastos_cobranzas', 'iva', 'dias_mora',
    //     'nro_cuota', 'total_cuotas', 'cuotas_pag', 'cuotas_pend', 'monto_cuota',
    //     'fecha_vto_cuota', 'ult_fech_pago', 'total_deuda_cuota', 'total_deuda', 'fecha_valor',
    //     'cod_dist', 'lote', 'tipo_poe', 'situacion', 'fecha_insert',
    // ];

    public $timestamps = false;
}
