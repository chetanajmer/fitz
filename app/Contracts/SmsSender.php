<?php

namespace App\Contracts;

/**
 * Sends transactional SMS (OTP codes). Bind a real provider (MSG91, Twilio, Gupshup…)
 * in AppServiceProvider based on config('gymai.sms.driver').
 */
interface SmsSender
{
    public function send(string $phone, string $message): void;
}
