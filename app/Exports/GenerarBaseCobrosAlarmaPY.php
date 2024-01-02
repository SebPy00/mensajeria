<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\CobrosAlarmasPY;
use Carbon\Carbon;

class GenerarBaseCobrosAlarmaPY implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    use Exportable;
    public function collection()
    {
        log::info('INICIA GENERACION DE BASE COBROS ALARMAS PY');

        $fecha = Carbon::now()->toDateString();
        $cobros = $this->getCobros($fecha);

        $lista = [];

        if (isset($cobros)) {
            foreach ($cobros as $cob){
                    $fila = [
                        'Numero Documento'=> $cob->nro_documento,
                        'Codigo Cliente DET3'=> $cob->cod_cliente,
                        'Cliente DET3'=> '',
                        'Operacion DET3'=> $cob->operacion,
                        'Cartera DET3'=> $cob->cartera,
                        'Saldo DET3'=> $cob->saldo,
                        'Fecha Pago DET3'=> $cob->fecha_pago,
                        'Monto Pagado DET3'=> $cob->monto_pagado,
                        'Numero Cuota DET3'=> $cob->nro_cuota,
                        'Tipo Operacion DET3'=> $cob->tipo_operacion,
                        'Producto DET3'=> $cob->producto,
                        'Segmento DET3'=>'',
                        'Numero Documento DET3'=> $cob->nro_documento,
                        'Cotizacion del Dolar DET3'=> $cob->cotizacion,
                        'Dias Mora DET3'=> $cob->dias_mora
                    ];
                    $lista[]= $fila;

            }
        }

        return collect($lista);
    }
    private function getCobros($fecha){
        $cobros = CobrosAlarmasPY::where('fecha_insert', $fecha)->get();
        return $cobros;
    }

    public function headings(): array
    {
        return [
            'Numero Documento',
            'Codigo Cliente DET3',
            'Cliente DET3',
            'Operacion DET3',
            'Cartera DET3',
            'Saldo DET3',
            'Fecha Pago DET3',
            'Monto Pagado DET3',
            'Numero Cuota DET3',
            'Tipo Operacion DET3',
            'Producto DET3',
            'Segmento DET3',
            'Numero Documento DET3',
            'Cotizacion del Dolar DET3',
            'Dias Mora DET3'
        ];
    }
}
