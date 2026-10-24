<?php

declare(strict_types=1);

/**
 * An error that is safe to show to the API client.
 * Anything else thrown inside a request is logged and reported as a
 * generic 500 error so internal details never reach the browser.
 */
final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $fieldErrors = []
    ) {
        parent::__construct($message);
    }

    public static function validation(array $fieldErrors): self
    {
        return new self(422, 'validation_failed', 'Please correct the highlighted fields.', $fieldErrors);
    }

    public static function notFound(string $message = 'The requested resource was not found.'): self
    {
        return new self(404, 'not_found', $message);
    }
}
