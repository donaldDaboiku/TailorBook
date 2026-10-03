<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\CustomerJob;
use App\Models\Expense;
use App\Models\Payment;
use App\Services\Balance;
use App\Services\Money;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $business = $this->business($request);
        $currency = $business->currency;
        $today = CarbonImmutable::now()->startOfDay();

        $periods = [
            'today' => $this->period(
                'Today',
                $today,
                $today,
                $business->id,
                $currency,
            ),
            'week' => $this->period(
                'This week',
                $today->startOfWeek(),
                $today->endOfWeek()->startOfDay(),
                $business->id,
                $currency,
            ),
            'month' => $this->period(
                'This month',
                $today->startOfMonth(),
                $today->endOfMonth()->startOfDay(),
                $business->id,
                $currency,
            ),
        ];

        $outstanding = $this->outstanding($business->id);

        return response()->json([
            'data' => [
                'currency' => $currency,
                'outstanding' => $outstanding,
                'outstanding_label' => Money::format($outstanding, $currency),
                'periods' => $periods,
            ],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function period(
        string $label,
        CarbonImmutable $from,
        CarbonImmutable $to,
        string $businessId,
        string $currency,
    ): array {
        $income = Balance::total(
            Payment::query()
                ->where('business_id', $businessId)
                ->whereDate('paid_on', '>=', $from->toDateString())
                ->whereDate('paid_on', '<=', $to->toDateString())
                ->pluck('amount')
                ->all(),
        );

        $expenses = Balance::total(
            Expense::query()
                ->where('business_id', $businessId)
                ->whereDate('spent_on', '>=', $from->toDateString())
                ->whereDate('spent_on', '<=', $to->toDateString())
                ->pluck('amount')
                ->all(),
        );

        $net = Balance::outstanding([$income], [$expenses]);

        return [
            'label' => $label,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'income' => $income,
            'income_label' => Money::format($income, $currency),
            'expenses' => $expenses,
            'expenses_label' => Money::format($expenses, $currency),
            'net' => $net,
            'net_label' => Money::format($net, $currency),
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
