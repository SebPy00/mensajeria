<?php

namespace App\Console\Commands;

Use App\Exports\ReporteGestionesBancopPrestamosExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;


class ReporteGestionesBancopPrestamos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gestiones:bancopPrestamos';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Comando para la generación del reporte de gestiones diarias de Bancop préstamos';

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
        log::info('INICIA REPORTE DE GESTION DIARIA BANCOP PRÉSTAMOS');

        $fecha = Carbon::now()->format('Ymd');

        // Genera y almacena el archivo CSV de forma asíncrona
        Excel::store(new ReporteGestionesBancopPrestamosExport(),  'GESTIONES_'.$fecha.'.csv', 's8');
    }
}
