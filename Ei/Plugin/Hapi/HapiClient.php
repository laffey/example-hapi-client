<?php
/**
 * api client
 *     handles the communication with hAPI
 * 
 * portal = customer interface which grabs data via this hapi client
 */

namespace Ei\Plugin\Hapi;

use \Ei\Model\Exception\ModelException;
use \Ei\Plugin\Hapi\Exception\HapiException;
use \Ei\Plugin\Hapi\Exception\HapiExceptionCodes;
use \Ei\Plugin\Hapi\Method\Exception\CurlException;
use \Ei\Plugin\Hapi\Method\Exception\HttpCodeException;
use \Ei\Plugin\Hapi\Method\Exception\MethodException;

class HapiClient
{
    /**
     * these methods return true with a null identity
     *
     * @var array
     */
    protected $_nullIdentityMethods = array(
        'test.echo',
        'users.reset_password',
        'hapi.authkeys.read',
        'jobs.status',
        'cache.delete',
        'devices.update_faceplate_image',
    );

    /**
     * hapi auth key for an uber admin
     * @var string
     */
    private $_adminKey;

    /**
     * hapi auth secret for an uber admin
     * @var string
     */
    private $_adminSecret;

    /**
     * hapi auth key for portal admin
     * @var string
     */
    private $_portalKey;

    /**
     * hapi auth secret for portal admin
     * @var string
     */
    private $_portalSecret;

    /**
     * the url where hApi calls are sent
     * @var string
     */
    private $_endpoint;

    /**
     * the ssl version to use when calling hapi via https
     *     possible values: 2 or 3
     * @var int
     */
    private $_sslVersion;

    /**
     * @var int - default 1
     */
    private $_verifyPeer;

    /**
     * @var int - default 1
     */
    private $_verifyHost;

    /**
     * the customer's hApi key
     * @var string
     */
    private $_key;

    /**
     * the customer's hApi secret
     * @var string
     */
    private $_secret = '';

    /**
     * id of client
     * @var int
     */
    private $_clientId = 0;

    /**
     * for subusers, id of contact; for superusers, empty
     * @var int
     */
    private $_contactId = null;

    /**
     * ip of client
     * @var int
     */
    private $_clientIp = 0;

    /**
     * the customer's username
     * @var string
     */
    private $_username;

    /**
     * @var MethodManager
     */
    protected $_methodManager;

    /**
     * the current method object
     * @var \Ei\Plugin\Hapi\Method\MethodInterface
     */
    protected $_method;

    /**
     * For making pretty logs
     *
     * @var LogHandler
     */
    protected $logHandler;

    const ENCRYPTION_MODE = 'aes128';

    /**
     * inits with the endpoint url and method manager
     *
     * @param string $endpoint
     * @param MethodManager $methodManager
     * @param string $adminKey
     * @param string $adminSecret
     * @param string $portalKey
     * @param string $portalSecret
     * @param int $sslVersion Default = 0
     * @param int $verifyPeer Default = 1
     * @param int $verifyHost Default = 1
     * @param LogHandler $logHandler
     */
    public function __construct($endpoint, MethodManager $methodManager, $adminKey, $adminSecret, $portalKey, $portalSecret, $sslVersion = 0, $verifyPeer = 1, $verifyHost = 1, LogHandler $logHandler = null)
    {
        $this->_endpoint = $endpoint;
        $this->_methodManager = $methodManager;
        $this->_adminKey = $adminKey;
        $this->_adminSecret = $adminSecret;
        $this->_portalKey = $portalKey;
        $this->_portalSecret = $portalSecret;
        $this->_sslVersion = $sslVersion;
        $this->logHandler = $logHandler;
        $this->_verifyPeer = $verifyPeer;
        $this->_verifyHost = $verifyHost;
    }

