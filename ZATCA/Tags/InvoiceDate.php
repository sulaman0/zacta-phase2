<?php

namespace ZATCA\Tags;

use ZATCA\Tag;

class InvoiceDate extends Tag
{
    public function __construct($value)
    {
        parent::__construct(3, $value);
    }
}

