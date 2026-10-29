<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Referrals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function show(Request $request, Referrals $referrals): JsonResponse
    {
        $business = $request->user()->business;
        abort_unless($business instanceof Business, 422, 'This account has no shop yet.');

        $code = $referrals->ensureCode($business);
        $counts = $referrals->counts($business);
        $offer = $referrals->offer();

        return response()->json([
            'data' => [
                'code' => $code,
                'link' => $referrals->link($code),
                'signed_up' => $counts['signed_up'],
                'paid' => $counts['paid'],
                'commission_label' => $counts['commission_label'],
                'skip' => $offer['skip'],
                'amount' => $offer['amount'],
            ],
        ]);
    }
}
