<?php
/**
 * CollectionFactory
 * spits out an array of a certain type of hapi response models
 */
namespace Ei\Model;

use \Ei\Model\Exception\ModelException;
use \Ei\Model\Factory\FactoryInterface;
use \Ei\Model\ModelFactory;

class CollectionFactory implements FactoryInterface
{

    /**
     * our model factory
     *
     * @var ModelFactory
     */
    protected $_modelFactory;

    /**
     * inject the modelFactory
     *
     * @param ModelFactory $modelFactory
     * @return void
     */
    public function __construct(ModelFactory $modelFactory)
    {
        $this->_modelFactory = $modelFactory;
    }

    /**
     * Returns an array of the $modelType objects
     *
     * @param string $modelType
     * @param array $collectionOfProperties
     * @param boolean $silent            *if false-> forces model data to match model; else other properties are allowed
     * @throws ModelException
     * @return array
     */
    public function create($modelType, $collectionOfProperties, $silent = true)
    {
        if (!is_array($collectionOfProperties)) {
            throw new ModelException('Second param must be an array of property arrays.');
        }

        $collection = array();
        foreach ($collectionOfProperties as $key => $modelProperties) {
            $collection[$key] = $this->_modelFactory->create($modelType)->load($modelProperties, $silent);
        }
        return $collection;
    }


}
