<?php

namespace Lubart\Discounts\Value;

use Lubart\Discounts\Exception\NotAllowedValueException;

final class Discount {

    public function __construct(public readonly float $value)
    {
        if($value < 0){
            throw new NotAllowedValueException("Discount cannot be lower 0");
        }

        if($value > 1){
            throw new NotAllowedValueException("Discount cannot be higher 100%");
        }
    }

    public static function percents(float $discountInPercents): self
    {
        return new self($discountInPercents / 100);
    }
}