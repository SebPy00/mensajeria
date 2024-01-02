<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;
use App\Models\ClientesAlarmasPY;
use GuzzleHttp\Client;
class InsertDatosClientesAlarmasPy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clientes:alarmas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando de inserción de clientes de AlarmasPy';

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
        $clientes = $this->getClientes();
        $this->insertarClientes($clientes);
    }

    private function getClientes(){
        log::info('Obteniendo clientes Alarmas Py');

        try {
            $url = 'https://sistemas.alarmas.com.py/ords/walrusws/apiInteSG/consCliente';
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
                log::info('SIN CLIENTES');
            } else {
                // Agrega un registro para ver la respuesta
                log::info('Respuesta de la API: ' . $responseData);
                return json_decode($responseData);
            }
        } catch (Exception $ex) {
            $pref = 'webservice => ';
            throw new Exception('ERROR: api CLIENTES AlarmasPY - ' . $pref . $ex->getMessage());
        }

    }

    private function insertarClientes($clientes){
        try{

            if(isset($clientes)){
                foreach ($clientes as $cli){
                    $alarmacli = new ClientesAlarmasPY();
                    $alarmacli->cod_cliente = $cli->cod_cliente;
                    $alarmacli->nro_documento = $cli->nro_de_documento;
                    $alarmacli->nom_cliente = $cli->nombre_del_cliente;
                    $alarmacli->tellab = $cli->tele_laboral;
                    $alarmacli->telefono = $cli->tele_particular;
                    $alarmacli->cel_alternativo = $cli->celu_alternativo;
                    $alarmacli->cel_lab = $cli->celu_laboral;
                    $alarmacli->cel_part = $cli->celu_particular;
                    $alarmacli->operacion = $cli->operacion;
                    $alarmacli->producto = $cli->producto;
                    $alarmacli->saldo_capital = $cli->saldo_capital;
                    $alarmacli->saldo_interes = $cli->saldo_interes;
                    $alarmacli->dias_mora = $cli->dias_de_mora;
                    $alarmacli->nro_cuota = $cli->numero_de_cuotas;
                    $alarmacli->total_cuotas = $cli->total_de_cuotas;
                    $alarmacli->cuotas_pag = $cli->cuotas_pagadas;
                    $alarmacli->cuotas_pend = $cli->cuotas_pendientes;
                    $alarmacli->monto_cuota = $cli->monto_de_cuota;
                    $alarmacli->fecha_vto_cuota = $cli->fecha_de_vto_cuota;
                    $alarmacli->fec_apertura = $cli->fech_apertura;
                    $alarmacli->dato_extra1 = $cli->inte_mora; // moratorio
                    $alarmacli->dato_extra2 = $cli->inte_puni; //punitorio
                    $alarmacli->dato_extra3 = $cli->inte_gast_admi; //gastos administrativos
                    $alarmacli->dato_extra4 = $cli->comi_boca_cobr; // comisión boca cobranza
                    $alarmacli->dato_extra5 = $cli->chapa; // comisión boca cobranza
                    $alarmacli->fecha_insert = Carbon::now()->toDateString();
                    $alarmacli->save();
                }
            }

        }catch(Exception $ex){
            throw new Exception($ex->getMessage());
        }
    }

    private function generarArchivoServicios(){

    }
}
