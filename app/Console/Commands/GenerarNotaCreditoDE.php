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
        $notasCredito = DB::Select(
            "",
        );
        
        if($notasCredito){
            foreach($notasCredito as $n){
                $this->guardarEnFacturaElectronica();
                // $linea_timbrado = $this->formarCamposTimbrado($f->timb, $f->serie);
                $this->getDatosNotaCredito($n);
            }
        }

    }

    private function getDatosNotaCredito($n){
        $notasCredito = ''; // AÑADIR SELECT PARA OBTENER DATOS

        $contador = 1;
        $cabecera = $this->linea1_cabecera();
        $detalle = $this->linea2_detalle($contador);
        $resumen = $this->linea9_resumen($cabecera, $detalle, $contador);
        $documentoElectronico = $this->linea100_documentoElectronico();
        $timbrado = $this->linea101_datostimbrado();
    }
    private function linea1_cabecera(){
        $cabecera = ''; // declaramos la línea que vamos a formar y se va a retornar

        $linea = 1;
        $textoFijoUno = 'DEBITCREDITADVICE';
        $fechaHora = '';
        $textoFijoDos = 'ORIGINAL';
        $nroDocumento = ''; // TIMBRADO - N° NOTA
        $GLNCreador = ''; // opcional
        $moneda = 'PYG'; // todas las notas se emiten en moneda paraguaya
        $importeTotal = ''; //importe total iva incluído
        $indicadorMovimiento = 'CREDIT'; // fijo porque solo generamos notas de crédito
        $GLNCliente = ''; // opcional
        $rucCliente = '';
        $fijoRuc = 'RUC';
        $GLNProveedor = ''; // opcional
        $rucProveedor = '80023587-8'; // se informa seguido del texto fijo RUC

        $cab = [$linea, $textoFijoUno, $fechaHora,
                $textoFijoDos, $nroDocumento, $GLNCreador,
                $moneda, $importeTotal, $indicadorMovimiento, 
                $GLNCliente, $rucCliente, $fijoRuc,
                $GLNProveedor, $rucProveedor, $fijoRuc];

        foreach($cab as $c){
            $cabecera .=  $c . ';';
        }

        return $cabecera;
        
    }

    private function linea2_detalle($lineaDetalle){
        $detalle = ''; // declaramos la línea que vamos a formar y se va a retornar

        $linea = 2;
        $indicadorMovimiento = 'CREDIT'; // fijo porque solo generamos notas de crédito
        $codMotivoAviso = ''; //obtener
        $descripcionMotivo = ''; //obtener
        $moneda = 'PYG'; // todas las notas se emiten en moneda paraguaya
        $monto = ''; 
        $tipoDocumento = 'INVOICE'; // corresponde a Factura, fijo para las notas de crédito.
        $nroDocumentoFactura = ''; //al que corresponde la nota
        $GLNCreador = ''; // opcional
        $fechaFactura = ''; //YYYY-MM-DD
        $codigoInterno = '';
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
        $producto = '';
        $porcIva = '';

        $det = [$linea, $indicadorMovimiento, $codMotivoAviso,
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

    private function linea100_documentoElectronico(){
        
        $doc_electronico = '';
        $linea = 100;
        $cod_seg = $this->generarCodSeguridad();

        $informacionInteresEmisor = ''; //permite informar 3000 caracteres
        $informacionInteresFisco = ''; //permite informar 3000 caracteres

        $de = [$linea,$cod_seg,$informacionInteresEmisor,$informacionInteresFisco];

        foreach($de as $d){
            $doc_electronico .=  $d . ';';
        }

       // return ['docelectronico'=>$doc_electronico, 'codseguridad'=> $cod_seg ];

       return $doc_electronico;
    }

    private function generarCodSeguridad(){
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
        //$this->guardarCodigoNota($codigo); --definir donde guardar
        $codigoSeguridad = str_repeat(0, (9-strlen($codigo))) . $codigo;
        return $codigoSeguridad;

    }

    private function linea101_datostimbrado(){
        $datosTimbrado = '';
        $linea = 101;
        $fechaInicio = '';
        $serie = '';
        $tipoDocumentoElectronico = 5; //nota de crédito electrónico - fijo 

        $timb = [$linea,$fechaInicio,$serie,$tipoDocumentoElectronico];

        foreach($timb as $t){
            $datosTimbrado .=  $t . ';';
        }

       return $datosTimbrado;
    }
    
    private function linea102_operacioncomercial(){
        $operacionComercial = '';
        
        $linea = 102;
        $tipoTransaccionEmisor = ''; // a determinar
        $condicionTipoCambio = ''; //no informamos
        $tipoCambio = ''; //no informamos
        $tipoImpuesto = 1; // IVA - codigo fijo para el tipo de impuesto que cobramos
        $condicionAnticipo = ''; //no hay anticipo, no se informa

        $ope = [$linea,$tipoTransaccionEmisor,$condicionTipoCambio,
                $tipoCambio,$tipoImpuesto,$condicionAnticipo];

        foreach($ope as $o){
            $operacionComercial .=  $o . ';';
        }

       return $operacionComercial;
    }

    private function linea103_receptordeldocumento(){
        $receptorDE = '';

        $linea = 103;
        $naturalezaReceptor = '';
        $tipoOperacion = '';
        $codPais = 'PRY';// codigo fijo 
        $tipoContribuyente = '';
        $tipoDoc = '';
        $nroDocumento = '';
        $razonSocial = '';
        $nombreFantasia = ''; // no informamos
        $direccionReceptor = ''; // no informamos
        $telReceptor = ''; // no informamos
        $celReceptor = ''; // no informamos
        $correoReceptor = ''; //HAY QUE INFORMAR PARA ENVIAR LOS DE AL CLIENTE
        $codCliente = ''; //no informamos
        $descripcionDocumento = '';
        $nroCasaReceptor = ''; // no informamos
        $codDepartamentoReceptor = ''; // no informamos
        $codDistritoReceptor = ''; // no informamos
        $codCiudadReceptor = ''; // no informamos

        $receptor = [$linea,$naturalezaReceptor,$tipoOperacion,
                    $codPais,$tipoContribuyente,$tipoDoc,
                    $nroDocumento,$razonSocial,$nombreFantasia,
                    $direccionReceptor,$telReceptor,$celReceptor,
                    $correoReceptor,$descripcionDocumento,$nroCasaReceptor,
                    $codDepartamentoReceptor,$codDistritoReceptor,$codCiudadReceptor];
        
        foreach($receptor as $r){
            $receptorDE .=  $r . ';';
        }

        return $receptorDE;          
    }

    private function linea110_camposnotacredito(){
        $camposNotaCredito = '';

        $linea = 110;

        //OBTENER MOTIVO
        $codMotivoEmision = '';

        $nota = [$linea,$codMotivoEmision];
        
        foreach($nota as $n){
            $camposNotaCredito .=  $n . ';';
        }

        return $camposNotaCredito;          
    }

    private function linea118_operacion($lineaDetalle){
        $operacion = '';

        $linea = 118;
        $codigoInterno = ''; //obtener
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

    private function linea119_precios($lineaDetalle){
        $precios = '';

        $linea = 119;
        $precioUnitarioProducto = ''; //obtener
        $tipoCambioItem = ''; //no informamos

        $precio = [$linea,$lineaDetalle,$precioUnitarioProducto,$tipoCambioItem];
        
        foreach($precio as $p){
            $precios .=  $p . ';';
        }

        return $precios;   

    }

    private function linea160_documentoasociado(){
        $documentoAsociado = '';

        $linea = 160;
        $tipoDocAsociado = 1 ; //cod 1 corresponde a 'electrónico'

        $CDCasociado = ''; //obtener el cdc de la factura
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

    //FALTA IMPLEMENTAR GENERADOR DEL TXT
}
