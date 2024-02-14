<?php

namespace App\Exports;

use Illuminate\Support\Facades\Log;
use App\Models\DatosEjemplo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GenerarBaseClientesNeotelExport
{
    public function export()
    {
        Log::info('INICIA GENERACION DE BASE CLIENTES NEOTEL');

        $fecha = Carbon::now()->toDateString();
        $clientes = $this->getClientes($fecha);
        $lista = [];

        if (isset($clientes)) {
            foreach ($clientes as $cli) {
                $vto = new Carbon($cli->fecha_vto_cuota);
                $apertura = new Carbon($cli->fec_apertura);

                $fila = [
                    'cod_cliente' => trim($cli->cod_cliente),
                    'nro_documento' => trim($cli->nro_documento),
                    'nom_cliente' => trim($cli->nom_cliente),
                    'tellab' => trim($cli->tellab),
                    'telefono' => trim($cli->telefono),
                    'cel_alternativo' => trim($cli->cel_alternativo),
                    'cel_lab' => trim($cli->cel_lab),
                    'cel_part' => trim($cli->cel_part),
                    'operacion' => trim($cli->operacion),
                    'segmento' => '',
                    'producto' => trim($cli->producto),
                    'tipo_cred_tarj' => '',
                    'tipo_ope' => '',
                    'tasa' => '',
                    'saldo_capital' => trim($cli->saldo_capital),
                    'saldo_interes' => trim($cli->saldo_interes),
                    'tipo_cambio' => '',
                    'dias_mora' => trim($cli->dias_mora),
                    'nro_cuota' => '1',
                    'total_cuotas' => '1',
                    'cuotas_pag' => '0',
                    'cuotas_pend' => '1',
                    'monto_mora' => '',
                    'monto_cuota' => trim($cli->monto_cuota),
                    'fecha_vto_cuota' => $vto->format('d/m/Y'),
                    'ult_fech_pago' => '',
                    'total_deuda' => '',
                    'estado' => '',
                    'fec_apertura' => $apertura->format('d/m/Y'),
                    'cat_riesgo' => '',
                    'dato_extra1' => trim($cli->dato_extra1),
                    'dato_extra2' => trim($cli->dato_extra2),
                    'dato_extra3' => trim($cli->dato_extra3),
                    'dato_extra4' => trim($cli->dato_extra4),
                    'dato_extra5' => '',
                    'dato_extra6' => '',
                    'dato_extra7' => '',
                    'COD_GESTOR' => ''
                ];
                $lista[] = $fila;
            }
        }

        Log::info('FIN GENERACION DE BASE CLIENTES NEOTEL');

        // Convertir la lista de datos a JSON
        $json_data = json_encode($lista);

        // URL del endpoint de la API
        $url = 'http://10.19.150.80/neoapi/webservice.asmx';

        // Parámetros adicionales para enviar a la API
        $params = [
            'idtask' => 39,
            'param1' => 'Ejemplo1',
            'param2' => $json_data
        ];

        // Cabeceras HTTP para JSON
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Enviar la solicitud HTTP POST con los datos JSON y las cabeceras
        $response = Http::withHeaders($headers)->post($url, $params);

        // Verificar la respuesta de la API
        if ($response->successful()) {
            // Solicitud exitosa
            return $response->json();
        } else {
            // Error en la solicitud
            return response()->json(['error' => 'Error al enviar los datos a la API'], $response->status());
        }
    }

    private function getClientes($fecha)
    {
        return DatosEjemplo::whereDate('fecha_insert', $fecha)->get();
    }
}
