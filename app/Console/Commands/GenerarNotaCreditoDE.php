<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\FECodigoSeguridad;
use App\Models\MotivoAvisoCredito;
use App\Models\Timbrado;
use App\Models\Factura;
use App\Models\FacturaElectronica;
use App\Models\Producto;
use App\Models\NotaCreditoDTE;
use App\Models\NotaCreditoDTEDetalle;
use App\Models\NotaElectronica;
use Illuminate\Support\Facades\DB;
use App\Models\Mail;
use App\Models\Cotizacion;

class GenerarNotaCreditoDE extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notacredito:txt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        log::info('Inicia recorrido de --tabla-- para generación de las facturas electrónicas');
        //obtenemos fecha de inicio y fin del mes actual

        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();

        $this->obtenerNotasMes($start, $end);
    }

    private function obtenerNotasMes($start, $end){
        //$nc = NotaCreditoDTE::where('fecha', '>=', $start)->where('fecha', '<=', $end)->get();
        
        $notas = DB::Select(
            "SELECT id from nota_credito_dte where fecha >= (:a) and fecha <= (:b) 
             and id not in (select notacreditoid from nota_electronica where idestadotxt != 6)  
             --limit 1
             ",['a'=> $start,'b' => $end]
        );
        
        if($notas){
            foreach($notas as $n){
                $nc = NotaCreditoDTE::where('id', $n->id)->first();
                $this->guardarNotaElectronica($nc);
                $this->generarArchivo($nc);
            }
        }

    }

    private function guardarNotaElectronica($nota){

        $nc = NotaElectronica::where('notacreditoid', $nota->id)->first();

        if(!$nc){
            $ntc = new NotaElectronica();
            $ntc->notacreditoid = $nota->id;
            $ntc->idestadotxt = 1;
            $ntc->save();
        }else{
            $nc->idestadotxt = 1;
            $nc->save();
        }
    }

    private function generarArchivo($nota){
        $factura = Factura::with('cliente')->where('fac', $nota->facnro)->where('timb', $nota->timbrado)->first();
        $det = NotaCreditoDTEDetalle::where('nota_credito', $nota->id)->get();
        $contador = 1;
        $detalle = '';
        $datosope = '';
        $precios = '';
        $iva = '';
        $anticipos = '';
        $notificacion  = '';
        $rucSigesa = '80023587-8';
        
        foreach($det as $d){
            if($contador ==  1){
                $cabecera = $this->linea1_cabecera($nota, $factura->cliente); //listo
                $detalle = $this->linea2_detalle($contador, $nota, $d, $factura);
                $datosope = $this->linea118_operacion($contador, $d);
                $precios = $this->linea119_precios($contador, $d);
                $iva = $this->linea120_iva($contador, $d);
                $anticipos = $this->linea128_descuentosAnticipos($contador);
                $notificacion = $this->linea980_notificacion($contador);
            }else{
                $detalle .= "\n"  . $this->linea2_detalle($contador, $nota, $d, $factura);
                $datosope .= "\n"  . $this->linea118_operacion($contador, $d);
                $precios .= "\n"  . $this->linea119_precios($contador, $d);
                $iva .= "\n"  . $this->linea120_iva($contador, $d);
                $anticipos .= "\n"  . $this->linea128_descuentosAnticipos($contador);
                $notificacion .= "\n"  . $this->linea980_notificacion($contador);

            }
            $contador +=1;
        }
        $resumen = $this->linea9_resumen($cabecera, $detalle, $contador);
        $de =  $this->linea100_documentoElectronico($nota->id);
        $documentoElectronico = $de['docelectronico'];
        $timbrado = $this->linea101_datostimbrado($nota->timbrado, substr($nota->facnro, 0, 7));
        $opecomercial = $this->linea102_operacioncomercial($nota);
        $dr = $this->linea103_receptordeldocumento($factura->cliente);
        $datosreceptor = $dr['datosReceptor'];
        $datosnc = $this->linea110_camposnotacredito($nota);
        $totalesant = $this->linea140_totalesant();
        $docasociado = $this->linea160_documentoasociado($nota);
        $infoAdicional = $this->linea180_informacionadicional();
        $envio = $this->linea999_indicadorenvio();
        //escribir txt
        $this->writeTxt($nota, $cabecera,
                        $detalle, $resumen, $documentoElectronico, $timbrado,
                        $opecomercial, $datosreceptor, $datosnc, $datosope, $precios, $iva, $anticipos,
                        $totalesant, $docasociado, $infoAdicional, $notificacion, $envio);

        $tipoEmision= 1; //fijo por el momento 
        $this->generarCDC($rucSigesa, $nota, $tipoEmision, $de['codseguridad']);
    }

    private function generarCDC($rucsigesa, $nota, $tipoEmision,$codSeg){
        $timb = Timbrado::where('timbrado', $nota->timbrado)->where('ser', substr($nota->facnro, 0, 7))->first();
        $nro_nota =$timb->ser . '-'. $nota->nro_nota;

        $tipodoc = '05';
        $tipoCont = '2';
        $fecha = Carbon::parse($nota->fecha)->format('Ymd');
        $rs = str_replace("-","",$rucsigesa);
        $nc = str_replace("-","",$nro_nota);
        $var = $tipodoc . $rs . $nc . $tipoCont . $fecha . $tipoEmision . $codSeg;

        log::info('variable: ' .$var);
        $digVerificador = $this->generarDigitoVerificacion($var);
        log::info('dig: ' . $digVerificador);
        $cdc = $var . $digVerificador;
        log::info('union: ' . $cdc);

        $this->guardarCDC($nota, $cdc);
    }

    private function generarDigitoVerificacion($cdc){
        $dV = DB::Select(
            "SELECT digito_verificador(:a)", ['a'=> $cdc]);
        return $dV[0]->digito_verificador;
    }  

    private function guardarCDC($nota, $cdc){
        log::info('Cdc de la nota ' . $nota->nro_nota . ' :' .$cdc);
        $nc = NotaElectronica::where('notacreditoid', $nota->id)->first();
        $nc->cdc = $cdc;
        $nc->save();
    }

    private function linea1_cabecera($nota, $cli){
        $timb = Timbrado::where('timbrado', $nota->timbrado)->where('ser', substr($nota->facnro,0,7))->first();
        $nro_nota =$timb->ser . '-'. $nota->nro_nota;
        log::info($nro_nota);

        $cabecera = ''; // declaramos la línea que vamos a formar y se va a retornar

        $linea = 1;
        $textoFijoUno = 'DEBITCREDITADVICE';
        $fechaHora = Carbon::parse($nota->fecha); 
        $textoFijoDos = 'ORIGINAL';
        $nroDocumento = $nota->timbrado.'-'.trim(str_replace("-","",$nro_nota)); // TIMBRADO - N° NOTA
        $GLNCreador = ''; // opcional
        $moneda = 'PYG';
        if($nota->mon == 1) $moneda = 'USD';
        $importeTotal = intVal($nota->total); //importe total iva incluído
        $indicadorMovimiento = 'CREDIT'; // fijo porque solo generamos notas de crédito
        $GLNCliente = ''; // opcional

        //VALIDACION DE CONTRIBUYENTE
        $documento =  $this->validarRuc(trim($cli->ruc), trim($cli->doc), trim($cli->docd));
        
        $fijoRuc = 'RUC';
        $GLNProveedor = ''; // opcional
        $rucProveedor = '80023587-8'; // se informa seguido del texto fijo RUC

        $cab = [$linea, $textoFijoUno, $fechaHora,
                $textoFijoDos, $nroDocumento, $GLNCreador,
                $moneda, $importeTotal, $indicadorMovimiento, 
                $GLNCliente, $documento, $fijoRuc,
                $GLNProveedor, $rucProveedor, $fijoRuc];

        foreach($cab as $c){
            $cabecera .=  $c . ';';
        }

        return $cabecera;
        
    }

    private function linea2_detalle($lineaDetalle, $nota, $detallenota, $factura){
        //datos externos
        $motivo = MotivoAvisoCredito::where('id', $nota->motivo_aviso)->first();
        $prod = Producto::where('con', $detallenota->producto)->first();

        //datos para la línea a generar
        $detalle = ''; // declaramos la línea que vamos a formar y se va a retornar

        $linea = 2;
        $indicadorMovimiento = 'CREDIT'; // fijo porque solo generamos notas de crédito
        $codMotivoAviso = $motivo->codigo; 
        $descripcionMotivo =utf8_encode($motivo->descripcion); 
        $moneda = 'PYG'; // todas las notas se emiten en moneda paraguaya
        $monto = intVal($detallenota->monto); 
        $tipoDocumento = 'INVOICE'; // corresponde a Factura, fijo para las notas de crédito.
        $nroDocumentoFactura = $nota->facnro; //al que corresponde la nota
        $GLNCreador = ''; // opcional
        $fechaFactura = $factura->fec; //YYYY-MM-DD
        $codigoInterno = $detallenota->producto;
        $monedaSegunComprador = ''; //opcional
        $precioUnitarioSegunComprador = ''; //opcional
        $monedaFactura = ''; //opcional
        $precioUnitarioFactura = ''; //opcional
        $cantidadProducto = 1; //fijo, siempre un producto por detalle
        $unidadMedida = 'PCS';
        $idiomaObs = 'SP'; //fijo
        $observacion = ''; //opcional
        $codMoneda = 'PYG';
        $precioUnitarioPublico = ''; //opcional
        $producto = trim($prod->nomcon);
        $porcIva = $detallenota->porcentaje_iva;

        $det = [$linea, $lineaDetalle, $indicadorMovimiento, $codMotivoAviso,
                $descripcionMotivo, $moneda, $monto,
                $tipoDocumento, $nroDocumentoFactura, $GLNCreador, 
                $fechaFactura, $codigoInterno, $monedaSegunComprador,
                $precioUnitarioSegunComprador, $monedaFactura, $precioUnitarioFactura,
                $cantidadProducto, $unidadMedida, $idiomaObs,
                $observacion, $codMoneda, $precioUnitarioPublico, $producto, $porcIva];

        foreach($det as $d){
            $detalle .=  $d . ';';
        }

        return $detalle;
    }

    private function linea9_resumen($cabecera, $detalles, $contador){
        $resumen = '';

        $caracteres = strlen($cabecera) +( strlen($detalles) - ($contador - 1));

        $linea = 9;

        $res = [$linea, ($contador - 1), $caracteres];

        foreach($res as $r){
            $resumen .=  $r . ';';
        }

        return $resumen;
    }

    private function linea100_documentoElectronico($idnota){
        
        $doc_electronico = '';
        $linea = 100;
        $cod_seg = $this->generarCodSeguridad($idnota);

        $informacionInteresEmisor = ''; //permite informar 3000 caracteres
        $informacionInteresFisco = ''; //permite informar 3000 caracteres

        $de = [$linea,$cod_seg,$informacionInteresEmisor,$informacionInteresFisco];

        foreach($de as $d){
            $doc_electronico .=  $d . ';';
        }

       return ['docelectronico'=>$doc_electronico, 'codseguridad'=> $cod_seg ];

       //return $doc_electronico;
    }

    private function generarCodSeguridad($idnota){
        $cod = NotaElectronica::where('notacreditoid', $idnota)->first();
        
        if($cod->codseguridad != ''){
            return $cod->codseguridad;
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

            //consultar si el código de seguridad puede ser repetible con el código que está en la factura
            $this->guardarCodigoNota($codigo); 
            $codigoSeguridad = str_repeat(0, (9-strlen($codigo))) . $codigo;
            $this->guardarCodigoNE($codigoSeguridad, $idnota);
            return $codigoSeguridad;
        }

    }

    private function guardarCodigoFE($codigoSeguridad, $idnota){
        $nc = NotaElectronica::where('notacreditoid', $idnota)->first();
        
        $nc->codseguridad = $codigoSeguridad;
        $nc->save();
    }

    private function guardarCodigoNota($codigo){
        $c = new FECodigoSeguridad();
        $c->codigo = $codigo;
        $c->save();
    }

    private function linea101_datostimbrado($timbrado, $ser){

        $timb = $this->getDatosTimbrado($timbrado, $ser);
        $datosTimbrado = '';
        $linea = 101;
        $fechaInicio = $timb->desfec;
        $serie = '';
        $tipoDocumentoElectronico = 5; //nota de crédito electrónico - fijo 

        $timb = [$linea,$fechaInicio,$serie,$tipoDocumentoElectronico];

        foreach($timb as $t){
            $datosTimbrado .=  $t . ';';
        }

       return $datosTimbrado;
    }
    
    private function linea102_operacioncomercial($nota){
        $operacionComercial = '';
        
        $linea = 102;
        $tipoTransaccionEmisor = ''; // a determinar
        $condicionTipoCambio = ''; //no informamos
        if($nota->moneda == 1) 
            $condicionTipoCambio = 1;

        //informamos cuando $moneda es diferente a 6900
        $tipoCambio = ''; 
        if($condicionTipoCambio == 1) {
            $cotizacion = Cotizacion::where('fec', Carbon::parse($nota->fecha)->format('Ymd'))->first();
            $tipoCambio = intval($cotizacion->cotiza);
        }
        $tipoImpuesto = 1; // IVA - codigo fijo para el tipo de impuesto que cobramos
        $condicionAnticipo = ''; //no hay anticipo, no se informa

        $ope = [$linea,$tipoTransaccionEmisor,$condicionTipoCambio,
                $tipoCambio,$tipoImpuesto,$condicionAnticipo];

        foreach($ope as $o){
            $operacionComercial .=  $o . ';';
        }

       return $operacionComercial;
    }

    private function linea103_receptordeldocumento($cliente){

        $receptorDE = '';
        $linea = 103;
        $r = $this->validarRuc(trim($cliente->ruc), trim($cliente->doc), trim($cliente->docd));
        $naturalezaReceptor = 2; //no contribuyente 
        if($r != '') $naturalezaReceptor = 1; //contribuyente 
        $tipo_operacion = $this->tipoOperacion($cliente->tipper, $cliente->doc);
        $codPais = 'PRY';// codigo fijo 
        $tipoContribuyente = '';
        if($naturalezaReceptor == 1){
            if($cliente->tipper ==  1 || $cliente->tipper == 0){ //FISICA
                $tipoContribuyente = 1;
            }elseif($cliente->tipper ==  2){
                $tipoContribuyente = 2;  //JURIDICA
            }
        }
        $tipoDoc = '';
        if($naturalezaReceptor == 2 && $tipo_operacion != 4) $tipoDoc = $this->validarCedula($cliente->doc);
        $nroDocumento = '';
        if($naturalezaReceptor == 2) $nroDocumento = $cliente->doc;
        $razonSocial = trim($cliente->nom ). ' ' . trim($cliente->ape);
        $nombreFantasia = ''; // no informamos
        $direccionReceptor = ''; // no informamos
        $telReceptor = ''; // no informamos
        $celReceptor = ''; // no informamos
        $datosCorreo = Mail::where('cedula', $cliente->doc)->first();
        $correoReceptor = ''; 
        if(isset($datosCorreo->correo)) $correoReceptor = $datosCorreo->correo;
        $codCliente = ''; //no informamos
        $descripcionDocumento = '';
        $nroCasaReceptor = ''; // no informamos
        $codDepartamentoReceptor = ''; // no informamos
        $codDistritoReceptor = ''; // no informamos
        $codCiudadReceptor = ''; // no informamos

        $receptor = [$linea,$naturalezaReceptor,$tipo_operacion,
                    $codPais,$tipoContribuyente,$tipoDoc,
                    $nroDocumento,$razonSocial,$nombreFantasia,
                    $direccionReceptor,$telReceptor,$celReceptor,
                    $correoReceptor, $codCliente, $descripcionDocumento,$nroCasaReceptor,
                    $codDepartamentoReceptor,$codDistritoReceptor,$codCiudadReceptor];
        
        foreach($receptor as $r){
            $receptorDE .=  $r . ';';
        }

        return ['datosReceptor'=> $receptorDE,'contribuyente'=>$naturalezaReceptor];         
    }

    private function linea110_camposnotacredito($nota){
        $camposNotaCredito = '';
        $linea = 110;
        //OBTENER MOTIVO
        $codMotivoEmision = $nota->motivo_emision;

        $nota = [$linea,$codMotivoEmision];
        
        foreach($nota as $n){
            $camposNotaCredito .=  $n . ';';
        }

        return $camposNotaCredito;          
    }

    private function linea118_operacion($lineaDetalle, $detalle){
        // $prod = Producto::where('regnro', $detalle->producto)->first();

        $operacion = '';

        $linea = 118;
        $codigoInterno = $detalle->producto; //obtener
        $partidaArancelaria = ''; //no informamos
        $nomenclaturaMC = ''; //no informamos
        $codDNCPgeneral = ''; // informar si es necesario
        $codDNCPespecifico = ''; // informar si es necesario
        $codGTIN = ''; //no informamos
        $codPaisProducto = ''; //no informamos
        $informacionInteres = ''; //no informamos
        $codDatos = ''; //consultar
        $cantMerma = ''; //consultar
        $porcMerma = ''; //consultar
        $cdcAnticipo = ''; //consultar

        $ope = [$linea,$lineaDetalle,$codigoInterno,$partidaArancelaria,$nomenclaturaMC,
                $codDNCPgeneral,$codDNCPespecifico,$codGTIN,$codPaisProducto,$informacionInteres,
                $codDatos,$cantMerma,$porcMerma,$cdcAnticipo ];
        
        foreach($ope as $o){
            $operacion .=  $o . ';';
        }

        return $operacion;   
    }

    private function linea119_precios($lineaDetalle, $detalle){
        $precios = '';

        $linea = 119;
        $precioUnitarioProducto = intVal($detalle->monto); 
        $tipoCambioItem = ''; //no informamos

        $precio = [$linea,$lineaDetalle,$precioUnitarioProducto,$tipoCambioItem];
        
        foreach($precio as $p){
            $precios .=  $p . ';';
        }

        return $precios;   

    }

    private function linea120_iva($lineaDetalle, $detalle){
        
        $camposIva = '';
        $afectacionTributaria = '';//1->gravado iva 2->exonerado 3->exento 4->grabado parcial
        $porcGravada = '';

        if($detalle->porcentaje_iva == 10 || $$detalle->porcentaje_iva == 5){
            $afectacionTributaria = 1;
            $porcGravada = 100;
        }else if($detalle->porcentaje_iva= 0){
            $afectacionTributaria = 3;
            $porcGravada = 0;          
        }
        
        $ci = [120,$lineaDetalle, $afectacionTributaria, $porcGravada];

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

    private function linea140_totalesant(){
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

    private function linea160_documentoasociado($nota){
        $documentoAsociado = '';
        $fe = FacturaElectronica::where('nro_factura', $nota->facnro)->where('timbrado', $nota->timbrado)->first();
        $linea = 160;
        $tipoDocAsociado = 1 ; //cod 1 corresponde a 'electrónico'

        $CDCasociado = $fe->cdc; //obtener el cdc de la factura
        $nroTimbradoImpreso = ''; //no informamos
        $establecimiento = ''; //no informamos
        $puntoExpedicion = ''; //no informamos
        $nroDocumento = ''; //no informamos
        $tipoDocImpreso = ''; //no informamos
        $fechaEmisionDocImpreso = ''; //no informamos
        $nroComprobanteRetencion = ''; //no informamos
        $nroResolucionCredFiscal = ''; //no informamos
        $tipoConstancia = ''; //no informamos
        $nroConstancia = ''; //no informamos
        $nroControlConstancia = ''; //no informamos

        $doc = [$linea,$tipoDocAsociado,$CDCasociado,$nroTimbradoImpreso,$establecimiento,
        $puntoExpedicion,$nroDocumento,$tipoDocImpreso,$fechaEmisionDocImpreso,$nroComprobanteRetencion,
        $nroResolucionCredFiscal,$tipoConstancia,$nroConstancia,$nroControlConstancia ];

        foreach($doc as $d){
            $documentoAsociado .=  $d . ';';
        }

        return $documentoAsociado;   

    }

    private function linea180_informacionadicional(){
        $infoAdicional = '';
        $linea = 180;
        $datos ='';
        $inf = [$linea, $datos];
        foreach($inf as $i){
            $infoAdicional .=  $i . ';';
        }

        return $infoAdicional;
    }

    private function linea980_notificacion($linDet){
        $notificacion = '';
        $linea = '980';
        $correo = 'anibarrola@sistemasygestiones.com.py';
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

    private function getDatosTimbrado($timb, $ser){
        $t = Timbrado::where('timbrado', $timb)->where('ser', $ser)->first(); 
        return $t;
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

    private function validarCedula($doc){

        $res = 1; //ci paraguaya 
  
        //return false si empieza con letra - ci extranj.
        $d = is_numeric($doc[0]);
        if(!$d) $res = 3;
        return $res;
    }

    private function writeTxt($nota, $cabecera, $detalle, $resumen, $documentoElectronico, $timbrado, 
    $opecomercial, $datosreceptor, $datosnc, $datosope, $precios, $iva, $anticipos, $totalesant, $docasociado, $infoAdicional, $notificacion, $envio){

        Storage::disk('s4')->put('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $cabecera);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $detalle);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $resumen);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $documentoElectronico);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $timbrado);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $opecomercial);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $datosreceptor);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $datosnc);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $datosope);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $precios);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $iva);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $anticipos);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $totalesant);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $docasociado);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $infoAdicional);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $notificacion);
        Storage::disk('s4')->append('NC-'.$nota->timbrado. '-'.$nota->nro_nota.'.txt', $envio);
    }
}


// query

// create table nota_electronica(
// 	id serial primary key,
// 	notacreditoid BIGINT, 
// 	created_at TIMESTAMP WITHOUT TIME ZONE,
// 	updated_at TIMESTAMP WITHOUT TIME ZONE,
// 	cdc character varying(44),
// 	idestadotxt integer,
// 	fechaprocesamiento timestamp without time zone,
// 	mensajeresultado character varying(300)
// )

// alter table nota_electronica
// 	add constraint UQ_notacredito
//  	unique (notacreditoid);