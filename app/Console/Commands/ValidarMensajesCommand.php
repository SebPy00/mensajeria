<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\EnvioMensajes;
use App\Models\EnvioMensajesDetalle;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ValidarMensajesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'validar:mensajes';

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
        try{
            
            $id = $this->getLoteAverificar();
            if($id) {
                Log::info("COMIENZA VERIFICACION DE MENSAJES LOTE " . $id);
                $this->cambiarEstadoLote($id);
                $this->getMensajesDespachados($id);
            }         
        }catch (Exception $ex) {
            throw new Exception('Error verificacion 1: '.$ex->getMessage());
        }
    }

    private function getLoteAverificar()
    {
        $lote = EnvioMensajes::where('idestado', 7)->orderBy('id', 'asc')->first();
        $res = 0;
        if ($lote)  $res = $lote->id;

        return $res;
    }

    public function cambiarEstadoLote($id){
        try {
            $cab = EnvioMensajes::where('id', $id)->where('idestado', '!=', 5)->first();
            
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

    public function getMensajesDespachados($id){
        try {
            $dt = EnvioMensajesDetalle::where('idenviomensaje', $id)->where('enviado',3)->get();
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

            $this->cambiarEstadoVerificacion($id);
            
        } catch (Exception $ex) {
            throw new Exception('Error verificacion 3: '.$ex->getMessage());
        }
    }
    public function cambiarEstadoVerificacion($id){
        
        $noenviado = EnvioMensajesDetalle::where('idenviomensaje', $id)->where('enviado', 2)
        ->where('intentos', '<', 3)->first();
        
        if($noenviado){
            log::info('pasa a pendiente');
            $cab = EnvioMensajes::where('id', $id)->first();
            $cab->idestado = 1; // pasa a pendiente
            $cab->save();
        }else{
            log::info('pasa a procesado');
            $cab = EnvioMensajes::where('id', $id)->first();
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
}
