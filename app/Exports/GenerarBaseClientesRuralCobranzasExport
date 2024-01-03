<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Log;
use App\Models\ClientesRuralCobranzas;
use Carbon\Carbon;

class GenerarBaseClientesRuralCobranzasExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    use Exportable;
    public function collection()
    {
        log::info('INICIA GENERACION DE BASE RURAL COBRANZAS');

        $fecha = Carbon::now()->toDateString();
        $clientes = $this->getClientes($fecha);

        $lista = [];

        if (isset($clientes)) {
            foreach ($clientes as $cli){
                $fila = [
                    'cod_cliente'=>trim($cli->cod_cliente),
                    'nro_documento'=>trim($cli->nro_documento),
                    'nom_cliente'=>trim($cli->nom_cliente),
                    'tellab'=>trim($cli->tellab),
                    'telefono'=>trim($cli->telefono),
                    'cel_alternativo'=>trim($cli->cel_alternativo),
                    'cel_lab'=>trim($cli->cel_lab),
                    'cel_part'=>trim($cli->cel_part),
                    'operacion'=>trim($cli->operacion),
                    'segmento'=>'',
                    'producto'=>trim($cli->producto),
                    'tipo_cred_tarj'=>'',
                    'tipo_ope'=>trim($cli->tipo_ope),
                    'tasa'=>'',
                    'saldo_capital'=>trim($cli->saldo_capital),
                    'saldo_interes'=>trim($cli->saldo_interes),
                    'tipo_cambio'=>trim($cli->tipo_cambio),
                    'dias_mora'=>trim($cli->dias_mora),
                    //'nro_cuota'=>trim($cli->nro_cuota),
                    'nro_cuota'=>trim($cli->nro_cuota),
                    //'total_cuotas'=>trim($cli->total_cuotas),
                    'total_cuotas'=>trim($cli->total_cuotas),
                    //'cuotas_pag'=>trim($cli->cuotas_pag),
                    'cuotas_pag'=>trim($cli->cuotas_pag),
                    //'cuotas_pend'=>trim($cli->cuotas_pend),
                    'cuotas_pend'=>trim($cli->cuotas_pend),
                    'monto_mora'=>'',
                    'monto_cuota'=>trim($cli->monto_cuota),
                    'fecha_vto_cuota'=>trim($cli->fecha_vto_cuota),
                    'ult_fech_pago'=>trim($cli->ult_fech_pago),
                    'total_deuda'=>trim($cli->total_deuda),
                    'estado'=>trim($cli->estado),
                    'fec_apertura'=> trim($cli->fec_apertura),
                    'cat_riesgo'=>'',
                    'dato_extra1'=>trim($cli->dato_extra1),
                    'dato_extra2'=>trim($cli->dato_extra2),
                    'dato_extra3'=>trim($cli->dato_extra3),
                    'dato_extra4'=>trim($cli->dato_extra4),
                    //'dato_extra5'=>trim($cli->dato_extra5),
                    'dato_extra5'=>'',
                    'dato_extra6'=>'',
                    'dato_extra7'=>'',
                    'cod_gestor'=>trim($cli->cod_gestor)
                ];
                $lista[]= $fila;

            }
        }

        return collect($lista);
    }

    private function getClientes($fecha){
        $clientes = ClientesRuralCobranzas::all();
        return $clientes;
    }

    public function headings(): array
    {
        return [
            'cod_cliente',
            'nro_documento',
            'nom_cliente',
            'tellab',
            'telefono',
            'cel_alternativo',
            'cel_lab',
            'cel_part',
            'operacion',
            'segmento',
            'producto',
            'tipo_cred_tarj',
            'tipo_ope',
            'tasa',
            'saldo_capital',
            'saldo_interes',
            'tipo_cambio',
            'dias_mora',
            'nro_cuota',
            'total_cuotas',
            'cuotas_pag',
            'cuotas_pend',
            'monto_mora',
            'monto_cuota',
            'fecha_vto_cuota',
            'ult_fech_pago',
            'total_deuda',
            'estado',
            'fec_apertura',
            'cat_riesgo',
            'dato_extra1',
            'dato_extra2',
            'dato_extra3',
            'dato_extra4',
            'dato_extra5',
            'dato_extra6',
            'dato_extra7',
            'cod_gestor'
        ];
    }
}
