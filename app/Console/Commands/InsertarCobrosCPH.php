<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;
use App\Models\CobrosCPH;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
class InsertarCobrosCPH extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cobros:cph';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Consumo de la api de cobros CPH';

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
        log::info('INICIA CONSUMO DE API PARA ACTUALIZACIÓN DE BASE DE COBROS CPH');
        $cobros = $this->getCobros();
        $this->insertar($cobros);
    }

    private function getCobros(){
        log::info('Obteniendo cobros cph');

        try {
            $client = new Client();
            $headers = [
                'SOAPACTION' => '"#POST"',
                'Cookie' => 'ASP.NET_SessionId=qsrrj3f2q2sr5y4gtynebpi4; ASP.NET_SessionId=k4o5ipvyzivllyatvnjenvaj',
                'Content-Type' => 'text/xml'
            ];

            //OBS:  <inf:Doc>CI</inf:Doc> PARA TRAER UN SOLO CLIENTE
            $body = '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:inf="InfBase">
            <soapenv:Header/>
            <soapenv:Body>
                <inf:Base_Api_Cph.COBROS>
                    <inf:Ent>10</inf:Ent>
                    <inf:Usuario>ApiUserCph</inf:Usuario>
                    <inf:Password>R3$tS04p</inf:Password>
                    <inf:Doc>TODOS</inf:Doc>
                    <inf:Fec1>2024-01-01</inf:Fec1>
                    <inf:Fec2>2024-01-08</inf:Fec2>
                </inf:Base_Api_Cph.COBROS>
            </soapenv:Body>
        </soapenv:Envelope>';

        $request = new Request('POST', 'http://192.168.15.92:1010/ApiSIGESA/aBase_Api_Cph.aspx?wsdl', $headers, $body);


            try {
                $res = $client->sendAsync($request)->wait();
                // Obtener el contenido de la respuesta y convertirlo a JSON
                $res = $res->getBody();
                $res =  simplexml_load_string(str_replace("SOAP-ENV:","",str_replace("InfBase","",$res)));
                //$res =  json_encode($res, true);
                //Imprimir la respuesta JSON
                log::info(print_r($res,true)) ;

                return json_encode($res, true);
            } catch (Exception $ex) {
                // Manejar la excepción
                echo 'Error: ' . $ex->getMessage();
            }

        } catch (Exception $ex) {
            $pref = 'webservice => ';
            throw new Exception('ERROR: api CLIENTES CPH - ' . $pref . $ex->getMessage());
        }

    }


    public function insertar($cobros){
        //$c = 0;
        $data = json_decode($cobros, true);

            if($data===null){ echo 'Error al decodificar el JSON';
            } else {
            foreach ($data['Body']['Base_Api_Cph.COBROSResponse']['Cobroscph']['cobroscphItem'] as $cobro) {

                ////////////////////////////////////////////////////////////////////////////////////////
                ///////////////////////////  FORMATEO DE ARRAYS//////////////////////////////////////
                ////////////////////////////////////////////////////////////////////////////////////////
                $segmento = $cobro['segmento'] ?? '';
                $seg = $segmento;
                if (is_array($segmento)) {
                    // Si es un array, implode los números
                    $seg = implode(', ', array_map('trim', $segmento));
                } else {
                    // Si no es un array, simplemente asigna el valor (puede ser un solo número)
                    $seg = trim((string)$segmento);
                }


                $producto = $cobro['producto'] ?? '';
                $prod = $producto;
                if (is_array($producto)) {
                    // Si es un array, implode los números
                    $prod = implode(', ', array_map('trim', $producto));
                } else {
                    // Si no es un array, simplemente asigna el valor (puede ser un solo número)
                    $prod = trim((string)$producto);
                }

                ///////////////////////////////////////////////////////////////////////////////////////

                /////////////////////////// guardamos el cobro - pasar a función ////////////////

                ///////////////////////////////////////////////////////////////////////////////////

                $cobCHP = new CobrosCPH();
                $cobCHP->cod_cliente = trim((string)($cobro['cod_cliente'] ?? ''));
                $cobCHP->nro_documento = trim((string)($cobro['cliente'] ?? ''));
                $cobCHP->operacion = trim((string)($cobro['operacion'] ?? ''));
                $cobCHP->saldo = ((float)($cobro['saldo'] ?? 0));
                $cobCHP->fecha_pago = trim((string)($cobro['fec_pago'] ?? ''));
                $cobCHP->mon_pagado = ((float)($cobro['mon_pagado'] ?? 0));
                $cobCHP->moratorio = ((float)($cobro['intmoratorio'] ?? 0));
                $cobCHP->punitorio = ((float)($cobro['intpunitorio'] ?? 0));
                $cobCHP->gastos_cob = ((float)($cobro['gastoscob'] ?? 0));
                $cobCHP->iva = ((float)($cobro['iva'] ?? 0));
                $cobCHP->nro_cuota = ((int)($cobro['nro_cuota'] ?? 0));
                $cobCHP->producto = trim((float)($cobro['tasa'] ?? 0));
                $cobCHP->segmento = $seg;
                $cobCHP->dias_mora = $prod;
                $cobCHP->save();
            }

        }
    }
}
