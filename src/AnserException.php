<?php

declare(strict_types=1);

namespace Anser;

/**
 * The API answered with an error. `$errorCode` is the API's error code (`not_found`, `validation_error`, …),
 * `$param` the field it is about (`to[1]`, `attachments[0].content`) when there is one, and `$requestId` what to
 * look up in the request logs; `getCode()` is the HTTP status.
 */
class AnserException extends \RuntimeException
{
    public readonly ?string $requestId;

    /**
     * @param array<string, string> $headers lower-case names
     */
    public function __construct(
        public readonly int $statusCode,
        public readonly string $errorCode,
        string $message,
        public readonly array $headers = [],
        public readonly ?string $param = null,
    ) {
        parent::__construct($message, $statusCode);
        $this->requestId = $headers['x-request-id'] ?? null;
    }
}
