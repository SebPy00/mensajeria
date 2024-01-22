<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;
use GuzzleHttp\Client;
use App\Models\GestionesSudameris;

class InsertGestionesSudameris extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insertGestiones:sudameris';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insertar las gestiones de Sudameris';

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
        log::info('INICIA PROCESO PARA INSERCIÓN DE GESTIONES SUDAMERIS');
        $fechaActual = Carbon::now();
        $fecha = $fechaActual->format('Y-m-d');
        $gestiones = $this->getGestiones($fecha);
        $this->insertarGestiones($gestiones);
        log::info('FIN PROCESO PARA INSERCIÓN DE GESTIONES SUDAMERIS');
    }

    private function insertarGestiones ($gestiones){

        if (isset($gestiones->Table) && is_array($gestiones->Table)) {
            log::info('Existen registros');
            foreach ($gestiones->Table as $elemento){
                try{

                    //AGREGAR VALIDACIÓN UNO A UNO PARA NO INSERTAR GESTIONES REPETIDAS
                    $fgestion = isset($elemento->Fecha_x0020__x0026__x0020_Hora) ? new Carbon ($elemento->Fecha_x0020__x0026__x0020_Hora): '';
                    $freagenda = isset($elemento->Fecha_x0020_de_x0020_Agenda) ? new Carbon ($elemento->Fecha_x0020_de_x0020_Agenda): '';

                    $rc = new GestionesSudameris();
                    $rc->base = isset($elemento->Bases_x0020_de_x0020_datos) ? $elemento->Bases_x0020_de_x0020_datos : '';
                    $rc->id_contacto = isset($elemento->Id_x0020_contacto) ? $elemento->Id_x0020_contacto : 0;
                    $rc->doc = isset($elemento->Nro_x0020_Documento) ? $elemento->Nro_x0020_Documento : '';
                    $rc->cod_cliente = isset($elemento->Cod_x0020_Cliente) ? $elemento->Cod_x0020_Cliente : '';
                    $rc->nombre_cliente = isset($elemento->Nombre_x0020_Cliente)  ? $elemento->Nombre_x0020_Cliente : '';
                    $rc->categoria = isset($elemento->Categoria) ? $elemento->Categoria : '';
                    $rc->sub_categoria = isset($elemento->Sub_x0020_Categoria)  ? $elemento->Sub_x0020_Categoria : '';
                    $rc->id_comentario = isset($elemento->IdComentario) ? $elemento->IdComentario : 0;
                    $rc->comentario = isset($elemento->Comentario) ? $elemento->Comentario : '';
                    $rc->fecha_hora = $fgestion;
                    $rc->fecha_agenda = $freagenda;
                    $rc->telefono = isset($elemento->Teléfono) ? $elemento->Teléfono : '';
                    $rc->usuario = isset($elemento->Usuario) ? $elemento->Usuario : '';
                    $rc->operacion = isset($elemento->Operacion) ? $elemento->Operacion : '';
                    $rc->save();

                }catch(Exception $ex){
                    throw new Exception($ex->getMessage());
                }
            }
        }


    }

    private function getGestiones($fecha){
        log::info('busca gestiones sudameris');

        //$fechaDesde = $fecha .'%2000:00:00';
        //$fechaHasta = $fecha .'%2023:59:59';

        $fechaDesde = '2024-01-18%2000:00:00'; //Usar para tener gestiones de dias anteriores
        $fechaHasta = '2024-01-21%2023:59:59'; //Usar para tener gestiones de dias anteriores

        $client = new Client();
        try {
            $url = 'http://10.19.150.80/neoapi/webservice.asmx/ExecuteTask03?idTask=28&param1=SUDAMERIS&param2='.$fechaDesde.'&param3='.$fechaHasta;
            //log::info($url);
            $response = $client->get($url);
            // Obtener el contenido del cuerpo de la respuesta en formato XML
            $xmlContent = $response->getBody();

            // Obtener el contenido del elemento <string> como texto
            $stringElement = simplexml_load_string($xmlContent);
            $jsonString = (string)$stringElement;
            //log::info(print_r($jsonString,true)) ;

            // Decodificar el JSON
            $json = json_decode($jsonString);
            //log::info(print_r($json,true)) ;

            if (!empty($json)) {
                log::info('json NO ESTA VACIO');
                return $json;
            }
        } catch (Exception $ex) {
            $pref = 'webservice => ';
            throw new Exception('ERROR: api SUDAMERIS - ' . $pref . $ex->getMessage());
        }
    }
}
