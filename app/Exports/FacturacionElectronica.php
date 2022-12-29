<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithEvents;
use Illuminate\Support\Facades\Log;
use App\Models\Factura;
use Carbon\Carbon;
ini_set('memory_limit', '-1');

class FacturacionElectronica implements FromCollection, WithHeadings,
WithEvents, ShouldAutoSize, WithStyles
{
    /**
    * @return \Illuminate\Support\Collection
    */
    use Exportable;
    // private $codEntidad;
    // private $tipoDocumento;
    // private $doc;

    // public function __construct(int $codEntidad, int $tipoDocumento)
    // {
    //     $this->codEntidad=  $codEntidad;
    //     $this->tipoDocumento = $tipoDocumento;
    //     log::info($this->doc);
    // }

    public function collection()
    {
        $lista = [];

        $data = Factura::where('');
        
        if($data){
            foreach ($data as $d){
                $fila = [
                    'mescompra'=>$d->mescom,
                    'ope'=>$d->nro_operacion_pagare,
                    'ci'=>$d->nroci,
                    'cliente'=>utf8_encode($d->cliente),
                    'estado'=>$d->estado,
                ];
                $lista[]= $fila;
            }
        }

        return collect($lista);
    }
    public function headings(): array
    {
        return [
            'Fecha Compra',
            'Nro. Operación',
            'Nro. CI',
            'Cliente',
            'Estado de Pagaré',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class    => function(AfterSheet $event) {
                
                $event->sheet->getDelegate()->getStyle('A:E')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

            },
        ];
    }
}
