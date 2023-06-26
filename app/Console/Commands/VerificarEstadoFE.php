<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\FacturaElectronica;
use GuzzleHttp\Client;
use Exception;

class VerificarEstadoFE extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'verificar:fe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar factura Electrónica';

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
       log::info ('INICIA VERIFICACION DE ESTADOS FE');
       
       $this->getFacturas();
    }

    private function getFacturas(){
        $facturas = DB::Select(
            "SELECT trim(nro_factura) as nro_factura,  timbrado, cdc from factura_electronica 
            where  idestadotxt = 1 order by id desc limit 100"
        );
        
        if($facturas){
            foreach($facturas as $f){
                try{
                    if($f->nro_factura != '' && $f->timbrado != ''  && $f->cdc != '')
                        $this->verificarEstado($f->nro_factura, $f->timbrado, $f->cdc);
                }catch(Exception $ex) {
                    $pref = 'error en verificación de factura => ';
                    throw new Exception( $pref . $ex->getMessage());
                }
            }
        }
    }

    private function verificarEstado($fac, $timb, $cdc){
        
        $nombrearchivo = $timb . '-' . $fac . '.txt';
        $url = env('DIR_SEIDI_STATUS'). $cdc . '/'. $nombrearchivo;
        $client = new Client;
        try {
            log::info($url);
            $response = $client->get($url);
            $res = json_decode($response->getBody());
            if (!empty($res)) {
                $this->guardarEstados($fac, $timb, $res);
            }
        } catch (Exception $ex) {
        $pref = 'seidi => ';
        throw new Exception( $pref . $ex->getMessage());
        }
    }

   private function guardarEstados($fac, $timb, $res){
    $fe = FacturaElectronica::where('nro_factura', $fac)->where('timbrado', $timb)->first();
    
    if(isset($res->payload->set->procesamiento)){
        $fe->fechaprocesamiento = Carbon::parse($res->payload->set->procesamiento->fechaProcesamiento);
        $fe->mensajeresultado = $res->payload->set->procesamiento->detalles[0]->mensajeResultado;
    }else{
        $fe->mensajeresultado = $res->payload->estado;
    }

    // if(isset($res->payload->error)){
    //     //$fe->fechaprocesamiento = Carbon::parse($res->payload->set->procesamiento->fechaProcesamiento);
    //     $fe->mensajeresultado = $res->payload->set->error->detalles[0]->descripcion;
    // }

    if($res->payload->estadoResultado == 'Rechazado')
        $fe->idestadotxt = 3; //RECHAZADO
    if($res->payload->estadoResultado == 'Aprobado')
        $fe->idestadotxt = 2; //APROBADO
    
    $fe->save();
    
   }
}
