<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Log;
use App\Models\ClientesVistaCPH;
use Carbon\Carbon;

class GenerarBaseClientesCPHExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    use Exportable;
    public function collection()
    {
        log::info('INICIA GENERACION DE BASE CPH');

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
                    'segmento'=>trim($cli->segmento),
                    'producto'=>trim($cli->product),
                    'tasa'=>trim($cli->tasa),
                    'total_saldo'=>trim($cli->total_saldo),
                    'saldo_cuota'=>trim($cli->saldo_cuota),
                    'moratorio'=>trim($cli->moratorio),
                    'punitorio'=>trim($cli->punitorio),
                    'gastos_cobranzas'=>trim($cli->gastos_cobranzas),
                    'iva'=>trim($cli->iva),
                    'dias_mora'=>trim($cli->dias_mora),
                    'nro_cuota'=>trim($cli->nro_cuotas),
                    'total_cuotas'=>trim($cli->total_cuotas),
                    'cuotas_pag'=>trim($cli->cuotas_pag),
                    'cuotas_pend'=>trim($cli->cuotas_pend),
                    'monto_cuota'=>trim($cli->monto_cuota),
                    'fecha_vto_cuota'=>trim($fechaVtoCuota),
                    'ult_fech_pago'=>trim($fechaPago),
                    'total_deuda_cuota'=>trim($cli->total_deuda_cuota),
                    'total_deuda'=>trim($cli->total_deuda),
                    'fecha_valor'=>trim($fechaValor),
                    'cod_dist'=>trim($cli->cod_list),
                    'lote'=>trim($cli->lote),
                    'tipo_poe'=>trim($cli->tipo_poe),
                    'situacion'=>trim($cli->situacion),
                    'cod_agente'=>trim($cli->cod_agente)
                ];
                $lista[]= $fila;

            }
        }

        return collect($lista);
    }

    private function getClientes($fecha){
        $clientes = ClientesVistaCPH::all();
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
            'tasa',
            'total_saldo',
            'saldo_cuota',
            'moratorio',
            'punitorio',
            'gastos_cobranzas',
            'iva',
            'dias_mora',
            'nro_cuota',
            'total_cuotas',
            'cuotas_pag',
            'cuotas_pend',
            'monto_cuota',
            'fecha_vto_cuota',
            'ult_fech_pago',
            'total_deuda_cuota',
            'total_deuda',
            'fecha_valor',
            'cod_dist',
            'lote',
            'tipo_poe',
            'situacion',
            'cod_agente'
        ];
    }
}
