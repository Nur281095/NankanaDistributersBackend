<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class BusinessException extends Exception
{
    public function __construct(
        string $message,
        private readonly int $statusCode = Response::HTTP_UNPROCESSABLE_ENTITY,
        private readonly ?array $errors = null,
    ) {
        parent::__construct($message);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, array<int, string>>|null
     */
    public function errors(): ?array
    {
        return $this->errors;
    }
}
