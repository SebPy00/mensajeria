<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\EnvioMensajes;
use App\Models\EnvioMensajesDetalle;
use App\Models\Cliente;
use App\Models\HistorialEnvioID;
use App\Models\ListaNegra;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Log;

class ProcesarLote implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    
    protected int $id;
    public $timeout = 7200;
    public function __construct($id)
    {   
        $this->id = $id;
        log::info('ID lote recibido a procesar:' . $id);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $vf = $this->verificarFechaHora();

        if($vf){
            $fechaActual= Carbon::now()->toDateString();
            $lote = EnvioMensajes::where('id', $this->id)->first();
            if($lote){
                $res = $this->verificarLote($lote, $fechaActual);
                if($res){
                    $estado = 2; //en proceso
                    $this->cambiarEstadoLote($lote, $estado);
                    $this->procesarLoteMensajes($lote);
                }else{ 
                    $this->procesarLote($lote);
                }
            }
        }
        
    }

    
    private function verificarFechaHora(){
        $res = 1;
        
        $date = new Carbon('today');
        $hora= Carbon::now()->toTimeString(); 
        if($date->dayName == 'domingo') $res = 0;
        if (!($hora >='08:00:00' && $hora <= '18:00:00')) $res = 0;

        return $res;
    }

    private function verificarLote($lote, $fechaActual){
        $res = 1;

        if(!($lote->fecha_envio >= $fechaActual && $lote->fecha_envio_hasta <= $fechaActual) || !($lote->idestado == 4 || $lote->idestado == 1) 
            || !($lote->aprobado == 1)) $res = 0;

        return $res;
    }

    private function cambiarEstadoLote($lote, $estado){
        $lote->idestado = $estado;
        if($estado == 2) $lote->intentos += 1;
        $lote->save();
    }

    public function procesarLoteMensajes($lote){
        try{
            $detalle = EnvioMensajesDetalle::where('idenviomensaje', $lote->id)
            ->where('enviado', 2)->where('intentos', '<', 3)->get();

            if(count($detalle)>0){
                log:info('Inicia recorrido para envio de mensajes');
                
                //VERIFICACION DE HORARIO

                $date = Carbon::now();
                $desde = '08:00:00';
                $hasta = '18:00:00';

                if($date->dayName == 'sábado'){
                    $hasta = '12:00:00';
                }

                $contador = 0;

                foreach ($detalle as $d) {
                    
                    $horaActual= Carbon::now()->toTimeString(); 

                    if ($horaActual >= $desde && $horaActual <= $hasta) {
                        if($contador < 100){
                            $this->procesar($d,  $lote->idareamensaje, $lote->tipo, $lote->idcategoriamensaje, utf8_decode($lote->mensaje));
                            $contador +=1;
                        }else{
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
                        $estado = 4; //DETENIDO
                        $this->cambiarEstadoLote($lote, $estado);
                        break;
                    }
                }
                $this->verificarEnvioMensajes($this->id);
            }else{
                $this->verificarEnvioMensajes($this->id);
            }
        }catch (Exception $ex) {
            throw new Exception($ex->getMessage());
        }
    }

    public function procesar($d, $area, $tipo, $categoria, $mensaje){
        $nro = '0'. $d->nrotelefono;
        $listaNegra = $this->verificarListaNegra($nro);
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

    public function verificarListaNegra($nro){
        $listaNegra = ListaNegra::where('tel', $nro)->where('sms', true)->first();
        return $listaNegra;
    }

    public function verificarCliente($ci){
        $cliente = Cliente::where('doc', $ci)->first();
         return $cliente;
    }

    public function estructuraMensajeUno($cliente, $mensaje, $nro, $categoria){
        $m = explode(":", $mensaje);
        $sms = $m[0]. trim(utf8_encode($cliente->nom )). ' '. trim(utf8_encode($cliente->ape)).$m[1]  ;

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

    public function enviarMensaje($url, $d){
        $client = new Client();
        try {
            //log::info($url);
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
            throw new Exception($pref . $ex->getMessage());
        }
    }

    public function verificarEstadoLote($id){
        $estado = EnvioMensajes::where('id', $id)
        ->where('idestado', 5)->first(); //traer si tiene estado CANCELADO

        return $estado;
    }

    public function verificarEnvioMensajes($id){
        $estado = $this->getLoteEnProceso($id);
        if($estado){
            log::info('Posponiendo verificación en 10 minutos');
            VerificarEnvioMensajes::dispatch($id)->delay(now()->addMinutes(10));
        }else{
            log::info('no disponible para verificaar');
        }
    }

    public function getLoteEnProceso($id){
        $estado = EnvioMensajes::where('id', $id)
        ->where('idestado', 2)->first(); //traer si tiene estado EN PROCESO

        return $estado;
    }

    public function procesarLote(){

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
        })->where('id',$this->id)->orderBy('id', 'asc')->first();

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
       
        $fechaActual= Carbon::now()->toDateString();

        $procesar = EnvioMensajes::where('fechaenvio', '<=', $fechaActual)
        ->where('fecha_envio_hasta', '<=', $fechaActual)
        ->where('tipo', '!=', 3 ) //tipo solo para subir chatbot y autogestor
        ->where(function ($q){
            $q->orWhere('idestado', 1); //PENDIENTE
            $q->orWhere('idestado', 4); //DETENIDO POR HORARIO
        })->where('aprobado', 1)->where('id',$this->id)->orderBy('id', 'asc')->first();

        if($procesar){
            log::info('------------ Lote vencido nro: '.$procesar->id.' pasa a procesado ------------');
            $procesar->idestado = 3;
            $procesar->save();
        }
    }

}
