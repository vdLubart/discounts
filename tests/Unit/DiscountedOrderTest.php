<?php

namespace Test\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use Lubart\Discounts\Controller\DiscountController;
use Lubart\Discounts\Exception\MethodNotExistException;
use Lubart\Discounts\Exception\WrongActionCallException;
use Lubart\Discounts\Exception\WrongInputDataException;
use Lubart\Discounts\Model\Order;
use PHPUnit\Framework\TestCase;
use stdClass;

class DiscountedOrderTest extends TestCase
{
    protected array $orders = [
        1 => [
            "id" => "1",
            "customer-id" => "1",
            "items" => [
                [
                  "product-id" => "B102",
                  "quantity" => "10",
                  "unit-price" => "4.99",
                  "total" => "49.90"
                ]
            ],
            "total" => "49.90"
        ],
        2 => [
            "id" => "2",
            "customer-id" => "2",
            "items" => [
                [
                  "product-id" => "B102",
                  "quantity" => "5",
                  "unit-price" => "4.99",
                  "total" => "24.95"
                ]
            ],
            "total" => "24.95"
        ],
        3 => [
            "id" => "3",
            "customer-id" => "3",
            "items" => [
                [
                  "product-id" => "A101",
                  "quantity" => "2",
                  "unit-price" => "9.75",
                  "total" => "19.50"
                ],
                [
                  "product-id" => "A102",
                  "quantity" => "1",
                  "unit-price" => "49.50",
                  "total" => "49.50"
                ]
            ],
            "total" => "69.00"
        ],
        4 => [
            "id" => "4",
            "customer-id" => "4",
            "items" => [
                [
                  "product-id" => "C101",
                  "quantity" => "10",
                  "unit-price" => "4.99",
                  "total" => "49.90"
                ]
            ],
            "total" => "49.90"
        ]
    ];

