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

class ReporteGestionesVinanzasExport implements FromCollection, WithHeadings
{
    use Exportable;
    public function collection()
    {
        log::info('INICIA REPORTE DE GESTION DIARIA VINANZAS');

        $fecha = Carbon::now()->format('Y-m-d');
        $gestiones = $this->getGestiones($fecha);

        $lista = [];

        if (isset($gestiones->Table) && is_array($gestiones->Table)) {
            foreach ($gestiones->Table as $elemento){
                    $fgestion = new Carbon ($elemento->FECHA_GESTION);

                    $freagenda = new Carbon ($elemento->FECHA_REAGENDADA);


                    $fila = [
                        'COD_PERSONA'=>$elemento->COD_PERSONA,
                        'NRO_DOCUMENTO'=>$elemento->NRO_DOCUMENTO,
                        'NOM_COMPLETO'=>$elemento->NOM_COMPLETO,
                        'FECHA_GESTION'=>$fgestion->format('d-m-Y'),
                        'GESTOR'=>isset($elemento->Gestor) ? $elemento->Gestor : '',
                        'COD_RESPUESTA'=>$elemento->COD_RESPUESTA,
                        'RESP_CORTA'=>$elemento->RESP_CORTA,
                        'RESPUESTA'=>$elemento->Respuesta,
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
       // $fecha = '2023-12-22';// SOLO PARA PRUEBAS
        $fechaDesde = '2024-01-02';
        $fechaHasta = '2023-01-03';
        $client = new Client();
        try {


   $url = 'http://10.19.150.80/neoapi/webservice.asmx/ExecuteTask03?idTask=28&param1=VinanzasCobranzas&param2='.$fechaDesde.' 00:00:00'.'&param3='.$fechaHasta.' 23:59:59';
 //$url = 'http://10.19.150.80/neoapi/webservice.asmx/ExecuteTask03?idTask=28&param1=VinanzasCobranzas&param2='.$fecha.' 18:00:00&param3='.$fecha.' 23:59:59';



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
