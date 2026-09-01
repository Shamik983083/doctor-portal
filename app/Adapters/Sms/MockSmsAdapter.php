<?php

namespace App\Adapters\Sms;

use App\Contracts\SmsAdapter;
use Illuminate\Support\Facades\Log;

/**
 * No-network SMS adapter — used when SMS_ENABLED=false, SMS_BAA_CONFIRMED=false,
 * or SMS_DRIVER=mock. Validates the number format and logs the send; never
 * actually delivers anything.
 */
final class MockSmsAdapter implements SmsAdapter
{
    public function send(string $to, string $body): bool
    {
        if (! $this->isValidE164($to)) {
            Log::warning('[SMS MOCK] Invalid E.164 number — send skipped.', ['to' => $to]);
            return false;
        }

        Log::info('[SMS MOCK] Would send SMS.', [
            'to'   => $to,
            'body' => $body,
        ]);

        return true;
    }

    public function isLive(): bool
    {
        return false;
    }

    private function isValidE164(string $number): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $number);
    }
}
