<?php

namespace App\Console\Commands;

use App\Jobs\VerificarEnvioMensajes;
use Illuminate\Console\Command;
use App\Models\EnvioMensajes;
use App\Models\EnvioMensajesDetalle;
use App\Models\Cliente;
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
        
        $hora= Carbon::now()->toTimeString();         
        
        if ($hora >='07:00:00' && $hora <= '19:00:00') {
            
            $fechaActual= Carbon::now()->toDateString();

            $ms = EnvioMensajes::where('fechaenvio', '<=', $fechaActual)
            ->where(function ($q){
                $q->orWhere('idestado', 1); //PENDIENTE
                $q->orWhere('idestado', 4); //DETENIDO POR HORARIO
            })->where('aprobado', 1)
            ->where('intentos', '<', 3)
            ->whereHas('detalles', function ($q) {
                $q->where('enviado', 2);
            })->get();

            if ($ms) {
                               
                foreach ($ms as $cabecera) {
                    log::info('------------ Inicio Envío mensajes en lote: ' . $cabecera->id . '------------');
                    
                    $mensaje = utf8_decode($cabecera->mensaje);
                    
                    $cabecera->idestado = 2;
                    $cabecera->intentos += 1;
                    $cabecera->save();

                    $detalle = EnvioMensajesDetalle::where('idenviomensaje', $cabecera->id)
                    ->where('enviado', 2)->get();
                    
                    $contador = 1;
                    foreach ($detalle as $d) {
                        
                        //CONTROLAMOS CADA 100 MENSAJES QUE EL LOTE NO HAYA SIDO CANCELADO
                        if($contador < 100){
                            log::info($contador);
                            $contador +=1;

                            //VERIFICAMOS EL HORARIO ANTES DE ENVIAR CADA MENSAJE PARA NO PASAR EL HORARIO PERMITIDO
                            $horaActual= Carbon::now()->toTimeString(); 
                            if ($horaActual >='07:00:00' && $horaActual <= '19:00:00') {
                                
                                //VERIFICAMOS QUE EL NRO NO ESTÉ EN LISTA NEGRA
                                $nro = '0'. $d->nrotelefono;
                                $listaNegra = ListaNegra::where('tel', $nro)->where('sms', true)->first();
                                
                                if($listaNegra){
                                    log::info('Nro: '. $nro . ' en lista negra');
                                    $d->enviado = 4; //nro en la lista negra
                                    $d->save();
                                    continue;
                                }else{
                                    
                                    // no es servicios
                                    if($cabecera->idareamensaje != 3){
                                        $cliente = Cliente::where('doc', $d->ci)->first();
                                        //si encuentra al cliente procedemos
                                        if(!$cliente){
                                            log::info('No se encuentra el cliente.');
                                            continue;
                                        }
                                    }
                                      //ARMAMOS LA ESTRUCTURA DEL MENSAJE SI ES PERSONALIZADO
                                    if($cabecera->tipo==2){
                                        $m = explode(":", $mensaje);
                                        $sms = $m[0]. trim(utf8_encode($cliente->nom )). ' '. trim(utf8_encode($cliente->ape)).$m[1]  ;

                                        //chatbot
                                        if($cabecera->idcategoriamensaje == 2) {
                                            $bot = explode("NROCLI", $sms);
                                            $mje = $bot[0].$cliente->cli.$bot[1];
                                                
                                            $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
                                                '&message='.$mje .'&msisdn='.$nro;
                                        }else{
                                            $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
                                            '&message='.$sms .'&msisdn='.$nro;
                                        }
                                        
                                    }
                                    //ARMAMOS LA ESTRUCTURA DEL MENSAJE SI ES GENERAL
                                    else{
                                        $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
                                        '&message='.$mensaje .'&msisdn='.$nro;
                                        
                                    }
                                
                                    //ENVIAMOS NUESTRO PEDIDO A LA API
                                    $client = new Client();
                                    try {
                                        log::info('Enviando..: ');
                                        log::info($url);
                                        $response = $client->post($url);
                                        $res = json_decode($response->getBody());
                                        if (!empty($res)) {
                                            log::info($res->message);
                                            if(!empty($res->id)){
                                                $d->idenvio = $res->id;
                                                $d->enviado = 3; //despachado para envio
                                                $d->intentos += 1; //agregamos cantidad de intentos
                                                $d->save();
                                            }else{
                                                $d->intentos += 1;
                                                $d->save();
                                                continue;
                                            }
                                        }

                                    } catch (Exception $ex) {
                                        $pref = 'webservice => ';
                                        throw new Exception($pref . $ex->getMessage());
                                        continue;
                                    }
                                }
                                 
                            }else{
                                log::info('No se puede realizar envío fuera de horario. Cambiando estado de la cabecera: DETENIDO');
                                $cabecera->idestado = 4;
                                $cabecera->save();
                                break (1);
                            }

                        }else{

                            $contador = 1;
                            $verificarCabecera = EnvioMensajes::where('id', $d->idenviomensaje)
                            ->where('idestado', 5)->first(); //traer si tiene estado CANCELADO

                            if($verificarCabecera){
                                
                                break (1);
                           
                            }else{
                                
                                //ENVIAMOS EL MENSAJE - REPETIMOS ESTRUCTURA PARA QUE NO DEJE DE ENVIAR EL MENSAJE ACTUAL DEL RECORRIDO
                                //-----------------------------------------------------------------------------------------------------
                                //VERIFICAMOS EL HORARIO ANTES DE ENVIAR CADA MENSAJE PARA NO PASAR EL HORARIO PERMITIDO

                                $horaActual= Carbon::now()->toTimeString(); 
                                if ($horaActual >='07:00:00' && $horaActual <= '19:00:00') {
                                    
                                    //VERIFICAMOS QUE EL NRO NO ESTÉ EN LISTA NEGRA
                                    $nro = '0'. $d->nrotelefono;
                                    $listaNegra = ListaNegra::where('tel', $nro)->where('sms', true)->first();
                                    
                                    if($listaNegra){
                                        log::info('Nro: '. $nro . ' en lista negra');
                                        $d->enviado = 4; //nro en la lista negra
                                        $d->save();
                                        continue;
                                    }else{

                                        // no es servicios
                                        if($cabecera->idareamensaje != 3){
                                            $cliente = Cliente::where('doc', $d->ci)->first();
                                            //si encuentra al cliente procedemos
                                            if(!$cliente){
                                                log::info('No se encuentra el cliente.');
                                                continue;
                                            }
                                        }
                                        
                                        //ARMAMOS LA ESTRUCTURA DEL MENSAJE SI ES PERSONALIZADO
                                        if($cabecera->tipo==2){
                                            $m = explode(":", $mensaje);
                                            $sms = $m[0].utf8_decode($d->nombre).$m[1]  ;

                                            //chatbot
                                            if($cabecera->idcategoriamensaje == 2) {
                                            $bot = explode("NROCLI", $sms);
                                            $mje = $bot[0].$cliente->cli.$bot[1]  ;
                                                
                                            $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
                                                '&message='.$mje .'&msisdn='.$nro;
                                            }else{
                                                $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
                                                '&message='.$sms .'&msisdn='.$nro;
                                            }
                                            
                                        }
                                        //ARMAMOS LA ESTRUCTURA DEL MENSAJE SI ES GENERAL
                                        else{
                                            $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
                                            '&message='.$mensaje .'&msisdn=0'.$nro;
                                            
                                        }
                                    
                                        //ENVIAMOS NUESTRO PEDIDO A LA API
                                        $client = new Client();
                                        try {
                                            log::info('Enviando..: ');
                                            log::info($url);
                                            $response = $client->post($url);
                                            $res = json_decode($response->getBody());
                                            if (!empty($res)) {
                                                log::info($res->message);
                                                if(!empty($res->id)){
                                                    $d->idenvio = $res->id;
                                                    $d->enviado = 3; //despachado para envio
                                                    $d->intentos += 1; //agregamos cantidad de intentos
                                                    $d->save();
                                                }else{
                                                    $d->intentos += 1;
                                                    $d->save();
                                                    continue;
                                                }
                                            }

                                        } catch (Exception $ex) {
                                            $pref = 'webservice => ';
                                            throw new Exception($pref . $ex->getMessage());
                                            continue;
                                        }
                                        
                                    }
                                
                                }else{
                                    log::info('No se puede realizar envío fuera de horario. Cambiando estado de la cabecera: DETENIDO');
                                    $cabecera->idestado = 4;
                                    $cabecera->save();
                                    break (1);
                                }

                            }
                        }
                    }

                    VerificarEnvioMensajes::dispatch($cabecera->id)
                    ->delay(now()->addMinutes(30));
                }
            }
        }
    }

}
