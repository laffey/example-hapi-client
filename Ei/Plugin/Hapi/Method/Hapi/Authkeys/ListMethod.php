<?php
/**
 * hapi.authkeys.list
 *
 */

namespace Ei\Plugin\Hapi\Method\Hapi\Authkeys;

use \Ei\Model as HapiResponse;
use \Ei\Plugin\Hapi\Method\AbstractMethod;
use \Ei\Plugin\Hapi\Method\Exception\MethodException;
use \Ei\Plugin\Hapi\Exception\HapiExceptionCodes;

class ListMethod extends AbstractMethod
{

    /**
     * @see AbstractMethod
     */
    protected $_params = array(
            'method'  => 'hapi.authkeys.list',
            'format'  => 'json'
    );

    protected $_isAdminOnlyMethod = true;

    protected $_isPortalRefOnly = true;

    /**
     * @see AbstractMethod
     * @param array $options        Conditions for the request.
     *                  'client_id'         *required
     */
    public function __construct(Array $options = array())
    {
        if ($this->_applicationName !== 'portal-reference') {
            throw new MethodException('The action you are attempting is not allowed.', HapiExceptionCodes::ACTION_NOT_ALLOWED);
        }
        if (empty($options['client_id'])) {
            throw new MethodException('You must provide the client id to get a list of authkeys.', HapiExceptionCodes::MISSING_REQUIRED_METHOD_PARAM);
        }
        $this->_postData = array(
            'customer_id'     => $options['client_id'],
        );
    }

    /**
     * @see AbstractMethod
     */
    public function setResponse($body, $header)
    {
        $data = json_decode($body, true);
        if ($this->_checkResponseStatus($data)) {
            // unwrap the data
            $data = $data['response'];
            if (empty($data['authkeys'])) {
                $data['authkeys'] = array();
            }
            $this->_createResponse($data['authkeys']);
        }
    }

}
