<?php

namespace Lubart\Discounts\Value;

use Lubart\Discounts\Exception\NotAllowedValueException;

final class Quantity {

    public function __construct(public readonly int $value)
    {
        if($value < 0){
            throw new NotAllowedValueException("Value cannot be smaller than 0");
        }
    }
}