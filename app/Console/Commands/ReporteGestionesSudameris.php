<?php

namespace App\Console\Commands;

use App\Exports\ReporteGestionesSudamerisExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\GestionesSudameris;

class ReporteGestionesSudameris extends Command
{
    protected $signature = 'gestiones:sudameris {inicio?} {fin?}';

    protected $description = 'Comando para la generación del reporte de gestiones diarias de Sudameris';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        log::info('INICIA REPORTE DE GESTION DIARIA SUDAMERIS');

        // Obtén las fechas de inicio y fin desde los argumentos o pide la entrada del usuario si no se proporcionan
        $inicio = $this->argument('inicio') ?: $this->ask('Ingrese la fecha de inicio (formato: YYYY-MM-DD):');
        $fin = $this->argument('fin') ?: $this->ask('Ingrese la fecha de fin (formato: YYYY-MM-DD):');

        // Valida que las fechas sean válidas
        try {
            $fechaInicio = Carbon::parse($inicio);
            $fechaFin = Carbon::parse($fin);
        } catch (\Exception $e) {
            $this->error('Error al analizar las fechas. Asegúrese de ingresar fechas válidas en formato YYYY-MM-DD.');
            return;
        }

        $registros = GestionesSudameris::whereBetween('fecha_hora', [$fechaInicio, $fechaFin])->get();

        // Verifica si hay registros antes de proceder con la exportación
        if ($registros->count() > 0) {
            // Genera y almacena el archivo TXT de forma asíncrona
            $nombreArchivo = 'GESTIONES_' . $fechaInicio->format('Ymd') . '_' . $fechaFin->format('Ymd') . '.txt';
            Excel::store(new ReporteGestionesSudamerisExport($registros), $nombreArchivo, 's7', \Maatwebsite\Excel\Excel::CSV);

            // Mueve el archivo después de almacenarlo
            //$this->moverArchivo($nombreArchivo);
        } else {
            $this->info('No hay registros para el rango de fechas especificado.');
        }
    }

    private function moverArchivo($nombreArchivo)
    {
        log::info('Mueve el archivo');
        $origen = 's7'; // Asegúrate de que este sea el disco correcto según tu configuración
        $destino = 's10'; // Asegúrate de que este sea el disco correcto según tu configuración

        Storage::disk($destino)->put($nombreArchivo, Storage::disk($origen)->get($nombreArchivo));
    }
}
