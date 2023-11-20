<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class ReadyStock implements FromCollection, WithHeadings,  WithColumnFormatting, ShouldAutoSize
{
    private $data;
    private $request;

    /**
    * @return \Illuminate\Support\Collection
    */
    public function __construct($data, $request)
    {
        $this->data = $data;
        $this->request = $request;
    }

    public function collection(){
        ini_set('memory_limit', '4096M');
        ini_set('max_execution_time', '0');

        $data = $this->data;

        return collect($data);
    }


    public function headings(): array
    {
        $header =  [
            'No',
            'Part Number',
            'Nama Part',
            'Class Produk',
            'Produk',
            'Sub Produk',
            'FRG',
            'HET'
        ];


        return $header;
    }

    public function columnFormats(): array {
        return [
            'A' => NumberFormat::FORMAT_GENERAL,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_TEXT,
            'E' => NumberFormat::FORMAT_TEXT,
            'F' => NumberFormat::FORMAT_TEXT,
            'G' => NumberFormat::FORMAT_TEXT,
            'H' => NumberFormat::FORMAT_GENERAL,
        ];
    }
}
