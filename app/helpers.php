<?php

use App\Models\Gym;
use App\Tenancy\TenantManager;

if (! function_exists('panel_theme')) {
    /** The viewer's panel theme from the "theme" cookie: "light" or "dark" (default). */
    function panel_theme(): string
    {
        return request()->cookie('theme') === 'light' ? 'light' : 'dark';
    }
}

if (! function_exists('money')) {
    /** Format an amount in Indian Rupees with Indian digit grouping (₹1,23,456.00). */
    function money(float|int|string|null $amount, bool $decimals = true): string
    {
        $amount = (float) $amount;
        $negative = $amount < 0;
        $amount = abs($amount);

        $whole = (string) floor($amount);
        $fraction = $decimals ? '.'.str_pad((string) round(($amount - floor($amount)) * 100), 2, '0', STR_PAD_LEFT) : '';
        if ($fraction === '.100') {
            $whole = (string) ((int) $whole + 1);
            $fraction = '.00';
        }

        if (strlen($whole) > 3) {
            $last3 = substr($whole, -3);
            $rest = substr($whole, 0, -3);
            $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
            $whole = $rest.','.$last3;
        }

        return ($negative ? '-' : '').config('gymai.currency_symbol', '₹').$whole.$fraction;
    }
}

if (! function_exists('current_gym')) {
    function current_gym(): ?Gym
    {
        return app(TenantManager::class)->gym();
    }
}