    protected array $customers = [
        1 => '{
    "id": "1",
    "name": "Coca Cola",
    "since": "2014-06-28",
    "revenue": "492.12"
  }',
        2 => '{
    "id": "2",
    "name": "Teamleader",
    "since": "2015-01-15",
    "revenue": "1505.95"
  }',
        3 => '{
    "id": "3",
    "name": "Jeroen De Wit",
    "since": "2016-02-11",
    "revenue": "0.00"
  }'
    ];

    protected array $products = [
        "A101" => '{
    "id": "A101",
    "description": "Screwdriver",
    "category": "1",
    "price": "9.75"
  }',
        "A102" => '{
    "id": "A102",
    "description": "Electric screwdriver",
    "category": "1",
    "price": "49.50"
  }',
        "B101" => '{
    "id": "B101",
    "description": "Basic on-off switch",
    "category": "2",
    "price": "4.99"
  }',
        "B102" => '{
    "id": "B102",
    "description": "Press button",
    "category": "2",
    "price": "4.99"
  }',
        "B103" => '{
    "id": "B103",
    "description": "Switch with motion detector",
    "category": "2",
    "price": "12.95"
  }'
    ];

    public function setUp(): void
    {
        require __DIR__ . '/../../bootstrap/bootstrap.php';
    }

    /** @test - discount applied to order of the gold customer */
    function discount_applied_to_order_of_the_gold_customer() {
        $mock = new MockHandler([
            new Response(200, [], $this->customers[2])
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $controller = new DiscountController($client);
        $request = (new ServerRequest('POST', '/discount/gold-customer'))
                        ->withParsedBody($this->orders[2]);
        $response = $controller->discountGoldCustomer(
            $request,
            new Response(200, []),
            []
        );

        $response->getBody()->rewind();
        $jsonResponse = json_decode($response->getBody()->getContents());

        $this->assertEquals(2.5, $jsonResponse->totalDiscount);
        $this->assertEquals($this->calculateTotal($jsonResponse), $jsonResponse->total);
    }

    /** @test - discount not applied to order of the ordinary customer */
    function discount_not_applied_to_order_of_the_ordinary_customer() {
        $mock = new MockHandler([
            new Response(200, [], $this->customers[1])
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $controller = new DiscountController($client);
        $request = (new ServerRequest('POST', '/discount/gold-customer'))
            ->withParsedBody($this->orders[1]);
        $response = $controller->discountGoldCustomer(
            $request,
            new Response(200, []),
            []
        );

        $response->getBody()->rewind();
        $jsonResponse = json_decode($response->getBody()->getContents());

        $this->assertEquals(0, $jsonResponse->totalDiscount);
        $this->assertEquals($this->calculateTotal($jsonResponse), $jsonResponse->total);
    }

    /** @test - get the sixth switcher in the order for free */
    function get_the_sixth_switcher_in_the_order_for_free() {
        $mock = new MockHandler([
            new Response(200, [], $this->products["B102"])
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $controller = new DiscountController($client);
        $request = (new ServerRequest('POST', '/discount/sixth-switcher-for-free'))
            ->withParsedBody($this->orders[1]);
        $response = $controller->getSixthSwitcherForFree(
            $request,
            new Response(200, []),
            []
        );

        $response->getBody()->rewind();
        $jsonResponse = json_decode($response->getBody()->getContents());

        $this->assertEquals(4.99, $jsonResponse->totalDiscount);
        $this->assertEquals($this->calculateTotal($jsonResponse), $jsonResponse->total);
    }

    /** @test - ignore sixth switcher discount if there is not switchers in the order */
    function ignore_sixth_switcher_discount_if_there_is_not_switchers_in_the_order() {
        $mock = new MockHandler([
            new Response(200, [], $this->products["A101"]) // Not a switcher product
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $controller = new DiscountController($client);
        $request = (new ServerRequest('POST', '/discount/sixth-switcher-for-free'))
            ->withParsedBody($this->orders[1]);
        $response = $controller->getSixthSwitcherForFree(
            $request,
            new Response(200, []),
            []
        );

        $response->getBody()->rewind();
        $jsonResponse = json_decode($response->getBody()->getContents());

        $this->assertEquals(0, $jsonResponse->totalDiscount);
        $this->assertEquals($this->calculateTotal($jsonResponse), $jsonResponse->total);
    }

    /** @test - apply discount to the cheapest tool */
    function apply_discount_to_the_cheapest_tool() {
        $mock = new MockHandler([
            new Response(200, [], $this->products["A101"]),
            new Response(200, [], $this->products["A102"])
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $controller = new DiscountController($client);
        $request = (new ServerRequest('POST', '/discount/cheapest-tool'))
            ->withParsedBody($this->orders[3]);
        $response = $controller->discountCheapestTool(
            $request,
            new Response(200, []),
            []
        );

        $response->getBody()->rewind();
        $jsonResponse = json_decode($response->getBody()->getContents());

        $this->assertEquals(1.95, $jsonResponse->totalDiscount);
        $this->assertEquals($this->calculateTotal($jsonResponse), $jsonResponse->total);
    }

    /** @test - ignore discount to the cheapest tool if there is no tool in the order */
    function ignore_discount_to_the_cheapest_tool_if_there_is_no_tool_in_the_order() {
        $mock = new MockHandler([
            new Response(200, [], $this->products["B101"]),
            new Response(200, [], $this->products["B102"])
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $controller = new DiscountController($client);
        $request = (new ServerRequest('POST', '/discount/sixth-switcher-for-free'))
            ->withParsedBody($this->orders[3]);
        $response = $controller->discountCheapestTool(
            $request,
            new Response(200, []),
            []
        );

        $response->getBody()->rewind();
        $jsonResponse = json_decode($response->getBody()->getContents());

        $this->assertEquals(0, $jsonResponse->totalDiscount);
        $this->assertEquals($this->calculateTotal($jsonResponse), $jsonResponse->total);
    }

    /** @test - expect exception if requested customer not found */
    function expect_exception_if_requested_customer_not_found() {
        $errorData = new stdClass();
        $errorData->error = new stdClass();
        $errorData->error->code = $errorCode = 404;
        $errorData->error->message = $errorMessage = "Customer with ID XXX does not exist";

        $mock = new MockHandler([
            new Response(404, [], json_encode($errorData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $controller = new DiscountController($client);
        $request = (new ServerRequest('POST', '/discount/gold-customer'))
            ->withParsedBody($this->orders[4]); // order referrers to the wrong customer
        $response = $controller->discountGoldCustomer(
            $request,
            new Response(200, []),
            []
        );

        $response->getBody()->rewind();
        $jsonResponse = json_decode($response->getBody()->getContents());

        $this->assertEquals($errorCode, $jsonResponse->error->code);
        $this->assertEquals($errorMessage, $jsonResponse->error->message);
    }

    /** @test - expect exception if requested product not found */
    function expect_exception_if_requested_product_not_found() {
        $errorData = new stdClass();
        $errorData->error = new stdClass();
        $errorData->error->code = $errorCode = 404;
        $errorData->error->message = $errorMessage = "Product with ID XXX does not exist";

        $mock = new MockHandler([
            new Response(404, [], json_encode($errorData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $controller = new DiscountController($client);
        $request = (new ServerRequest('POST', '/discount/sixth-switcher-for-free'))
            ->withParsedBody($this->orders[4]); // order referrers to the wrong customer
        $response = $controller->getSixthSwitcherForFree(
            $request,
            new Response(200, []),
            []
        );

        $response->getBody()->rewind();
        $jsonResponse = json_decode($response->getBody()->getContents());

        $this->assertEquals($errorCode, $jsonResponse->error->code);
        $this->assertEquals($errorMessage, $jsonResponse->error->message);
    }

    /** @test - expect exception if requested product not found in discount tools */
    function expect_exception_if_requested_product_not_found_in_discount_tools() {
        $errorData = new stdClass();
        $errorData->error = new stdClass();
        $errorData->error->code = $errorCode = 404;
        $errorData->error->message = $errorMessage = "Product with ID XXX does not exist";

        $mock = new MockHandler([
            new Response(404, [], json_encode($errorData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $controller = new DiscountController($client);
        $request = (new ServerRequest('POST', '/discount/cheapest-tool'))
            ->withParsedBody($this->orders[4]); // order referrers to the wrong customer
        $response = $controller->discountCheapestTool(
            $request,
            new Response(200, []),
            []
        );

        $response->getBody()->rewind();
        $jsonResponse = json_decode($response->getBody()->getContents());

        $this->assertEquals($errorCode, $jsonResponse->error->code);
        $this->assertEquals($errorMessage, $jsonResponse->error->message);
    }

    /** @test - expect exception if action method not exist */
    function expect_exception_if_action_method_not_exist() {
        $this->expectException(MethodNotExistException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage("Method does not exist");

        $controller = new DiscountController(new Client());
        $request = (new ServerRequest('POST', '/discount/cheapest-tool'))
            ->withParsedBody($this->orders[4]); // order referrers to the wrong customer
        $controller->notExist(
            $request,
            new Response(200, []),
            []
        );
    }

    /** @test - expect exception if action method has wrong first attribute */
    function expect_exception_if_action_method_has_wrong_first_attribute() {
        $this->expectException(WrongActionCallException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage("Attributes type not correct");

        $controller = new DiscountController(new Client());
        $controller->discountGoldCustomer(
            '/discount/cheapest-tool'
        );
    }

    /** @test - expect exception if action method has wrong second attribute */
    function expect_exception_if_action_method_has_wrong_second_attribute() {
        $this->expectException(WrongActionCallException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage("Attributes type not correct");

        $controller = new DiscountController(new Client());
        $request = (new ServerRequest('POST', '/discount/cheapest-tool'))
            ->withParsedBody($this->orders[4]); // order referrers to the wrong customer
        $controller->discountGoldCustomer(
            $request,
            '/order/4/discount/cheapest-tool'
        );
    }

    /** @test - expect exception if order data does not contain id */
    function expect_exception_if_order_data_does_not_contain_id() {
        $data = $this->orders[1];
        unset($data['id']);

        $this->expectExceptionMessage(WrongInputDataException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage("Wrong input 'id' value");

        Order::buildFromSource($data);
    }

    /** @test - expect exception if order data does not contain customer id */
    function expect_exception_if_order_data_does_not_contain_customer_id() {
        $data = $this->orders[1];
        unset($data['customer-id']);

        $this->expectExceptionMessage(WrongInputDataException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage("Wrong input 'customer-id' value");

        Order::buildFromSource($data);
    }

    /** @test - expect exception if order data does not contain items */
    function expect_exception_if_order_data_does_not_contain_items() {
        $data = $this->orders[1];
        unset($data['items']);

        $this->expectExceptionMessage(WrongInputDataException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage("Wrong input 'items' value");

        Order::buildFromSource($data);
    }

    /** @test - expect exception if order data does not contain item data */
    function expect_exception_if_order_data_does_not_contain_item_data() {
        $data = $this->orders[1];
        $data['items'] = [[]];

        $this->expectExceptionMessage(WrongInputDataException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage("Wrong input 'items' value");

        Order::buildFromSource($data);
    }

    /** @test - expect exception if order data does not contain total */
    function expect_exception_if_order_data_does_not_contain_total() {
        $data = $this->orders[1];
        unset($data['total']);

        $this->expectExceptionMessage(WrongInputDataException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage("Wrong input 'total' value");

        Order::buildFromSource($data);
    }

    private function calculateTotal($json): float
    {
        $total = 0;
        foreach($json->items as $item){
            $total += $item->{'unit-price'} * $item->quantity;
        }

        return sprintf("%.2f", $total - $json->totalDiscount);
    }

}
