<?php

namespace ZATCA\Tags;

use ZATCA\Tag;

class InvoiceTotalAmount extends Tag
{
    public function __construct($value)
    {
        parent::__construct(4, $value);
    }
}


