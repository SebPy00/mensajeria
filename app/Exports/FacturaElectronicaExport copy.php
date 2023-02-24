<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Factura;  

//ini_set('memory_limit', '-1');

class FacturaElectronicaExport implements FromCollection
{
    use Exportable;
    private $nro_factura;
    private $timbrado;
    public function __construct(string $nro_factura, int $timbrado)
    {
        $this->nro_factura=  $nro_factura;
        $this->timbrado=  $timbrado;
        log::info('Factura electronica: '. $this->nro_factura .' timb: ' . $timbrado );
    }
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $lista = [];

        
        $cabecera =  DB::Select
        ("SELECT f.fec::timestamp, f.timb, trim(f.fac) as fac, f.mon, f.cli,
        (SELECT CASE 
            WHEN doctip = 1 THEN c.doc
            WHEN doctip = 2 THEN c.ruc 
        END FROM cli c WHERE c.cli=f.cli limit 1) AS documento
        from fac f
        join cli c on f.cli = c.cli
        where f.fac = (:a) and f.timb = (:b) and LENGTH(trim(fac)) = 15 limit 1", ['a'=> $this->nro_factura, 'b'=> $this->timbrado]);

        if($cabecera){
            $mon = '';
            if ($cabecera[0]->mon == 6900) $mon = 'PYG';
            if ($cabecera[0]->mon == 1) $mon = 'USD';
            $fila = [
                'tiporegistro'=>1,
                'fijo'=>'INVOICE',
                'fecha_hora'=>$cabecera[0]->fec,
                'fijo'=>'ORIGINAL',
                'timbr_fac'=>$cabecera[0]->timb.'-'.$cabecera[0]->fac,
                'gln_creador'=>'',
                'ruc_emisor'=>'80023587-8',//ruc sigesa
                'fijo'=>'RUC',
                'moneda'=>$mon,
                'gln_comprador'=>'',
                'ruc_comprador'=>'',
                'fijo_ruc'=>'',
                'gln_pagador'=>'',
                'nro_ruc_pagador'=>'',
                'fijo_ruc_dos'=>'',
                'gln_proveedor_benef'=>'',
                'nro_ruc_proveedor_benef'=>'',
                'fijo_ruc_tres'=>'',
                'gln_proveedor'=>'',
                'nro_ruc_proveedor'=>'',
                'fijo_ruc_cuat'=>'',
                'gln_comprador'=>'',
                'ruc_cliente'=>$cabecera[0]->documento,
                'fijo_ruc_cliente'=>'RUC',
                'gln_prove_vendedor'=>'',
                'ruc_vendedor'=>'80023587-8',//ruc sigesa
                'fijo_ruc_vend'=> 'RUC;'
            ];
            $lista[]= $fila;
        }

       
        return collect($lista);
    }
    
}
