<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Models\GestionesAlarmasPY;

class InsertGestionesAlarmasPY extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insertargestiones:alarmaspy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insertar las gestiones de Alarmas PY';

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
        log::info('INICIA PROCESO PARA INSERCIÓN DE GESTIONES DE NEOTEL A SIGESA');
        $fechaActual = Carbon::now();
        $fecha = $fechaActual->format('Y-m-d');
        $gestiones = $this->getGestiones($fecha);
        $this->insertarGestiones($gestiones);
    }

    private function insertarGestiones ($gestiones){

        if (isset($gestiones->Table) && is_array($gestiones->Table)) {
            foreach ($gestiones->Table as $elemento){
                try{

                    //AGREGAR VALIDACIÓN UNO A UNO PARA NO INSERTAR GESTIONES REPETIDAS
                    $fgestion = isset($elemento->FECHA_GESTION) ? new Carbon ($elemento->FECHA_GESTION): '';
                    $freagenda = isset($elemento->FECHA_REAGENDADA) ? new Carbon ($elemento->FECHA_REAGENDADA): '';

                    $rc = new GestionesAlarmasPY();
                    $rc->cod_persona = isset($elemento->COD_PERSONA) ? $elemento->COD_PERSONA : '';
                    $rc->nro_documento = isset($elemento->NRO_DOCUMENTO) ? $elemento->NRO_DOCUMENTO : '';
                    $rc->nom_cliente = isset($elemento->NOMBRE_COMPLETO) ? $elemento->NOMBRE_COMPLETO : '';
                    $rc->fecha_gestion = $fgestion;
                    $rc->gestor = isset($elemento->GESTOR) ? $elemento->GESTOR : '';
                    $rc->resp_corta = isset($elemento->RESP_CORTA) ? $elemento->RESP_CORTA : '';
                    $rc->respuesta = isset($elemento->RESPUESTA)  ? $elemento->RESPUESTA : '';
                    $rc->fecha_reagenda = $freagenda;
                    $rc->identificador = isset($elemento->IDENTIFICADOR)  ? $elemento->IDENTIFICADOR : '';
                    $rc->save();

                }catch(Exception $ex){
                    throw new Exception($ex->getMessage());
                }
            }
        }


    }

    private function getGestiones($fecha){
        log::info('busca gestiones AlarmasPY');

        $fechaDesde = $fecha . ' 00:00:00';
        $fechaHasta = $fecha .' 23:59:59';

        $fechaDesde = '2024-01-02 00:00:00'; //Usar para tener gestiones de dias anteriores
        $fechaHasta = '2023-01-02 23:59:59'; //Usar para tener gestiones de dias anteriores

        $client = new Client();
        try {
            $url = 'http://10.19.150.80/neoapi/webservice.asmx/ExecuteTask03?idTask=28&param1=AlarmasPY&param2='.$fechaDesde.'&param3='.$fechaHasta.'';
            log::info($url);
            $response = $client->get($url);
            // Obtener el contenido del cuerpo de la respuesta en formato XML
            $xmlContent = $response->getBody();

            // Obtener el contenido del elemento <string> como texto
            $stringElement = simplexml_load_string($xmlContent);
            $jsonString = (string)$stringElement;

            // Decodificar el JSON
            $json = json_decode($jsonString);

            if (!empty($json)) {
                return $json;
            }
        } catch (Exception $ex) {
            $pref = 'webservice => ';
            throw new Exception('ERROR: api AlarmasPY - ' . $pref . $ex->getMessage());
        }
    }
}
