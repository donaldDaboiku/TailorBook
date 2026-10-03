<?php

namespace App\Http\Controllers\Api;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Business;
use App\Models\Expense;
use App\Services\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Expense::class);

        $business = $this->business($request);
        $month = $request->query('month');

        $expenses = Expense::query()
            ->where('business_id', $business->id)
            ->with('category')
            ->when(
                is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1,
                function ($query) use ($month) {
                    [$year, $monthNumber] = explode('-', $month);
                    $query->whereYear('spent_on', (int) $year)
                        ->whereMonth('spent_on', (int) $monthNumber);
                },
            )
            ->orderByDesc('spent_on')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $business = $this->business($request);
        $data = $request->validated();

        $expense = Expense::query()->create([
            'business_id' => $business->id,
            'expense_category_id' => $data['expense_category_id'],
            'amount' => Money::normalize($data['amount']),
            'spent_on' => $data['spent_on'],
            'description' => $data['description'] ?? null,
            'method' => PaymentMethod::from($data['method']),
            'note' => $data['note'] ?? null,
        ])->load('category');

        return ExpenseResource::make($expense)
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(Request $request, Expense $expense): JsonResponse
    {
        $this->authorize('delete', $expense);

        $expense->delete();

        return response()->json([
            'message' => 'Expense archived.',
        ]);
    }

    private function business(Request $request): Business
    {
        $business = $request->user()?->business;

        abort_unless($business instanceof Business, 403, 'Set up your shop first.');

        return $business;
    }
}
