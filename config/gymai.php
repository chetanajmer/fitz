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

    // Member app membership card: show "Expiring soon" (and the renew call-to-action) this many days before the end date.
    'membership_expiring_soon_days' => 7,

    // Member app OTP login.
    'otp' => [
        'length' => 6,
        'ttl_seconds' => 600,          // OTP valid for 10 minutes (the registered SMS template says 10)
        'resend_cooldown_seconds' => 30,
        'max_attempts' => 5,           // wrong guesses allowed per OTP
        'max_sends_per_hour' => 5,     // per phone number
        'max_sends_per_ip' => 15,      // per IP address within ip_window_minutes (the store review number is exempt)
        'ip_window_minutes' => 10,
        // Return the OTP in the API response — only for local testing without an SMS provider.
        'expose_in_response' => (bool) env('OTP_EXPOSE_IN_RESPONSE', false),
        // Store review account (Google Play): this number always gets this code and no SMS is sent. Leave empty to turn off.
        'demo_phone' => env('OTP_DEMO_PHONE'),
        'demo_code' => env('OTP_DEMO_CODE'),
    ],

    // SMS provider for OTPs. "log" writes messages to storage/logs (development).
    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        // SMS_DRIVER=smartping. The text must match the template registered on DLT exactly; {otp} is the code.
        'smartping' => [
            'url' => env('SMARTPING_URL', 'https://pgapi.smartping.io/fe/api/v1/send'),
            'username' => env('SMARTPING_USERNAME'),
            'password' => env('SMARTPING_PASSWORD'),
            'sender' => env('SMARTPING_SENDER', 'PAMFIN'),
            'template' => env('SMARTPING_TEMPLATE', 'Your OTP for verification is {otp} It is valid for 10 minutes. Do not share this code with anyone. PAM FINCAP'),
        ],
    ],

    // Slugs that can never be used by a gym because they collide with application routes.
    'reserved_slugs' => [
        'admin', 'app', 'api', 'platform', 'login', 'logout', 'register', 'register-gym', 'join', 'join-gym', 'me',
        'member', 'members', 'livewire', 'up', 'storage', 'build', 'assets', 'css', 'js', 'images',
        'password', 'forgot-password', 'reset-password', 'dashboard', 'pricing', 'about', 'contact',
        'terms', 'privacy', 'privacy-policy', 'delete-account', 'help', 'support', 'gymai', 'www', 'mail', 'checkin', 'receipt', 'invoice',
        'suspended', 'login-as', 'favicon.ico', 'robots.txt', 'gyms',
    ],
];
