<?php
/**
 * hapi.authkeys.read -- authorizes a username.password
 */

namespace Ei\Plugin\Hapi\Method\Hapi\Authkeys;

use \Ei\Model as HapiResponse;
use \Ei\Plugin\Hapi\Method\AbstractMethod;
use \Ei\Plugin\Hapi\Method\Exception\MethodException;
use \Ei\Plugin\Hapi\Exception\HapiExceptionCodes;

class ReadMethod extends AbstractMethod
{
    /**
     * must be defined for each child
     * @var array
     */
    protected $_params = array(
            'method'  => 'hapi.authkeys.read',
            'format'  => 'json'
    );

    /**
     * either http or https
     * @var string
    */
    protected $_protocol = 'https://';

    /**
     * if hapi method must be accessed via admin credentials
     * @var boolean
     */
    protected $_isAdminOnlyMethod = false;

    protected $_isAuthRequest = true;

    /**
     * @var array required parameters
     */
     protected $_requiredParams = array(
             'username',
             'password',
     );

    /**
     * init this method with a username and password
     *         keys for $options
     *             'username' => string
     *             'password' => string
     *
     * @param array $options
     */
    public function __construct($options = array())
    {
        parent::__construct($options);

        $this->_authString = $this->_postData['username'] . ':' . $this->_postData['password'];
        $this->_postData['verbosity'] = 'extended';
    }

    /**
     * Returns the full url for the method, including url query
     *
     * @param string $endpoint
     * @param string $key
     * @param string $secret
     * @throws MethodException
     * @return string
     */
    public function getUrl($endpoint, $key, $secret)
    {
        if (empty($endpoint)) {
            throw new MethodException('Endpoint required for url');
        }

        $httpQuery = http_build_query($this->_params, '', '&');
        return $this->_protocol . $endpoint . '/version/' . $this->_version . '/?'. $httpQuery;
    }

    /**
     * set the response from curl execution
     *    a valid response for this method should include an authkey root key, with the info we need
     *
     * @param string $body
     * @param string $header
     * @throws MethodException
     * @return void
     */
    public function setResponse($body, $header)
    {
        $data = json_decode($body, true);
        if ($this->_checkResponseStatus($data)) {
            $data = $data['response'];
            if (!isset($data['authkey'])) {
                throw new MethodException('Invalid response received from hApi for this method. Response: ' . $body, HapiExceptionCodes::METHOD_PARSING_ERROR);
            }
            $this->_createResponse($data['authkey']);
        }
    }
}
