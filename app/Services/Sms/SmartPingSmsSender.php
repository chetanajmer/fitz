<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * SmartPing SMS gateway (pgapi.smartping.io). Indian operators only deliver text registered on DLT, so every OTP is
 * sent as the registered template with our code in it, whatever wording the caller used. All our OTP messages start
 * with the code. Credentials come from .env and are never logged.
 */
class SmartPingSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        $config = config('gymai.sms.smartping');

        if (! preg_match('/^\d{4,8}\b/', trim($message), $code)) {
            throw new RuntimeException('SmartPing can only send OTP messages that start with the code.');
        }
        if (blank($config['username']) || blank($config['password'])) {
            throw new ApiException('SMS sending is not set up yet. Please try again later.', 'OTP_SEND_FAILED', 503);
        }

        $digits = preg_replace('/\D/', '', $phone);

        try {
            $response = Http::timeout(15)->get($config['url'], [
                'username' => $config['username'],
                'password' => $config['password'],
                'unicode' => 'false',
                'from' => $config['sender'],
                'to' => '91'.substr($digits, -10),
                'text' => str_replace('{otp}', $code[0], $config['template']),
            ]);
            $accepted = $response->json('statusCode') == 200 && $response->json('state') === 'SUBMIT_ACCEPTED';
        } catch (Throwable $e) {
            $accepted = false;
            $response = null;
            Log::error('SmartPing SMS could not be sent', ['error' => $e->getMessage()]);
        }

        if (! $accepted) {
            if ($response) {
                Log::error('SmartPing SMS was not accepted', ['status' => $response->status(), 'reply' => mb_substr($response->body(), 0, 300)]);
            }

            throw new ApiException("We couldn't send the SMS. Please try again in a minute.", 'OTP_SEND_FAILED', 503);
        }
    }
}
