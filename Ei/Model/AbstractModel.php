<?php
/**
 * AbstractModel
 * abstract hapi response model
 */

namespace Ei\Model;

use \Ei\Model\Exception\ModelException;

abstract class AbstractModel implements \ArrayAccess, \Countable, \IteratorAggregate, \Serializable
{
    /**
     * where we store our model properties
     * @var array
     */
    protected $_data = array();

    /**
     * define this in your model, look at
     *    Ticket model for an example
     *
     * @var array
     */
    protected static $_hydrator = array();

    /**
     * @param array $properties
     * @param bool $silent
     * @throws ModelException
     */
    public function __construct($properties = array(), $silent = false)
    {
        if ($properties instanceof \stdClass) {
            $properties = (array)$properties;
        }
        if (!is_array($properties)) {
            throw new ModelException('Invalid type for $properties at object initialization.');
        }
        foreach ($properties as $key => $property) {
            $this->offsetSet($key, $property, $silent);
        }
    }

    /**
     * Load an array of data into the object.
     * @param \Iterator $data Data to apply to the object
     * @param bool $scrub  Skip (remove) keys from $data that do not exist as properties of the model
     * @return AbstractModel This model instance
     */
    public function load($data, $scrub = false)
    {
        foreach ($data as $key => $value) {
            $this->offsetSet($key, $value, $scrub);
        }
        return $this;
    }

    /**
     * static function
     *      grab the $_hydrator map from model definition
     *      return null if there is none (logs error)
     * Takes an array of key value pairs
     * Converts the key to the correct model properties
     *      and returns the new array
     *
     * @param array $data
     * @return array
     */
    public static function hydrate($data)
    {
        $hydrator = null;
        try {
            $hydrator = static::$_hydrator;
        } catch (\Exception $e) {
            trigger_error('Undefined hydrator for model', E_USER_WARNING);
        }
        if (empty($hydrator)) {
            trigger_error('Undefined or empty hydrator in your model', E_USER_WARNING);
            return array();
        }

        $modelProperties = array();
        foreach ($hydrator as $responseParam => $property) {
            if (array_key_exists($responseParam, $data)) {
                $modelProperties[$property] = $data[$responseParam];
            }
        }

        return $modelProperties;
    }

    /**
     * html escapes the value before returning it
     *     optional flag to dispable the escaping
     *
     * @param string $key
     * @param boolean $escapeValue
     * @return mixed
     */
    public function get($key, $escapeValue = true)
    {
        if (!array_key_exists($key, $this->_data)) {

            if ($logger = $this->logger()) {
                //log this error with specific trace
                $logger->addContextVar('calling_class', get_class($this));
                $logger->addContextVar('key', $key);
                $logger->addContextVar('escapeValue', $escapeValue);
                $logger->enableLogTrace();
                $logger->log("Invalid model property for '$key'", 0, 'abstract_model');
                $logger->disableLogTrace();
            }

            return '';
        }
        if ($escapeValue) {
            $value = $this->_data[$key];
            if (is_array($value)) {
                return $this->_escapeValues($value);
            } elseif (is_object($value)) {
                return $value;
            } else {
                return htmlentities($value, ENT_NOQUOTES);
            }
        }
        return $this->_data[$key];
    }

    /**
     * calls offsetSet()
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function set($key, $value)
    {
        $this->offsetSet($key, $value);
        return $this;
    }

    /**
     * abstract method: get the data assembled into a table
     *
     * @return array table data
     */
    public function getTableData()
    {
        return false;
    }

    /**
     * get the value for $key and format as a date/time
     *         $format takes the same values as the php function date()
     *
     * @param string $key
     * @param string $format        *ex: 'd/m/Y H:i:s'
     * @returns string $date
     */
    public function getDate($key, $format)
    {
        $value = $this->offsetGet($key);
        if (!empty($value)) {
            if (preg_match('/^[0-9]+$/', $value)) {
                //numeric value, assume is a unix timestamp
                $value = date($format, $value);
            } else {
                $value = date($format, strtotime($value));
            }
        }

        return $value;
    }

    /**
     * format the value for $key as USD currency
     *
     * @param string $key
     * @returns string - money value
     */
    public function getCurrency($key)
    {
        $value = $this->offsetGet($key);
        if ($value === 0 || $value === '0' || !empty($value)) {
            $value = money_format('%n', (float)$value);
        }

        return $value;
    }

    /**
     * return a value for the given offset index
     * @param string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    /**
     * set a property value
     *
     * @param string $offset
     * @param mixed $value
     * @param bool $silent            *if false, notices will be triggered when invalid property being set, else silently ignores the property (does not set)
     * @return void
     */
    public function offsetSet($offset, $value, $silent = false)
    {
        if (array_key_exists($offset, $this->_data)) {
            $this->_data[$offset] = $value;
        } else {
            //invalid property
            if (!$silent) {
                trigger_error('Trying to set invalid model property, "' . $offset . '".');
            }
        }
    }

    /**
     * check if array index exists
     * @param string $offset
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->_data[$offset]);
    }

    /**
     * unset an array value
     *     dont allow for this obj
     *
     * @param string $offset
     * @return void
     * @throws ModelException
     */
    public function offsetUnset($offset)
    {
        if (!array_key_exists($offset, $this->_data)) {
            trigger_error('Trying to unset invalid model property, "' . $offset . '".');
        } else {
            $this->_data[$offset] = null;
        }
    }

    /**
     * count properties for this response object
     *
     * @return int
     */
    public function count()
    {
        return count($this->_data);
    }

    /**
     * json encode so this obj can be serialized
     *
     * @return string
     */
    public function serialize()
    {
        return serialize($this->_data);
    }

    /**
     * json decode to init object from serialization
     *
     * @param string $serialized
     * @return void
     */
    public function unserialize($serialized)
    {
        $this->_data = unserialize($serialized);
    }

    /**
     * (non-PHPdoc)
     * @see IteratorAggregate::getIterator()
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->_data);
    }

    /**
     * convert obj to array
     *
     * @return array
     */
    public function toArray()
    {
        return $this->_data;
    }

    /**
     * escape all values in the array
     *
     * @param array $value
     * @return mixed
     */
    protected function _escapeValues($value)
    {
        $newValue = array();
        foreach ($value as $key => $data) {
            $secureKey = htmlentities($key, ENT_NOQUOTES);
            if (is_array($data)) {
                $newValue[$secureKey] = $this->_escapeValues($data);
            } elseif (is_object($data)) {
                $newValue[$secureKey] = $data;
            } else {
                $newValue[$secureKey] = htmlentities($data, ENT_NOQUOTES);
            }
        }
        return $newValue;
    }

    /**
     * Gets a log handler if there's one configured. Null otherwise.
     *
     * @return \Ei\Util\LogHandler
     */
    protected function logger()
    {
        //this is temporary so that we can figure out what's happening with the get() method
        try {
            return LogHandlerFactory::get('logger');
        } catch (\Exception $e) {
            //do nothing since the logger config has changed and this isn't something that is critical
        }
    }
}
