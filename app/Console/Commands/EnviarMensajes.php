<?php

namespace App\Console\Commands;

use App\Jobs\VerificarEnvioMensajes;
use Illuminate\Console\Command;
use App\Models\EnvioMensajes;
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
        

        $hora= Carbon::now()->toTimeString();         
        log::info('Hora: '.$hora);
        
        if ($hora >='07:00:00' && $hora <= '19:00:00') {
            
            log::info('------------ Inicio Envío mensajes -----------');

            $fechaActual= Carbon::now()->toDateString();

            $ms = EnvioMensajes::with('detalles')
            ->where('fechaenvio', '<=', $fechaActual)
            ->where('aprobado', 1)
            ->where('idestado', 1)
            ->where('intentos', '<', 3)
            ->whereHas('detalles', function ($q) {
                $q->where('enviado', 2);
            })->get();

            if ($ms) {
                foreach ($ms as $cabecera) {
                    $mensaje = utf8_decode($cabecera->mensaje);
                    
                    $cabecera->idestado = 2;
                    $cabecera->intentos += 1;
                    $cabecera->save();
                    foreach ($cabecera->detalles as $detalle) {
                        $nro = $detalle['nrotelefono'];
                        if($cabecera->tipo==2){
                            $m = explode(":", $mensaje);
                            $sms = $m[0]. " ".utf8_decode($detalle['nombre']).$m[1]  ;
                            $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
                            '&message='.$sms .'&msisdn=0'.$nro;
                        }
                        else{
                            $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
                            '&message='.$mensaje .'&msisdn=0'.$nro;
                        }
                        
                        $client = new Client();
                        try {
                            log::info('Enviando..: ');
                            log::info($url);
                            $response = $client->post($url);
                            $res = json_decode($response->getBody());
                            if (!empty($res)) {
                                log::info($res->menssage);
                                if($res->message == 'success'){
                                    $detalle['idenvio'] = $res->id;
                                    $detalle['enviado'] = 3; //despachado para envio
                                    $detalle['intentos'] += 1;
                                    $detalle->save();
                                }else{
                                    $detalle['intentos'] += 1;
                                    $detalle->save();
                                    continue;
                                }
                            }
                        } catch (Exception $ex) {
                            $pref = 'webservice => ';
                            throw new Exception($pref . $ex->getMessage());
                            continue;
                        }
                    }

                    VerificarEnvioMensajes::dispatch($cabecera->id)
                    ->delay(now()->addMinutes(30));
                }
            }
        }
    }

}
