<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\NotaCreditoDTE;
use App\Models\NotaElectronica;
use GuzzleHttp\Client;
use Exception;
class VeridicarEstadoNE extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verificar:ne';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar Notas de Credito';

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
        log::info ('INICIA VERIFICACION DE ESTADOS NC');
        $this->getNotas();
    }

    private function getNotas(){
        $notas = DB::Select(
            "SELECT id, notacreditoid, cdc from nota_electronica 
            where  idestadotxt = 1 limit 100"
        );
        
        if($notas){
            foreach($notas as $nota){
                $this->verificarEstado($nota);
            }
        }
    }

    private function verificarEstado($nota){
        $datos = NotaCreditoDTE::where('id', $nota->notacreditoid)->first();
        
        $nombrearchivo = 'NC-'. $datos->timbrado . '-' . $datos->nro_nota . '.txt';
        $url = env('DIR_SEIDI_STATUS'). $nota->cdc . '/'. $nombrearchivo;
        $client = new Client;
        try {
            //log::info($url);
            $response = $client->get($url);
            $res = json_decode($response->getBody());
            if (!empty($res)) {
                $this->guardarEstados($nota->id, $res);
            }
        } catch (Exception $ex) {
        $pref = 'seidi => ';
        throw new Exception( $pref . $ex->getMessage());
        }
    }

   private function guardarEstados($idne, $res){
    $nota = NotaElectronica::where('id', $idne)->first();

    if(isset($res->payload->set->procesamiento)){
        $nota->fechaprocesamiento = Carbon::parse($res->payload->set->procesamiento->fechaProcesamiento);
        $nota->mensajeresultado = $res->payload->set->procesamiento->detalles[0]->mensajeResultado;
    }

    // if(isset($res->payload->error)){
    //     //$fe->fechaprocesamiento = Carbon::parse($res->payload->set->procesamiento->fechaProcesamiento);
    //     $fe->mensajeresultado = $res->payload->set->error->detalles[0]->descripcion;
    // }
    
    if($res->payload->estadoResultado == 'Rechazado')
        $nota->idestadotxt = 3; //RECHAZADO
    if($res->payload->estadoResultado == 'Aprobado')
        $nota->idestadotxt = 2; //APROBADO
    
    $nota->save();
    
   }

}
