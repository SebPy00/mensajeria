<?php

namespace App\Console\Commands;

use App\Exports\ReporteGestionesVinanzasExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ReporteGestionesVinanzas extends Command
{
    protected $signature = 'gestiones:vinanzas';

    protected $description = 'Comando para la generación del reporte de gestiones diarias de Vinanzas';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        log::info('INICIA REPORTE DE GESTION DIARIA VINANZAS');

        $fecha = Carbon::now()->format('dmY');

        // Genera y almacena el archivo CSV de forma asíncrona
         Excel::store(new ReporteGestionesVinanzasExport(), $fecha .'_GESTIONES'. '.csv', 's7');
	    // Excel::store(new ReporteGestionesVinanzasExport(), $fecha .'_GESTIONES'. '.csv', 's10');

        // Mueve el archivo después de almacenarlo
        //$this->moverArchivo($fecha);
    }

    private function moverArchivo($fecha)
    {
        log::info('Mueve el archivo');
        $nombreArchivo = $fecha . '_GESTIONES.csv';
        $origen = 's7';
        $destino = 's10';

        Storage::disk($destino)->put($nombreArchivo, Storage::disk($origen)->get($nombreArchivo));
    }
}
