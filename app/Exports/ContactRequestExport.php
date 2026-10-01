<?php

namespace App\Exports;

use Maatwebsite\Excel\Excel;
use App\Models\Frontend\ContactRequest;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ContactRequestExport implements FromCollection, WithHeadings, WithMapping
{

    /**
    * Optional Writer Type
    */
    private $writerType = Excel::XLSX;
    
    /**
    * Optional headers
    */
    private $headers = [
        'Content-Type' => 'text/csv',
    ];

    public function headings(): array
    {
        return [
            '#',
            'Name',
            'Email',
            'Message',
            'Created At',
        ];
    }

    /**
    * @param mixed $contact
    */
    public function map($contact): array
    {
        return [
            $contact->id,
            $contact->name,
            $contact->email,
            $contact->message,
            Carbon::parse($contact->created_at)->format("d-m-Y H:i A")
        ];
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return ContactRequest::get(['id','name','email','message','created_at']);
    }
}