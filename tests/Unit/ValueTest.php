<?php

namespace Test\Unit;

use Lubart\Discounts\Exception\NotAllowedValueException;
use Lubart\Discounts\Value\Discount;
use Lubart\Discounts\Value\NumericID;
use Lubart\Discounts\Value\Quantity;
use PHPUnit\Framework\TestCase;

class ValueTest extends TestCase {

    /** @test - build quantity */
    function build_quantity() {
        $quantity = new Quantity(5);
        $this->assertEquals(5, $quantity->value);
    }

    /** @test - build zero quantity */
    function build_zero_quantity() {
        $quantity = new Quantity(0);
        $this->assertEquals(0, $quantity->value);
    }

    /** @test - get an exception on build negative quantity */
    function get_an_exception_on_build_negative_quantity() {
        $this->expectException(NotAllowedValueException::class);

        new Quantity(-5);
    }

    /** @test - build positive int number */
    function build_positive_id() {
        $id = new NumericID(5);
        $this->assertEquals(5, $id->value);
    }

    /** @test - get an exception on build zero value for positive number */
    function get_an_exception_on_build_zero_value_for_id() {
        $this->expectException(NotAllowedValueException::class);

        new NumericID(0);
    }

    /** @test - get an exception on apply negative value for positive number */
    function get_an_exception_on_apply_negative_value_for_id() {
        $this->expectException(NotAllowedValueException::class);

        new NumericID(-5);
    }

    /** @test - build discount value */
    function build_discount_value() {
        $discount = new Discount(0.5);
        $this->assertEquals(0.5, $discount->value);
    }

    /** @test - get exception if discount value bigger than one */
    function get_exception_if_discount_value_bigger_than_one() {
        $this->expectException(NotAllowedValueException::class);

        new Discount(1.1);
    }

    /** @test - get exception if discount value smaller zero */
    function get_exception_if_discount_value_smaller_zero() {
        $this->expectException(NotAllowedValueException::class);

        new Discount(-0.5);
    }

    /** @test - get discount with percent value */
    function get_discount_with_percent_value() {
        $discount = Discount::percents(50);
        $this->assertEquals(0.5, $discount->value);
    }

}