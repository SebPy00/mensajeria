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


class Kernel extends ConsoleKernel
{

    protected $commands = [
        EnviarMensajes::class,
        PoblarDatos::class,
        SendEmailsSolicitudPagaré::class,
        PoblarTelefonosyMails::class,
        EnviarMensajeInterno::class
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
                    ->everyMinute();
                    $schedule->command('enviar:gw')
                    ->everyTenMinutes();
                }
            }else{
                if ($hora >='08:00:00' && $hora <= '12:00:00') {
                    $schedule->command('enviar:mensajes')
                    ->everyMinute();
                    $schedule->command('enviar:gw')
                    ->everyTenMinutes();
                }
            }
        }

        $schedule->command('enviar:correos')
        ->weekly()
        ->tuesdays()
        ->at('08:00');

        //$schedule->command('poblar:telymail')->everyFiveMinutes();
        // $dm = Cliente::where('datos_migrados', false)->first();
        // if($dm){
        //    // log::info('ATENCIÓN! POBLANDO TABLAS DE DATOS DE CLIENTES');
        //     $schedule->command('poblar:datoscliente')->everyTwoMinutes();
        // }
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