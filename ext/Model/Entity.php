<?php

namespace Lubart\External\Model;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use stdClass;

class Entity
{
    protected function getSource(string $repository, string $id): ?string
    {
        $sourceFile = __DIR__ . '/Repository/' . $repository . '/item' . $id . '.json';

        $data = null;
        if(file_exists($sourceFile)){
            $data = file_get_contents($sourceFile);
        }

        return $data;
    }

    protected function respond(Response $response, string $data, int $status = 200): Response
    {
        $response->getBody()->write($data);
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    protected function respondWithError(Response $response, string $message, int $status): Response
    {
        $data = new stdClass();
        $data->error = new stdClass();
        $data->error->code = $status;
        $data->error->message = $message;

        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    public function order(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $data = $this->getSource('Order', $id);

        if(is_null($data)){
            return $this->respondWithError($response, "Order with ID " . $id . " does not exist", 404);
        }

        return $this->respond($response, $data);
    }

    public function customer(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $data = $this->getSource('Customer', $id);

        if(is_null($data)){
            return $this->respondWithError($response, "Customer with ID " . $id . " does not exist", 404);
        }

        return $this->respond($response, $data);
    }

    public function product(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $data = $this->getSource('Product', $args['id']);

        if(is_null($data)){
            return $this->respondWithError($response, "Product with ID " . $id . " does not exist", 404);
        }

        return $this->respond($response, $data);
    }

}