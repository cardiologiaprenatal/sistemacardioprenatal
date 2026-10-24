<?php

namespace App\Contracts;

interface MessagingChannel
{
    public function name(): string;

    public function send(string $externalChatId, string $text): bool;
}
