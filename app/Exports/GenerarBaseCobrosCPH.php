<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\CobrosVistaCPH;
use Carbon\Carbon;

class GenerarBaseCobrosCPH implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    use Exportable;
    public function collection()
    {
        log::info('INICIA GENERACION DE BASE COBROS RURAL COBRANZAS');

        $fecha = Carbon::now()->toDateString();
        $cobros = $this->getCobros($fecha);

        $lista = [];

        if (isset($cobros)) {
            foreach ($cobros as $cob){
                $carbonFecha = Carbon::createFromFormat('m/d/y', trim($cob->fecha_pago));
                $fechaPago = $carbonFecha->format('j/n/Y');
                $saldoDet3 = (string) $cob->saldo;
                $saldoFormateado = str_replace(',', '.', $saldoDet3);
                $fila = [
                    'Numero Documento'=> $cob->nro_documento,
                    'Codigo Cliente DET3'=> $cob->cod_cliente,
                    'Cliente'=> $cob->nro_documento,
                    'Operacion DET3'=> $cob->operacion,
                    #'Cartera DET3'=> $cob->cartera,
                    'Saldo DET3'=> $saldoFormateado,
                    'Fecha Pago DET3'=> $fechaPago,
                    'Monto Pagado DET3'=> $cob->mon_pagado,
                    'Numero Cuota DET3'=> $cob->nro_cuota,
                    'Producto DET3'=> $cob->producto,
                    'Segmento DET3'=>'',
                    'Numero Documento DET3'=> $cob->nro_documento,
                    'Dias Mora DET3'=> $cob->dias_mora,
                    'Interes Moratorio DET3'=> $cob->moratorio,
                    'Gastos Cobranza DET3'=> $cob->gastos_cob,
                    'IVA DET3'=> $cob->iva,
                    'Interes Punitorio DET3'=> $cob->punitorio,
                ];
                $lista[]= $fila;

            }
        }

        return collect($lista);
    }
    private function getCobros($fecha){
        $cobros = CobrosVistaCPH::all();
        return $cobros;
    }

    public function headings(): array
    {
        return [
            'Numero Documento',
            'Codigo Cliente DET3',
            'Cliente',
            'Operacion DET3',
            #'Cartera DET3',
            'Saldo DET3',
            'Fecha Pago DET3',
            'Monto Pagado DET3',
            'Numero Cuota DET3',
            'Producto DET3',
            'Segmento DET3',
            #'Tipo Operacion DET3',
            'Numero Documento DET3',
            #'Cotizacion del Dolar DET3',
            'Dias Mora DET3',
            #'Tipo Pago DET3'
            'Interes Moratorio DET3',
            'Gastos Cobranza DET3',
            'IVA DET3',
            'Interes Punitorio DET3'
        ];
    }
}
