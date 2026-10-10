<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CustomerJob;
use App\Models\Payment;
use App\Services\Balance;
use App\Services\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $today = now()->startOfDay();
        $weekEnd = $today->copy()->addDays(6)->toDateString();
        $todayDate = $today->toDateString();

        $activeJobs = CustomerJob::query()
            ->where('business_id', $business->id)
            ->count();

        $dueSoon = CustomerJob::query()
            ->where('business_id', $business->id)
            ->whereDate('service_date', '>=', $todayDate)
            ->whereDate('service_date', '<=', $weekEnd)
            ->count();

        $upcoming = CustomerJob::query()
            ->where('business_id', $business->id)
            ->whereDate('service_date', '>=', $todayDate)
            ->whereDate('service_date', '<=', $weekEnd)
            ->with('customer')
            ->withSum('payments', 'amount')
            ->orderBy('service_date')
            ->limit(8)
            ->get();

        $outstanding = $this->outstanding($business->id);

        return response()->json([
            'data' => [
                'outstanding' => $outstanding,
                'outstanding_label' => Money::format($outstanding, $business->currency),
                'active_jobs' => $activeJobs,
                'due_soon' => $dueSoon,
                'upcoming_jobs' => $upcoming
                    ->map(fn (CustomerJob $job) => $this->jobRow($job, $business->currency))
                    ->values(),
            ],
        ]);
    }

    public function jobs(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $today = now()->toDateString();

        $jobs = CustomerJob::query()
            ->where('business_id', $business->id)
            ->with('customer')
            ->withSum('payments', 'amount')
            ->orderBy('service_date')
            ->limit(100)
            ->get();

        $rows = $jobs->map(fn (CustomerJob $job) => $this->jobRow($job, $business->currency));

        return response()->json([
            'data' => [
                'upcoming' => $rows->filter(fn (array $row) => $row['service_date'] >= $today)->values(),
                'overdue' => $rows->filter(fn (array $row) => $row['service_date'] < $today)->values(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function jobRow(CustomerJob $job, string $currency): array
    {
        $agreed = (string) $job->agreed_amount;
        $paid = (string) ($job->payments_sum_amount ?? '0');
        $outstanding = Balance::outstanding([$agreed], [$paid]);

        return [
            'id' => $job->id,
            'customer_id' => $job->customer_id,
            'customer_name' => $job->customer?->name,
            'title' => $job->title,
            'service_date' => $job->service_date?->toDateString(),
            'outstanding_label' => Money::format($outstanding, $currency),
        ];
    }

    private function outstanding(string $businessId): string
    {
        return Balance::outstanding(
            CustomerJob::query()
                ->where('business_id', $businessId)
                ->pluck('agreed_amount')
                ->all(),
            Payment::query()
                ->where('business_id', $businessId)
                ->where(function ($query) {
                    $query->whereNull('customer_job_id')->orWhereHas('job');
                })
                ->pluck('amount')
                ->all(),
        );
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
