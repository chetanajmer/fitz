<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/** Development driver: writes SMS messages to the application log instead of sending them. */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('[SMS] to '.$phone.': '.$message);
    }
}
