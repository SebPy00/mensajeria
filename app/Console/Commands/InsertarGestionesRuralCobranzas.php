<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Models\GestionesRuralCobranzas;

class InsertarGestionesRuralCobranzas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insertar:gestionRC';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insertar las gestiones de Rural Cobranzas';

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
        $fechaDesde = Carbon::now()->subHour();
        $fechaHasta = Carbon::now();

        $gestiones = $this->getGestiones($fechaDesde, $fechaHasta);

        $this->insertarGestiones($gestiones);
    }

    private function insertarGestiones ($gestiones){
        try{

            if (isset($gestiones->Table) && is_array($gestiones->Table)) {
                foreach ($gestiones->Table as $elemento){
                    $fgestion = new Carbon ($elemento->FECHA_GESTION);
                    $freagenda = new Carbon ($elemento->FECHA_REAGENDADA);

                    $ges = GestionesRuralCobranzas::where('identificador', $elemento->IDENTIFICADOR)->first();
                    if (($elemento->COD_GESTOR = 11151 || $elemento->COD_GESTOR = 11152) && !$ges ){
                        $rc = new GestionesRuralCobranzas();
                        $rc->cod_persona = isset($elemento->COD_PERSONA) ? $elemento->COD_PERSONA : '';
                        $rc->nro_documento = isset($elemento->NRO_DOCUMENTO) ? $elemento->NRO_DOCUMENTO : '';
                        $rc->nom_cliente = isset($elemento->NOMBRE_COMPLETO) ? $elemento->NOMBRE_COMPLETO : '';
                        $rc->fecha_gestion = $fgestion;
                        $rc->gestor = isset($elemento->GESTOR) ? $elemento->GESTOR : '';
                        $rc->cod_respuesta = isset($elemento->COD_RESPUESTA) ? $elemento->COD_RESPUESTA : '';
                        $rc->resp_corta = isset($elemento->RESP_CORTA) ? $elemento->RESP_CORTA : '';
                        $rc->respuesta = isset($elemento->RESPUESTA)  ? $elemento->RESPUESTA : '';
                        $rc->fecha_reagenda = $freagenda;
                        $rc->operacion = isset($elemento->OPERACION) ? $elemento->OPERACION : '';
                        $rc->identificador = isset($elemento->IDENTIFICADOR) ? $elemento->IDENTIFICADOR : 0;
                        $rc->save();
                    }
                }
            }

        }catch(Exception $ex){
            throw new Exception($ex->getMessage());
        }
    }

    private function getGestiones($fechaDesde, $fechaHasta){
        log::info('busca gestiones Rural Cobranzas');
        // $fechaDesde =  '2023-10-24 00:00:00'; //pruebas
        // $fechaHasta = '2023-10-24 23:59:59';///pruebas

        $client = new Client();
        try {
            $url = 'http://10.19.150.80/neoapi/webservice.asmx/ExecuteTask03?idTask=28&param1=RuralCobranzas&param2='.$fechaDesde.'&param3='.$fechaHasta.'';
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
            throw new Exception('ERROR: reporteria vinanzas - ' . $pref . $ex->getMessage());
        }
    }
}
