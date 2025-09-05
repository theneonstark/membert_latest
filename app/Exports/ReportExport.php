<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class ReportExport implements FromCollection
{
    /**
    * @return \Illuminate\Support\Collection
    */
    
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }
    
    public function collection()
    {
        return new Collection($this->data);
    }
}
