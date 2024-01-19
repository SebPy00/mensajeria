<?php

namespace App\Exports;

use App\Models\GestionesSudameris;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class ReporteGestionesSudamerisExport implements FromCollection, WithHeadings, WithMapping, WithCustomCsvSettings
{
    protected $registros;

    public function __construct($registros)
    {
        $this->registros = $registros;
    }

    public function collection()
    {
        log::info('INICIA REPORTE DE GESTION DIARIA SUDAMERIS');

        $gestiones = $this->registros;

        $lista = [];

        if (isset($gestiones->Table) && is_array($gestiones->Table)) {
            log::info('Existen gestiones en table');
            foreach ($gestiones->Table as $elemento) {
                $fgestion = new Carbon($elemento->FECHA_GESTION);
                $freagenda = new Carbon($elemento->FECHA_REAGENDADA);

                $fila = [
                    'base' => $elemento->BASE,
                    'id_contacto' => $elemento->ID_CONTACTO,
                    'doc' => $elemento->DOC,
                    'cod_cliente' => $elemento->COD_CLIENTE,
                    'nombre_cliente' => $elemento->NOMBRE_CLIENTE,
                    'categoria' => $elemento->CATEGORIA,
                    'sub_categoria' => $elemento->SUB_CATEGORIA,
                    'id_comentario' => $elemento->ID_COMENTARIO,
                    'comentario' => $elemento->COMENTARIO,
                    'fecha_hora' => $fgestion->toDateTimeString(),
                    'fecha_agenda' => $freagenda->toDateTimeString(),
                    'telefono' => $elemento->TELEFONO,
                    'usuario' => $elemento->USUARIO,
                    'operacion' => $elemento->OPERACION,
                ];

                if ($elemento->RESP_CORTA != 'Cerrado por Proceso') {
                    $lista[] = $fila;
                } else {
                    Log::info('Registro filtrado: ' . json_encode($fila));
                }
            }
        }

        return collect($lista);
    }

    public function headings(): array
    {
        // Define los encabezados de las columnas en el archivo
        return [
            'base',
            'id_contacto',
            'doc',
            'cod_cliente',
            'nombre_cliente',
            'categoria',
            'sub_categoria',
            'id_comentario',
            'comentario',
            'fecha_hora',
            'fecha_agenda',
            'telefono',
            'usuario',
            'operacion',
        ];
    }

    public function map($fila): array
    {
        // Mapea cada valor de la fila para personalizar la salida
        return $fila;
    }

    public function getCsvSettings(): array
    {
        // Configuración personalizada del CSV
        return [
        'file' => 'reporte.txt', // Cambia el nombre del archivo si es necesario
        'type' => 'text/plain',
        'delimiter' => ';',
        'enclosure' => '',
        'line_ending' => "\n",
        ];
    }
}
