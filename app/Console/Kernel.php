<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Cliente;
use App\Console\Commands\EnviarMensajes;
use App\Console\Commands\EnviarMensajeInterno;
use App\Console\Commands\PoblarDatos;
use App\Console\Commands\PoblarTelefonosyMails;
use App\Console\Commands\SendEmailsSolicitudPagaré;
use App\Console\Commands\ValidarMensajesCommand;
use App\Console\Commands\VerificarEstadoFE;
use App\Console\Commands\MakeArchivoFactura;
use App\Console\Commands\GenerarNotaCreditoDE;
use App\Console\Commands\VeridicarEstadoNE;
use App\Console\Commands\EnvioMensajeCRM;
use App\Console\Commands\ReporteGestionesVinanzas;
use App\Console\Commands\VerificarMensajesCRM;
use App\Console\Commands\InsertarGestionesRuralCobranzas;
use App\Console\Commands\InsertDatosClientesAlarmasPy; //ACTIVO
use App\Console\Commands\InsertDatosCobrosAlarmasPY; //PENDIENTE
use App\Console\Commands\InsertGestionesAlarmasPY; //ACTIVO
use App\Console\Commands\GenerarArchivosAlarmasPY; //ACTIVO
//use App\Console\Commands\ReportesGestionesAlarmasPY; //ACTIVO
use App\Models\ConfiguracionMensajes as ModelsConfiguracionMensajes;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        EnviarMensajes::class,
        PoblarDatos::class,
        SendEmailsSolicitudPagaré::class,
        PoblarTelefonosyMails::class,
        EnviarMensajeInterno::class,
        ValidarMensajesCommand::class,
        VerificarEstadoFE::class,
        MakeArchivoFactura::class,
        GenerarNotaCreditoDE::class,
        VeridicarEstadoNE::class,
        EnvioMensajeCRM::class,
        VerificarMensajesCRM::class,
        ReporteGestionesVinanzas::class,
        InsertarGestionesRuralCobranzas::class,
        InsertDatosClientesAlarmasPy::class,
        InsertDatosCobrosAlarmasPY::class,
        InsertGestionesAlarmasPY::class,
        GenerarArchivosAlarmasPY::class,
  ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {

        //MENSAJERIA POR LOTE
        $date = new Carbon('today');

        if($date->dayName != 'domingo'){
            $hora= Carbon::now()->toTimeString();
            if($date->dayName != 'sábado'){
                if ($hora >='08:00:00' && $hora <= '18:00:00') {
                    $schedule->command('enviar:mensajes')
                    ->everyTenMinutes();
                }
            }else{
                if ($hora >='08:00:00' && $hora <= '12:00:00') {
    	           $schedule->command('enviar:mensajes')
                   ->everyTenMinutes();
                }
            }
        }

        //MENSAJERIA INTERNA
	//CHATBOT Y AUTOGESTOR
       	 $schedule->command('enviar:gw')->everyTwoHours();

        //VALIDARMENSAJESCOMANDO - FORZOSO

	$schedule->command('validar:mensajes')->hourly();

        //SOLICITUD DE DOCUMENTOS POR CORREO

        $schedule->command('enviar:correos')
        ->weekly()
        ->tuesdays()
        ->at('08:00');

        //GENERAR TXT FACTURAS
        $schedule->command('factura:txt')
        ->daily()
        ->at('08:30');

        $schedule->command('factura:txt')
        ->daily()
        ->at('12:00');

        //GENERAR NOTA CREDITO

        $schedule->command('notacredito:txt')->hourly();

        //VERIFICAR ESTADOS DE FACTURAS Y NOTAS
        $schedule->command('verificar:fe')->everyTenMinutes();
        $schedule->command('verificar:ne')->hourly();

        // POBLAR TABLA DE DATOS PERSONALES, LABORALES, MAILS, TELEFONOS - DOMINGOS
        if($date->dayName == 'domingo'){
            $schedule->command('poblar:telymail')->everyTwoMinutes();
            $schedule->command('poblar:datoscliente')->everyTwoMinutes();
        }

        //VER CONFIGURACION Y MANDAR MENSAJES POR BULK
        $configuracion = ModelsConfiguracionMensajes::first();
       	if ($configuracion->envio_bulk && $configuracion->contador < 50000)
        	$schedule->command('crm:envio')->everyMinute();

        //VERIFICAR MENSAJES CRM BULK
        $schedule->command('verificar:smscrm')->everyFifteenMinutes();

        //GENERAR ARCHIVO DE GESTIONES VINANZAS
        $schedule->command('gestiones:vinanzas')->daily()->at('19:30');


        //INSERTAR GESTIONES RURAL COBRANZAS
        $schedule->command('insertar:gestionRC')->hourly(); //RURAL COBRANZAS



        /////////////////////////////////////////SERVICIOS////////////////////////////////////////////////

        ////////////////////
        ////ALARMASPY///////
        ////////////////////

        //CONSUMO DE API DE CLIENTE E INSERCION DE ASIGNACION EN BASE SIGESA
	$schedule->command('clientes:alarmas')->daily()->at('7:00');
	$schedule->command('cobros:alarmas')->daily()->at('7:30'); //ALARMASPY
        //GENERACION DE ARCHIVOS DE BASE PARA SERVICIOS
  	$schedule->command('generar:alarmaspy')->daily()->at('7:50'); //ALARMASPY
        //INSERTAR GESTIONES CLIENTES EN BASE SIGESA DESDE NEOTEL
    	$schedule->command('insertargestiones:alarmaspy')->daily()->at('19:30');
  


  }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}


//limpiar cache
// php artisan config:cache
// php artisan config:clear
// php artisan cache:clear

//restart cron
// sudo systemctl start crond.service
