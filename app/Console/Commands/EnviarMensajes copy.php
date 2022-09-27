<?php

namespace App\Console\Commands;

use App\Jobs\VerificarEnvioMensajes;
use Illuminate\Console\Command;
use App\Models\EnvioMensajes;
use App\Models\EnvioMensajesDetalle;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Log;

class EnviarMensajes2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enviar:mensajes2';

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

            $ms = EnvioMensajes::
            where('fechaenvio', '<=', $fechaActual)
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
                        $array = [
                            'message' => $mensaje,
                            'msisdn' => '0' .  $nro
                        ];
                        

                        $url = env('DIR_WEBSERVICE');
                        $client = new Client();
                        $opt = [
                            'headers' => [
                                'Accept' => 'application/json',
                                'Key' => env('CLAVE_WEBSERVICE'),
                            ],
                            'json' => $array
                        ];
            
                        // $url = 'https://tigob.beekun.com/pushapi?key=6324a87a66ab35.51685497';
                        // $url .= '&message='.$mensaje;
                        // $url .= '&msisdn=0'.$nro;

                        try {
                            log::info('Enviando..: ');
                            log::info($url);
                            $response = $client->post($url, $opt);
                            $res = json_decode($response->getBody());
                            if (!empty($res)) {
                                if($res->message == 'success'){
                                    $detalle['idenvio'] = $res->id;
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
                        }
                    }
                    
                    // VerificarEnvioMensajes::dispatch([ 'idDetalle'=>$detalle['id'],'idMensaje'=>$res->id])
                    // ->delay(now()->addSeconds(15));
                    VerificarEnvioMensajes::dispatch($cabecera->id)
                    ->delay(now()->addMinutes(5));
                }
            }
        }
    }

}