    /**
     * send request to hApi
     *     $methodKey maps to the method class via method manager
     *
     * @param string $methodKey
     * @param array $options                -- optional post data
     * @param boolean $adminOverride        -- optional exclude client_id
     * @throws HapiException
     * @throws \Ei\Model\Exception\ModelException
     * @throws \Exception
     * @return \Ei\Model\AbstractModel | array
     */
    public function callMethod($methodKey, $options = array(), $adminOverride = false)
    {
        if ($this->_clientId <= 0 && !$adminOverride) {
            if (!in_array($methodKey, $this->_nullIdentityMethods)) {
                throw new HapiException('The client id has not been set', HapiExceptionCodes::NO_CLIENT_ID_SPECIFIED);
            }
        } else {
            if (!isset($options['client_id']) && !$adminOverride) {
                $options['client_id'] = $this->_clientId;
            }
        }

        $this->_method = $this->_methodManager->get(
            $methodKey,
            $options
        );

        $tId = microtime(true);
        $methodName = $this->_method->getHapiMethodName();
        $this->logHandler->addContextVar('id', $tId);
        $this->logHandler->addContextVar('method_name', $methodName);
        $this->logHandler->addContextVar('hapi_key', $this->getKey());
        $this->logHandler->logHttpContext(
            'hAPI client request',
            0,
            'hapi_client_request'
        );

        try {
            $this->_callApi();
        } catch (CurlException $e) {
            throw new HapiException($e->getMessage(), $e->getCode());
        } catch (HttpCodeException $e) {
            throw new HapiException($e->getMessage(), $e->getCode());
        } catch (MethodException $e) {
            throw new HapiException($e->getMessage(), $e->getCode());
        } catch (ModelException $e) {
            //thrown when we tried to create a model object or collection
            throw $e;
        } catch (\Exception $e) {
            //Eek.
            throw $e;
        }
        $requestTime = microtime(true) - $tId;

        $this->logHandler->addContextVar('id', $tId);
        $this->logHandler->addContextVar('method_name', $methodName);
        $this->logHandler->addContextVar('time_taken', $requestTime);
        $this->logHandler->addContextVar('hapi_key', $this->getKey());
        $this->logHandler->logHttpContext(
            'hAPI client response',
            0,
            'hapi_client_response'
        );

        if ($this->_method->isSuccess()) {
            return $this->_method->getResponse();
        } else {
            // hApi sent back an error message
            return array(
                    'error' => $this->_method->getErrorMessage()
            );
        }
    }

