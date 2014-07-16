<?php
/**
 * interface for hapi methods
 */

namespace Ei\Plugin\Hapi\Method;

/**
 * @category   Ei
 * @package    Ei_Plugin_Hapi
 * @subpackage Method
 */
interface MethodInterface
{
    /**
     * Returns the full url for the method, including url query
     *
     * @param string $endpoint
     * @param string $key
     * @param string $secret
     * @return string
     */
    public function getUrl($endpoint, $key, $secret);

    /**
     * Returns the post data
     *
     * @return array
     */
    public function getPostData();

    /**
     * If an username:password must be included in request
     *
     * @return boolean
     */
    public function isAuthRequest();

    /**
     * get the username:password string
     *
     * @return string
     */
    public function getAuth();

    /**
     * compress?
     *
     * @return boolean
     */
    public function requiresEncoding();

    /**
     * encoding format allowed for this method
     *
     * @return string
     */
    public function getEncoding();

    /**
     * _response setter
     * set the response from curl execution
     *     separate out the body, status code, curl error info
     *
     * @param string $body
     * @param string $header
     * @return void
     */
    public function setResponse($body, $header);

    /**
     * _response getter
     *
     * @return \Ei\Model\AbstractModel
     */
    public function getResponse();

    /**
     * was the communication with hApi a success?
     *
     * @return boolean
     */
    public function isSuccess();

    /**
     * if was hApi communication was not a success, get the error message
     *
     * @return string
     */
    public function getErrorMessage();

    /**
     * for non-200 stats code responses,
     *     check if this method can handle the response
     *
     * @param int $httpCode
     */
    public function handlesHttpCode($httpCode);

    /**
     * setting response body requires knowledge of the returned http status code
     *
     * @param int $httpCode
     * @param string $responseBody
     * @return void
     */
    public function setNon200Response($httpCode, $responseBody);
}
