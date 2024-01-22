<?php

namespace App\Exports;

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
        Log::info('Número de registros de gestiones: ' . count($gestiones));
        log::info($gestiones);

        $lista = [];

        foreach ($gestiones as $elemento) {
            $fgestion = new Carbon($elemento['Fecha_x0020__x0026__x0020_Hora']);
            $freagenda = isset($elemento['Fecha_x0020_de_x0020_Agenda']) ? new Carbon($elemento['Fecha_x0020_de_x0020_Agenda']) : null;

            $fila = [
                'base' => $elemento['Bases_x0020_de_x0020_datos'],
                'id_contacto' => $elemento['Id_x0020_contacto'],
                'doc' => $elemento['Nro_x0020_Documento'],
                'cod_cliente' => $elemento['Cod_x0020_Cliente'],
                'nombre_cliente' => $elemento['Nombre_x0020_Cliente'],
                'categoria' => $elemento['Categoria'],
                'sub_categoria' => $elemento['Sub_x0020_Categoria'],
                'id_comentario' => $elemento['IdComentario'],
                'comentario' => $elemento['Comentario'],
                'fecha_hora' => $fgestion->toDateTimeString(),
                'fecha_agenda' => $freagenda ? $freagenda->toDateTimeString() : null,
                'telefono' => $elemento['Teléfono'],
                'usuario' => $elemento['Usuario'],
                'operacion' => $elemento['Operacion'],
            ];

            if ($elemento['Resolución_x0020_Originadora'] != 'Cerrado por Proceso') {
                $lista[] = $fila;
            } else {
                Log::info('Registro filtrado: ' . json_encode($fila));
            }
        }

        return collect($lista);
    }

    public function headings(): array
    {
        return [
            'base',
            'id_contacto',
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
        ];
    }

    public function map($fila): array
    {
        return $fila;
    }

    public function getCsvSettings(): array
    {
        return [
            'file' => 'reporte.txt',
            'type' => 'text/plain',
            'delimiter' => ';',
            'enclosure' => '',
            'line_ending' => "\n",
        ];
    }
}
