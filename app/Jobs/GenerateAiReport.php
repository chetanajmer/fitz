<?php

namespace App\Jobs;

use App\Models\AiReport;
use App\Models\Gym;
use App\Services\AiReportService;
use App\Tenancy\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/** Generates one pending AI report through OpenRouter, inside the report's gym. */
class GenerateAiReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 85;

    public function __construct(public int $reportId, public int $gymId) {}

    public function handle(TenantManager $tenant, AiReportService $reports): void
    {
        $gym = Gym::find($this->gymId);

        if (! $gym) {
            return;
        }

        $tenant->runAs($gym, function () use ($reports) {
            if ($report = AiReport::find($this->reportId)) {
                $reports->generate($report);
            }
        });
    }

    /** A crashed or timed-out job must not keep the member's credits reserved. */
    public function failed(?Throwable $e): void
    {
        AiReport::forGym($this->gymId)->whereKey($this->reportId)->where('status', AiReport::PENDING)
            ->update(['status' => AiReport::FAILED, 'error' => mb_substr((string) $e?->getMessage(), 0, 255), 'updated_at' => now()]);
    }
}
