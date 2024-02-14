<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Exports\GenerarBaseClientesNeotelExport;

class ExportBaseClientesNeotel extends Command
{
    protected $signature = 'export:clientes-neotel';

    protected $description = 'Exportar base de clientes de Neotel';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $exportador = new GenerarBaseClientesNeotelExport();
        $exportador->export();
        $this->info('Exportación de base de clientes de Neotel completada.');
    }
}
