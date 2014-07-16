<?php
/**
 * abstract method class
 */

namespace Ei\Plugin\Hapi\Method;

use \Ei\Model\ModelFactory;
use \Ei\Model\CollectionFactory;
use \Ei\Plugin\Hapi\Method\Exception\MethodException;
use \Ei\Plugin\Hapi\Exception\HapiException;
use \Ei\Model\Exception\ModelException;
use \Ei\Plugin\Hapi\Exception\HapiExceptionCodes;

abstract class AbstractMethod implements MethodInterface
{

    const NO_AGILE_CODE = 11000;
    const NO_AGILE_MESSAGE = 'The server list is currently unavailable.';

    /**
     * if null, no request was sent
     *     otherwise this will be a response obj
     *
     * @var \Ei\Model\AbstractModel | array | \Ei\Model\AbstractModel
     */
    protected $_response = null;

    /**
     * must be defined for each child
     * @var array
     */
    protected $_params = array(
            'method'  => '',
            'format'  => 'json'
    );

    /**
     * either http or https
     * @var string
     */
    protected $_protocol = 'https://';

    /**
     * version of hApi to connect to
     *     1.0 or 1.5
     *
     * @var string
     */
    protected $_version = '1.5';

    /**
     * post data to send to hApi
     * @var array
     */
    protected $_postData = array();

    /**
     * error code from a hApi response
     * @var int
     */
    protected $_hapiErrorCode = 0;

    /**
     * error message from a hApi response
     * @var string
     */
    protected $_hapiErrorMessage;

    /**
     * only used if a non-200 response was received
     * @var int
     */
    protected $_httpStatusCode;

    /**
     * if hapi method must be accessed via admin credentials
     * @var boolean
     */
    protected $_isAdminOnlyMethod = true;

    /**
     * @var ModelFactory
     */
    protected $_modelFactory;

    /**
     * @var CollectionFactory
     */
    protected $_collectionFactory;

    /**
     * parsed values for header response string
     * @var array
     */
    protected $_headers;

    /**
     * array of required parameter names
     * @var array
     */
    protected $_requiredParams = array();

    /**
     * array of required optional names
     * @var array
     */
    protected $_optionalParams = array();

    /**
     * array of static parameters with values
     * @var array
     */
    protected $_staticParams = array();

    /**
     * @var int
     */
    protected $_clientId = 0;

    /**
     * @var bool
     */
    protected $_isAuthRequest = false;

    /**
     * @var string
     */
    protected $_authString = '';

    /**
     * @var bool
     */
    protected $_requiresEncoding = false;

    /**
     * @var string
     */
    protected $_encodingFormat = 'gzip,deflate';

    /**
     * @var string
     */
    protected $_applicationName = APPLICATION_NAME;

    /**
     * @var LogHandler
     */
    protected $_logHandler;

    /**
     * init this method with params
     * @throws MethodException
     * @param array $options
     * @param LogHandler $logHandler
     */
    public function __construct($options = array(), $logHandler = null)
    {
        // if uber, prepend all keys with uber_
        if (isset($this->_params['method']) && $this->_params['method'] === 'uber.api.call') {
            foreach ($options as $key => $value) {
                if (substr($key, 0, 5) !== 'uber_') {
                    $options['uber_'.$key] = $value;
                }
            }
        }

        $allParams = array_merge($this->_requiredParams, $this->_optionalParams);

        // putting this first allows for static params to get overwritten
        foreach ($this->_staticParams as $staticParam => $value) {
            $this->_postData[$staticParam] = $value;
        }

        foreach ($allParams as $paramKey => $param) {
            if (isset($options[$param])) {
                $this->_postData[$param] = $options[$param];
                if ($param == 'client_id' || $param == 'uber_client_id' || $param == 'customer_id') {
                    $this->_clientId = $options[$param];
                }
            } else {
                //if client_id given, and param is customerId, convert
                if ($param == 'customer_id' || $param == 'uber_client_id' || $param == 'client_id') {
                    if (isset($options['customer_id'])) {
                        $this->_postData[$param] = $options['customer_id'];
                        $this->_clientId = $options['customer_id'];
                    } elseif (isset($options['uber_client_id'])) {
                        $this->_postData[$param] = $options['uber_client_id'];
                        $this->_clientId = $options['uber_client_id'];
                    } elseif (isset($options['client_id'])) {
                        $this->_postData[$param] = $options['client_id'];
                        $this->_clientId = $options['client_id'];
                    } elseif (isset($this->_requiredParams[$param])) {
                        throw new MethodException('Missing required param ' . $param, HapiExceptionCodes::MISSING_REQUIRED_METHOD_PARAM);
                    }
                } elseif (isset($this->_requiredParams[$paramKey])) {
                    throw new MethodException('Missing required param ' . $param, HapiExceptionCodes::MISSING_REQUIRED_METHOD_PARAM);
                }
            }
        }

        $this->_logHandler = $logHandler;
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

        $httpQuery = $this->_getHttpQuery($key, $secret);

        return $this->_protocol . $endpoint . '/version/' . $this->_version . '/?'. $httpQuery;
    }

