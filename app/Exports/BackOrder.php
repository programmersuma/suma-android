<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Maatwebsite\Excel\Events\BeforeSheet;
use Maatwebsite\Excel\Events\AfterSheet;
use Maatwebsite\Excel\Concerns\WithMapping;

class BackOrder implements FromCollection, WithHeadings,  WithColumnFormatting, ShouldAutoSize
{
    private $data;
    private $request;
    private $file_name;

    /**
    * @return \Illuminate\Support\Collection
    */
    public function __construct($data, $request, $nama_file)
    {
        $this->data = $data;
        $this->request = $request;
        $this->file_name = $nama_file;
    }

    public function collection(){
        ini_set('memory_limit', '4096M');
        ini_set('max_execution_time', '0');

        $collection = collect($this->data);

        $headerRow = [
            'NO',
            'SALESMAN',
            'DEALER',
            'PART_NUMBER',
            'DESCRIPTION',
            'KETERANGAN',
            'JML_BO',
        ];

        $data = $collection->prepend($headerRow);
        return $data;
    }


    public function headings(): array
    {
        $header[] = [
            'Nama File :',
            $this->file_name,
        ];
        $header[] = [
            'Tanggal :',
            date('Y-m-d h:i:s'),
        ];
        $header[] = [
            ' ',
            ' ',
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
            'G' => NumberFormat::FORMAT_GENERAL
        ];
    }
}
