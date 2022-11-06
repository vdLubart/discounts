<?php

namespace Lubart\Discounts\Model;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lubart\Discounts\Exception\WrongInputDataException;

final class Order extends Model
{
    protected array $fields = ['id', 'customer-id', 'items', 'total'];

    /**
     * Additional property indicates the total order discount
     *
     * @var float $totalDiscount
     */
    public float $totalDiscount = 0;

    /**
     * @param array $source
     * @return static
     * @throws WrongInputDataException
     * @throws GuzzleException
     */
    public static function buildFromSource(array $source): self
    {
        $instance = new Order(new Client());

        $instance->validateSource($source);
        foreach ($instance->fields as $field){
            $instance->{$field} = $source[$field];
        }
        return $instance;
    }

    /**
     * @param array $source
     * @throws WrongInputDataException
     */
    private function validateSource(array $source): void
    {
        if(!isset($source['id']) or (int)$source['id'] <= 0){
            throw new WrongInputDataException("Wrong input 'id' value", 500);
        }

        if(!isset($source['customer-id']) or (int)$source['customer-id'] <= 0){
            throw new WrongInputDataException("Wrong input 'customer-id' value", 500);
        }

        if(!isset($source['items']) or !is_array($source['items']) or empty($source['items'])){
            throw new WrongInputDataException("Wrong input 'items' value", 500);
        }

        foreach ($source['items'] as $item){
            if(!is_array($item) or empty($item) or
                !isset($item['product-id']) or !is_string($item['product-id']) or empty($item['product-id']) or
                !isset($item['quantity']) or (int)$item['quantity'] <= 0 or
                !isset($item['unit-price']) or (float)$item['unit-price'] <= 0 or
                !isset($item['total']) or (float)$item['total'] <= 0
            ){
                throw new WrongInputDataException("Wrong input 'items' value", 500);
            }
        }

        if(!isset($source['total']) or (float)$source['total'] <= 0){
            throw new WrongInputDataException("Wrong input 'total' value", 500);
        }
    }
}