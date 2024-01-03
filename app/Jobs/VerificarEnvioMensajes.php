<?php

namespace App\Jobs;

use App\Models\EnvioMensajes;
use App\Models\EnvioMensajesDetalle;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class VerificarEnvioMensajes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected int $id;
    //public $tries = 5;
    public $timeout = 7200;
    public function __construct($id)
    {   
        $this->id = $id;
        log::info('ID lote recibido en verificación:'.$id);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{
            Log::info("----Comienza verificación del Lote ----:" . $this->id);
            $lote = $this->cambiarEstadoLote();

            if($lote) $this->getMensajesDespachados();
            
        }catch (Exception $ex) {
            throw new Exception('Error verificacion 1: '.$ex->getMessage());
        }
    }
    
    public function cambiarEstadoLote(){
        try {
            $cab = EnvioMensajes::where('id', $this->id)->where('idestado', '!=', 5)->first();
            
            if($cab){
                $cab->idestado = 6; // verificando
                $cab->save();
                return true;
            }else{
                return false;
            }
        } catch (Exception $ex) {
            throw new Exception('Error verificacion 2: '. $ex->getMessage());
            return false;
        }
        
    }

    public function getMensajesDespachados(){
        try {
            $dt = EnvioMensajesDetalle::where('idenviomensaje', $this->id)->where('enviado',3)->get();
            $queue = 0;
            $error = 0;
            
            foreach($dt as $d){
                if(!empty($d->idenvio))
                {
                    $url = env('DIR_WEBSERVICE_STATUS').'?key='. env('CLAVE_WEBSERVICE'). '&message_id=' . $d->idenvio;
                    $res = $this->verificar($d, $url);
                    if($res == 'Q'){
                        $queue += 1;
                    }elseif($res == 'E' || $res == 'ERROR'){
                        $error += 1;
                    }
                }
            }

            log::info('Verificamos '.$error . ' mensajes con errores');
            log::info('Verificamos '.$queue . ' mensajes que siguen en cola');

            $this->cambiarEstadoVerificacion();
            
        } catch (Exception $ex) {
            throw new Exception('Error verificacion 3: '.$ex->getMessage());
        }
    }

    public function cambiarEstadoVerificacion(){
        
        $noenviado = EnvioMensajesDetalle::where('idenviomensaje', $this->id)->where('enviado', 2)
        ->where('intentos', '<', 3)->first();
        
        if($noenviado){
            log::info('pasa a pendiente');
            $cab = EnvioMensajes::where('id', $this->id)->first();
            $cab->idestado = 1; // pasa a pendiente
            $cab->save();
        }else{
            log::info('pasa a procesado');
            $cab = EnvioMensajes::where('id', $this->id)->first();
            $cab->idestado = 3; // pasa a estado procesado - cabecera
            $cab->save();
        }
    }

    public function verificar($d, $url){
        $client = new Client;
        try {
           // log::info($url);
            $response = $client->post($url);
            $res = json_decode($response->getBody());
            if (!empty($res)) {
                //log::info($res->message);
                if($res->status == 'DELIVERED' ){
                    $d->enviado= 1;
                    $d->save();
                    return 'OK';
                }else if($res->status == 'QUEUED'){
                    return 'Q';
                }else{
                    $d->enviado= 2;
                    $d->save();
                    return 'E';
                }
            }
        } catch (Exception $ex) {
            $pref = 'webservice => ';
            throw new Exception('Error verificacion 4: '.$pref . $ex->getMessage());
            return 'ERROR';
        }
    }
    public function retryUntil()
    {
        // will keep retrying, by backoff logic below
        // until 24 hours from first run.
        // After that, if it fails it will go
        // to the failed_jobs table
        return now()->addHours(24);
    }

    public function backoff()
    {
        return [7200, 7200, 7200, 7200, 7200, 10800 ];
    } 
}
