<?php

namespace App\Console\Commands;

use App\Exports\ReporteGestionAlarmasPYExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ReportesGestionesAlarmasPY extends Command
{
    protected $signature = 'reportegestiones:alarmaspy';

    protected $description = 'Comando para la generación del reporte de gestiones diarias de alarmaspy';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        log::info('INICIA REPORTE DE GESTION DIARIA ALARMASPY');

        $fecha = Carbon::now()->format('dmY');

        // Genera y almacena el archivo CSV de forma asíncrona
        Excel::store(new ReporteGestionAlarmasPYExport(), 'gestionalarma'.$fecha. '.csv', 's9');
        Excel::store(new ReporteGestionAlarmasPYExport(), 'gestionalarma'.$fecha.'.xlsx', 's9');

    }

}
