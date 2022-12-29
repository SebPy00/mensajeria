<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use App\Models\Cliente;

class Kernel extends ConsoleKernel
{
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
            if ($hora >='07:00:00' && $hora <= '19:00:00') {
                $schedule->command('enviar:mensajes')
                ->everyMinute();
            }
        }

        $schedule->command('enviar:correos')
        ->weekly()
        ->tuesdays()
        ->at('08:00');

        $dm = Cliente::where('datos_migrados', false)->first();
        if($dm){
           // log::info('ATENCIÓN! POBLANDO TABLAS DE DATOS DE CLIENTES');
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
