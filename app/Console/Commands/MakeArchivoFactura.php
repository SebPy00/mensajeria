<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Factura;
use App\Models\FacturaElectronica;
use Illuminate\Support\Facades\Storage;
use App\Models\DineroTipo;
use App\Models\FECodigoSeguridad;
use App\Models\Timbrado;
use App\Models\Mail;
use GuzzleHttp\Client;

class MakeArchivoFactura extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'factura:txt';

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

        $this->generarFacturas();
        $this->obtenerFacturasMes($start, $end);
    }

    private function generarFacturas(){
        $client = new Client;
        $url = 'http://10.19.150.11/devsigesa/ajax/genFac.php?op=genFacturas&genPsig=no';
        $response = $client->get($url);
        log::info('termina el proceso de generación de fac');
    }

    private function obtenerFacturasMes($start, $end){
        log::info('obtenemos facturas del mes');
        $facturas = DB::Select(
            "SELECT trim(fac) as fac, timb, timbfec, serie from fac where fec >= (:a) and fec <= (:b)
             and timb = 16470445
             and trim(fac)
             not in (select nro_factura from factura_electronica where idestadotxt != 6) and col = 1
             group by fac, timb, timbfec, serie order by fac
             --limit 10
             ",['a'=> '2023-07-15', 'b'=> $end]
        );

        if($facturas){
            foreach($facturas as $f){
                $this->guardarEnFacturaElectronica($f->fac, $f->timb);
                $linea_timbrado = $this->formarCamposTimbrado($f->timb, $f->serie);
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
        $camposIva = '';
        $camposCheque = '';
        $notificacion = '';
        $descAnt = '';
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

        $rucSigesa = '80023587-8';
        $fecha = '';

        $factura = Factura::with('cliente')->where('fac', $fac)->where('timb',$timb)->get();
        foreach($factura as $f){

            if($contador ==  1){
                $cli = $f->cliente;
                $cabecera = $this->formarCabecera($f, $rucSigesa,  $cli);
                $fecha = Carbon::parse($f->fec);
                $detalles = $this->formarDetalle($f, $contador);
                $itemsOpe = $this->itemsOperacion($contador, $f->con);
                $precios = $this->camposPrecio($contador,$f->preuni );
                $dintip =$f->dintip;
                $camposIva = $this->camposIva($contador, $f->ivapor, $f->exe, ($f->can * $f->preuni));
                if($f->mon == 1){
                    $moneda = 'USD';
                    $cotizacion = $f->cotizacion ;
                }
                $notificacion = $this->linea980_notificacion($contador);
                $descAnt = $this->linea128_descuentosAnticipos($contador);
            }else{
                $detalles .= "\n"  . $this->formarDetalle($f,$contador);
                $itemsOpe .= "\n"  . $this->itemsOperacion($contador, $f->con);
                $precios .= "\n"  . $this->camposPrecio($contador,$f->preuni );
                $camposIva .= "\n"  . $this->camposIva($contador, $f->ivapor, $f->exe, ($f->can * $f->preuni));
                $notificacion .= "\n"  . $this->linea980_notificacion($contador);
                $descAnt .= "\n"  . $this->linea128_descuentosAnticipos($contador);
            }
            if ($f->dintip == 221 || $f->dintip == 8 || $f->dintip == 13 || $f->dintip == 231 || $f->dintip == 271){
                if($camposCheque ==''){
                    $camposCheque = $this->camposCheque($contador, $f);
                }else{
                    $camposCheque .= "\n"  . $this->camposCheque($contador, $f);
                }
            }
            if(intval($f->ivapor) == 10){
                $gra_10 += $f->gra;
                $imp_10 += $f->iva;
            }elseif(intval($f->ivapor) == 5){
                $gra_5 += $f->gra;
                $imp_5 += $f->iva;
            }
            $total_neto += $f->exe + $f->gra;
            $total += $f->can * $f->preuni;
            $contador ++;
        }

        $totales = $this->formarTotales($gra_10, $gra_5, $imp_10, $imp_5, $total_neto, $total);
        $caracteres = strlen($cabecera) +( strlen($detalles) - ($contador - 1)) + (strlen($totales) + 1);
        $resumen = $this->formarResumen($contador, $caracteres);
        $datosDoc = $this->formarLineaDE($fac, $timb);
        $lineaDE =  $datosDoc['docelectronico'];
        $opeComercial = $this->formarCamposOperacionComercial($factura);
        $dReceptor =  $this->formarDatosReceptor($cli);
        $datosReceptor = $dReceptor['datosReceptor'];
        $datosFE = $this->camposFacturaElectronica($dintip);
        $datosCondicion = $this->condicionOperacion($fac, $timb);
        $condicionOperacion = $datosCondicion['linea'];
        $condicion = $datosCondicion['condicion'];
        if ($condicion == 1){
            $formaPago = $this->formaPagoContado($dintip, $total,$moneda, $cotizacion);
        }else{
            $formaPago = $this->formaPagoCredito();
        }

        $ctotales = $this->camposSubtotalesTotales();
        $infoAdicional = $this->linea180_informacionadicional($f->ope, $timb);
        $indicadorEnvio = $this->linea999_indicadorenvio();
        $this->writeTxt($cabecera,$detalles, $totales, $resumen, $lineaDE,
                        $fac, $timb, $linea_timbrado, $opeComercial, $datosReceptor,
                        $datosFE, $condicionOperacion, $formaPago, $camposCheque, $itemsOpe, $precios,
                        $camposIva, $ctotales, $infoAdicional, $notificacion, $descAnt, $indicadorEnvio);

        $tipoEmision= 1; //fijo por el momento
        $this->generarCDC($rucSigesa, $fac, $fecha, $tipoEmision, $datosDoc['codseguridad'], $timb);
    }

    private function formarCabecera($f, $rucSigesa, $cli){
        $cabecera = '';
        $linea = 1;
        $fechaHora = $f->fec . ' ' . $f->hor;
        $timFac = $f->timb.'-'.trim(str_replace("-","",$f->fac));
        $mon = 'PYG';
        if($f->mon == 1) $mon = 'USD';

        //VALIDACION DE CONTRIBUYENTE
        $documento = $this->validarRuc(trim($cli->ruc), trim($cli->doc), trim($cli->docd));

        $cab = [$linea,'INVOICE',$fechaHora,'ORIGINAL',$timFac,'',$rucSigesa,'RUC',
                    $mon,'','','','','','','','','','','','','',$documento,'RUC','',$rucSigesa,'RUC'
               ];

        foreach($cab as $c){
            $cabecera .=  $c . ';';
        }

        return $cabecera;
    }

    private function formarDetalle($f, $contador){
        $detalle = '';

        $det = [2,$contador,$f->con,$f->can,'PCS', $f->fec,
        'SP',$f->det,intval($f->gra),'PCS','','','','','','','','VALUE_ADDED_TAX',
        intval($f->gra),intval($f->iva),intval($f->ivapor),'STANDARD_RATE','',''
        ];

        foreach($det as $d){
            $detalle .=  $d . ';';
        }

        return $detalle;
    }

    private function formarTotales($gra_10, $gra_5, $imp_10, $imp_5, $total_neto, $total){
        $totales = '';

        $tot = [5,$total_neto,$imp_5+$imp_10,'VALUE_ADDED_TAX',$gra_5,$imp_5,5,'STANDARD_RATE','VALUE_ADDED_TAX',
            $gra_10,$imp_10,10,'STANDARD_RATE','VALUE_ADDED_TAX',0,0,0,'STANDARD_RATE',$total,'BASIC_NET',
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
        $linea = 9;
        $res = [$linea,($contador - 1),$caracteres];

        foreach($res as $r){
            $resumen .=  $r . ';';
        }

        return $resumen;
    }

    private function formarLineaDE($fac, $timb){

        $doc_electronico = '';
        $linea = 100;
        $cod_seg = $this->generarCodSeguridad($fac, $timb);

        //CAMPOS DE INTERÉS
        $informacionInteresEmisor = '';
        $informacionInteresReceptor = '';

        $de = [$linea,$cod_seg,$informacionInteresEmisor,$informacionInteresReceptor];

        foreach($de as $d){
            $doc_electronico .=  $d . ';';
        }

        return ['docelectronico'=>$doc_electronico, 'codseguridad'=> $cod_seg ];
    }

    private function formarCamposTimbrado($timbrado, $serie){
        $t = $this->getDatosTimbrado($timbrado);
        $timbrado = '';

        $linea = 101;
        if($serie == 1) $serie = '';
        $tim = [$linea,$t->inicio_set,$serie,1];

        foreach($tim as $t){
            $timbrado .=  $t . ';';
        }

        return $timbrado;
    }

    private function formarCamposOperacionComercial($fact){
        $linea= 102;

        $operacionComercial = '';
        $tipoTransaccion = 2; //PRESTACION DE SERVICIOS FIJO

        //condicion del tipo de cambio
        $mon = 'PYG';
        $ctcambio = '';

        $cotizacion = '';
        foreach($fact as $f){
            if($f->mon == 1)
                $mon = 'USD';
                $cotizacion = $f->cotizacion;
        }

        if($mon != 'PYG') $ctcambio = 1;

        //tipo de cambio
        $tcamb = '';
        if($ctcambio == 1) $tcamb = $cotizacion;

        $impuesto = 1; //IVA SIEMPRE
        $anticipo = ''; //NO UTILIZAMOS

        $opeC = [$linea, $tipoTransaccion, $ctcambio, $tcamb, $impuesto, $anticipo ];

        foreach($opeC as $o){
            $operacionComercial .=  $o . ';';
        }

        return $operacionComercial;
    }

    //REVISAR
    private function formarDatosReceptor($cliente){
        $datosReceptor = '';
        $linea = 103;
        $r = $this->validarRuc(trim($cliente->ruc), trim($cliente->doc), trim($cliente->docd));
        $contribuyente = 2; //no contribuyente
        if($r != '') $contribuyente = 1; //contribuyente
        $tipo_operacion = $this->tipoOperacion($cliente->tipper, $cliente->doc);

        $tipoContribuyente = '';
        if($contribuyente == 1){
            if($cliente->tipper ==  1 || $cliente->tipper == 0){
                $tipoContribuyente = 1; //FISICA
            }elseif($cliente->tipper ==  2 || $cliente->tipper ==  3){
                $tipoContribuyente = 2;  //JURIDICA
            }
        }
        $tipoDoc = '';
        if($contribuyente == 2 && $tipo_operacion != 4) $tipoDoc = $this->validarCedula($cliente->doc);
        $doc = '';
        if($contribuyente == 2) $doc = $cliente->doc;
        $razonSocial = utf8_encode(trim($cliente->nom )). ' ' . utf8_encode(trim($cliente->ape));
        $nombreFantasia = '';
        $direccion = '';
        if($tipo_operacion == 4) $direccion = 'Sin direccion';
        $codPais = 'PRY';
        if($tipo_operacion == 4) $codPais = 'ARG';
        $tel = '';
        $cel = '';
        $datosCorreo = Mail::where('cedula', $cliente->doc)->first();
        $mail = '';
        if(isset($datosCorreo->correo)) $mail = $datosCorreo->correo;
        $codCli = '';
        $nroCasa = '';
        if($tipo_operacion == 4) $nroCasa = 555;
        $codDep = '';
        $distrito = '';
        $codCiu = '';

        $opeC = [$linea, $contribuyente, $tipo_operacion, $codPais, $tipoContribuyente,
                $tipoDoc, $doc, $razonSocial, $nombreFantasia, $direccion,
                $tel, $cel, $mail, $codCli, '', $nroCasa, $codDep, $distrito, $codCiu];


        foreach($opeC as $o){
            $datosReceptor .=  $o . ';';
        }

        return ['datosReceptor'=> $datosReceptor,'contribuyente'=>$contribuyente];
    }

    private function camposFacturaElectronica($dintip){
        $linea = 104;

        $facElectronica = '';

        $res = $this->determinarIndicadorPresencia($dintip);

        $indPresencia = $res['cod'];
        $descIndPrecencia = '';
        if($indPresencia == 9)  $descIndPrecencia = $res['des'];

        $fec = [$linea,$indPresencia,$descIndPrecencia, ''];

        foreach($fec as $f){
            $facElectronica .=  $f . ';';
        }

        return $facElectronica;
    }

    private function camposCheque($nroDetalle, $factura){

        $camposCheque = '';

        $dt = DineroTipo::where('dintip', $factura->dintip)->first();

        $ch = [115, $nroDetalle, $factura->dindet, $dt->formapago ];

        foreach($ch as $c){
            $camposCheque .=  $c . ';';
        }

        return $camposCheque;
    }

    private function condicionOperacion($fac, $timb){
        $linea = 112;

        //1->CONTADO 2->CREDITO - SOLO EMITIMOS FACTURAS AL CONTADO
        $condicion = $this->getCondicion($fac, $timb);
        $conOpe = '';
        $co = [$linea, $condicion];

        foreach($co as $c){
            $conOpe .=  $c. ';';
        }

        return ['linea'=>$conOpe, 'condicion'=> $condicion ];
    }

    private function getCondicion($fac, $timb){

        $condicion = DB::Select(
            "SELECT COALESCE(a.factip,1) as factip
            FROM fac LEFT JOIN admmov a ON a.cpte=fac.fac AND a.timb=fac.timb
            WHERE fac.fac=(:a) and fac.timb=(:b) LIMIT 1",
            ['a' => $fac, 'b' =>$timb]) ;

        return $condicion[0]->factip  ;

    }

    private function formaPagoContado($dintip, $monto, $moneda, $cambio){
        $formaPago = '';
        $linea = 113;
        $lineaDetalle = 1; //solo presentamos una línea 113 porque manejamos un solo tipo de pago para todo el documento
        $tipoPago = $this->determinarTipoPago($dintip); //OTRO
        $descripciontp = '';
        if($tipoPago == 99) $descripciontp =  $tipoPago['des'];
        if($cambio == 1) $cambio = '';

        $tp = [$linea,$lineaDetalle, $tipoPago['cod'], $monto, $moneda, $cambio, $descripciontp];

        foreach($tp as $t){
            $formaPago .=  $t. ';';
        }

        return $formaPago;
    }

    private function formaPagoCredito(){
        $formaPago = '';
        $linea = 116;
        $condOperacion = 1; //plazo: 1, cuota: 2
        $plazoCredito = '1 mes';
        $cantCuotas = '';
        $montoEntrega = '';

        $tp = [$linea,$condOperacion,$plazoCredito,$cantCuotas,$montoEntrega];

        foreach($tp as $t){
            $formaPago .=  $t. ';';
        }

        return $formaPago;
    }

    private function itemsOperacion($linDet, $codInt){
        $itemsOpe = '';
        $linea = 118;
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
        $io = [$linea, $linDet, $codInt, $arancel, $ncm, $dncpGral, $dncpEspc, $gtin, $codPais, $infoInt, $codRel, $cantQuiebra, $porcQuiebra, $cdcAnticipo];

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

    private function camposIva($linDet, $ivapor, $exentas, $totalDetalle){
        $camposIva = '';


        $afectacionTributaria = '';//1->gravado iva 2->exonerado 3->exento 4->grabado parcial
        $porcGravada = '';
        if(($ivapor == 10 || $ivapor == 5) && intval($exentas) == 0){
            $afectacionTributaria = 1;
            $porcGravada = 100;
        }
        else if(intval($exentas) != 0){
            if($exentas == $totalDetalle){
                $afectacionTributaria = 3;
                $porcGravada = 0;
            }else{
                $afectacionTributaria = 1;
                $por = round((((float)$exentas * 100) / $totalDetalle), 0); //PORCENTAJE EXENTO
                $porcGravada = 100 - $por; // PORCETAJE DE GRAVADA DE IVA
            }
        }

        $ci = [120,$linDet, $afectacionTributaria, $porcGravada];

        foreach($ci as $c){
            $camposIva .=  $c. ';';
        }

        return $camposIva;
    }

    private function linea128_descuentosAnticipos($linDet){
        $descuentosAnticipos = '';
        $linea = 128;

        //NO SE APLICAN DESCUENTOS NI ANTICIPOS
        $descuento = 0;
        $anticipo = 0;
        $antGlobal = 0;

        $dA = [$linea,$linDet,$descuento,$anticipo, $antGlobal];

        foreach($dA as $d){
            $descuentosAnticipos .=  $d . ';';
        }

        return $descuentosAnticipos;
    }

    private function camposSubtotalesTotales(){
        $ctotales = '';
        $linea = 140;
        // COMPLETAMOS CON CERO PORQUE NO FACTURAMOS DESCUENTO NI COMISION
        $descuento = 0;
        $comision = 0;
        $liqiva = 0;
        $redondeo = 'false';

        $ct = [$linea, $descuento, $comision, $liqiva, $redondeo ];

        foreach($ct as $c){
            $ctotales .=  $c. ';';
        }

        return $ctotales;
    }

    private function linea180_informacionadicional($operacion){
        $infoAdicional = '';
        $linea = 180;
        $datos = '{nro. operacion: ' . $operacion . '}';
        $inf = [$linea, $datos];
        foreach($inf as $i){
            $infoAdicional .=  $i . ';';
        }

        return $infoAdicional;
    }

    private function linea980_notificacion($linDet){
        $notificacion = '';
        $linea = '980';
        $correo = 'ti@sistemasygestiones.com.py';
        $codNotificacion = 'RECHAZO_DTE';

        $not = [$linea, $linDet, $codNotificacion, $correo];
        foreach($not as $n){
            $notificacion .=  $n . ';';
        }

        return $notificacion;
    }

    private function linea999_indicadorenvio(){
        $indicadorEnvio = '';
        $linea = '999';
        $envioSET = 'true';
        $envioEDI = 'false';

        $ie = [$linea, $envioSET, $envioEDI];
        foreach($ie as $i){
            $indicadorEnvio .=  $i . ';';
        }

        return $indicadorEnvio;
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
        }elseif($doc != '' && trim($dig) != ''){
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
        //tipper -> 0,1: fisica, 2:juridica , 3:gubernamental
        // Tipo de operación
        // 1= B2B
        // 2= B2C
        // 3= B2G (Cliente gubernamental)
        // 4= B2F (Esta última opción debe utilizarse solo en caso de servicios para empresas o personas físicas del exterior)

        $valCed = $this->validarCedula($doc);
        $res = 2; //B2C
        if($tipper == 1 || $tipper == 0) $res = 2; //B2C
        if($tipper == 2) $res = 1; //B2B
        if($tipper == 3) $res = 3; //B2G
        if($valCed == 3) $res = 4; //B2F

        return $res;
    }


    private function guardarEnFacturaElectronica($nroFactura, $timbrado){
        log::info('Entra para guardar en tabla de control ' . $nroFactura);

        $fac = FacturaElectronica::where('nro_factura', $nroFactura)->where('timbrado', $timbrado)->first();

        if(!$fac){
            $f = new FacturaElectronica();
            $f->nro_factura = $nroFactura;
            $f->idestadotxt = 1;
            $f->timbrado = $timbrado;
            $f->txt = 1;
            $f->save();
        }else{
            $fac->idestadotxt = 1;
            $fac->txt = 1;
            $fac->save();
        }
    }

    private function writeTxt($cabecera,$detalles, $totales, $resumen, $delectronico, $fac, $timb, $linea_timbrado,
    $opeComercial, $datosReceptor, $datosFE, $condicionOperacion, $formaPago, $camposCheque , $itemsOpe, $precios,
     $camposIva, $ctotales, $infoAdicional, $notificacion, $descAnt, $indicadorEnvio){
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
        if($camposCheque != '') Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $camposCheque);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $itemsOpe);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $precios);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $camposIva);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $descAnt);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $ctotales);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $infoAdicional);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $notificacion);
        Storage::disk('s4')->append($timb . '-'.$fac.'.txt', $indicadorEnvio);
    }

    private function generarCodSeguridad($fac, $timb){
        $cod = FacturaElectronica::where('nro_factura', $fac)->where('timbrado', $timb)->first();

        if($cod->codseguridad != ''){
            $codSeg = str_repeat(0, (9-strlen($cod->codseguridad))) . $cod->codseguridad;
            return $codSeg;
        }else{
            $codigo = rand(1,999999999);
            $res = 0;

            while($res == 0){
                $codSeg = FECodigoSeguridad::where('codigo', $codigo)->first();
                if($codSeg){
                    $codigo = rand(1,999999999);
                }else{
                    $res = 1;
                }
            }

            $this->guardarCodigo($codigo);
            $codigoSeguridad = str_repeat(0, (9-strlen($codigo))) . $codigo;
            $this->guardarCodigoFE($codigoSeguridad, $fac, $timb);
            return $codigoSeguridad;
        }
    }


    private function guardarCodigo($codigo){
        $c = new FECodigoSeguridad();
        $c->codigo = $codigo;
        $c->save();
    }

    private function guardarCodigoFE($codigoSeguridad, $fac, $timb){
        $fc = FacturaElectronica::where('nro_factura', $fac)->where('timbrado', $timb)->first();

        $fc->codseguridad = $codigoSeguridad;
        $fc->save();
    }

    private function generarCDC($rucsigesa,$nrofac,$fechaEmision,$tipoEmision,$codSeg,$timb){
        $tipodoc = '01';
        $tipoCont = '2';
        $fecha = $fechaEmision->format('Ymd');
        $rs = str_replace("-","",$rucsigesa);
        $nf = str_replace("-","",$nrofac);
        $var = $tipodoc . $rs . $nf . $tipoCont . $fecha . $tipoEmision . $codSeg;

        log::info('variable: ' .$var);
        $digVerificador = $this->generarDigitoVerificacion($var);
        log::info('dig: ' . $digVerificador);
        $cdc = $var . $digVerificador;
        log::info('union: ' . $cdc);

        $this->guardarCDC($nrofac, $cdc, $timb);
    }

    private function generarDigitoVerificacion($cdc){
        $dV = DB::Select(
            "SELECT digito_verificador(:a)", ['a'=> $cdc]);
        return $dV[0]->digito_verificador;
    }

    private function guardarCDC($nrofac, $cdc, $timb){
        log::info('Cdc de la factura ' . $nrofac . ' :' .$cdc);
        $f = FacturaElectronica::where('nro_factura', $nrofac)->where('timbrado', $timb)->first();
        $f->cdc = $cdc;
        $f->save();
    }

    private function getDatosTimbrado($timb){
        $t = Timbrado::where('timbrado', $timb)->first();
        return $t;
    }
}
