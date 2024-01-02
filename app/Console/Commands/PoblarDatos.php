<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;
use App\Models\DatosPersonales;
use App\Models\DatosLaborales;
use App\Models\Telefono;
use App\Models\Mail;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PoblarDatos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'poblar:datoscliente';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Pobla las tablas de datos laborales y personales';

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
        // $date = new Carbon('today');
        // $fecha = $date->dayName . ' ' . $date->toDateTimeString();
        // log::info('ATENCIÓN! POBLANDO TABLAS DE DATOS DE CLIENTES, '. $fecha);

        //DEFINIR CANTIDAD DE CLIENTES POR PROCESO
        $cantidad = 1000;
        $this->obtenerClientes($cantidad);
    }

    public function obtenerClientes($cantidad){
       
        $clientes = Cliente::where('datos_migrados', false)
        ->limit($cantidad)->get();

        if($clientes){
            $this->cambiarEstadoCliente($clientes, true);
            $noEncontrados = 0;
            foreach ($clientes as $cliente){
               // log::info('cliente : '.$cliente->cli);
                $res = $this->obtenerDatosPersonales($cliente->cli);
                if ($res == 1){
                    $this->obtenerDatosLaborales($cliente->cli);
                }else{
                    $noEncontrados++;
                }
            }
            log::info('Datos no encontrados de '.$noEncontrados.' registros en cli, de '. $cantidad .' seleccionados.');
        }else{
            log::info('no hay clientes con datos a migrar');
        }
    }

    public function cambiarEstadoCliente($clientes, $estado){
        try{
            foreach ($clientes as $cliente){
                $cliente->datos_migrados = $estado;
                $cliente->save();
            }
        }catch(Exception $e){
            throw new Exception($e->getMessage());
        }
        
    }

    public Function obtenerDatosPersonales($cli){
        $datosClientes = DB::Select(
            "SELECT  
                c.doc as documento,
                ec.id_est_civil AS idestadocivil,
                ec.estado_civil as estadocivildescripcion,
                c.pardir AS direccion,
                RTRIM(c.parnro) AS nrocasa,
                c.parbar AS barrio,
                pd.dep AS iddepartamento, 
                pd.nomdep as nomdepartamento,
                pc.id AS idciudad,
                pc.nomciu as nomciudad
            FROM cli c 
            left join estado_civil ec on c.estciv = ec.id_est_civil
            left join paisciu pc on c.pardis = pc.ciu
            left join paisdep pd on c.pardep = pd.dep
            WHERE (c.cli IN ( SELECT o.cli1 FROM ope o WHERE (o.emp = ANY (ARRAY[1, 6])) 
            AND (o.opesit = ANY (ARRAY[1, 2])) AND (o.ges <> ALL (ARRAY[298, 426, 427, 428])))) and c.cli = (:a)",
            ['a' => $cli]) ;
    
        if($datosClientes){
            $this->poblarDatosPersonales($datosClientes);
            return 1;
        }else{
            //log::info('datos personales no encontrados');
            return 0;
        }
    }

    public Function poblarDatosPersonales($datos){
        $dc = DatosPersonales::where('cedula', $datos[0]->documento)->first();
        if(!$dc){
            try{
                $dp = new DatosPersonales();
                $dp->cedula = $datos[0]->documento;
                $dp->idestadocivil = $datos[0]->idestadocivil ?? null;
                $dp->direccion = $datos[0]->direccion?? null;
                $dp->nrocasa = $datos[0]->nrocasa ?? null;
                $dp->barrio = $datos[0]->barrio ?? null;
                $dp->idciudad = $datos[0]->idciudad ?? null;
                $dp->iddepartamento = $datos[0]->iddepartamento ?? null;
                $dp->save();
            }catch(Exception $e){
                throw new Exception($e->getMessage());
            }
        }
        // else{
        //     log::info('Cliente ya tiene sus datos personales migrados');
        // }
    }

    public Function obtenerDatosLaborales($cli){
        $datosClientes = DB::Select(
            "SELECT  
                c.doc as documento,
                c.comdir AS direccion,
                c.labcar AS cargo
            FROM cli c 
            WHERE (c.cli IN ( SELECT o.cli1 FROM ope o WHERE (o.emp = ANY (ARRAY[1, 6])) 
            AND (o.opesit = ANY (ARRAY[1, 2])) AND (o.ges <> ALL (ARRAY[298, 426, 427, 428]))))and c.cli  = (:a)",
            ['a' => $cli]) ;
    
        if($datosClientes){
            $this->poblarDatosLaborales($datosClientes);
        }else{
            log::info('datos laborales no encontrados');
        }
    }

    public Function poblarDatosLaborales($datos){
        $dc = DatosLaborales::where('cedula', $datos[0]->documento)->first();
        if(!$dc){
            try{   
                $dl = new DatosLaborales();
                $dl->cedula = $datos[0]->documento;
                $dl->direccion = $datos[0]->direccion ?? null;
                $dl->cargo = $datos[0]->cargo ?? null;
                $dl->save();
            }catch ( Exception $e){
                throw new Exception($e->getMessage());
            }
        }
        // else{
        //     log::info('Cliente ya tiene sus datos laborales migrados');
        // }
    }

}
