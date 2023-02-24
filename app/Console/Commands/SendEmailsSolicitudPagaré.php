<?php

namespace App\Console\Commands;

use App\Exports\SolicitudPagaresPendientesExport;
use App\Models\FrecuenciaEnvioCorreo;
use App\Models\EmpresaMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Mail;
use App\Mail\SolicitudPagareMail;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SendEmailsSolicitudPagaré extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'enviar:correos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Envio programado de mails para solicitud de pagarés pendientes por entidad';

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
        log::info('INICIO DE ENVÍO DE MAILS');

        $enviarCorreo = DB::Select(
            "SELECT 
                fe.id,
                c.codemp, 
                c.nomcodemp, 
                f.descripcion,
                f.dias,
                (date(now()) - date(fe.fecha_ult_correo)) as diasTranscurridos
            from frecuencia_envio_empresa fe join codemp c on c.codemp = fe.codemp
            join frecuencia f on f.id = fe.idfrecuencia
            where (date(now()) - date(fe.fecha_ult_correo)) >= f.dias
            order by fe.id asc") ;
        
        if($enviarCorreo){
           
            foreach($enviarCorreo as $enviar ){
                
                $mails = EmpresaMail::with('tipo')->where('codemp', $enviar->codemp)->get();
                
                if($mails){
                    
                    foreach($mails as $mail){
                        
                        $this->guardarReporte($enviar->codemp, $enviar->nomcodemp, $mail->idtipo, $mail->tipo->descripcion );
                        Mail::to($mail->email)->send(new SolicitudPagareMail
                        ($enviar->nomcodemp, 'SolicitudPagaresPendientes_'. $enviar->nomcodemp .'_'.$mail->tipo->descripcion . '.xlsx'));
                    
                    }
                }

                $frecuencia = FrecuenciaEnvioCorreo::where('id', $enviar->id)->first();
                $frecuencia->fecha_ult_correo = Carbon::now()->toDateTimeString();
                $frecuencia->save();
            }
        }
        return 0;
    }

    public function guardarReporte($codEntidad, $nomEntidad, $tipoDocumento, $descripcion) {

        log::info('tipodoc: '.$tipoDocumento);
        return Excel::store(new SolicitudPagaresPendientesExport($codEntidad, $tipoDocumento),
            'SolicitudPagaresPendientes_'. $nomEntidad .'_'.$descripcion. '.xlsx', 's4'
        );

    }
}
