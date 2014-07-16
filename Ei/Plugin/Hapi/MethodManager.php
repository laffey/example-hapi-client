<?php
/**
 * method manager
 *      maps string keys to method classes for the hapi client
 */

namespace Ei\Plugin\Hapi;

use \Ei\Model\ModelFactory;
use \Ei\Model\CollectionFactory;
use \Ei\Plugin\Hapi\Method\Exception\MethodException;

class MethodManager
{
    /**
     * map method keys to their class
     * @var array
     */
    protected $_methodMap = array(
            'hapi.authkeys.list'                      => '\Ei\Plugin\Hapi\Method\Hapi\Authkeys\ListMethod',
            'hapi.authkeys.read'                      => '\Ei\Plugin\Hapi\Method\Hapi\Authkeys\ReadMethod',
            'users.contacts.list'                     => '\Ei\Plugin\Hapi\Method\Hapi\Users\Contacts\ListMethod',
    );

    /**
     * @var ModelFactory
     */
    protected $_modelFactory;

    /**
     * @var CollectionFactory
     */
    protected $_collectionFactory;

    /**
     * @var LogHandler
     */
    protected $_logHandler;

    /**
     * inject the appropriate factories
     *
     * @param ModelFactory $modelFactory
     * @param CollectionFactory $collectionFactory
     * @param LogHandler $logHandler
     */
    public function __construct(ModelFactory $modelFactory, CollectionFactory $collectionFactory, LogHandler $logHandler)
    {
        $this->_modelFactory = $modelFactory;
        $this->_collectionFactory = $collectionFactory;
        $this->_logHandler = $logHandler;
    }

    /**
     * init a method obj
     *
     * @param string $methodKey
     * @param array $options            -- inject method class with $options (optional)
     * @throws \Ei\Plugin\Hapi\Method\Exception\MethodException
     * @return \Ei\Plugin\Hapi\Method\MethodInterface
     */
    public function get($methodKey, $options = array())
    {
        if (!array_key_exists($methodKey, $this->_methodMap)) {
            throw new MethodException('Method key does not exist, "' . $methodKey . '". Cannot init hApi method.');
        }

        $method = new $this->_methodMap[$methodKey]($options, $this->_logHandler);

        $method->setModelFactory($this->_modelFactory)
               ->setCollectionFactory($this->_collectionFactory);

        return $method;
    }
}
