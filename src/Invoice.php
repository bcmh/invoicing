<?php

namespace BCMH;

use CloudManaged\FreeAgent\FreeAgent;
use CloudManaged\FreeAgent\Contact;
use CloudManaged\FreeAgent\Project;

class Invoice
{

    public $invoice;
    public $freeagent;

    public function __construct(FreeAgent $freeagent, $invoiceId)
    {

        $this->freeagent = $freeagent;
        $this->invoice = (new \CloudManaged\FreeAgent\Invoice($freeagent))->getASingleInvoice($invoiceId);

        $contactUrl = explode('/', $this->invoice['contact']);

        $this->invoice['invoice_items'] = array_map(function($row) {

            $tax_rate = ($row['sales_tax_rate'] / 100);

            $row['tax'] = ((float)$row['price'] * (float)$row['quantity']) * $tax_rate;
            return $row;
        }, $this->invoice['invoice_items']);

        $this->invoice['contact'] = (new Contact($freeagent))->getASingleContact(array_pop($contactUrl));
        $this->invoice['project'] = $this->getProject();
    }

    public function isList($items) {
        $is_listing = true;

        foreach ($items as $i) {
            if (isset($i['item_type'])) {
                $is_listing = false;
            }
        }

        return $is_listing;
    }

    public function getProject() {

        if (!isset($this->invoice['project'])) {
            return false;
        }

        $projectUrl = explode('/', $this->invoice['project']);
        return (new Project($this->freeagent))->getASingleProject(array_pop($projectUrl));
    }

    public function data() {

        // Prepare data
        $this->invoice['isListing'] = $this->isList($this->invoice['invoice_items']);

        return $this->invoice;
    }
}