<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
ini_set('memory_limit', '-1');
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class MoverArchivo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mover:archivo';

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
        $s3 = Storage::disk('s6');
        $local = Storage::disk('s10');
        //$fecha = carbon::now()->toString();
       	$fecha = Carbon::now()->format('dmY');
        $fileName = $fecha . '_GESTIONES.csv';
        $contents = $s3->get($fileName);
        $local->put($fileName, $contents);
    }
}
