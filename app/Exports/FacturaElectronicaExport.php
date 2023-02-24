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

        $factura = Factura::with('cliente')->where('fac', $this->nro_factura)->where('timb',$this->timbrado)->get();

        $contador = 1;
        
        $total_neto = 0;
        $imp_5 = 0;
        $imp_10 = 0;
        $gra_5 = 0;
        $gra_10 = 0;
        $total = 0;

        $caracteres = 0;
        foreach($factura as $f){
            /////////////////////////////////////////CABECERA///////////////////////////////////////////
            if($contador ==  1){
                $mon = 'PYG';
                if($f->mon == 1) $mon = 'USD';
                if($f->cliente->doctip == 1) $documento = $f->cliente->doc;
                if($f->cliente->doctip == 2) $documento = $f->cliente->ruc;

                $cab = [
                    'tiporegistro'=>1,
                    'fijo_inv'=>'INVOICE',
                    'fecha_hora'=>Carbon::parse($f->fec),
                    'fijo_ori'=>'ORIGINAL',
                    'timbr_fac'=>$f->timb.'-'.trim($f->fac),
                    'gln_creador'=>'',
                    'ruc_emisor'=>'80023587-8',//ruc sigesa
                    'fijo_r'=>'RUC',
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
                    'ruc_cliente'=>$documento,
                    'fijo_ruc_cliente'=>'RUC',
                    'gln_prove_vendedor'=>'',
                    'ruc_vendedor'=>'80023587-8',//ruc sigesa
                    'fijo_ruc_vend'=> 'RUC;'
                ];
                $lista[]= $cab;
                
                //////////////////////////////////////
                // sumar caracteres de la cabecera///
                /////////////////////////////////////

                $caracteres += 
                    (strlen($lista[0]['tiporegistro']) + 1) + 
                    (strlen($lista[0]['fijo_inv']) + 1) + 
                    (strlen($lista[0]['fecha_hora']) + 1) +
                    (strlen($lista[0]['fijo_ori']) + 1) +
                    (strlen($lista[0]['timbr_fac']) + 1) +
                    (strlen($lista[0]['gln_creador']) + 1) +
                    (strlen($lista[0]['ruc_emisor']) + 1) +
                    (strlen($lista[0]['fijo_r']) + 1) +
                    (strlen($lista[0]['moneda']) + 1) +
                    (strlen($lista[0]['gln_comprador']) + 1) +
                    (strlen($lista[0]['ruc_comprador']) + 1) +
                    (strlen($lista[0]['fijo_ruc']) + 1) +
                    (strlen($lista[0]['gln_pagador']) + 1) +
                    (strlen($lista[0]['nro_ruc_pagador']) + 1) +
                    (strlen($lista[0]['fijo_ruc_dos']) + 1) +
                    (strlen($lista[0]['gln_proveedor_benef']) + 1) +
                    (strlen($lista[0]['nro_ruc_proveedor_benef']) + 1) +
                    (strlen($lista[0]['fijo_ruc_tres']) + 1) +
                    (strlen($lista[0]['gln_proveedor']) + 1) +
                    (strlen($lista[0]['nro_ruc_proveedor']) + 1) +
                    (strlen($lista[0]['fijo_ruc_cuat']) + 1) +
                    (strlen($lista[0]['gln_comprador']) + 1) +
                    (strlen($lista[0]['ruc_cliente']) + 1) +
                    (strlen($lista[0]['fijo_ruc_cliente']) + 1) +
                    (strlen($lista[0]['gln_prove_vendedor']) + 1) +
                    (strlen($lista[0]['ruc_vendedor']) + 1) +
                    (strlen($lista[0]['fijo_ruc_vend']));
            }
            /////////////////////////////////////DETALLES////////////////////////////////////////////
            if(intval($f->ivapor) == 10) $iva = 10;
            if(intval($f->ivapor) == 5) $iva = '0,5';
            
            $fila = [
                'tiporegistro'=>2,
                'nro_linea'=>$contador,
                'cod_interno'=>'determinarcodigo',
                'cantidad'=>$f->can,
                'unid_medida'=>'PCS', //PCS = unidades KGS= Kilos LTS= litros
                'fech_entrega'=>Carbon::parse($f->fec),
                'idioma'=>'SP',
                'descripcion_art'=>$f->det,//consultar
                'importe_unitario'=>$f->pre_uni,
                'unid_med'=>'PCS', //PCS = unidades KGS= Kilos LTS= litros
                'fec_hor_remision'=>'',//opc
                'nro_remision'=>'',//opc
                'fec_hor_creacion'=>'',//opc
                'nro_orden'=>'',//opc
                'gln_comp'=>'',//opc
                'ruc_comp'=>'',//opc
                'fijo_ruc'=>'',//opc
                'fijo_tax'=>'VALUE_ADDED_TAX',
                'imp_gravado'=>$f->gra,
                'iva'=>$f->iva,
                'porc_iva'=>$iva,
                'fijo_rate'=>'STANDARD_RATE',
                'recargo_o_descuento'=>'',//opc
                'desc_opc'=>';'//opc
            ];
            $lista[]= $fila;
           
            //////////////////////////// CALCULOS PARA TOTALES//////////////////////////////
            if($iva == 10){
                $gra_10 = $f->grav;
                $imp_10 = $f->iva;
            }elseif($iva == '0,5'){
                $gra_5 = $f->grav;
                $imp_5 = $f->iva;
            } 
            $total_neto = $f->exe + $f->grav;
            $total = $f->tot;

            $contador ++;
        }

        //////////////FILA TOTALES////////////////

        $totales = [
            'tiporegistro'=>5,
            'monto_neto'=>$total_neto,
            'total_impuesto'=>$imp_5+$imp_10,
            'texto_fijo_1'=>'VALUE_ADDED_TAX',
            'tot_grav_5'=>$gra_5,
            'tot_iva_5'=> $imp_5,
            'porc_5'=>5,
            'texto_fijo_2'=>'STANDARD_RATE',
            'texto_fijo_3'=>'VALUE_ADDED_TAX',
            'tot_grav_10'=>$gra_10,
            'tot_iva_10'=> $imp_10,
            'porc_10'=>10,
            'texto_fijo_4'=>'STANDARD_RATE',
            'texto_fijo_5'=>'VALUE_ADDED_TAX',
            'tot_grav_x'=>'',//opc
            'tot_iva_x'=>'',//opc
            'porc_iva'=>'',//opc
            'texto_fijo_6'=>'STANDARD_RATE',
            'total'=>$total,
            'texto_fijo_7'=>'BASIC_NET',
            'texto_fijo_8'=>'RECEIPT_OF_GOODS',
            'pred_pago'=>'MONTHS', //DAYS, WEEKS, MONTHS            
            'periodo_pago'=>';'//opc
        ];

        $lista[]= $totales;

        //////////////////////FILA RESUMEN//////////////////////////

        $resumen = [
            'tiporegistro'=>9,
            'nro_items'=>$contador,
            'total_impuesto'=>$caracteres
        ];

        $lista[]= $resumen;
        
        return collect($lista);
    }
    
}
