<?php 

namespace BCMH;

class Company extends ObjectArrayAccess {
    protected $container = [];

    function __construct() {
        $this->container = [
            'name' => 'Bravo. Charlie. Mike. Hotel',
            'address1' => 'Third Floor',
            'address3' => '288 Upper St',
            'city' => 'London',
            'postcode' => 'N1 2TZ',
            'contact_email' => 'accounts@bcmh.co.uk',
            'website' => 'http://bcmh.co.uk',
            'phone_number' => '+44 (0)20 3857 4800'
        ];
    }
}
