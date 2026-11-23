<?php

return [
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Platform Admin'),
        'email' => env('SUPER_ADMIN_EMAIL'),
        'password' => env('SUPER_ADMIN_PASSWORD'),
    ],

    // Default SaaS price (INR) per active member per month. Super Admin can change it from the panel.
    'default_price_per_member' => 20,
    'currency' => 'INR',
    'currency_symbol' => '₹',
    'invoice_due_days' => 15,

    // Member app OTP login.
    'otp' => [
        'length' => 6,
        'ttl_seconds' => 300,          // OTP valid for 5 minutes
        'resend_cooldown_seconds' => 30,
        'max_attempts' => 5,           // wrong guesses allowed per OTP
        'max_sends_per_hour' => 5,     // per phone number
        // Return the OTP in the API response — only for local testing without an SMS provider.
        'expose_in_response' => (bool) env('OTP_EXPOSE_IN_RESPONSE', false),
    ],

    // SMS provider for OTPs. "log" writes messages to storage/logs (development).
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
    ],

    // Slugs that can never be used by a gym because they collide with application routes.
    'reserved_slugs' => [
        'admin', 'app', 'api', 'platform', 'login', 'logout', 'register', 'register-gym', 'join', 'me',
        'member', 'members', 'livewire', 'up', 'storage', 'build', 'assets', 'css', 'js', 'images',
        'password', 'forgot-password', 'reset-password', 'dashboard', 'pricing', 'about', 'contact',
        'terms', 'privacy', 'help', 'support', 'gymai', 'www', 'mail', 'checkin', 'receipt', 'invoice',
        'suspended', 'favicon.ico', 'robots.txt',
    ],
];
