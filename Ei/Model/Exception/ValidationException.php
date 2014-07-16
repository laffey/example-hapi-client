<?php

namespace Ei\Model\Exception;

use Ei\Model\ValidatableInterface;

class ValidationException extends \DomainException implements ExceptionInterface
{
    /**
     * Errors.
     *
     * @var array
     */
    protected $errors = array();

    /**
     * The original document which caused the validation exception.
     *
     * @var ValidatableInterface
     */
    protected $document;

    /**
     * @param array $errors The array of errors per the ValidatableInterface.
     * @param ValidatableInterface $document
     */
    public function __construct(array $errors, ValidatableInterface $document)
    {
        $this->errors = $errors;
        $this->document = $document;
    }

    /**
     * Retrieves all the errors.
     *
     * @return array All the errors.
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Retrieves the errors for a field if there are any.
     *
     * @param string $field The field name to retrieve the error for.
     *
     * @return array|string|null Null if not found, array if multiple errors, or string if a single error.
     */
    public function getFieldError($field)
    {
        if (isset($this->errors[$field])) {
            return $this->errors[$field];
        }
    }

    /**
     * Retrieves the original document which caused the validation issue.
     *
     * @return ValidatableInterface
     */
    public function getDocument()
    {
        return $this->document;
    }
}
