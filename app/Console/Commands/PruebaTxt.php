<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Models\Factura;
use Illuminate\Support\Facades\Storage;

class PruebaTxt extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prueba:txt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
        $nro_factura = '001-004-0000001';
        $timbrado = 16139608;

        $factura = Factura::with('cliente')->where('fac', $nro_factura)->where('timb',$timbrado)->get();

        $contador = 1;
        
        $string_cab = '';
        foreach($factura as $f){
            /////////////////////////////////////////CABECERA///////////////////////////////////////////
            if($contador ==  1){
                $mon = 'PYG';
                if($f->mon == 1) $mon = 'USD';
                if($f->cliente->doctip == 1) $documento = $f->cliente->doc;
                if($f->cliente->doctip == 2) $documento = $f->cliente->ruc;

                $cab = [1,'INVOICE',Carbon::parse($f->fec),'ORIGINAL',$f->timb.'-'.trim($f->fac),'','80023587-8','RUC',
                    $mon,'','','','','','','','','','','','','',$documento,'RUC','','80023587-8','RUC'
                ];

                foreach($cab as $c){
                    $string_cab .=  $c . ';';
                }

            }
            
            $contador ++;
        }

        $this->guardarArchivo($string_cab);
    }

    private function guardarArchivo($st){
        Storage::disk('s4')->put('fac.txt', $st );
    }
}
