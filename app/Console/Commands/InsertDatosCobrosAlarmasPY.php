<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;
use App\Models\CobrosAlarmasPY;
use GuzzleHttp\Client;

class InsertDatosCobrosAlarmasPY extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cobros:alarmas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Proceso que inserta los cobros de AlarmasPY en la base';

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
        $cobros = $this->getCobros();
        log::info(count($cobros));
        $this->insertarCobros($cobros);

    }

    private function getCobros(){
        log::info('Obteniendo cobros de Alarmas Py');

        try {
            $url = 'https://sistemas.alarmas.com.py/ords/walrusws/apiInteSG/consCobranza';
            log::info($url);

            $authorizationToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzcWxwbHVzIiwic3ViIjoiYXBpIiwiYXVkIjoiYXBleCIsImlhdCI6MTY5ODQxODQyMiwiaWQiOiAxNn0.wGAIqdwzIYn0Q3u-Ab1kMyWmybZp-9jMb0eD5aEPssQ';
            $headers = [
                'Authorization' =>  $authorizationToken
            ];
            $client = new Client();
            $response = $client->request('POST', $url, [
                'headers' => $headers,
            ]);
            $responseData = $response->getBody();


            if (empty($responseData)) {
                log::info('SIN COBROS');
            } else {
                // Agrega un registro para ver la respuesta
                log::info('Respuesta de la API: ' . $responseData);
                return json_decode($responseData);
            }
        } catch (Exception $ex) {
            $pref = 'webservice => ';
            throw new Exception('ERROR: api COBROS AlarmasPY - ' . $pref . $ex->getMessage());
        }
    }

    private function insertarCobros($listacobros){

        try{

            if(isset($listacobros)){
                log::info('llega a insertar clientes');
                foreach ($listacobros as $cobros){
                    $cob = new CobrosAlarmasPY();
                    $cob->cod_cliente = $cobros->cod_cliente;
                    $cob->operacion = $cobros->operacion;
                    $cob->cartera = $cobros->cartera;
                    $cob->saldo = $cobros->saldo;
                    $cob->fecha_pago = $cobros->fecha_pago;
                    $cob->monto_pagado = $cobros->monto_pago;
                    $cob->nro_cuota = $cobros->nro_cuota;
                    $cob->tipo_operacion = $cobros->tipo_operacion;
                    $cob->producto = $cobros->producto;
                    $cob->nro_documento = $cobros->ci;
                    $cob->cotizacion = $cobros->cotizacion;
                    $cob->dias_mora = $cobros->dia_de_mora;
                    $cob->fecha_insert = Carbon::now()->toDateString();
                    $cob->save();
                }
            }
        }catch(Exception $ex){
            throw new Exception($ex->getMessage());
        }
    }
}
