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

class VerificarEnvioMensajes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected int $id;

    public function __construct($id)
    {   
        $this->id = $id;
        log::info($id);
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        Log::info("----Verificar envio----");
        //obtenemos datos de la cabecera
        $cab = EnvioMensajes::where('id', $this->id)->first();
        $cab->idestado = 1; // verificando
        $cab->save();

        $dt = EnvioMensajesDetalle::where('idenviomensaje', $this->id)->where('enviado',3)->get();

        $verificar = 0; // bandera para volver a verificar
        $errorEnvio = 0; // bandera para reintentar
        foreach($dt as $d){
            if(!empty($d->idenvio))
            {    
                $client = new Client;

                $url = env('DIR_WEBSERVICE_STATUS').'?key='. env('CLAVE_WEBSERVICE'). '&message_id=' . $d->idenvio;

                try {
                    log::info('Consultando..: ');
                    log::info($url);
                    $response = $client->post($url);
                    $res = json_decode($response->getBody());
                    if (!empty($res)) {
                        log::info($res->message);
                        if($res->status == 'DELIVERED' ){
                            $d->enviado= 1;
                            $d->save();
                        }else if($res->status == 'QUEUED'){
                            $verificar = 1;
                        }else{
                            $d->enviado= 2;
                            $d->save();
                            $errorEnvio = 1;
                        }
                    }
                } catch (Exception $ex) {
                    $pref = 'webservice => ';
                    throw new Exception($pref . $ex->getMessage());
                    continue;
                }
            }
        }

        log::info('CAMBIAR ESTADO DEL LOTE');
        
        //si no encontró errores
        if( $verificar == 0 && $errorEnvio == 0){
            //verificamos que no hayan mensajes sin enviar
            $noenviado = EnvioMensajesDetalle::where('idenviomensaje', $this->id)->where('enviado',2)->first();
            if(empty($noenviado)){
                log:info('no tiene mensajes sin enviar');
                $cab->idestado = 3; // pasa a estado procesado - cabecera
                $cab->save();
                
            }else{
                if($cab->intentos < 3){
                    $cab->idestado = 1; // pasa a pendiente
                    $cab->save();
                }else{
                    $cab->idestado = 3; // pasa a estado procesado - cabecera
                    $cab->save();
                }
            }
        }else {
            if ($errorEnvio == 1) {
                log::info('hay mensajes con errores');
                if($cab->intentos < 3){
                    $cab->idestado = 1; // pendiente
                    $cab->save();
                }else{
                    $cab->idestado = 3; // pasa a estado procesado - cabecera
                    $cab->save();
                }
            }
            if ($verificar == 1) {
                log::info('hay mensajes que siguen en cola');
                VerificarEnvioMensajes::dispatch($this->id) // vuelve a verificar porque hay mensajes que siguen en cola
                ->delay(now()->addMinutes(15));
            }
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
        return [60, 60, 60, 60, 60, 180 ];
    }
}