    /**
     * Returns the query part of the url
     *
     * @param string $key
     * @param string $secret
     * @throws MethodException
     * @return string
     */
    protected function _getHttpQuery($key, $secret)
    {
        if (empty($key)) {
            throw new MethodException('Authorization key required for customer');
        }
        if (empty($secret)) {
            throw new MethodException('Authorization secret required for customer');
        }

        // build get request parameter array
        $queryData = $this->_params;
        $queryData['key'] = $key;
        $queryData['timestamp'] = date(DATE_ISO8601);

        // generate api signature value
        $allParams = array_merge($this->getPostData(), $queryData);
        ksort($allParams);
        $signature = $secret;
        foreach ($allParams as $key => $value) {
            $signature .= $key . $value;
        }
        $queryData['api_sig'] = md5($signature);

        return http_build_query($queryData, '', '&');
    }

    /**
     * Returns the post data
     *
     * @return array
     */
    public function getPostData()
    {
        if (empty($this->_postData) || (!is_array($this->_postData) && !is_object($this->_postData))) {
            return array();
        }

        $post = array();

        $this->_flattenArray($this->_postData, $post);

        return $post;
    }

    /**
     * Flattens arrays
     * @param array|\stdClass $arrays
     * @param array $new - saves the new post data into here
     * @param string $prefix - optional string to prefix
     */
    protected function _flattenArray($arrays, &$new = array(), $prefix = null)
    {
        if (is_object($arrays)) {
            $arrays = get_object_vars($arrays);
        }

        foreach ($arrays as $key => $value) {
            $k = isset($prefix) ? $prefix . '[' . $key . ']' : $key;
            if (is_array($value) || is_object($value)) {
                $this->_flattenArray($value, $new, $k);
            } else {
                $new[$k] = $value;
            }
        }
    }

    /**
     * If an username:password must be included in request
     *
     * @return boolean
     */
    public function isAuthRequest()
    {
        return $this->_isAuthRequest;
    }

    /**
     * get the username:password string
     *
     * @return string
     */
    public function getAuth()
    {
        return $this->_authString;
    }

    /**
     * compress?
     *
     * @return boolean
     */
    public function requiresEncoding()
    {
        return $this->_requiresEncoding;
    }

    /**
     * encoding format allowed for this method
     *
     * @return string
     */
    public function getEncoding()
    {
        return $this->_encodingFormat;
    }

    /**
     * set the response from curl execution
     *     separate out the body, status code, curl error info
     * Response validation goes here for each method
     *
     * @param string $body
     * @param string $header
     * @throws MethodException
     */
    public function setResponse($body, $header)
    {
        $data = json_decode($body, true);
        if (!$data) {
            throw new MethodException('Hapi response was not properly formatted json.', HapiExceptionCodes::BAD_JSON_RESPONSE);
        }
        if ($this->_checkResponseStatus($data)) {
            if ($this->_params['format'] == 'json' && isset($data['response'])) {
                $data = $data['response'];
            }
            $this->_createResponse($data);
        }
    }

