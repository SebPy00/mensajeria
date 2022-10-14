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
use App\Models\Pagare;
use Carbon\Carbon;

class SolicitudPagaresPendientesExport implements FromCollection, WithHeadings,
WithEvents, ShouldAutoSize, WithStyles
{
    use Exportable;
    private $codEntidad;
    private $tipoDocumento;
    private $doc;
    public function __construct(int $codEntidad, int $tipoDocumento)
    {
        $this->codEntidad=  $codEntidad;
        $this->tipoDocumento = $tipoDocumento;
        log::info('INICIANDO ARCHIVO, entidad: '. $this->codEntidad . ' doc: '.$this->tipoDocumento);
        switch ($this->tipoDocumento){
            case 1:
                $this->doc = 'TARJETAS';
            case 2:
                $this->doc = 'PAGARE';
            case 3:
                $this->doc = 'Ambos';
        }
    }

    public function collection()
    {
        $lista = [];

        //traemos los datos a mostrar
        if($this->codEntidad == 1){ //tambien registrado en el sistema como codemp 0, queremos mostrar en el mismo registro
            if($this->doc == 'Ambos'){
                
                $data = DB::select("SELECT c.mescom, ip.ope, ip.nroci, ip.cliente, ip.estado
                FROM view_inventario_pagare ip join car c on ip.car= c.car where estado = 'PENDIENTE' and (c.codemp = (:a) or  c.codemp = 0) order by c.feccom",
                ['a' => $this->codEntidad]);

                //ACTUALIZAR PAGARÉS 
                log::info('ACTUALIZANDO PAGARE');
                DB::select("UPDATE pagare SET cant_solicitudes = cant_solicitudes + 1, fecha_ult_solicitud = (:a)
                where ope in (SELECT ip.ope FROM view_inventario_pagare ip join car c on ip.car= c.car 
                where estado = 'PENDIENTE' and (c.codemp = (:b) or  c.codemp = 0))",
                ['a'=> Carbon::now()->toDateString(),'b' => $this->codEntidad]);

            }else{

                $data = DB::select("SELECT c.mescom, ip.ope, ip.nroci, ip.cliente, ip.estado
                FROM view_inventario_pagare ip join car c on ip.car= c.car where estado = 'PENDIENTE'
                and (c.codemp = (:a) or  c.codemp = 0) and ip.tipodocumento = (:b) order by c.feccom",
                ['a' => $this->codEntidad, 'b' => $this->doc]);

                //ACTUALIZAR PAGARÉS
                log::info('ACTUALIZANDO PAGARE');
                DB::select("UPDATE pagare SET cant_solicitudes = cant_solicitudes + 1, fecha_ult_solicitud = (:a)
                where ope in (SELECT  ip.ope FROM view_inventario_pagare ip join car c on ip.car= c.car 
                where estado = 'PENDIENTE' and (c.codemp = (:b) or  c.codemp = 0) and ip.tipodocumento = (:c))",
                ['a'=> Carbon::now()->toDateString(),'b' => $this->codEntidad, 'c' => $this->doc]);

            }
        }elseif($this->codEntidad == 8){ //ITAU - ENVIAR SOLO 50 REGISTROS

            if($this->tipoDocumento == 3){

                log::info('ENTIDAD: ITAU');
                $data = DB::select("SELECT c.mescom, ip.ope, ip.nroci, ip.cliente, ip.estado
                FROM view_inventario_pagare ip join car c on ip.car= c.car where estado = 'PENDIENTE' and c.codemp = (:a) order by c.feccom limit 50",
                ['a' => $this->codEntidad]);

                //ACTUALIZAR PAGARÉS
                log::info('ACTUALIZANDO PAGARE');
                DB::select("UPDATE pagare SET cant_solicitudes = cant_solicitudes + 1, fecha_ult_solicitud = (:a)
                where ope in (SELECT ip.ope FROM view_inventario_pagare ip join car c on ip.car= c.car
                where estado = 'PENDIENTE' and c.codemp = (:b) order by c.feccom limit 50)",
                ['a'=> Carbon::now()->toDateString(),'b' => $this->codEntidad]);

            }else{

                $data = DB::select("SELECT c.mescom, ip.ope, ip.nroci, ip.cliente, ip.estado
                FROM view_inventario_pagare ip join car c on ip.car= c.car where estado = 'PENDIENTE' and c.codemp = (:a)
                and ip.tipodocumento = (:b) order by c.feccom limit 50",
                ['a' => $this->codEntidad, 'b' => $this->doc]);

                //ACTUALIZAR PAGARÉS
                log::info('ACTUALIZANDO PAGARE');
                DB::select("UPDATE pagare SET cant_solicitudes = cant_solicitudes + 1, fecha_ult_solicitud = (:a)
                where ope in (SELECT ip.ope FROM view_inventario_pagare ip join car c on ip.car= c.car
                where estado = 'PENDIENTE' and c.codemp = (:b) and ip.tipodocumento = (:c) order by c.feccom limit 50)",
                ['a'=> Carbon::now()->toDateString(),'b' => $this->codEntidad, 'c' => $this->doc]);
            }
        }else{

            if($this->tipoDocumento == 3){

                $data = DB::select("SELECT c.mescom, ip.ope, ip.nroci, ip.cliente, ip.estado
                FROM view_inventario_pagare ip join car c on ip.car= c.car where estado = 'PENDIENTE' 
                and c.codemp = (:a) order by c.feccom", ['a' => $this->codEntidad]);
                
                //ACTUALIZAR PAGARÉS
                log::info('ACTUALIZANDO PAGARE');
                DB::select("UPDATE pagare SET cant_solicitudes = cant_solicitudes + 1, fecha_ult_solicitud = (:a)
                where ope in (SELECT ip.ope FROM view_inventario_pagare ip join car c on ip.car= c.car
                where estado = 'PENDIENTE' and c.codemp = (:b))",
                ['a'=> Carbon::now()->toDateString(),'b' => $this->codEntidad]);

            }else{

                $data = DB::select("SELECT c.mescom, ip.ope, ip.nroci, ip.cliente, ip.estado
                FROM view_inventario_pagare ip join car c on ip.car= c.car where estado = 'PENDIENTE' 
                and c.codemp = (:a) and ip.tipodocumento = (:b) order by c.feccom",
                ['a' => $this->codEntidad, 'b' => $this->doc]);

                //ACTUALIZAR PAGARÉS
                log::info('ACTUALIZANDO PAGARE');
                DB::select("UPDATE pagare SET cant_solicitudes = cant_solicitudes + 1, fecha_ult_solicitud = (:a)
                where ope in (SELECT ip.ope FROM view_inventario_pagare ip join car c on ip.car= c.car
                where estado = 'PENDIENTE' and c.codemp = (:b) and ip.tipodocumento = (:c))",
                ['a'=> Carbon::now()->toDateString(),'b' => $this->codEntidad, 'c' => $this->doc]);
            }
        }
        
        if($data){
            foreach ($data as $d){
                $fila = [
                    'mescompra'=>$d->mescom,
                    'ope'=>$d->ope,
                    'ci'=>$d->nroci,
                    'cliente'=>utf8_encode($d->cliente),
                    'estado'=>$d->estado,
                ];
                $lista[]= $fila;

                //agregar cambios en pagaré - MUY LENTO
                // $p = Pagare::where('ope', $d->ope)->first();
                // log::info($p->regnro);
                // $p->cant_solicitudes += 1;
                // $p->fecha_ult_solicitud = Carbon::now()->toDateString();         
                // $p->save();

            
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
