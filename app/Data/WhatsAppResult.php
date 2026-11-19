<?php

namespace App\Data;

final readonly class WhatsAppResult
{
    public function __construct(public string $status, public int $httpStatus, public ?string $providerReference = null, public ?string $error = null, public ?string $rawResponse = null) {}

    public function accepted(): bool
    {
        return $this->status === 'accepted_unconfirmed';
    }
}
