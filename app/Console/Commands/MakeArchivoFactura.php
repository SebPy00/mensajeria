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

        $total = 0;
        $total_neto = 0;
        $imp_5 = 0;
        $imp_10 = 0;
        $gra_5 = 0;
        $gra_10 = 0;
        
        $datosReceptor = '';
        $datosFE = '';
        $factura = Factura::with('cliente')->where('fac', $fac)->where('timb',$timb)->get();
        foreach($factura as $f){
            
            if($contador ==  1){
                $cabecera = $this->formarCabecera($f);
                $detalles = $this->formarDetalle($f, $contador);
                $datosReceptor = $this->formarDatosReceptor($f->cliente);
                $datosFE = $this->camposFacturaElectronica($f->dintip);
            }else{
                $detalles .= "\n"  . $this->formarDetalle($f,$contador);
            }
            
            if(intval($f->ivapor) == 10){
                $gra_10 = $f->grav;
                $imp_10 = $f->iva;
            }elseif(intval($f->ivapor) == 5){
                $gra_5 = $f->grav;
                $imp_5 = $f->iva;
            } 
            $total_neto = $f->exe + $f->grav;
            $total = $f->tot;
            $contador ++;
        }

        $totales = $this->formarTotales($gra_10, $gra_5, $imp_10, $imp_5, $total_neto, $total);
        $caracteres = strlen($cabecera) +( strlen($detalles) - ($contador - 1)) + (strlen($totales) + 1);
        $resumen = $this->formarResumen($contador, $caracteres);
        $lineaDE = $this->formarLineaDE();
        $opeComercial = $this->formarCamposOperacionComercial($f);
        $condicionOperacion = $this->condicionOperacion();
       
        $this->writeTxt($cabecera,$detalles, $totales, $resumen, $lineaDE, 
                        $fac, $timb, $linea_timbrado, $opeComercial, $datosReceptor, 
                        $datosFE, $condicionOperacion);
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

    private function formaPagoContado($dintip, $monto, $mon, $cambio){
        //SERIA MEJOR CREAR UNA TABLA, ALMACENAR ESTOS CODIGOS Y RELACIONARLAS CON DINTIP, PARA QUE EL DIA DE MAÑANA SE ESTIRE DE AHÍ CUANDO SE CREEN MÁS DINTIPS
        ////////////////////////////////////
        ////CODIGOS PARA FORMAS DE PAGO////
        ///////////////////////////////////
        // 1= Efectivo  (1, 54, )
        // 2= Cheque (8, 13, 221, 231, 271)
        // 3= Tarjeta de crédito
        // 4= Tarjeta de débito
        // 5= Transferencia (2,3,7,9, 10, 14, 15, 22, 23, 25, 27, 31, 32, 44, 45, 46, 48, 49, 52, 53, 56, 57, )
        // 6= Giro
        // 7= Billetera electrónica
        // 8= Tarjeta empresarial
        // 9= Vale
        // 10= Retención
        // 11= Anticipo
        // 12= Valor fiscal
        // 13= Valor comercial
        // 14= Compensación
        // 15= Permuta
        // 16= Pago bancario (Informar solo si 104.1=5)
        // 17 = Pago Móvil
        // 18 = Donación
        // 19 = Promoción
        // 20 = Consumo Interno
        // 21 = Pago Electrónico (4, 33, 35, 36, 51)
        // 99 = Otro
        ///////////////////////////////////////(5, 6, 11, 12, 24, 455 )->dintip que no sé como clasificar

        $formaPago = '';
        
        $tipoPago = $this->determinarTipoPago($dintip); //OTRO
        
        $descripciontp = '';
        if($tipoPago == 99) $descripciontp = '';
        $tp = [113,1, $tipoPago, $monto, $mon, $cambio, $descripciontp];

        foreach($tp as $t){
            $formaPago .=  $t. ';';
        }

        return $formaPago;
    }
    private function determinarIndicadorPresencia($dintip){
        // 1->operacion presencial
        // 2->operacion electronica
        // 4->venta domicilio
        // 5->operacion bancaria 
        // 6->operacion ciclica
        // 9->otro

        $dt = DineroTipo::where('dintip', $dintip)->first();

        $indp = 9; //otro por defecto
        $desc = $dt->nomdt;
        if($dintip == 1 || $dintip == 54) 
            $indp = 1;
        elseif($dintip == 4 || $dintip == 5 || $dintip == 33 || $dintip == 35 || $dintip == 36 || $dintip == 51) 
            $indp = 2;
        elseif($dintip == 2 || $dintip == 3 || $dintip == 7 || $dintip == 8 || $dintip == 9 || $dintip == 10 || $dintip == 12
                || $dintip == 13 || $dintip == 14 || $dintip == 15 || $dintip == 22 || $dintip == 23 || $dintip ==24 || $dintip ==25 || $dintip ==27
                || $dintip == 31 || $dintip ==32 || $dintip == 44 || $dintip == 45 || $dintip == 46 || $dintip == 48 || $dintip == 49 || $dintip == 52
                || $dintip == 53 || $dintip ==56 || $dintip == 57 || $dintip == 221 || $dintip == 231 || $dintip == 271)
            $indp = 5;
        
        return ['cod'=> $indp,'des'=>$desc];
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
    $opeComercial, $datosReceptor, $datosFE, $condicionOperacion){
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
    }
}
