<?php

namespace App\Contracts;

use App\Data\WhatsAppResult;

interface WhatsAppProvider
{
    public function sendText(string $recipient, string $message): WhatsAppResult;
}
