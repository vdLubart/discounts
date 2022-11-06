<?php

use GuzzleHttp\Client;
use Lubart\Discounts\Controller\DiscountController;
use Lubart\External\Model\Entity as ExternalEntity;
use Middlewares\TrailingSlash;
use Slim\App;
use Slim\Exception\HttpNotFoundException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use GuzzleHttp\Psr7\Response;

return function (App $app) {

    $app->add(new TrailingSlash(false));
    $app->add(function (
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ) {
        try {
            return $handler->handle($request);
        } catch (HttpNotFoundException $httpException) {
            $response = (new Response())
                ->withStatus(404)
                ->withHeader('Content-Type', 'application/json');
            $data = [
                'error' => [
                    'code' => 404,
                    'message' => "Endpoint not found"
                ]
            ];
            $response->getBody()->write(json_encode($data));

            return $response;
        }
    });

    $container = $app->getContainer();

    $container->set(DiscountController::class, function () {
        return new DiscountController(new Client());
    });

    // Simulate external service to get customer and product
    $app->get('/customer/{id:\d+}', [ExternalEntity::class, 'customer']);
    $app->get('/product/{id:[A-Z]\d{3,}}', [ExternalEntity::class, 'product']);

    // Discount end-points
    $app->post('/discount/gold-customer', [DiscountController::class, 'discountGoldCustomer']);
    $app->post('/discount/sixth-switcher-for-free', [DiscountController::class, 'getSixthSwitcherForFree']);
    $app->post('/discount/cheapest-tool', [DiscountController::class, 'discountCheapestTool']);
};