<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Events\BeforeSheet;

class ReadyStock implements FromCollection, WithHeadings,  WithColumnFormatting, ShouldAutoSize
{
    private $data;
    private $request;
    private $nama_files;

    /**
    * @return \Illuminate\Support\Collection
    */
    public function __construct($data, $request, $nama_file)
    {
        $this->data = $data;
        $this->request = $request;
        $nama_files = $nama_file;
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
            'Part_Number',
            'Description',
            'HET'
        ];


        return $header;
    }

    public function columnFormats(): array {
        return [
            'A' => NumberFormat::FORMAT_GENERAL,
            'B' => NumberFormat::FORMAT_TEXT,
            'C' => NumberFormat::FORMAT_TEXT,
            'D' => NumberFormat::FORMAT_GENERAL
        ];
    }

    public function registerEvents(): array
    {
        // Append a row after the export has been completed
        return [
            AfterSheet::class => function (BeforeSheet $event) {
                $event->sheet->appendRow(['Nama File : ', $this->nama_files]);
                $event->sheet->appendRow(['Tanggal : ', date('Y-m-d His')]);
                $event->sheet->appendRow(['']);
                $event->sheet->appendRow(['']);
            },
        ];
    }
}