    /**
     * attempt to log the user in and save the key / secret
     *     b/c this method call is slightly different than all the rest,
     *     we do not use the methodManager
     *
     * @param string $username
     * @param string $password
     * @return \Ei\Model\AbstractModel | array
     */
    public function validateCredentials($username, $password)
    {
        $this->_method = $this->_methodManager->get(
            'hapi.authkeys.read',
            array(
                    'username' => $username,
                    'password' => $password
            )
        );

        $tId = microtime(true);
        $this->logHandler->addContextVar('method_name', 'hapi.authkeys.read');
        $this->logHandler->addContextVar('username', $username);
        $this->logHandler->addContextVar('id', $tId);
        $this->logHandler->logHttpContext(
            "HapiClient debug",
            0,
            'hapi_client_request'
        );
        try {
            $this->_callApi();
        } catch (CurlException $e) {
            throw new HapiException($e->getMessage(), $e->getCode());
        } catch (HttpCodeException $e) {
            throw new HapiException($e->getMessage(), $e->getCode());
        } catch (MethodException $e) {
            throw new HapiException($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            //Eek.
            throw $e;
        }
        $requestTime = microtime(true) - $tId;

        $this->logHandler->addContextVar('id', $tId);
        $this->logHandler->addContextVar('method_name', 'hapi.authkeys.read');
        $this->logHandler->addContextVar('username', $username);
        $this->logHandler->addContextVar('time_taken', $requestTime);
        $this->logHandler->logHttpContext(
            'hAPI client response',
            0,
            'hapi_client_response'
        );

        if ($this->_method->isSuccess()) {
            $hapiResponse = $this->_method->getResponse();
            $this->_key = $hapiResponse['key'];
            $this->_secret = $hapiResponse['secret'];
            return $hapiResponse;
        } else {
            // hApi sent back an error message
            return array(
                    'error' => $this->_method->getErrorMessage()
                    );
        }
    }

    /**
     * send request to hApi, using $this->_method
     *     return $this->_method
     *     with response info inside it
     *
     * @return \Ei\Plugin\Hapi\Method\MethodInterface
     * @throws CurlException
     * @throws HttpCodeException
     */
    protected function _callApi()
    {
        // curl session
        $ch = curl_init();
        $key = $this->_key;
        $secret = $this->_secret;
        if ($this->_method->isAdminOnlyMethod()) {
            $key = $this->_adminKey;
            $secret = $this->_adminSecret;
        }
        curl_setopt($ch, CURLOPT_URL, $this->_method->getUrl(
            $this->_endpoint,
            $key,
            $secret
        ));
        curl_setopt($ch, CURLOPT_HEADER, 1);
        if (!empty($this->_sslVersion)) {
            curl_setopt($ch, CURLOPT_SSLVERSION, $this->_sslVersion);
        }

        $headers = array('X-Ubersmith-Orig-IP: ' . $this->_clientIp);
        if ($this->_clientId && !$this->_method->isAuthRequest()) {
            $userHeader = $this->_clientId;
            if ($this->_contactId) {
                $userHeader .= '-' . $this->_contactId;
            }

            if ($this->_method->isPortalMethod()) {
                $secret = $this->_portalSecret;
            } else {
                $secret = $this->_adminSecret;
            }

            //create an IV and and save an encoded version for header transport
            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::ENCRYPTION_MODE));
            $encodedIv = base64_encode($iv);

            //encrypt the data, keeping in mind the data is already base64 encoded
            $userHeader = openssl_encrypt($userHeader, self::ENCRYPTION_MODE, $secret, 0, $iv);
            $headers[] = 'X-Ubersmith-Orig-User: ' . $userHeader;
            $headers[] = 'X-Ubersmith-Orig-User-IV: ' . $encodedIv;
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Portal hAPI PHP Client; $Revision: 1 $');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_method->getPostData());
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->_verifyPeer);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->_verifyHost);
        if ($this->_method->isAuthRequest()) {
            curl_setopt($ch, CURLOPT_USERPWD, $this->_method->getAuth());
        }
        if ($this->_method->requiresEncoding()) {
            curl_setopt($ch, CURLOPT_ENCODING, $this->_method->getEncoding());
        }

        $response = curl_exec($ch);
        $curlerrcode = curl_errno($ch);
        $curlerr = curl_error($ch);
        $httpCode = 0;
        $responseHeader = '';
        $responseBody = '';
        if (!$curlerrcode) {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $responseHeader = substr($response, 0, $headerSize);
            $responseBody = substr($response, $headerSize);
        }
        curl_close($ch);

        // check for errors
        if ($curlerrcode != 0) {
            throw new CurlException('Request failed. Curl error: ' . $curlerrcode . ': ' . $curlerr, HapiExceptionCodes::CURL_ERROR);
        }
        if ($httpCode != 200) {
            if ($this->_method->handlesHttpCode($httpCode)) {
                $this->_method->setNon200Response($httpCode, $responseBody);
            } else {
                throw new HttpCodeException('Request failed. Http response code: ' . $httpCode . '. Response: ' . $responseBody, HapiExceptionCodes::BAD_HTTP_STATUS);
            }
        } else {
            $this->_method->setResponse($responseBody, $responseHeader);
        }

        return $this->_method;
    }

    /**
     * _key setter
     *
     * @param string $key
     * @return HapiClient
     */
    public function setKey($key)
    {
        $this->_key = $key;
        return $this;
    }

    /**
     * _key getter
     *
     * @return string
     */
    public function getKey()
    {
        return $this->_key;
    }

    /**
     * _secret setter
     *
     * @param string $secret
     * @return HapiClient
     */
    public function setSecret($secret)
    {
        $this->_secret = $secret;
        return $this;
    }

    /**
     * _secret getter
     *
     * @return string
     */
    public function getSecret()
    {
        return $this->_secret;
    }

    /**
     * _username getter
     *
     * @return string
     */
    public function getUserName()
    {
        return $this->_username;
    }

    /**
     * @param int $clientIp
     * @return $this
     */
    public function setClientIp($clientIp)
    {
        $this->_clientIp = $clientIp;
        return $this;
    }

    /**
     * @param int $clientId
     * @return $this
     */
    public function setClientId($clientId)
    {
        $this->_clientId = $clientId;
        return $this;
    }

    /**
     * @param int $contactId
     * @return $this
     */
    public function setContactId($contactId)
    {
        $this->_contactId = $contactId;
        return $this;
    }

}
