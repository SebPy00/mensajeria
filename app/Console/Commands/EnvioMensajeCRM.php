<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\GestionesDelaOperacion;
use App\Models\ConfiguracionMensajes;
use App\Models\CRMBulk;
use Illuminate\Support\Facades\DB;
use GuzzleHttp\Client;

class EnvioMensajeCRM extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:envio';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para envío de mensajes insertados en opeges';

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

        log::info('Inicia comando para envío de mensajes insertados en opeges');

        $canales = array (100, 103, 104, 106, 153);

        $this->noEnviadosListaNegra();
        foreach ($canales as $canal){
            $listado = $this->obtenerListado($canal);
            $this->procesarListado($listado);
        }

    }

    private function noEnviadosListaNegra(){
        $noenviados = DB::Select("UPDATE opeges SET gesest=154 WHERE regnro in (
            SELECT
                og.regnro
            FROM opeges og
            LEFT JOIN cli c ON c.cli=og.cli
            LEFT JOIN lista_negra ON TRIM(tel)::bigint=TRIM(gestel)::bigint AND sms AND (cedula='0' OR cedula=c.doc)
            WHERE og.gesest IN (100,103,104,106,155) AND sms IS NOT NULL
        ) AND gesest IN (100,103,104,106,155)");
    }

    private function obtenerListado ($canal){

        $condicion="AND og.fec>=(now()::date)-2";
        $condicion2="AND og.usu=1";
        if ($canal=="106"){
            $condicion="AND og.fec>=(now()::date)-5";
            $condicion2="AND (og.usu=1 OR og.usu=520)";
        }
        if ($canal=="103"){
            $condicion="AND og.fec>=(now()::date)-7";
        }
        if ($canal=="100" || $canal=="153"){
            $condicion2="";
        }

        $listado = DB::Select(
            "SELECT
                og.regnro
                ,og.ope
                ,regexp_replace(regexp_replace(trim(sinacentos2(og.gestex)), E'\n$', '', 'g'), E'\n','. ', 'g') as gestex -- %0A es enter
                ,og.gestel
                ,og.fec
                ,og.hor
                ,(now()::date)-1 as ayer
                ,(now()::date) as hoy
            FROM opeges og
            LEFT JOIN cli c ON c.cli=og.cli
            LEFT JOIN lista_negra ON TRIM(tel)::bigint=TRIM(gestel)::bigint AND sms AND (cedula='0' OR cedula=c.doc)
            WHERE
                og.gesest=$canal
                $condicion
                AND og.fec<=now()
                AND (now()::time)<'18:00:00.000'
                AND (now()::time)>'08:00:00.000'
                AND (extract(isodow from now())!='6' OR (extract(isodow from now())='6' AND (now()::time)<'12:00:00.000')) --que no sea sabado posterior a las 12
                AND LENGTH(og.gestel)>=9
                AND extract(isodow from now())!='7' --que no sea domingo
                AND TO_CHAR(CURRENT_DATE,'dd/mm')!='25/12' --que no sea navidad
                AND TO_CHAR(CURRENT_DATE,'dd/mm')!='30/04' --que no sea dia del maestro
                AND TO_CHAR(CURRENT_DATE,'dd/mm')!='01/01' --que no sea año nuevo
                AND (now()<CONCAT(TO_CHAR(CURRENT_DATE,'24/12/yyyy'),' 13:00:00.000')::TIMESTAMP OR CURRENT_DATE>TO_CHAR(CURRENT_DATE,'25/12/yyyy')::DATE) -- que no sea noche buena
                --AND gestel not in (select tel from lista_negra where sms)
                AND sms IS NULL
                --and og.regnro = 21041376
                $condicion2
            ORDER BY CONCAT(og.fec, ' ', hor)::timestamp asc limit 30");

        return $listado;
    }

    private function procesarListado($lista){

        foreach ( $lista as $l){
            $configuracion = ConfiguracionMensajes::first();
            if($configuracion->contador < 50000)
                $this->procesarMensaje($l, $configuracion);
        }
    }



    private function procesarMensaje($registro, $configuracion){
    	//$mensaje=str_replace("\\xf1","ñ",utf8_encode($registro->gestex));
    	$mensaje=str_replace("\\xf1","ñ",$registro->gestex);
        $mensaje=str_replace("\\xd1","Ñ",$mensaje);
        $mensaje=str_replace(" ","%20",$mensaje);
        $mensaje=str_replace("&","%26",$mensaje);

        $nro= str_replace(".","",$registro->gestel);//quitamos puntos
        $nro= str_replace(" ","",$nro);//quitamos espacios
        //si comienza con 9, agregar 0 = 991727336
        if(strlen($nro)==9 && $nro[0]=="9" ){
            $nro="0$nro";
        }

        $url = env('DIR_WEBSERVICE').'?key='. env('CLAVE_WEBSERVICE') .
        '&message='.$mensaje .'&msisdn='.$nro;

        $this->enviarMensaje($registro, $url, $configuracion);
    }

    public function enviarMensaje($registro, $url, $configuracion){
        $mensaje = GestionesDelaOperacion::where('regnro', $registro->regnro)->first();

       // log::info('url:' . $url);
       // log::info($registro->regnro);
       // log::info($configuracion->contador);

        $client = new Client();
        try {
            $response = $client->post($url);
            $res = json_decode($response->getBody());
            if (!empty($res)) {
                if(!empty($res->id)){
                    //guardamos el id
                    $historial = CRMBulk::where('regnro', $registro->regnro)->first();

                    if(!$historial){
                        $historial = new CRMBulk();
                        $historial->regnro = $registro->regnro;
                        $historial->idenvio = $res->id;
                        $historial->save();
                    }else{
                        $historial->idenvio = $res->id;
                        $historial->save();
                    }

                    //modificamos el registro opeges
                    $mensaje->gesest = 155; //en proceso de envio
                    $mensaje->intentos += 1;
                    $mensaje->save();

                    //agregar al contador de mensajes
                    $configuracion->contador += 1;
                    $configuracion->save();
                }else{
                    if($mensaje->intentos < 2){
                        $mensaje->gesest = 153; //reenviar
                        $mensaje->intentos += 1;
                        $mensaje->save();
                    }else{
                        $mensaje->gesest = 154; //no enviado
                        $mensaje->intentos += 1;
                        $mensaje->save();
                    }

                }
            }
        } catch (Exception $ex) {
           if ($ $mensaje->intentos>2){
        	$mensaje->intentos -= 1;		
           }
            
            $pref = 'webservice => ';
            log::info('ERROR 1: funcion enviarMensaje - ' . $pref . $ex->getMessage());
            sleep(60);
        }
    }
}
