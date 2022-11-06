<?php

namespace Lubart\Discounts\Model;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use stdClass;

abstract class Model
{
    private string $server;

    protected string $entity;

    /**
     * Property indicates the entity json structure
     *
     * @var array|string[] $fields
     */
    protected array $fields = ['id'];

    /**
     * Model constructor
     *
     * @param Client $client
     * @param string|null $id
     * @throws GuzzleException
     */
    public function __construct(protected Client $client, ?string $id = null)
    {
        $modelClass = strtolower(get_called_class());
        $this->entity = array_slice(explode('\\', $modelClass), -1)[0];
        $this->server = trim(env(strtoupper($this->entity) . '_SERVER', @$_SERVER['HTTP_HOST']), '/');

        if(!is_null($id)){
            $this->find($id);
        }
    }

    /**
     * @param string $id
     * @return stdClass|null
     * @throws GuzzleException
     */
    protected function getSource(string $id): ?stdClass
    {
        $response = $this->client->get($this->server . '/' . $this->entity . '/' . $id);

        return json_decode($response->getBody()->getContents());
    }

    /**
     * @param stdClass|null $source
     * @return $this
     */
    protected function buildModel(?stdClass $source): self
    {
        if(!is_null($source)){
            foreach ($this->fields as $field){
                $this->{$field} = method_exists($this, $field) ? $this->{$field}($source->{$field}) : $source->{$field};
            }
        }

        return $this;
    }

    /**
     * @param string $id
     * @return $this|null
     * @throws GuzzleException
     */
    public function find(string $id): ?self
    {
        $source = $this->getSource($id);

        return $this->buildModel($source);
    }

    /**
     * @return string
     */
    public function encode(): string
    {
        $data = [];

        $properties = array_values(
            array_diff(
                array_keys(get_object_vars($this)),
                array_keys(get_class_vars(self::class)))
        );

        foreach($properties as $field){
            $data[$field] = $this->{$field};
        }

        return json_encode($data);
    }
}