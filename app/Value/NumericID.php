<?php

namespace Lubart\Discounts\Value;

use Lubart\Discounts\Exception\NotAllowedValueException;

final class NumericID {

    public function __construct(public readonly int $value)
    {
        if($value <= 0){
            throw new NotAllowedValueException("Value cannot be smaller or equal than 0");
        }
    }
}