    /**
     * _response getter
     *
     * @return \Ei\Model\AbstractModel
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * was the communication with hApi a success?
     *
     * @return boolean
     * @throws MethodException
     */
    public function isSuccess()
    {
        if ($this->_hapiErrorCode == 0) {
            if ($this->_response === null) {
                throw new MethodException('No request has been made to hapi', HapiExceptionCodes::NO_RESPONSE_MADE_TO_HAPI);
            }
        } else {
            return false;
        }
        return true;
    }

    /**
     * for non-200 status code responses,
     *     check if this method can handle the response
     *
     * @param int $httpCode
     * @returns false
     */
    public function handlesHttpCode($httpCode)
    {
        return false;
    }

    /**
     * setting response body requires knowledge of the returned http status code
     *
     * @param int $httpCode
     * @param string $responseBody
     * @throws MethodException
     */
    public function setNon200Response($httpCode, $responseBody)
    {
        throw new MethodException('This method does not accept responses other than 200 OK.');
    }

    /**
     * if hApi communication was not a success, get the error message
     *
     * @return string
     */
    public function getErrorMessage()
    {
        return $this->_hapiErrorMessage;
    }

    /**
     * if hApi communication was not a success, get the error code
     *
     * @return int
     */
    public function getErrorCode()
    {
        return $this->_hapiErrorCode;
    }

    /**
     * $_modelFactory setter
     *
     * @param ModelFactory $modelFactory
     * @return $this
     */
    public function setModelFactory(ModelFactory $modelFactory)
    {
        $this->_modelFactory = $modelFactory;
        return $this;
    }

    /**
     * $_collectionFactory setter
     *
     * @param CollectionFactory $collectionFactory
     * @return $this
     */
    public function setCollectionFactory(CollectionFactory $collectionFactory)
    {
        $this->_collectionFactory = $collectionFactory;
        return $this;
    }

    /**
     * _isAdminOnlyMethod getter
     *
     * @return boolean
     */
    public function isAdminOnlyMethod()
    {
        return $this->_isAdminOnlyMethod;
    }

