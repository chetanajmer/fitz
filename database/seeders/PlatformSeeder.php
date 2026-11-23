<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use App\Models\PricingRate;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        if (! PricingRate::exists()) {
            PricingRate::create([
                'price_per_member' => config('gymai.default_price_per_member'),
                'effective_from' => '2020-01-01',
                'notes' => 'Launch price',
            ]);
        }

        foreach (PlatformSetting::DEFAULTS as $key => $value) {
            PlatformSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
