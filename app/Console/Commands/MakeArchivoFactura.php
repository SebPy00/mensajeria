<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Exports\FacturaElectronicaExport;
use App\Models\Factura;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\FacturaElectronica;
use Illuminate\Support\Facades\Storage;
use App\Models\DineroTipo;
class MakeArchivoFactura extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'get:facturas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seleccionar las facturas del mes';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        log::info('Inicia recorrido de fac para generación de las facturas electrónicas');
        //obtenemos fecha de inicio y fin del mes actual

        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();

        $this->obtenerFacturasMes($start, $end);
    }

    private function obtenerFacturasMes($start, $end){
        $facturas = DB::Select(
            "SELECT trim(fac) as fac, timb, timbfec, serie from fac where fec >= (:a) and fec <= (:b) and
             trim(fac) not in (select nro_factura from factura_electronica) and col |= 1
             group by fac, timb, timbfec, serie order by fac limit 2",
            ['a'=> $start,'b' => $end]);
        
        if($facturas){
            foreach($facturas as $f){
                $this->guardarEnFacturaElectronica($f->fac, $f->timb);
                $linea_timbrado = $this->formarCamposTimbrado($f->timbfec, $f->serie);
                $this->getDatosFactura($f->fac, $f->timb, $linea_timbrado);
            }
        }

    }


    private function getDatosFactura($fac, $timb, $linea_timbrado){
        $contador = 1;
        $cabecera = '';
        $detalles = '';
        $itemsOpe = '';
        $precios = '';
        //totales
        $total = 0;
        $total_neto = 0;
        $imp_5 = 0;
        $imp_10 = 0;
        $gra_5 = 0;
        $gra_10 = 0;
                
        //necesito almacenar una vez
        $dintip = '';
        $cli = '';
        $moneda = 'PYG';
        $cotizacion = 1;
        
        $factura = Factura::with('cliente')->where('fac', $fac)->where('timb',$timb)->get();
        foreach($factura as $f){
            
            if($contador ==  1){
                $cabecera = $this->formarCabecera($f);
                $detalles = $this->formarDetalle($f, $contador);
                $itemsOpe = $this->itemsOperacion($contador, '');
                $precios = $this->camposPrecio($contador,$f->preuni );
                $dintip =$f->dintip;
                $cli = $f->cliente;
                
                if($f->mon == 1){
                    $moneda = 'USD'; 
                    $cotizacion = $f->cotiza == 1 ;
                }
            }else{
                $detalles .= "\n"  . $this->formarDetalle($f,$contador);
                $itemsOpe .= "\n"  . $this->itemsOperacion($contador, '');
                $precios .= "\n"  . $this->camposPrecio($contador,$f->preuni );

            }
            if(intval($f->ivapor) == 10){
                $gra_10 = $f->grav;
                $imp_10 = $f->iva;
            }elseif(intval($f->ivapor) == 5){
                $gra_5 = $f->grav;
                $imp_5 = $f->iva;
            } 
            $total_neto += $f->exe + $f->grav;
            $total += $f->can * $f->preuni;
            $contador ++;
        }

        $totales = $this->formarTotales($gra_10, $gra_5, $imp_10, $imp_5, $total_neto, $total);
        $caracteres = strlen($cabecera) +( strlen($detalles) - ($contador - 1)) + (strlen($totales) + 1);
        $resumen = $this->formarResumen($contador, $caracteres);
        $lineaDE = $this->formarLineaDE();
        $opeComercial = $this->formarCamposOperacionComercial($f);
        $datosReceptor = $this->formarDatosReceptor($cli);
        $datosFE = $this->camposFacturaElectronica($dintip);
        $condicionOperacion = $this->condicionOperacion();
        $formaPago = $this->formaPagoContado($dintip, $total,$moneda, $cotizacion);

        $this->writeTxt($cabecera,$detalles, $totales, $resumen, $lineaDE, 
                        $fac, $timb, $linea_timbrado, $opeComercial, $datosReceptor, 
                        $datosFE, $condicionOperacion, $formaPago, $itemsOpe, $precios );
    }

    private function formarCabecera($f){
        $cabecera = '';
        $mon = 'PYG';
        if($f->mon == 1) $mon = 'USD';
        if($f->cliente->doctip == 1) $documento = $f->cliente->doc;
        if($f->cliente->doctip == 2) $documento = $f->cliente->ruc;

        $cab = [1,'INVOICE',Carbon::parse($f->fec),'ORIGINAL',$f->timb.'-'.trim($f->fac),'','80023587-8','RUC',
            $mon,'','','','','','','','','','','','','',$documento,'RUC','','80023587-8','RUC'
        ];

        foreach($cab as $c){
            $cabecera .=  $c . ';';
        }

        return $cabecera;
    }

    private function formarDetalle($f, $contador){
        $detalle = '';

        if(intval($f->ivapor) == 10) $iva = 10;
        if(intval($f->ivapor) == 5) $iva = '0,5';
        $det = [2,$contador,'determinarcodigo',$f->can,'PCS', //PCS = unidades KGS= Kilos LTS= litros
            Carbon::parse($f->fec),'SP',$f->det,//consultar
            $f->pre_uni,'PCS', //PCS = unidades KGS= Kilos LTS= litros
            '','','','','','','','VALUE_ADDED_TAX',$f->gra,$f->iva,$iva,'STANDARD_RATE','',''
        ];

        foreach($det as $d){
            $detalle .=  $d . ';';
        }

        return $detalle;
    }

    private function formarTotales($gra_10, $gra_5, $imp_10, $imp_5, $total_neto, $total){
        $totales = '';

        $tot = [5,$total_neto,$imp_5+$imp_10,'VALUE_ADDED_TAX',$gra_5,$imp_5,5,'STANDARD_RATE','VALUE_ADDED_TAX',
            $gra_10,$imp_10,10,'STANDARD_RATE','VALUE_ADDED_TAX','','','','STANDARD_RATE',$total,'BASIC_NET',
            'RECEIPT_OF_GOODS','MONTHS', //DAYS, WEEKS, MONTHS            
            ''
        ];

        foreach($tot as $t){
            $totales .=  $t . ';';
        }

        return $totales;
    }

    private function formarResumen($contador, $caracteres){
        $resumen = '';

        $res = [9,$contador,$caracteres];

        foreach($res as $r){
            $resumen .=  $r . ';';
        }

        return $resumen;
    }

    private function formarLineaDE(){
        //generar codigo
        $cod_seg = 'codseguridad';
        $doc_electronico = '';
        $de = [100,$cod_seg,'',''];

        foreach($de as $d){
            $doc_electronico .=  $d . ';';
        }

        return $doc_electronico;
    }

    private function formarCamposTimbrado($fechaTimbrado, $serie){
        
        $timbrado = '';
        if($serie == 1) $serie = '';
        $tim = [101,$fechaTimbrado,$serie,1];

        foreach($tim as $t){
            $timbrado .=  $t . ';';
        }

        return $timbrado;
    }

    private function formarCamposOperacionComercial($fact){
        $operacionComercial = '';
        $tipoTransaccion = ''; //a definir

        //condicion del tipo de cambio
        $mon = 'PYG';
        $ctcambio = '';

        foreach($fact as $f){
            if($f->mon == 1) $mon = 'USD';
        }

        if($mon != 'PYG') $ctcambio = 1;

        //tipo de cambio 
        $tcamb = '';
        if($ctcambio == 1) $tcamb = 'informar'; //NECESITO EJEMPLO

        $impuesto = 1; //IVA SIEMPRE?
        $anticipo = ''; 

        $opeC = [102, $tipoTransaccion, $ctcambio, $tcamb, $impuesto, $anticipo ];

        foreach($opeC as $o){
            $operacionComercial .=  $o . ';';
        }

        return $operacionComercial;
    } 

    private function formarDatosReceptor($cliente){
        $datosReceptor = '';

        $r = $this->validarRuc($cliente->ruc, $cliente->doc, $cliente->docd);
        $contribuyente = 2; //no contribuyente 
        if($r != '') $contribuyente = 1; //contribuyente 

        $tipo_operacion = $this->tipoOperacion($cliente->tipper, $cliente->doc);
        $codPais = 'PRY';

        $tipoContribuyente = '';
        if($contribuyente == 1 &&  $tipo_operacion == 2) $tipoContribuyente = 1;
        if($contribuyente == 1 &&  $tipo_operacion == 1) $tipoContribuyente = 2;

        $tipoDoc = '';
        if($contribuyente == 2 && $tipo_operacion != 4) $tipoDoc = $this->validarCedula($cliente->doc);

        $doc = '';
        if($contribuyente == 2) $doc = $cliente->doc;

        $razonSocial = $cliente->nom . ' ' . $cliente->ape;

        $direccion = '';
        if($tipo_operacion == 4)$direccion = $cliente->pardir;

        $tel = ''; $cel = ''; $mail = ''; $codCli = ''; 
        $nroCasa = ''; //preguntar
        $codDep = $cliente->pardep;
        if($tipo_operacion == 4) $codDep = '';

        $distrito = ''; //preguntar

        $codCiu = $cliente->pardis;
        if($tipo_operacion == 4) $codCiu = '';

        $opeC = [103, $contribuyente, $tipo_operacion, $codPais, $tipoContribuyente, $tipoDoc, $doc, $razonSocial, '', $direccion,
                $tel, $cel, $mail, $codCli, '', $nroCasa, $codDep, $distrito, $codCiu]; 

        
        foreach($opeC as $o){
            $datosReceptor .=  $o . ';';
        }

        return $datosReceptor;
    }

    private function camposFacturaElectronica($dintip){
        
        $facElectronica = '';
        
        $res = $this->determinarIndicadorPresencia($dintip);

        $indPresencia = $res['cod'];
        $descIndPrecencia = '';
        if($indPresencia == 9)  $descIndPrecencia = $res['des']; 

        $fec = [104,$indPresencia,$descIndPrecencia, ''];

        foreach($fec as $f){
            $facElectronica .=  $f . ';';
        }

        return $facElectronica;
    }

    private function condicionOperacion(){
        $conOpe = '';
        
        $condicion = 1; //1->CONTADO 2->CREDITO

        $co = [112,$condicion];

        foreach($co as $c){
            $conOpe .=  $c. ';';
        }

        return $conOpe;
    }

    private function formaPagoContado($dintip, $monto, $moneda, $cambio){
        $formaPago = '';
        
        $tipoPago = $this->determinarTipoPago($dintip); //OTRO
        $descripciontp = '';
        if($tipoPago == 99) $descripciontp =  $tipoPago['des'];
        if($cambio == 1) $cambio = '';

        $tp = [113,1, $tipoPago['cod'], $monto, $moneda, $cambio, $descripciontp];

        foreach($tp as $t){
            $formaPago .=  $t. ';';
        }

        return $formaPago;
    }

    private function itemsOperacion($linDet, $codInt){
        $itemsOpe = '';
        $arancel = '';
        $ncm = '';     
        $dncpGral = '';  
        $dncpEspc = '';    
        $gtin = '';    
        $codPais = '';    
        $infoInt = '';
        $codRel = ''; //DETERMINAR 1->TOLERANCIA DE QUIEBRA 2->TOLERANCIA DE MERMA - opcional si 101.3 = 7 (no es el caso, es 1->fac electro)
        $cantQuiebra = ''; //obligatorio si se informa 118.10
        $porcQuiebra = ''; //obligatorio si se informa 118.10
        $cdcAnticipo = ''; //obligatorio para transaccion igual a anticipo
        $io = [118,1, $linDet, $codInt, $arancel, $ncm, $dncpGral, $dncpEspc, $gtin, $codPais, $infoInt, $codRel, $cantQuiebra, $porcQuiebra, $cdcAnticipo];

        foreach($io as $i){
            $itemsOpe .=  $i. ';';
        }

        return $itemsOpe;
    }

    private function camposPrecio($linDet, $precioUnit ){
        $camposPrecio = '';
        $tipoCambio = ''; //NO SE INFORMA PORQUE TENEMOS UN SOLO TIPO DE CAMBIO PARA TODA LA FACTURA
        $cp = [119,$linDet, $precioUnit, $tipoCambio];

        foreach($cp as $c){
            $camposPrecio .=  $c. ';';
        }

        return $camposPrecio;
    }

    private function determinarIndicadorPresencia($dintip){
        $dt = DineroTipo::with('indicadorPresencia')->where('dintip', $dintip)->first();
        return ['cod'=> $dt->indicadorPresencia->cod,'des'=>$dt->nomdt];
    }

    private function determinarTipoPago($dintip){
        $dt = DineroTipo::with( 'formaPago')->where('dintip', $dintip)->first();
        return ['cod'=> $dt->formaPago->cod,'des'=>$dt->nomdt];
    }

    private function validarRuc($ruc, $doc, $dig){
        //validar ruc
        $r = '';
        if(substr_count($ruc, '-') == 1){
            $r = $ruc;
        }elseif($doc != '' && $dig != ''){
            $r = $doc . '-' . $dig;
        }
        
        return $r;
    }

    private function validarCedula($doc){

        $res = 1; //ci paraguaya 

        //return false si empieza con letra - ci extranj.
        $d = is_numeric($doc[0]);
        if(!$d) $res = 3;
        return $res;
    }
    
    private function tipoOperacion ($tipper, $doc){
        //tipper -> 1 - juridica 2->fisica
        // Tipo de operación
        // 1= B2B
        // 2= B2C
        // 3= B2G
        // 4= B2F (Esta última opción debe utilizarse solo en caso de servicios para empresas o personas físicas del exterior)
        
        $valCed = $this->validarCedula($doc);
        $res = 2; //B2C
        if($tipper == 1) $res = 1; //B2B
        if($valCed == 3) $res = 4; //B2F

        return $res;
    }

    
    private function guardarEnFacturaElectronica($nroFactura, $timbrado){
        log::info('Entra para guardar en tabla de control');
        $f = new FacturaElectronica();
        $f->nro_factura = $nroFactura;
        $f->timbrado = $timbrado;
        $f->save();
    }

    private function writeTxt($cabecera,$detalles, $totales, $resumen, $delectronico, $fac, $timb, $linea_timbrado, 
    $opeComercial, $datosReceptor, $datosFE, $condicionOperacion, $formaPago, $itemsOpe, $precios ){
        Storage::disk('s4')->put($timb . '-'.$fac.'.txt', $cabecera);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $detalles);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $totales);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $resumen);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $delectronico);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $linea_timbrado);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $opeComercial);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $datosReceptor);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $datosFE);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $condicionOperacion);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $formaPago);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $itemsOpe);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $precios);
    }
}
