<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\ReferralReward;
use App\Models\Setting;
use App\Services\Referrals;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function index(Referrals $referrals): JsonResponse
    {
        $board = $referrals->adminRewards();

        return response()->json([
            'data' => [
                'offer' => $referrals->offer(),
                'salespeople' => $referrals->salespeople(),
                'rewards' => $board['rewards'],
                'activities' => $board['activities'],
            ],
        ]);
    }

    public function approve(ReferralReward $reward, Request $request, Referrals $referrals): JsonResponse
    {
        $referrals->approve($reward, $request->user());

        return response()->json([
            'message' => 'Free month approved.',
        ]);
    }

    public function reject(ReferralReward $reward, Referrals $referrals): JsonResponse
    {
        $referrals->reject($reward);

        return response()->json([
            'message' => 'Free month declined.',
        ]);
    }

    public function update(Request $request, Referrals $referrals): JsonResponse
    {
        $data = $request->validate([
            'skip' => ['required', 'integer', 'min:0', 'max:500'],
            'amount' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        Setting::query()->updateOrCreate(
            ['key' => 'referral_skip'],
            ['value' => (string) ((int) $data['skip'])],
        );
        Setting::query()->updateOrCreate(
            ['key' => 'referral_amount'],
            ['value' => (string) ((int) $data['amount'] * 100)],
        );

        return response()->json([
            'message' => 'Referral commission updated.',
            'data' => $referrals->offer(),
        ]);
    }

    public function storeSalesperson(Request $request, Referrals $referrals): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $person = $referrals->createSalesperson($data['name']);

        return response()->json([
            'message' => 'Salesperson added.',
            'data' => [
                'id' => $person->id,
                'name' => $person->name,
                'code' => $person->code,
                'signed_up' => 0,
                'paid' => 0,
                'commission_label' => '₦0.00',
                'shops' => [],
            ],
        ], 201);
    }
}
