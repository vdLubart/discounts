<?php

namespace Lubart\Discounts\Model;

final class Product extends Model
{
    protected array $fields = ['id', 'description', 'category', 'price'];

    protected function category(string $val): int
    {
        return $val;
    }
}