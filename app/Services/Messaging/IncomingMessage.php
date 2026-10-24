<?php

namespace App\Services\Messaging;

final class IncomingMessage
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $canal,
        public readonly string $externalUserId,
        public readonly string $chatId,
        public readonly string $texto,
        public readonly ?string $username = null,
        public readonly ?string $displayName = null,
        public readonly ?int $timestamp = null,
        public readonly array $metadata = [],
    ) {}
}
