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
        $dt = EnvioMensajesDetalle::where('idenviomensaje', $this->id)->get();

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
                        }
                    }
                } catch (Exception $ex) {
                    $pref = 'webservice => ';
                    throw new Exception($pref . $ex->getMessage());
                }
            }
        }

        log::info('CAMBIAR ESTADO DEL LOTE');
        $cab = EnvioMensajes::where('id', $this->id)->first();
        $noenviado = EnvioMensajesDetalle::where('idenviomensaje', $this->id)->where('enviado',2)->first();
        if(empty($noenviado)){
            log:info('entra');
            $cab->idestado = 3;
            $cab->save();
        }else{
            $cab->idestado = 1;
            $cab->save();
        }
        
    }
}
