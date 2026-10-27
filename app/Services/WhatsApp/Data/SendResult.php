<?php

namespace App\Services\WhatsApp\Data;

/**
 * Normalized outcome of one send attempt. "retryable" marks transient failures
 * (timeouts, rate limits, 5xx) that the queue should retry with backoff.
 */
final readonly class SendResult
{
    /**
     * @param  array<string, mixed>  $response  Provider response body, without credentials.
     */
    public function __construct(
        public bool $success,
        public ?string $messageId = null,
        public ?string $error = null,
        public ?int $httpStatus = null,
        public array $response = [],
        public bool $retryable = false,
    ) {}

    /**
     * @param  array<string, mixed>  $response
     */
    public static function sent(?string $messageId, ?int $httpStatus = null, array $response = []): self
    {
        return new self(true, $messageId, null, $httpStatus, $response);
    }

    /**
     * @param  array<string, mixed>  $response
     */
    public static function failed(string $error, ?int $httpStatus = null, array $response = [], bool $retryable = false): self
    {
        return new self(false, null, mb_substr($error, 0, 500), $httpStatus, $response, $retryable);
    }
}
