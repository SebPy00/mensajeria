<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;
use GuzzleHttp\Client;

ini_set('memory_limit', '-1');
class ReporteGestionAlarmasPYExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    use Exportable;
    public function collection()
    {
        log::info('INICIA REPORTE DE GESTION DIARIA ALARMASPY');

        $fecha = Carbon::now()->toDateString();
        $gestiones = $this->getGestiones($fecha);

        $lista = [];

        if (isset($gestiones->Table) && is_array($gestiones->Table)) {
            foreach ($gestiones->Table as $elemento){
                    $fgestion = new Carbon ($elemento->FECHA_GESTION);

                    $freagenda = new Carbon ($elemento->FECHA_REAGENDADA);


                    $fila = [
                        'COD_PERSONA'=>$elemento->COD_PERSONA,
                        'NRO_DOCUMENTO'=>$elemento->NRO_DOCUMENTO,
                        'NOM_COMPLETO'=>$elemento->NOMBRE_COMPLETO,
                        'FECHA_GESTION'=>$fgestion->format('d-m-Y'),
                        'GESTOR'=>isset($elemento->Gestor) ? $elemento->Gestor : '',
                        'COD_RESPUESTA'=>$elemento->COD_RESPUESTA,
                        'RESP_CORTA'=>$elemento->RESP_CORTA,
                        'RESPUESTA'=>$elemento->RESPUESTA,
                        'FECHA_REAGENDADA'=>$freagenda->format('d-m-Y')
                    ];

                    if( $elemento->RESP_CORTA != 'Cerrado por Proceso')
                        $lista[]= $fila;

            }
        }

        return collect($lista);
    }

    private function getGestiones($fecha){
        log::info('busca gestiones');
//        $fecha = '2023-12-11';// SOLO PARA PRUEBAS
        $client = new Client();
        try {
            $url = 'http://10.19.150.80/neoapi/webservice.asmx/ExecuteTask03?idTask=28&param1=AlarmasPy&param2='.$fecha .' 00:00:00'.'&param3='.$fecha.' 23:59:59';
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
            throw new Exception('ERROR: reporteria AlarmasPY - ' . $pref . $ex->getMessage());
        }
    }

    public function headings(): array
    {
        return [
            'COD_PERSONA',
            'NRO_DOCUMENTO',
            'NOM_COMPLETO',
            'FECHA_GESTION',
            'GESTOR',
            'COD_RESPUESTA',
            'RESP_CORTA',
            'RESPUESTA',
            'FECHA_REAGENDADA'
        ];
    }
}
