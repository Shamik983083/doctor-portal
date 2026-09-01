<?php

namespace App\Adapters\Sms;

use App\Contracts\SmsAdapter;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Twilio SMS adapter.
 *
 * Active only when SMS_ENABLED=true AND SMS_BAA_CONFIRMED=true AND SMS_DRIVER=twilio.
 * The AppServiceProvider binding enforces both gates; this class does not re-check them.
 *
 * Uses the Twilio Messages REST API directly via Laravel Http so the project
 * does not need the Twilio SDK package at this stage.
 */
final class TwilioSmsAdapter implements SmsAdapter
{
    private string $sid;
    private string $token;
    private string $from;

    public function __construct(string $sid, string $token, string $from)
    {
        $this->sid   = $sid;
        $this->token = $token;
        $this->from  = $from;
    }

    public function send(string $to, string $body): bool
    {
        if (! $this->isValidE164($to)) {
            Log::warning('[SMS TWILIO] Invalid E.164 number — send skipped.', ['to' => $to]);
            return false;
        }

        try {
            $response = Http::withBasicAuth($this->sid, $this->token)
                ->asForm()
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->sid}/Messages.json", [
                    'To'   => $to,
                    'From' => $this->from,
                    'Body' => $body,
                ]);

            if ($response->successful()) {
                return true;
            }

            Log::error('[SMS TWILIO] Delivery failed.', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'to'     => $to,
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('[SMS TWILIO] Exception during send.', [
                'error' => $e->getMessage(),
                'to'    => $to,
            ]);

            return false;
        }
    }

    public function isLive(): bool
    {
        return true;
    }

    private function isValidE164(string $number): bool
    {
        return (bool) preg_match('/^\+[1-9]\d{7,14}$/', $number);
    }
}
