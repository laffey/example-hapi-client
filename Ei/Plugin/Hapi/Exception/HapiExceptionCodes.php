<?php
/**
 * hapi client exception
 *     >= 10000 severe issue
 *     < 10000 important, but common exception
 *     < 1000 minor exception
 *
 */

namespace Ei\Plugin\Hapi\Exception;

class HapiExceptionCodes
{
    /* >= 10000 severe issue */
    const CURL_ERROR                       = 10000;
    const BAD_HTTP_STATUS                  = 10200;
    const BAD_JSON_RESPONSE                = 10300;
    const BAD_UBER_RESPONSE                = 10400;
    const ACTION_NOT_ALLOWED               = 10911;
    const MODEL_NOT_INSTANCIATED           = 10001;
    const NO_CLIENT_ID_SPECIFIED           = 10912;

    /* < 10000 important, but common exception */
    const JSON_MISSING_STATUS              = 6600;
    const JSON_MISSING_ERROR_INFO          = 6601;
    const JSON_MISSING_ERROR_MSG           = 6602;
    const JSON_MISSING_RESPONSE_INFO       = 6603;
    const NO_RESPONSE_MADE_TO_HAPI         = 6604;
    const NO_RESPONSE_HEADER_SET           = 6605;
    const HEADER_MISSING_STATUS            = 6606;
    const HEADER_MISSING_ERROR_INFO        = 6607;
    const HEADER_MISSING_ERROR_MSG         = 6608;
    const HEADER_MISSING_VALUE             = 6609;

    const UBER_MISSING_STATUS_INFO         = 6610;
    const UBER_MISSING_DATA                = 6611;
    const UBER_BAD_RESPONSE_CONTENT        = 6612;

    const HAPI_AUTHENTICATION_ERROR        = 7500;
    const HAPI_INVALID_USER                = 7501;
    const TOO_MANY_LOGIN_ATTEMPTS          = 7502;
    const HAPI_UNEXPECTED_RESPONSE         = 7503;
    const METHOD_PARSING_ERROR             = 7700;

    const MISSING_REQUIRED_METHOD_PARAM    = 8800;
    const INVALID_METHOD_PARAM             = 8801;
    const CLIENT_IS_NOT_OWNER              = 8802;

    /* < 1000 minor exception */
}
