<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\GestionesDelaOperacion;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;
use Exception;

class VerificarMensajesCRM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verificar:smscrm';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para verificar los mensajes salientes del CRM por el bulk';

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
        Log::info("COMIENZA VERIFICACION DE MENSAJES ");
        $registros = $this->getRegistros();

        Log::info("Cantidad verificados: " . $registros);

    }

    private function getRegistros(){
        $lista = DB::Select("SELECT cm.regnro, cm.idenvio 
        from crm_bulk cm join opeges on cm.regnro = opeges.regnro 
        where opeges.gesest = 155 ");

        foreach ($lista as $registro){
            $this->verificarEnvio($registro);
        }

        return count($lista);
    }

    private function verificarEnvio($registro){
        $url = env('DIR_WEBSERVICE_STATUS').'?key='. env('CLAVE_WEBSERVICE'). '&message_id=' . $registro->idenvio;
        //log::info($url);
        $client = new Client;
        try {
            $response = $client->post($url);
            $res = json_decode($response->getBody());
            if (!empty($res)) {
                $mensaje = GestionesDelaOperacion::where('regnro', $registro->regnro)->first();
               // log::info($res->message);
                if($res->status == 'DELIVERED' ){
                    $mensaje->gesest= 101;
                    $mensaje->save();
                }else if($res->status == 'QUEUED'){
                    $mensaje->gesest= 155;
                    $mensaje->save();
                }else{
                    if($mensaje->intentos < 3){
                        $mensaje->gesest = 153; //reenviar
                        $mensaje->save();
                    }else{
                        $mensaje->gesest = 154; //no enviado
                        $mensaje->save();
                    }
                }
            }
        } catch (Exception $ex) {
            $pref = 'webservice => ';
            throw new Exception('Error verificacion 4: '.$pref . $ex->getMessage());
        }
    }
}
