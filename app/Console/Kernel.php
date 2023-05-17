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


class Kernel extends ConsoleKernel
{

    protected $commands = [
        EnviarMensajes::class,
        PoblarDatos::class,
        SendEmailsSolicitudPagaré::class,
        PoblarTelefonosyMails::class,
        EnviarMensajeInterno::class,
        ValidarMensajesCommand::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $date = new Carbon('today');

        if($date->dayName != 'domingo'){
            $hora= Carbon::now()->toTimeString(); 
            if($date->dayName != 'sábado'){
                if ($hora >='08:00:00' && $hora <= '18:00:00') {
                    $schedule->command('enviar:mensajes')
                    ->everyFiveMinutes();
                }
            }else{
                if ($hora >='08:00:00' && $hora <= '12:00:00') {
                    $schedule->command('enviar:mensajes')
                    ->everyFiveMinutes();
                }
            }
        }

        //MENSAJERIA INTERNA

        $schedule->command('enviar:gw')->everyTwoHours();

        //VALIDARMENSAJESCOMANDO - FORZOSO

        $schedule->command('validar:mensajes')->hourly();

        //SOLICITUD DE DOCUMENTOS POR CORREO

        $schedule->command('enviar:correos')
        ->weekly()
        ->tuesdays()
        ->at('08:00');

        // POBLAR TABLA DE DATOS PERSONALES, LABORALES, MAILS, TELEFONOS - DOMINGOS
        if($date->dayName == 'domingo'){
            $schedule->command('poblar:telymail')->everyTwoMinutes();       
            $schedule->command('poblar:datoscliente')->everyTwoMinutes();
        }
        
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