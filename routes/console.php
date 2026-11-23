<?php

use App\Models\Gym;
use App\Services\BillingService;
use App\Services\MembershipService;
use App\Services\StreakService;
use App\Tenancy\TenantManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('gymai:refresh-memberships', function (MembershipService $memberships) {
    $result = $memberships->refreshStatuses();
    $this->info("Expired: {$result['expired']} · Activated: {$result['activated']}");
})->purpose('Sync stored membership statuses (upcoming → active → expired) with their dates');

Artisan::command('gymai:generate-invoices {month? : Billing month as YYYY-MM (defaults to last month)}', function (BillingService $billing) {
    $month = $this->argument('month')
        ? Carbon::createFromFormat('Y-m-d', $this->argument('month').'-01')
        : Carbon::today()->subMonthNoOverflow();

    $result = $billing->generateForAllGyms($month);
    $this->info("Invoices for {$month->format('F Y')}: {$result['created']} created, {$result['skipped']} already existed.");
})->purpose('Generate monthly SaaS invoices for every gym');

Artisan::command('gymai:refresh-streaks {gym? : Gym id (defaults to all gyms)}', function (StreakService $streaks) {
    $gyms = Gym::query()->when($this->argument('gym'), fn ($q, $id) => $q->whereKey($id))->get();
    $total = 0;
    foreach ($gyms as $gym) {
        $total += app(TenantManager::class)->runAs($gym, fn () => $streaks->refreshGym($gym));
    }
    $this->info("Streaks recalculated for {$total} member(s) in {$gyms->count()} gym(s).");
})->purpose('Recalculate attendance streaks (breaks streaks that lapsed overnight)');

Schedule::command('gymai:refresh-memberships')->dailyAt('00:10');
Schedule::command('gymai:refresh-streaks')->dailyAt('00:20');
Schedule::command('gymai:generate-invoices')->monthlyOn(1, '02:00');
