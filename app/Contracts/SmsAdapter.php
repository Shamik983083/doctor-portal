<?php

namespace App\Contracts;

interface SmsAdapter
{
    /**
     * Send an SMS message.
     *
     * @param string $to   Recipient phone number in E.164 format (+15551234567).
     * @param string $body Message text. Must not contain PHI — caller's responsibility.
     *
     * @return bool True on success or accepted-for-delivery. False on a
     *              recoverable failure (invalid number, adapter disabled).
     *              Never throws — callers do not handle SMS exceptions.
     */
    public function send(string $to, string $body): bool;

    /** Whether this adapter will actually deliver messages (vs. mock/log only). */
    public function isLive(): bool;
}
