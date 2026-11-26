<?php

namespace Tests\Feature;

use App\Contracts\SmsSender;
use App\Exceptions\ApiException;
use App\Services\Sms\SmartPingSmsSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmartPingSmsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['gymai.sms.driver' => 'smartping', 'gymai.sms.smartping.username' => 'user', 'gymai.sms.smartping.password' => 'secret-pass']);
    }

    public function test_the_code_is_sent_in_the_registered_template(): void
    {
        Http::fake(['pgapi.smartping.io/*' => Http::response(['statusCode' => 200, 'state' => 'SUBMIT_ACCEPTED'])]);

        $sms = app(SmsSender::class);
        $this->assertInstanceOf(SmartPingSmsSender::class, $sms);
        $sms->send('+91 98765-43210', '482913 is your Gym 24 app login OTP. It is valid for 10 minutes.');

        Http::assertSent(function (Request $request) {
            return $request['to'] === '919876543210' && $request['from'] === 'PAMFIN' && $request['username'] === 'user'
                && $request['text'] === 'Your OTP for verification is 482913 It is valid for 10 minutes. Do not share this code with anyone. PAM FINCAP';
        });
    }

    public function test_a_refused_sms_fails_clearly_without_logging_the_password(): void
    {
        Http::fake(['pgapi.smartping.io/*' => Http::response(['statusCode' => 400, 'state' => 'REJECTED', 'description' => 'Template mismatch'])]);
        Log::spy();

        try {
            app(SmsSender::class)->send('9876543210', '123456 is your Fitnix sign-in OTP.');
            $this->fail('The refused SMS was not reported.');
        } catch (ApiException $e) {
            $this->assertSame('SMS_FAILED', $e->errorCode);
        }

        Log::shouldHaveReceived('error')->withArgs(fn (string $message, array $context) => ! str_contains(json_encode($context), 'secret-pass'));
    }

    public function test_messages_without_a_code_are_not_sent(): void
    {
        Http::fake();
        $this->expectException(\RuntimeException::class);

        try {
            app(SmsSender::class)->send('9876543210', 'Your gym is closed tomorrow.');
        } finally {
            Http::assertNothingSent();
        }
    }
}
