<?php
/**
 * abstract hapi client exception
 * 
 */

namespace Ei\Plugin\Hapi\Exception;

use Ei\Util\LogHandler;

abstract class AbstractHapiException extends \Exception implements HapiExceptionInterface
{
    /**
     * Holds an instance of the log handler for logging all exceptions that extend this
     * exception class.
     *
     * @var LogHandler
     */
    protected static $logger;

    /**
     * Sets a logger to use for logging information about all exceptions extending this base type.
     *
     * @param LogHandler $logger The logger to use for logging these exceptions.
     */
    public static function setExceptionLogger(LogHandler $logger)
    {
        static::$logger = $logger;
    }

    /**
     * We catch most hapi plugin issues, but we still want to debug this stuff
     * So here we will override the default behavior of \Exception, so we can
     * log the suppressed exceptions
     *
     * @param string $message The message.
     * @param int $code (optional) Some code.
     * @param \Exception $previous (optional) A previous exception.
     */
    public function __construct($message, $code = null, $previous = null)
    {
        parent::__construct($message, $code, $previous);

        //log this exception if a logger is available
        if (static::$logger) {
            static::$logger->handleException($this);
        }
    }
}