    /**
     * see if an error was returned by hApi
     *
     * @param array $responseData
     * @throws MethodException
     * @throws HapiException
     * @return boolean
     */
    protected function _checkResponseStatus($responseData)
    {
        $result = true;
        //each format version has to be treated differently
        if ($this->_params['format'] == 'json') {
            if ($this->_version == '1.5') {
                if (!isset($responseData['response']) || !isset($responseData['response']['status'])) {
                    throw new MethodException('No status in hApi response. Missing \'response\' or \'status\' attribute.', HapiExceptionCodes::JSON_MISSING_STATUS);
                }
                $response = $responseData['response'];
                if ($response['status'] != 'ok') {
                    if (!isset($response['error'])) {
                        throw new MethodException('Hapi response status is failed, but there is no error information included with response.', HapiExceptionCodes::JSON_MISSING_ERROR_INFO);
                    }
                    $errorInfo = $response['error'];
                    if (!isset($errorInfo['code']) || !isset($errorInfo['message'])) {
                        throw new MethodException('Hapi response status is failed, but there is no error information included with response.', HapiExceptionCodes::JSON_MISSING_ERROR_MSG);
                    } elseif ((int) $errorInfo['code'] == 1) {
                        //check for an auth error response
                        if ($errorInfo['message'] == 'Invalid login or password' || strpos($errorInfo['message'], 'invalid username') !== false) {
                            throw new \Ei\Plugin\Hapi\Exception\HapiException('Hapi reported ' . $errorInfo['message'], $this->_authError($errorInfo['message']));
                        }
                    }
                    $this->_hapiErrorCode = (int)$errorInfo['code'];
                    $this->_hapiErrorMessage = $errorInfo['message'];
                    //for some reason we get an error code 0 returned when an error did occur, so manually set
                    if ($this->_hapiErrorCode === 0) {
                        $this->_hapiErrorCode = -999;
                    }
                    $this->_logHapiError();
                    $result = false;
                }
            } else {
                //check 1.0 response
                if (!isset($responseData['attributes']) || !isset($responseData['attributes']['stat'])) {
                    throw new MethodException('No status in hApi response. Missing \'attributes\' or \'stat\' attribute.', HapiExceptionCodes::JSON_MISSING_STATUS);
                }
                if ($responseData['attributes']['stat'] != 'ok') {
                    if (empty($responseData['err']['attributes']) || !is_array($responseData['err']['attributes'])) {
                        throw new MethodException('Hapi response status is failed, but there is no error information included with response.', HapiExceptionCodes::JSON_MISSING_ERROR_INFO);
                    }

                    $errorInfo = $responseData['err']['attributes'];

                    if (!isset($errorInfo['code']) || !isset($errorInfo['msg'])) {
                        throw new MethodException('Hapi response status is failed, but there is no error information included with response.', HapiExceptionCodes::JSON_MISSING_ERROR_MSG);
                    } elseif ((int) $errorInfo['code'] == 1) {
                        //check for an auth error response
                        if ($errorInfo['msg'] == 'Invalid login or password' || strpos($errorInfo['msg'], 'invalid username') !== false) {
                            throw new \Ei\Plugin\Hapi\Exception\HapiException('Hapi reported ' . $errorInfo['msg'], $this->_authError($errorInfo['msg']));
                        }
                    }
                    $this->_hapiErrorCode = (int)$errorInfo['code'];
                    $this->_hapiErrorMessage = $errorInfo['msg'];
                    //for some reason we get an error code 0 returned when an error did occur, so manually set
                    if ($this->_hapiErrorCode === 0) {
                        $this->_hapiErrorCode = -999;
                    }
                    $this->_logHapiError();
                    $result = false;
                }
            }
        } elseif ($this->_params['format'] == 'raw') {
            //response issues will not be in body, but in header
            $hapiStatus = $this->getHeaderValue('X-hAPI-Status');
            if (empty($hapiStatus)) {
                throw new MethodException('No status in hApi response header. Missing or empty \'X-hAPI-Status\'.', HapiExceptionCodes::HEADER_MISSING_STATUS);
            }
            if ($hapiStatus != 'ok') {
                $this->_hapiErrorCode = $this->getHeaderValue('X-hAPI-Error-Code');
                if ($this->_hapiErrorCode === '') {
                    throw new MethodException('Hapi response status is failed, but there is no error information included with response.', HapiExceptionCodes::HEADER_MISSING_ERROR_INFO);
                }
                $this->_hapiErrorMessage = 'File id must be invalid, as no file information was returned';
                //for some reason we get an error code 0 returned when an error did occur, so manually set
                if ($this->_hapiErrorCode == 0) {
                    $this->_hapiErrorCode = -999;
                }
                $this->_logHapiError();
                $result = false;
            }
        } else {
            if (!isset($responseData['@attributes']) || !isset($responseData['@attributes']['stat'])) {
                throw new MethodException('No status in hApi response. Missing \'stat\' attribute.', HapiExceptionCodes::JSON_MISSING_STATUS);
            }
            if ($responseData['@attributes']['stat'] != 'ok') {
                if (empty($responseData['err']) || !is_array($responseData['err'])) {
                    throw new MethodException('Hapi response status is failed, but there is no error information included with response.', HapiExceptionCodes::JSON_MISSING_ERROR_INFO);
                }
                foreach ($responseData['err'] as $errorInfo) {
                    if (!isset($errorInfo['code']) || !isset($errorInfo['msg'])) {
                        throw new MethodException('Hapi response status is failed, but there is no error information included with response.', HapiExceptionCodes::JSON_MISSING_ERROR_MSG);
                    } elseif (!empty($errorInfo['code'][0]['#text']) && (int)$errorInfo['code'][0]['#text'] == 1) {
                        throw new \Ei\Plugin\Hapi\Exception\HapiException('Hapi reported '.$errorInfo['msg'], $this->_authError($errorInfo['msg']));
                    }
                    //for some reason we get an error code 0 returned when an error did occur, so manually set
                    if ($this->_hapiErrorCode === 0) {
                        $this->_hapiErrorCode = -999;
                    }
                    $this->_logHapiError();
                }

                $result = false;
            }
        }
        if ($this->_hapiErrorCode === self::NO_AGILE_CODE) {
            // the real errmsg's been logged; obscure it now.
            $this->_hapiErrorMessage = self::NO_AGILE_MESSAGE;
        }
        return $result;
    }

