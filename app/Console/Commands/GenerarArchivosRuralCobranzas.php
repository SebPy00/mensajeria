<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
Use App\Exports\GenerarBaseClientesRuralCobranzasExport;
use App\Exports\GenerarBaseCobrosRuralCobranzas;

class GenerarArchivosRuralCobranzas extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generar:ruralcobranzas';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera archivos de las bases de rural cobranzas';

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
        Excel::store(new GenerarBaseClientesRuralCobranzasExport(),  'baseclientes'.$fecha.'.xlsx', 's9');
        Excel::store(new GenerarBaseCobrosRuralCobranzas(),  'basecobros'.$fecha.'.xlsx', 's9');
    }
}
