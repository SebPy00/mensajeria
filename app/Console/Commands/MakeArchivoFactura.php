<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MakeArchivoFactura extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'factura:txt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generar archivo txt de la factura emitida para el cliente';

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
        return 0;
    }
}