    /**
     * see if an error was returned by uber api call
     *
     * @param array $responseData
     * @return boolean
     * @throws MethodException
     */
    protected function _checkUberResponse($responseData)
    {
        if (!$responseData) {
            throw new MethodException('Invalid response received from the server.', HapiExceptionCodes::BAD_UBER_RESPONSE);
        }
        if (!array_key_exists('status', $responseData) || !array_key_exists('error_code', $responseData) || !array_key_exists('error_message', $responseData)) {
            throw new MethodException('Uber response is missing basic information on status and errors', HapiExceptionCodes::UBER_MISSING_STATUS_INFO);
        }
        if (!$responseData['status']) {
            // uber replied with error
            $this->_hapiErrorCode = (int)$responseData['error_code'];
            $this->_hapiErrorMessage = $responseData['error_message'];
            if ($this->_hapiErrorCode == 0) {
                $this->_hapiErrorCode = -999;
            }
            return false;
        }
        if (!array_key_exists('data', $responseData)) {
            // status is ok, but we didn't receive the data??
            throw new MethodException('Uber response did not include any data', HapiExceptionCodes::UBER_MISSING_DATA);
        }

        return true;
    }


    /**
     * If an authentication error occurs, determine which Exception Code to use.
     * In the future, consider making this more generic by accepting
     *   more error codes as params, or by returning an exception class
     *   along with the code.
     *
     * @param string $message The message that was given by an auth error.
     * @return string The HAPI Exception Code for the given error
     */
    private function _authError($message)
    {
        if (strpos($message, 'invalid username') !== false) {
            return HapiExceptionCodes::HAPI_INVALID_USER;
        } elseif (strpos($message, 'too many login attempts') !== false) {
            return HapiExceptionCodes::TOO_MANY_LOGIN_ATTEMPTS;
        }

        return HapiExceptionCodes::HAPI_AUTHENTICATION_ERROR;
    }



    /**
     * temp replacement for a factory
     *
     * @param array $data
     * @param string|null $responseType
     * @return void
     */
    protected function _createResponse($data, $responseType = null)
    {
        if (!empty($responseType)) {
            //specific type of response to return
            $responseClass = '\Ei\Model\Hapi\ResponseType\\' . $responseType;
            $this->_response = new $responseClass($data);
        } else {
            //return generic array
            $this->_response = $data;
        }
    }

    /**
     * @param $modelType
     * @param array $data
     */
    protected function _setResponseModel($modelType, array $data)
    {
        $this->_response = $this->_createModel($modelType, $data);
    }

    /**
     * @param $modelType
     * @param array $data
     */
    protected function _setResponseCollection($modelType, array $data)
    {
        $this->_response = $this->_createCollection($modelType, $data);
    }

    /**
     * @param $modelType
     * @param $data
     * @return array
     */
    protected function _createCollection($modelType, $data)
    {
        $collection = array();

        foreach ($data as $key => $responseData) {
            $collection[$key] = $this->_createModel($modelType, $responseData);
        }

        return $collection;
    }

    /**
     * create model with response data
     *
     * @param string $modelType     *map in ModelFactory
     * @param array $responseData           *array of arrays of response data
     * @throws MethodException
     * @throws ModelException
     * @returns model
     */
    protected function _createModel($modelType, array $responseData)
    {
        try {
            $modelProperties = $this->_modelFactory->convertToModelProperties($modelType, $responseData);
            return $this->_modelFactory->create($modelType, $modelProperties);
        } catch (ModelException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new MethodException('Model, ' . $modelType . ' could not be instanciated. Error: ' . $e->getMessage(), HapiExceptionCodes::MODEL_NOT_INSTANCIATED);
        }
    }

