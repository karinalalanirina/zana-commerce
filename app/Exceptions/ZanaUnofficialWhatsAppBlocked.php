<?php

namespace App\Exceptions;

use RuntimeException;

class ZanaUnofficialWhatsAppBlocked extends RuntimeException
{
    public function __construct(
        public readonly string $provider,
        public readonly string $operation,
        public readonly ?int $workspaceId = null,
        ?string $message = null,
    ) {
        parent::__construct($message ?: 'Unofficial WhatsApp providers are disabled for this environment.');
    }
}
