<?php

namespace Lubart\Discounts\Controller;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Lubart\Discounts\Exception\EntityNotAvailableException;
use Lubart\Discounts\Exception\MethodNotExistException;
use Lubart\Discounts\Exception\WrongActionCallException;
use Lubart\Discounts\Exception\WrongInputDataException;
use Lubart\Discounts\Model\Customer;
use Lubart\Discounts\Model\Order;
use Lubart\Discounts\Model\Product;
use Lubart\Discounts\Value\Discount;
use Lubart\Discounts\Value\NumericID;
use Lubart\Discounts\Value\Quantity;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class DiscountController
{
    protected Request $request;

    protected Response $response;

    protected array $attributes;

    protected Order $order;

    private float $goldCustomerRevenue;

    private float $goldCustomerDiscount;

    private int $toolCategory;

    private int $switcherCategory;

    private int $requiredSwitchersAmount;

    private float $cheapestToolDiscount;

    public function __construct(protected Client $client) {
        $this->goldCustomerRevenue = (float)env('GOLD_CUSTOMER_REVENUE', 1000);
        $this->goldCustomerDiscount = (float)env('GOLD_CUSTOMER_DISCOUNT', 10);
        $this->toolCategory = (int)env('TOOL_CATEGORY', 1);
        $this->switcherCategory = (int)env('SWITCHER_CATEGORY', 2);
        $this->requiredSwitchersAmount = (int)env('REQUIRED_SWITCHES_AMOUNT', 5);
        $this->cheapestToolDiscount = (float)env('CHEAPEST_TOOL_DISCOUNT', 20);
    }

    /**
     * Magic method to call protected action method from the
     * basic controller class
     *
     * @param string $method
     * @param array $args
     * @return Response
     * @throws MethodNotExistException
     * @throws WrongActionCallException
     */
    public function __call(string $method, array $args): Response
    {
        if(!($args[0] instanceof Request) or !($args[1] instanceof Response) or !is_array($args[2])){
            throw new WrongActionCallException("Attributes type not correct", 500);
        }

        if(!method_exists($this, $method)){
            throw new MethodNotExistException("Method does not exist", 500);
        }

        $this->request = $args[0];
        $this->response = $args[1];
        $this->attributes = $args[2];

        try {
            $this->order = Order::buildFromSource($this->request->getParsedBody());
            $discountedOrder = $this->{$method}(...$args[2]);
        } catch (GuzzleException $e) {
            return $this->respondWithError(new EntityNotAvailableException($this->getExceptionMessage($e), $e->getCode()));
        } catch (EntityNotAvailableException | WrongInputDataException $e){
            return $this->respondWithError($e);
        }

        return $this->respond($discountedOrder);
    }

    /**
     * Action method for the /discount/gold-customer route
     *
     * @return Order
     * @throws GuzzleException
     */
    protected function discountGoldCustomer(): Order
    {
        $customer = new Customer($this->client, $this->order->{'customer-id'});

        if($customer->revenue >= $this->goldCustomerRevenue){
            $this->order->totalDiscount += $orderDiscount = (float)sprintf('%.2f',$this->order->total * $this->goldCustomerDiscount / 100);
            $this->order->total -= $orderDiscount;
        }

        return $this->order;
    }

    /**
     * Action method for the /discount/sixth-switcher-for-free
     * Custom implementation of the getNextProductForFree method.
     * Sell every 6th item from the switcher category (id = 2) for free
     *
     * @return Order
     * @throws EntityNotAvailableException
     */
    protected function getSixthSwitcherForFree(): Order
    {
        return $this->getNextProductForFree($this->order, new Quantity($this->requiredSwitchersAmount), new NumericID($this->switcherCategory));
    }

    /**
     * Sell the (n+1)-th item from the same category in order for free
     *
     * @param Order $order
     * @param Quantity $minAmount
     * @param NumericID $categoryId
     * @return Order
     * @throws EntityNotAvailableException
     */
    private function getNextProductForFree(Order $order, Quantity $minAmount, NumericID $categoryId): Order
    {
        $totalDiscount = 0;

        foreach($order->items as $item){
            try {
                $product = new Product($this->client, $item['product-id']);
            } catch (GuzzleException $e){
                throw new EntityNotAvailableException($this->getExceptionMessage($e), $e->getCode());
            }

            if($item['quantity'] > $minAmount->value and $product->category === $categoryId->value){
                $freeQuantity = floor($item['quantity'] / ($minAmount->value + 1));
                $item['discount'] = $discount = $freeQuantity * $item['unit-price'];
                $totalDiscount += $discount;
                $item['total'] -= $discount;
            }
        }

        $order->totalDiscount += $totalDiscount;
        $order->total -= $totalDiscount;

        return $order;
    }

    /**
     * Action method for the /discount/cheapest-tool
     * Custom implementation of the discountCheapestItemInCategory method
     * Set the discount of 20% to the cheapest tool (id = 1) in the order if at least
     * two tools are in the order.
     *
     * @return Order
     * @throws EntityNotAvailableException
     */
    protected function discountCheapestTool(): Order
    {
        return $this->discountCheapestItemInCategory($this->order, new NumericID($this->toolCategory), Discount::percents($this->cheapestToolDiscount));
    }

    /**
     * Set the $discount to the cheapest item in the category
     *
     * @param Order $order
     * @param NumericID $categoryId
     * @param Discount $discount
     * @param Quantity $minAmount
     * @return Order
     * @throws EntityNotAvailableException
     */
    private function discountCheapestItemInCategory(Order $order, NumericID $categoryId, Discount $discount, Quantity $minAmount = new Quantity(2)): Order
    {
        $categoryProducts = [];
        $cheapestItemInCategory = null;

        foreach($order->items as $item){
            try {
                $product = new Product($this->client, $item['product-id']);
            } catch (GuzzleException $e){
                throw new EntityNotAvailableException($this->getExceptionMessage($e), $e->getCode());
            }

            if($product->category === $categoryId->value){
                for($i = 0; $i < $item['quantity']; $i++){
                    $categoryProducts[] = $item;
                    if(is_null($cheapestItemInCategory) or $cheapestItemInCategory['unit-price'] > $item['unit-price']){
                        $cheapestItemInCategory = $item;
                    }
                }
            }
        }

        if(!is_null($cheapestItemInCategory) and count($categoryProducts) >= $minAmount->value){
            $cheapestItemInCategory['discount'] = $itemDiscount = (float)sprintf('%.2f',(float)$cheapestItemInCategory['unit-price'] * $discount->value);
            $cheapestItemInCategory['total'] -= $itemDiscount;
            $order->totalDiscount += $itemDiscount;
            $order->total -= $itemDiscount;
        }

        return $order;
    }

    /**
     * @param Order $order
     * @param int $status
     * @return Response
     */
    private function respond(Order $order, int $status = 200): Response
    {
        $this->response->getBody()->write($order->encode());
        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    /**
     * @param Exception $exception
     * @return Response
     */
    private function respondWithError(Exception $exception): Response
    {
        $data = [
            'error' => [
                'code' => $exception->getCode(),
                'message' => $exception->getMessage()
            ]
        ];

        $this->response->getBody()->write(json_encode($data));
        return $this->response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($exception->getCode());
    }

    /**
     * @param Exception $e
     * @return string
     */
    private function getExceptionMessage(Exception $e): string
    {
        $responseContents = json_decode($e->getResponse()->getBody()->getContents());
        if(isset($responseContents->error) and isset($responseContents->error->message)) {
            return $responseContents->error->message;
        }

        return $e->getMessage();
    }
}