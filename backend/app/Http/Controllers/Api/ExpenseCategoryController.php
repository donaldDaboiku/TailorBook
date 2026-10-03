<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $categories = ExpenseCategory::query()
            ->whereNull('business_id')
            ->orderBy('sort_order')
            ->get();

        return ExpenseCategoryResource::collection($categories);
    }
}
