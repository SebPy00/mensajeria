<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Cliente;
use App\Models\Telefono;
use App\Models\Mail;
use Carbon\Carbon;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
class PoblarTelefonosyMails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'poblar:telymail';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Poblar las tablas de teléfono y mail';

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
        $cantidad = 1000;
        $this->obtenerClientes($cantidad);
    }

    public function obtenerClientes($cantidad){
       
        $clientes = Cliente::where('tel_mail_migrados', false)
        ->limit($cantidad)->get();

        if($clientes){
            $this->cambiarEstadoCliente($clientes, true);
            $noEncontrados = 0;
            foreach ($clientes as $cliente){
                // log::info('cliente : '.$cliente->cli);
                $res = $this->obtenerTelefonos($cliente->cli);
                if ($res == 1){
                    //log::info('Buscando mail');
                    $this->obtenerMails($cliente->cli);
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
                $cliente->tel_mail_migrados = $estado;
                $cliente->save();
            }
        }catch(Exception $e){
            throw new Exception($e->getMessage());
        }
        
    }

    public function obtenerTelefonos($cli){
        $telefonosClientes = DB::Select(
        "SELECT 
            c.doc as documento,
            --laborales
            regexp_replace(btrim(c.labtel::text),'[^0-9]+'::text, ''::text, 'g'::text) AS tellaboral,
            regexp_replace(btrim(c.labnro::text),'[^0-9]+'::text, ''::text, 'g'::text) AS numlaboral,
            regexp_replace(btrim(c.labcel::text), '[^0-9]+'::text, ''::text, 'g'::text) AS cellaboral,
            --particulares - todos celulares
            regexp_replace(btrim(c.telmas::text), '[^0-9]+'::text, ''::text, 'g'::text) AS telmas, 
            regexp_replace(btrim(c.telcel::text), '[^0-9]+'::text, ''::text, 'g'::text) AS telcel,
            regexp_replace(split_part(btrim(c.partel::text), '/',1),'[^0-9]+'::text, ''::text, 'g'::text) AS telpartuno,
            regexp_replace(split_part(btrim(c.partel::text), '/',2),'[^0-9]+'::text, ''::text, 'g'::text) AS telpartdos,
            regexp_replace(btrim(c.parcel::text),'[^0-9]+'::text, ''::text, 'g'::text) AS celparticular
        FROM cli c WHERE (c.cli IN ( SELECT o.cli1 FROM ope o
        WHERE (o.emp = ANY (ARRAY[1, 6])) AND (o.opesit = ANY (ARRAY[1, 2]))
        AND (o.ges <> ALL (ARRAY[298, 426, 427, 428])))) and c.cli = (:a)",
        ['a' => $cli]) ;
        if($telefonosClientes){
            $this->poblarTelefono($telefonosClientes);
            return 1;
        }else{
            //log::info('datos no encontrados');
            return 0;
        }
    }

    public function obtenerMails($cli){
        $mailsClientes = DB::Select(
            "SELECT * from (SELECT 
            trim(c.doc) as documento,
            (case 
                when (trim(c.paremail) !~ '^[A-Za-z0-9._%-]+@[A-Za-z0-9-]+[.][A-Za-z]+$')
                    then ''
                ELSE trim(c.paremail)
            end ) as mail,
            (case 
                when (trim(c.comemail) !~ '^[A-Za-z0-9._%-]+@[A-Za-z0-9-]+[.][A-Za-z]+$')
                    then ''
                ELSE trim(c.comemail)
            end ) AS mailempresa,
            (case 
                when (trim(c.labemail) !~ '^[A-Za-z0-9._%-]+@[A-Za-z0-9-]+[.][A-Za-z]+$')
                    then ''
                ELSE trim(c.labemail)
            end )  AS maillaboral 
        FROM cli c WHERE (c.cli IN ( SELECT o.cli1 FROM ope o
        WHERE (o.emp = ANY (ARRAY[1, 6])) AND (o.opesit = ANY (ARRAY[1, 2]))
        AND (o.ges <> ALL (ARRAY[298, 426, 427, 428])))) and (trim(c.paremail) != '' OR
        trim(c.comemail) != '' or trim(c.labemail) != '') and c.cli = (:a)) as m where m.mail != '' OR m.mailempresa != ''
        OR m.maillaboral != ''",
        ['a' => $cli]) ;
        if($mailsClientes){
            $this->poblarMail($mailsClientes);
        }else{
            log::info('mails no encontrados');
        }
    }

    public function poblarTelefono($telefonos){
        $ci = $telefonos[0]->documento;
        $tellaboral = intval($telefonos[0]->tellaboral);
        $numlaboral = intval($telefonos[0]->numlaboral);
        $cellaboral = intval($telefonos[0]->cellaboral);
        $telmas = intval($telefonos[0]->telmas);
        $telcel = intval($telefonos[0]->telcel);
        $telpartuno = intval($telefonos[0]->telpartuno);
        $telpartdos = intval($telefonos[0]->telpartdos);
        $celparticular = intval($telefonos[0]->celparticular);
        if($tellaboral) $this->guardarTel($ci,false,false,$tellaboral);
        if($numlaboral) $this->guardarTel($ci,false,false,$numlaboral);
        if($cellaboral) $this->guardarTel($ci,false,false,$cellaboral);
        if($telmas) $this->guardarTel($ci,false,true,$telmas);
        if($telcel) $this->guardarTel($ci,false,true,$telcel);
        if($telpartuno) $this->guardarTel($ci,false,true,$telpartuno);
        if($telpartdos) $this->guardarTel($ci,false,true,$telpartdos);
        if($celparticular) $this->guardarTel($ci,true,true,$celparticular);
    }

    public function poblarMail($mails){
        $ci = $mails[0]->documento;
        $mail = $mails[0]->mail;
        $mailempresa = $mails[0]->mailempresa;
        $maillaboral = $mails[0]->maillaboral;
        if($mail) $this->guardarMail($ci,true,true,$mail);
        if($mailempresa) $this->guardarMail($ci,false,false,$mailempresa);
        if($maillaboral) $this->guardarMail($ci,false,false,$maillaboral);
    }

    public function guardarMail($ci, $principal, $particular, $correo){
        $m = Mail::where('correo', $correo)->where('cedula', $ci)->first();
        if(!$m){
            try{
                $c = new Mail();
                $c->cedula = $ci;
                $c->principal = $principal;
                $c->particular = $particular;
                $c->correo = $correo;
                $c->save();
            }catch(Exception $e){
                throw new Exception($e->getMessage());
            }
        }else{
            log::info('El mail ya fue registrado para este cliente');
        }

    }

    public function guardarTel($ci, $principal, $particular,$nro){
        $n = Telefono::where('numero', $nro)->where('cedula', $ci)->first();
        if(!$n){
            $prefijo = substr($nro, 0, 2);
            if($prefijo == 21 || (($prefijo == 96 || $prefijo == 97 || $prefijo == 98 || $prefijo == 99) && strlen($nro) == 9) || strlen($nro) == 6){
                $cel = true;
                if($prefijo == 21 || strlen($nro) == 6) $cel = false;
                if(strlen($nro) < 10 && strlen($nro) > 5){
                    try{
                        $tel = new Telefono();
                        $tel->cedula = $ci;
                        $tel->principal = $principal;
                        $tel->particular = $particular;
                        $tel->numero = $nro;
                        $tel->celular = $cel;
                        $tel->save();
                    }catch(Exception $e){
                        throw new Exception($e->getMessage());
                    }
                }else{
                    log::info('el nro no cumple con el formato correcto');
                }
            }else{
                log::info('el nro no cumple con el formato correcto');
            }
        }else{
            log::info('El nro ya fue registrado para este cliente');
        }
    }
}
