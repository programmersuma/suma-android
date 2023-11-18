<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ReadyStock implements FromCollection, WithHeadings, ShouldAutoSize
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
}
