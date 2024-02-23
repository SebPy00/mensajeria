<?php

namespace App\Console\Commands;

use App\Jobs\VerificarEnvioMensajes;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;
use App\Models\EnvioMensajes;
use App\Models\EnvioMensajesDetalle;
use App\Models\Cliente;
use App\Models\HistorialEnvioID;
use App\Models\ListaNegra;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Log;

class EnviarMensajes extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enviar:mensajes';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envío de mensajes';

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
        $date = new Carbon('today');
        if($date->dayName != 'domingo'){
            $hora= Carbon::now()->toTimeString();

            if ($hora >='07:00:00' && $hora <= '19:00:00') {
                $this->obtenerLote();
            }
        }else{
            log::info('ATENCIÓN! NO SE PROCESAN MENSAJES LOS DÍAS DOMINGO');
        }
    }

    public function obtenerLote(){

        $fechaActual= Carbon::now()->toDateString();

        $lote = EnvioMensajes::where('fechaenvio', '<=', $fechaActual)
        ->where('fecha_envio_hasta', '>=', $fechaActual)
        ->where('tipo', '!=', 3 ) //tipo solo para subir chatbot y autogestor
        ->where(function ($q){
            $q->orWhere('idestado', 1); //PENDIENTE
            $q->orWhere('idestado', 4); //DETENIDO POR HORARIO
        })->where('aprobado', 1)
        ->whereHas('detalles', function ($q) {
            $q->where('enviado', 2);
        })->orderBy('id', 'asc')->first();

        if($lote){
            log::info('------------ Inicio Envío mensajes en lote: ' . $lote->id . '------------');
            $lote->idestado = 2;
            $lote->intentos += 1;
            $lote->save();
            $this->procesarLoteMensajes($lote);
        }else{
            //log::info('------------ Sin lotes pendientes de envío ----------a--');
            $this->lotesPendientesVencidos();
        }
    }

    public function lotesPendientesVencidos(){

        //BUSCAR PENDIENTES CON FECHA VENCIDA
        $fechaActual= Carbon::now()->toDateString();

        $vencido = EnvioMensajes::where('fechaenvio', '<=', $fechaActual)
        ->where('fecha_envio_hasta', '<=', $fechaActual)
        ->where('tipo', '!=', 3 ) //tipo solo para subir chatbot y autogestor
        ->where(function ($q){
            $q->orWhere('idestado', 1); //PENDIENTE
            $q->orWhere('idestado', 4); //DETENIDO POR HORARIO
        })->where('aprobado', 1)
        ->whereHas('detalles', function ($q) {
            $q->where('enviado', 3); // despachado - a verificar
        })->orderBy('id', 'asc')->first();

        if($vencido){
            log::info('------------ Verificando lote vencido : ' . $vencido->id . '------------');
            $vencido->idestado = 2;
            $vencido->save();
            $this->verificarEnvioMensajes($vencido->id);
        }else{
            $this->pasarAprocesadoLoteVencido();
        }
    }

    public function pasarAprocesadoLoteVencido(){
        //BUSCAR PENDIENTES CON FECHA VENCIDA
        //log::info('------------ Busqueda lote pendiente con fecha vencida ------------');
        $fechaActual= Carbon::now()->toDateString();

        $procesar = EnvioMensajes::where('fechaenvio', '<=', $fechaActual)
        ->where('fecha_envio_hasta', '<=', $fechaActual)
        ->where('tipo', '!=', 3 ) //tipo solo para subir chatbot y autogestor
        ->where(function ($q){
            $q->orWhere('idestado', 1); //PENDIENTE
            $q->orWhere('idestado', 4); //DETENIDO POR HORARIO
        })->where('aprobado', 1)->orderBy('id', 'asc')->first();

        if($procesar){
            log::info('------------ Lote vencido nro: '.$procesar->id.' pasa a procesado ------------');
            $procesar->idestado = 3;
            $procesar->save();
        }
    }

    public function procesarLoteMensajes($lote){
        try{
            $detalle = EnvioMensajesDetalle::where('idenviomensaje', $lote->id)
            ->where('enviado', 2)->where('intentos', '<', 1)->orderBy('id', 'desc')->get();

            if($detalle){
                log:info('Inicia recorrido para envio de mensajes');

                //VERIFICACION DE HORARIO

                $date = Carbon::now();
                $desde = '08:00:00';
                $hasta = '18:00:00';

                if($date->dayName == 'sábado'){
                	//12:00
                    $hasta = '12:00:00';
                }

                $contador = 0;
                $contador2 = 0;
                foreach ($detalle as $d) {
			try{
		            $horaActual= Carbon::now()->toTimeString();
		            if ($horaActual >= $desde && $horaActual <= $hasta) {
		             	/*$contador2 +=1;
		            	if ($contador2 == 100){
		            		$contador2=1;
		            		sleep(30);
		            	} */
		                if($contador < 100){
		                    $this->procesar($d,  $lote->idareamensaje, $lote->tipo, $lote->idcategoriamensaje, utf8_decode($lote->mensaje));
		                    $contador +=1;
		                }else{
		                    sleep(30);
		                    $estado = $this->verificarEstadoLote($lote->id);
		                    if(empty($estado)){
		                        $this->procesar($d,  $lote->idareamensaje, $lote->tipo, $lote->idcategoriamensaje, utf8_decode($lote->mensaje));
		                        $contador = 1;
		                    }else{
		                        break;
		                    }
		                }
		            }else{
		                log::info('No se puede realizar envío fuera de horario. Cambiando estado de la cabecera: DETENIDO');
		                $lote->idestado = 4;
		                $lote->save();
		                break;
		            }
		 	}catch (Exception $ex) {
			    log::info('ERROR 1.5: foreach Peticion:  - '. $ex->getMessage());
			    sleep(30);
			}
                }
               $this->verificarEnvioMensajes($lote);
            }
        }catch (Exception $ex) {
            throw new Exception('ERROR 1: funcion procesarLoteMensajes - ' . $ex->getMessage());
        }
    }

    public function verificarEnvioMensajes($lote){
        if(isset($lote->id)){
            $estado = $this->getLoteEnProceso($lote->id);
            // log::info('Posponiendo verificación en 10 minutos');
            // VerificarEnvioMensajes::dispatch($lote->id)->delay(now()->addMinutes(10));
            // CAMBIAMOS ESTADO DEL LOTE PORQUE EL JOB NO ESTÁ FUNCIONANDO
            $lote->idestado = 7; //A VERIFICAR
            $lote->save();
        }else{
            log::info('no disponible para verificaar');
        }
    }

    public function getLoteEnProceso($id){
        $estado = EnvioMensajes::where('id', $id)
        ->where('idestado', 2)->first(); //traer si tiene estado EN PROCESO

        return $estado;
    }

    public function verificarEstadoLote($id){
        $estado = EnvioMensajes::where('id', $id)
        ->where('idestado', 5)->first(); //traer si tiene estado CANCELADO

        return $estado;
    }

    public function procesar($d, $area, $tipo, $categoria, $mensajeOriginal){
        $mensaje = urlencode($mensajeOriginal)
        $nro = '0'. $d->nrotelefono;
        $listaNegra = $this->verificarListaNegra($nro, $d->ci);
        if(!empty($listaNegra)){
            $d->enviado = 4; //nro en la lista negra
            $d->save();
        }else{
            if( $area != 3){ // si el area no es servicios
                $cliente = $this->verificarCliente($d->ci);
                if(empty($cliente)){
                    return;
                }
                if($tipo == 2) $url = $this->estructuraMensajeUno($cliente, $mensaje, $nro, $categoria);
                if($tipo == 1) $url = $this->estructuraMensajeDos($mensaje, $nro, $categoria, $cliente);
            }else{
                $url = $this->estructuraMensajeDos($mensaje, $nro, $categoria, '');
            }
            $this->enviarMensaje($url, $d);
        }
    }

    public function enviarMensaje($url, $d){
        $client = new Client();
        try {
            log::info($url);
            $response = $client->post($url);
            $res = json_decode($response->getBody());
            if (!empty($res)) {
                //log::info($res->message);
                if(!empty($res->id)){
                    //guardamos el historial
                    $historial = new HistorialEnvioID();
                    $historial->idenviotigo = $res->id;
                    $historial->iddetallemensaje = $d->id;
                    $historial->save();
                    //modificamos el detalle
                    $d->idenvio = $res->id;
                    $d->enviado = 3; //despachado para envio
                    $d->intentos += 1; //agregamos cantidad de intentos
                    $d->save();
                }else{
                    $d->intentos += 1;
                    $d->save();
                }
            }
        } catch (Exception $ex) {
            $pref = 'webservice => ';
            log::info('ERROR 1: funcion enviarMensaje - ' . $pref . $ex->getMessage());
            //throw new Exception('ERROR 1: funcion enviarMensaje - ' . $pref . $ex->getMessage());
        }
    }

    public function estructuraMensajeUno($cliente, $mensaje, $nro, $categoria){
        $m = explode(":", $mensaje);
        $sms = $m[0]. trim(($cliente->nom )). ' '. trim(($cliente->ape)).$m[1]  ;

        if($categoria == 2) { //chatbot
            $bot = explode("NROCLI", $sms);
            $mje = $bot[0].$cliente->cli.$bot[1];
            $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
            '&message='.$mje .'&msisdn='.$nro;
        }else{
            $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
            '&message='.$sms .'&msisdn='.$nro;
        }
        return $url;
    }

    public function estructuraMensajeDos( $mensaje, $nro, $categoria, $cliente){
        if($categoria == 2) { //chatbot
            $bot = explode("NROCLI", $mensaje);
            $mje = $bot[0].$cliente->cli.$bot[1];
            $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
            '&message='.$mje .'&msisdn='.$nro;
        }else{
            $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
            '&message='.$mensaje .'&msisdn='.$nro;
        }
        return $url;
    }

    public function verificarListaNegra($nro, $ci){

        $listaNegra = ListaNegra::where('tel', $nro)
        ->where(function ($q) use ($ci) {
            $q->orWhere('cedula', $ci);
            $q->orWhere('cedula', '0');
        })
        ->where('sms', true)->first();

        // $listaNegra = DB::Select("SELECT * from lista_negra
        // where tel = (:a) and (cedula = (:b) or cedula = '0')",
        // ['a'=> $nro,'b' => $ci]);

        return $listaNegra;
    }

    public function verificarCliente($ci){
        $cliente = Cliente::where('doc', $ci)->first();
         return $cliente;
    }

}
