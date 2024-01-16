<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
Use App\Exports\GenerarBaseClientesCPHExport;
Use App\Exports\GenerarBaseCobrosCPH;

class GenerarArchivosCPH extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generar:cph';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera archivos de las bases de CPH';

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
        $fecha = Carbon::now()->format('Ymd');

        //Excel::store(new GenerarBaseClientesAlarmaPYExport(),  'baseclientesalarmaslocal'.$fecha.'.csv', 's9');
        Excel::store(new GenerarBaseClientesCPHExport(),  'baseclientesCPH'.$fecha.'.xlsx', 's7');
        Excel::store(new GenerarBaseCobrosCPH(),  'basecobrosCPH'.$fecha.'.xlsx', 's7');
    }
}
