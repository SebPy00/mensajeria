<?php

namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;
use App\Models\ClientesCPH;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

class InsertarClientesCPH2 extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'clientes:cph';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando de inserción de clientes de CPH en base de datos sigesa a partir de una api';

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
        log::info('INICIA CONSUMO DE API PARA ACTUALIZACIÓN DE BASE DE CLIENTES CPH');
        $clientes = $this->getClientes();
        $this->insertarClientes($clientes);
    }

    public function insertarClientes($clientes){
        //$c = 0;
        $data = json_decode($clientes, true);

            if($data===null||empty($data['Body']['Base_Api_Cph.COBROSResponse']['Cobroscph'])){
                echo 'La API esta vacia';
            } else {
            foreach ($data['Body']['Base_Api_Cph.CLIENTESResponse']['Clientescph']['clientescphItem'] as $cliente) {


                ///////////////////////////LA FECHA IDEALMENTE DEBIÓ ESTAR EN FORMATO FECHA
                //$fechaVtoCuota = trim((string)($cliente['fechadevtodelacuota'] ?? ''));
                //$fechaVtoCuota = $fechaVtoCuota ? Carbon::createFromFormat('d/m/y', $fechaVtoCuota)->format('Y-m-d') : null;
                //$fechaUltPago = trim((string)($cliente['ultimafechadepago'] ?? ''));
                //  $fechaUltPagoCarbon = $fechaUltPago ? Carbon::createFromFormat('d/m/y', $fechaUltPago)->format('Y-m-d') : null;
                //  Log::info($fechaUltPagoCarbon);
                //$fechaValor = trim((string)($cliente['fechavalor'] ?? ''));
                //$fechaValorCarbon = $fechaValor ? Carbon::createFromFormat('d/m/y', $fechaValor)->format('Y-m-d') : null;
                ///////////////////////////////////////////////////////////////



                ////////////////////////////////////////////FORMATEO DE ARRAYS DE NUMEROS////////////////////////////////////////////////
                //******************************************* PASAR A FUNCION*****************************////////////////////////////
                ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

                $celularparticular = $cliente['celularparticular'] ?? '';
                $cel_part = $celularparticular;
                if (is_array($celularparticular)) {
                    // Si es un array, implode los números
                    $cel_part = implode(', ', array_map('trim', $celularparticular));
                } else {
                    // Si no es un array, simplemente asigna el valor (puede ser un solo número)
                    $cel_part = trim((string)$celularparticular);
                }

                $telefono = $cliente['telefonoparticular'] ?? '';
                $tel = $telefono;
                if (is_array($telefono)) {
                    // Si es un array, implode los números
                    $tel = implode(', ', array_map('trim', $telefono));
                } else {
                    // Si no es un array, simplemente asigna el valor (puede ser un solo número)
                    $tel = trim((string)$telefono);
                }

                $telefonolaboral = $cliente['telefonolaboral'] ?? '';
                $tellab = $telefonolaboral;
                if (is_array($telefonolaboral)) {
                    // Si es un array, implode los números
                    $tellab = implode(', ', array_map('trim', $telefonolaboral));
                } else {
                    // Si no es un array, simplemente asigna el valor (puede ser un solo número)
                    $tellab = trim((string)$telefonolaboral);
                }

                $celularalternativo = $cliente['celularalternativo'] ?? '';
                $cel_alternativo = $celularalternativo;
                if (is_array($celularalternativo)) {
                    // Si es un array, implode los números
                    $cel_alternativo = implode(', ', array_map('trim', $celularalternativo));
                } else {
                    // Si no es un array, simplemente asigna el valor (puede ser un solo número)
                    $cel_alternativo = trim((string)$celularalternativo);
                }

                $celularlaboral = $cliente['celularlaboral'] ?? '';
                $cel_lab = $celularlaboral;
                if (is_array($celularlaboral)) {
                    // Si es un array, implode los números
                    $cel_lab = implode(', ', array_map('trim', $celularlaboral));
                } else {
                    // Si no es un array, simplemente asigna el valor (puede ser un solo número)
                    $cel_lab = trim((string)$celularlaboral);
                }
                ////////////////////////////////////////////////////////////////////////////////////////
                /////////////////////////// MAS FORMATEO DE ARRAYS//////////////////////////////////////
                ////////////////////////////////////////////////////////////////////////////////////////
                $segmento = $cliente['segmento'] ?? '';
                $seg = $segmento;
                if (is_array($segmento)) {
                    // Si es un array, implode los números
                    $seg = implode(', ', array_map('trim', $segmento));
                } else {
                    // Si no es un array, simplemente asigna el valor (puede ser un solo número)
                    $seg = trim((string)$segmento);
                }


                $producto = $cliente['producto'] ?? '';
                $prod = $producto;
                if (is_array($producto)) {
                    // Si es un array, implode los números
                    $prod = implode(', ', array_map('trim', $producto));
                } else {
                    // Si no es un array, simplemente asigna el valor (puede ser un solo número)
                    $prod = trim((string)$producto);
                }

                $lote = $cliente['lote'] ?? '';
                $lot = $lote;
                if (is_array($lote)) {
                    // Si es un array, implode los números
                    $lot = implode(', ', array_map('trim', $lote));
                } else {
                    // Si no es un array, simplemente asigna el valor (puede ser un solo número)
                    $lot = trim((string)$lote);
                }


                ///////////////////////////////////////////////////////////////////////////////////////

                /////////////////////////// guardamos el cliente - pasar a función ////////////////

                ///////////////////////////////////////////////////////////////////////////////////

                $cliCPH = new ClientesCPH();
                $cliCPH->cod_cliente = trim((string)($cliente['COD_CLIENTE'] ?? ''));
                $cliCPH->nro_documento = trim((string)($cliente['NRODEDOCUMENTO'] ?? ''));
                $cliCPH->nom_cliente = trim((string)($cliente['nombredelcliente'] ?? ''));
                $cliCPH->tellab = $tellab;
                $cliCPH->telefono = $tel;
                $cliCPH->cel_alternativo = $cel_alternativo;
                $cliCPH->cel_lab = $cel_lab;
                $cliCPH->cel_part = $cel_part;
                $cliCPH->operacion = trim((string)($cliente['operacion'] ?? ''));
                $cliCPH->segmento = $seg;
                $cliCPH->producto = $prod;
                $cliCPH->tasa = trim((float)($cliente['tasa'] ?? 0));
                $cliCPH->total_saldo = ((float)($cliente['saldocapital'] ?? 0));
                $cliCPH->saldo_cuota = ((float)($cliente['saldocuota'] ?? 0));
                $cliCPH->moratorio = ((float)($cliente['intmoratorio'] ?? 0));
                $cliCPH->punitorio = ((float)($cliente['intpunitorio'] ?? 0));
                $cliCPH->gastos_cobranzas = ((float)($cliente['gastoscob'] ?? 0));
                $cliCPH->iva = ((float)($cliente['iva'] ?? 0));
                $cliCPH->dias_mora = ((int)($cliente['diasdemora'] ?? 0));
                $cliCPH->nro_cuota = ((int)($cliente['numerodecuota'] ?? 0));
                $cliCPH->total_cuotas = ((int)($cliente['totaldecuotas'] ?? 0));
                $cliCPH->cuotas_pag = ((int)($cliente['cuotaspagadas'] ?? 0));
                $cliCPH->cuotas_pend = ((int)($cliente['cuotaspendientes'] ?? 0));
                $cliCPH->monto_cuota = ((float)($cliente['montocuota'] ?? 0));
                $cliCPH->fecha_vto_cuota = trim((string)($cliente['fechadevtodelacuota'] ?? ''));
                $cliCPH->ult_fech_pago = trim((string)($cliente['ultimafechadepago'] ?? ''));
                $cliCPH->total_deuda_cuota = ((float)($clienteCPH['totaldeudacuota'] ?? 0));
                $cliCPH->total_deuda = ((float)($clienteCPH['totaldeuda'] ?? 0));
                $cliCPH->fecha_valor =  trim((string)($cliente['fechavalor'] ?? ''));
                $cliCPH->cod_dist = trim((string)($clienteCPH['coddist'] ?? ''));
                $cliCPH->lote = $lot;
                $cliCPH->tipo_poe = ((int)($clienteCPH['tipoope'] ?? 0));
                $cliCPH->situacion = trim((string)($clienteCPH['situacion'] ?? ''));
                $cliCPH->fecha_insert = Carbon::now()->toDateString();
                $cliCPH->save();
            }

        }
    }

    private function getClientes(){
        log::info('Obteniendo clientes');

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
                <inf:Base_Api_Cph.CLIENTES>
                    <inf:Ent>10</inf:Ent>
                    <inf:Usuario>ApiUserCph</inf:Usuario>
                    <inf:Password>R3$tS04p</inf:Password>
                    <inf:Doc>TODOS</inf:Doc>
                    <inf:Resp>?</inf:Resp>
                    <inf:Respuesta>?</inf:Respuesta>
                </inf:Base_Api_Cph.CLIENTES>
            </soapenv:Body>
        </soapenv:Envelope>';

            $request = new Request('POST', 'http://192.168.15.92:1010/ApiSIGESA/aBase_Api_Cph.aspx?wsdl', $headers, $body);


            try {
                $res = $client->sendAsync($request)->wait();
                // Obtener el contenido de la respuesta y convertirlo a JSON
                $res = $res->getBody();
                $res =  simplexml_load_string(str_replace("SOAP-ENV:","",str_replace("InfBase","",$res)));

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

}
