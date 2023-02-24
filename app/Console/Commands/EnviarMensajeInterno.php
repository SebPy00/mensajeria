<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Models\EnvioMensajes;
use App\Models\EnvioMensajesDetalle;
use App\Models\Operacion;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\GestionesDelaOperacion;
use Illuminate\Support\Facades\DB;

class EnviarMensajeInterno extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enviar:gw';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envios de mensaje por GW, insertar gestión';

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
       // log::info('DETENIDO');
        $this->obtenerLote();
    }

    private function obtenerLote(){

        $fechaActual= Carbon::now()->toDateString();

        $lote = EnvioMensajes::where('fechaenvio', '<=', $fechaActual)
        ->where('fecha_envio_hasta', '>=', $fechaActual)
        ->where('tipo', 3 ) 
        ->where('aprobado', 1)
        ->where('idestado', 1)
        ->orderBy('id', 'asc')->first();

        if($lote){
            log::info('------------ Inicio Envío mensajes en lote: ' . $lote->id . '------------');
            $this->procesarLote($lote);
        }
    }
    
    private function procesarLote($lote){
        try{
            $detalle = EnvioMensajesDetalle::where('idenviomensaje', $lote->id)->get();
            if($detalle){

                $contador = 0;
                $canal = $this->determinarCanalFecha($lote->idcategoriamensaje);

               // log:info('Inicia recorrido para inserción de opeges');
                foreach ($detalle as $d){
                    $ope = Operacion::whereRelation('cliente', 'doc', $d->ci)->first();
                    
                    //cada 500 inserciones vamos consultando de vuelta el canal 
                    if($contador >= 500){ 
                        $canal = $this->determinarCanalFecha($lote->idcategoriamensaje);
                        $contador = 0;
                    }

                    if($lote->idcategoriamensaje == 2){
                        $obs ='ChatBot ' . $lote->observacion;
                    }elseif($lote->idcategoriamensaje == 4){
                        $obs ='Autogestor ' . $lote->observacion;
                    }
                     //PRUEBA DE FUNCIONALIDAD
                    log::info('canal: ' . $canal['canal']);
                    log::info('fecha: ' . $canal['fecha']);
                    if($ope) $this->insertaropeges($ope->ope, $ope->cli1, $canal['canal'], $d->nrotelefono, $lote->mensaje, $obs, $canal['fecha']);
                    $contador ++;
                }
            }
            $this->cambiarEstado($lote);
        }catch(Exception $ex){
            throw new Exception($ex->getMessage());
        }
    }

    private function cambiarEstado($lote){
        $lote->idestado = 3; //pasamos a procesado o a en proceso?
        $lote->save();
    }

    private function insertaropeges($ope, $cli, $canal, $tel, $mje, $obs, $date){
        $hora= Carbon::now()->toTimeString();
        try{
            $go = new GestionesDelaOperacion();
            $go->fec = $date;
            $go->gesfec = $date;
            $go->hor = $hora;
            $go->ope = $ope;
            $go->cli = $cli;
            $go->gesest = $canal;
            $go->gestel = $tel;
            $go->gestex = $mje;
            $go->ges = 1;
            $go->usu = 1;
            $go->emp = 1;
            $go->obs = $obs;
            $go->obsvocal = $mje;
            $go->save();
        }catch(Exception $ex){
            throw new Exception($ex->getMessage());
        }
    }

    private function determinarCanalFecha($tipo){
        $date = Carbon::now()->toDateString();
        $canal = $this->getDisponibilidadCanal(0);
        $res = 0;
        if($canal['canal'] != 0){
            if($canal['disponibilidad'] < 10000){
                $res = $canal['canal'];
            }else{
                $data = $this->determinarNuevaFecha();
                $res = $data['canal'];
                $date = Carbon::now()->addDay( $data['dia'])->toDateString();
            }
            return['canal'=> $res,'fecha'=>$date];
        }else{
            if($tipo == 2){
                $res = 106;
            }elseif($tipo == 2){
                $res = 103;
            }
            return['canal'=> $res,'fecha'=>$date];
        }
        
    }

    public function determinarNuevaFecha(){
        $day = 1;
        $r = $this->getDisponibilidadCanal($day);
        while ($r['disponibilidad'] >= 10000){
            $day ++;
            $r = $this->getDisponibilidadCanal($day);
        }

        return['canal'=> $r['canal'],'dia'=>$day];
    }

    public function getDisponibilidadCanal($day){
        $canal = DB::Select(
        "SELECT '103'::int as canal, 
            coalesce((SELECT count(*) FROM opeges 
            WHERE gesest IN (103)  
            AND fec = current_date + CAST('$day days' AS INTERVAL) group by gesest), 0)
            union all			
        SELECT '104'::int as canal, 
            coalesce((SELECT count(*) FROM opeges 
            WHERE gesest IN (104)  
            AND fec = current_date + CAST('$day days' AS INTERVAL) group by gesest), 0)
            union all
        SELECT '106'::int as canal, 
            coalesce((SELECT count(*) FROM opeges 
            WHERE gesest IN (106)  
            AND fec = current_date + CAST('$day days' AS INTERVAL) group by gesest), 0)
        ");
        
        $res = 0;
        $contador = 0;
        if($canal){
            $contador = $canal[0]->coalesce;
            $res = $canal[0]->canal;
            foreach ($canal as $c){
                if($c->coalesce < $contador){
                    $contador = $c->coalesce;
                    $res = $c->canal;
                }
            }
        }
        log::info('CANAL SELECCIONADO:' . $res);
        return ['canal'=> $res,'disponibilidad'=>$contador];
    }
}