    /**
     * turn the header into usable hash array
     *
     * @param string $header
     * @return array
     */
    protected function _parseHeader($header)
    {
        if (empty($header)) {
            return array();
        }

        $headerLines = explode("\n", $header);
        $this->_headers = array();
        foreach ($headerLines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $lineParts = explode(':', $line);
                if (count($lineParts) > 1) {
                    $headerKey = array_shift($lineParts);
                    $headerValue = '';

                    foreach ($lineParts as $linePart) {
                        $headerValue .= ($headerValue == '' ? '' : ':') . $linePart;
                    }

                    $this->_headers[$headerKey] = trim($headerValue);
                }
            }
        }
        return $this->_headers;
    }

    /**
     * grab value from the header, by specifying the header key
     *
     *         example header:
     *           Date: Tue, 05 Mar 2013 21:14:16 GMT
     *           Server: Apache/2.2.22 (Ubuntu)
     *           X-Powered-By: PHP/5.3.10-1ubuntu3.4
     *           X-hAPI-Status: ok
     *           Content-Length: 3359
     *           Content-Type: application/json; charset=utf-8
     *
     *         example function call:
     *           getHeaderValue('X-Powered-By')
     *           --> returns 'PHP/5.3.10-1ubuntu3.4'
     *
     * @param string $headerKey
     * @throws MethodException
     * @return string
     */
    public function getHeaderValue($headerKey)
    {
        if (!is_array($this->_headers)) {
            throw new MethodException('The response header has not been set or parsed yet.', HapiExceptionCodes::NO_RESPONSE_HEADER_SET);
        }

        if (!isset($this->_headers[$headerKey])) {
            throw new MethodException('Invalid or missing hapi response header specified, "' . $headerKey . '".', HapiExceptionCodes::HEADER_MISSING_VALUE);
        }

        return $this->_headers[$headerKey];
    }

    /**
     * we want to track hapi's error responses
     */
    protected function _logHapiError()
    {
        if (empty($this->_logHandler)) {
            return;
        }

        $result = array();
        foreach ($this->_postData as $key => $value) {
            // I'm converting arguments to their type
            // (prevents passwords from ever getting logged as anything other than 'string')
            if ($key == 'password' && $key == 'secret') {
                $value = '*****';
            }
            $result[] = $key . '=' . $value;
        }

        $context = array(
            'message'   => $this->_hapiErrorMessage,
            'method'    => $this->getHapiMethodName(),
            'version'   => $this->_version,
            'format'    => $this->_params['format'],
            'code'      => $this->_hapiErrorCode,
            'params'    => implode(" ", $result)
        );

        $this->_logHandler->addContextVar('hapi_context', $context);
        $this->_logHandler->logHttpContext('hAPI Error', 501, 'hapi_error');

    }

    /**
     * return the hapi method called, plus additional info:
     *      if $extended = true,
     *          add to the string: is_admin
     *          and if uber.api.call, uber method name
     *      else,
     *          just return the hapi method name
     *
     * @param boolean $extended
     * @return string
     */
    public function getHapiMethodName($extended = true)
    {
        $methodName = '';

        if (isset($this->_params['method'])) {
            $methodName = $this->_params['method'];
            if ($extended) {
                if ($this->_isAdminOnlyMethod) {
                    $methodName .= '::as_admin';
                }
                if ($this->_params['method'] == 'uber.api.call' && isset($this->_postData['uber_method_name'])) {
                    $methodName .= '::' . $this->_postData['uber_method_name'];
                }
            }
        }

        return $methodName;
    }

    public function isPortalMethod()
    {
        $parts = explode('.', $this->_params['method']);

        if ($parts[0] == 'portal') {
            return true;
        }

        return false;
    }
}
