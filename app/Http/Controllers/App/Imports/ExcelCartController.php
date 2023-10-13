<?php

namespace App\Http\Controllers\App\Imports;

use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ExcelCartController implements ToModel, WithHeadingRow
{
    /**
     * @param array $row
     *
     * @return User|null
     */
    public function model(array $row)
    {
        return ([
           'part_number'    => trim($row['part_number']),
           'order'          => trim($row['order']),
        ]);
    }
}
