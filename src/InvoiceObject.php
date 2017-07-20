<?php

namespace BCMH;

class InvoiceObject extends ObjectArrayAccess
{

    protected $container = [];

    function __construct($invoiceObject)
    {
        $address = [];

        if ($invoiceObject->Contact->Addresses) {
            foreach ($invoiceObject->Contact->Addresses as $addr) {
                $address = [
                    "addressline1" => $addr->getAddressLine1(),
                    "addressline2" => $addr->getAddressLine2(),
                    "addressline3" => $addr->getAddressLine3(),
                    "addressline4" => $addr->getAddressLine4(),
                    "city" => $addr->getCity(),
                    "region" => $addr->getRegion(),
                    "postalcode" => $addr->getPostalCode(),
                    "country" => $addr->getCountry()
                ];
            }
        }


        $invoice_items = [];

        if (count($invoiceObject->getLineItems())) {
            foreach ($invoiceObject->getLineItems() as $row) {
                $item = [];

                foreach (array_keys($row->getProperties()) as $prop) {
                    $method = "get" . $prop;
                    $item[strtolower($prop)] = $row->${"method"}();
                }
                $invoice_items[] = $item;
            }
        }

        $this->container = [
            'id' => $invoiceObject->InvoiceID,
            'reference' => $invoiceObject->InvoiceNumber,
            'status' => $invoiceObject->Status,
            'dated_on' => $invoiceObject->Date->format('d-m-Y'),
            'po_reference' => $invoiceObject->Reference,
            'project' => false,
            'comments' => false,
            'invoice_items' => $invoice_items,
            'isListing' => 'isListing',
            'net_value' => 'net_value',
            'total_tax' => $invoiceObject->TotalTax,
            'total_value' =>  $invoiceObject->Total,
            'net_value' => 'net_value',
            'sub_total' => $invoiceObject->SubTotal,
            'sales_tax_value' => $invoiceObject->TotalTax,
            'total_value' => $invoiceObject->Total,
            'contact' => [
                'first_name' => $invoiceObject->Contact->FirstName,
                'last_name' => $invoiceObject->Contact->LastName,
                'organisation_name' => $invoiceObject->Contact->Name,
                'address' => $address
            ]
        ];
    }
}
