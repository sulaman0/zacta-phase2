<?php

namespace ZATCA\Tags;

use ZATCA\Tag;

class Seller extends Tag
{
    public function __construct($value)
    {
        parent::__construct(1, $value);
    }
}

