<?php
/**
 * FactoryInterface
 * interface for our model and value object factories
 */
namespace Ei\Model\Factory;

interface FactoryInterface
{
    /**
     * the standard function for factories
     * 
     * @param string $modelType
     * @param array $properties
     * @return array | AbstractModel | AbstractList
     */
    public function create($modelType, $properties);
}
