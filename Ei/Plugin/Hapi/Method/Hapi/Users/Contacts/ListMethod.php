<?php
/**
 * users.contacts.list - pull all contacts for the current client
 */

namespace Ei\Plugin\Hapi\Method\Hapi\Users\Contacts;

use \Ei\Plugin\Hapi\Method\AbstractMethod;
use \Ei\Plugin\Hapi\Method\Exception\MethodException;
use \Ei\Plugin\Hapi\Exception\HapiExceptionCodes;

class ListMethod extends AbstractMethod
{

    /**
     * must be defined for each child
     * @var array
     */
    protected $_params = array(
            'method'  => 'hapi.users.contacts.list',
            'format'  => 'json'
    );

    /**
     * array of required parameter names
     * @var array
     */
    protected $_requiredParams = array('customer_id');

    protected $_optionalParams = array('user_login', 'contact_id', 'verbosity');

    /**
     * set the response from curl execution
     *
     * @param string $body
     * @param string $header
     * @return void
     */
    public function setResponse($body, $header)
    {
        $data = json_decode($body, true);

        if ($this->_checkResponseStatus($data)) {
            $this->_setResponseCollection('contact', $data['response']['contacts']);
        }
    }

}
