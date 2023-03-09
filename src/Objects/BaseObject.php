<?php

namespace Telegram\Bot\Objects;

use Illuminate\Support\Enumerable;
use Illuminate\Support\Traits\EnumeratesValues;
use InvalidArgumentException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Class BaseObject.
 *
 * @mixin Collection
 */
abstract class BaseObject extends Collection
{
    /**
     * Builds collection entity.
     *
     * @param array|mixed $data
     */
    public function __construct($data)
    {
        parent::__construct($this->getRawResult($data));
    }

    /**
     * Property relations.
     *
     * @return array
     */
    abstract public function relations(): array;

    /**
     * Magically access collection data.
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->getPropertyValue($key);
    }

    /**
     * Magically map to an object class (if exists) and return data.
     *
     * @param string $property Name of the property or relation.
     * @param mixed $default Default value or \Closure that returns default value.
     * @return mixed
     */
    protected function getPropertyValue(string $property, $default = null)
    {
        $property = Str::snake($property);
        if (!$this->offsetExists($property)) {
            return value($default);
        }

        $value = $this->items[$property];

        $relations = $this->relations();
        if (isset($relations[$property])) {
            return $this->getRelationValue($property, $value);
        }

        /** @var BaseObject $class */
        $class = 'Telegram\Bot\Objects\\' . Str::studly($property);

        if (class_exists($class)) {
            return $class::make($value);
        }

        if (is_array($value)) {
            return TelegramObject::make($value);
        }

        return $value;
    }

    /**
     * @param array $relationRawData
     * @return array|Enumerable|EnumeratesValues|BaseObject
     */
    protected function getRelationValue(string $relationName, iterable $relationRawData)
    {
        /** @var class-string<BaseObject>|list<class-string<BaseObject>> $relation */
        $relation = $this->relations()[$relationName];

        if (is_string($relation)) {
            if (!class_exists($relation)) {
                throw new InvalidArgumentException(sprintf('Could not load “%s” relation: class “%s” not found.', $relationName, $relation));
            }

            return $relation::make($relationRawData);
        }
        /** @var class-string<BaseObject> $clasString */
        $clasString = $relation[0];
        $relatedObjects = Collection::make();
        // @todo array type can be used in v4
        foreach ($relationRawData as $singleObjectRawData) {
            $relatedObjects[] = $clasString::make($singleObjectRawData);
        }
        return $relatedObjects;
    }

    /**
     * Get an item from the collection by key.
     *
     * @param mixed $key
     * @param mixed $default
     * @return mixed|static
     */
    public function get($key, $default = null)
    {
        $value = parent::get($key, $default);
        if (null === $value) {
            return null;
        }
        if (!is_array($value)) {
            return $value;
        }

        return $this->getPropertyValue($key, $default);
    }

    /**
     * Returns raw response.
     *
     * @return array|mixed
     */
    public function getRawResponse()
    {
        return $this->items;
    }

    /**
     * Returns raw result.
     *
     * @return mixed
     */
    public function getRawResult($data)
    {
        return data_get($data, 'result', $data);
    }

    /**
     * Get Status of request.
     *
     * @return mixed
     */
    public function getStatus()
    {
        return data_get($this->items, 'ok', false);
    }

    /**
     * Detect type based on fields.
     */
    public function objectType(): ?string
    {
        return null;
    }

    /**
     * Determine if the object is of given type.
     *
     * @param string $type
     * @return bool
     */
    public function isType(string $type): bool
    {
        if ($this->offsetExists($type)) {
            return true;
        }

        return $this->objectType() === $type;
    }

    /**
     * Determine the type by given types.
     */
    protected function findType(array $types): ?string
    {
        return $this->keys()
            ->intersect($types)
            ->pop();
    }

    /**
     * Magic method to get properties dynamically.
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (!Str::startsWith($method, 'get')) {
            return false;
        }

        $property = substr($method, 3);

        return $this->getPropertyValue($property);
    }
}
