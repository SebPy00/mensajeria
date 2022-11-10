<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;

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
        $hora= Carbon::now()->toTimeString(); 
        if ($hora >='07:00:00' && $hora <= '19:00:00') {
            $schedule->command('enviar:mensajes')
            ->everyMinute();
        }
        
        $schedule->command('enviar:correos')
            ->weekly()
            ->tuesdays()
            ->at('08:00');
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
