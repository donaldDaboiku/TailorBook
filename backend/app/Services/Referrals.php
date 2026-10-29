<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\Business;
use App\Models\ReferralActivity;
use App\Models\ReferralReward;
use App\Models\Salesperson;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class Referrals
{
    /**
     * @return array{business_id: string}|array{salesperson_id: string}|null
     */
    public function resolve(?string $code): ?array
    {
        $code = strtoupper(trim((string) $code));

        if ($code === '') {
            return null;
        }

        $businessId = Business::query()->where('invite_code', $code)->value('id');

        if (is_string($businessId)) {
            return ['business_id' => $businessId];
        }

        $salespersonId = Salesperson::query()->where('code', $code)->value('id');

        if (is_string($salespersonId)) {
            return ['salesperson_id' => $salespersonId];
        }

        throw ValidationException::withMessages([
            'invite_code' => ['That invite code is not recognised.'],
        ]);
    }

    /**
     * @param  array{business_id: string}|array{salesperson_id: string}|null  $referrer
     */
    public function assign(Business $business, ?array $referrer): void
    {
        if ($referrer === null) {
            return;
        }

        $business->forceFill([
            'referred_by_business_id' => $referrer['business_id'] ?? null,
            'referred_by_salesperson_id' => $referrer['salesperson_id'] ?? null,
        ])->save();
    }

    public function ensureCode(Business $business): string
    {
        if (is_string($business->invite_code) && $business->invite_code !== '') {
            return $business->invite_code;
        }

        $code = $this->freshCode();
        $business->forceFill(['invite_code' => $code])->save();

        return $code;
    }

    public function createSalesperson(string $name): Salesperson
    {
        return Salesperson::query()->create([
            'name' => $name,
            'code' => $this->freshCode(),
        ]);
    }

    /**
     * @return array{skip: int, amount: int, amount_kobo: int, amount_label: string}
     */
    public function offer(): array
    {
        $kobo = $this->storedInt('referral_amount', 0);

        return [
            'skip' => $this->storedInt('referral_skip', 0),
            'amount' => (int) ($kobo / 100),
            'amount_kobo' => $kobo,
            'amount_label' => $this->moneyLabel($kobo),
        ];
    }

    public function link(string $code): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/?invite='.$code;
    }

    /**
     * @return array{signed_up: int, paid: int, commission_label: string}
     */
    public function counts(Business $business): array
    {
        $row = $this->countsForBusinesses(collect([$business]))[$business->id] ?? null;

        return [
            'signed_up' => $row['signed_up'] ?? 0,
            'paid' => $row['paid'] ?? 0,
            'commission_label' => $row['commission_label'] ?? $this->moneyLabel(0),
        ];
    }

    /**
     * @param  Collection<int, Business>  $businesses
     * @return array<string, array{signed_up: int, paid: int, commission_label: string, shops: list<array{name: string, email: string|null, paid: bool}>}>
     */
    public function countsForBusinesses(Collection $businesses): array
    {
        foreach ($businesses as $business) {
            $this->ensureCode($business);
        }

        $offer = $this->offer();
        $ids = $businesses->pluck('id')->filter()->all();
        $grouped = $this->childrenGrouped('referred_by_business_id', $ids);
        $rows = [];

        foreach ($ids as $id) {
            $rows[$id] = $this->tally($grouped[$id] ?? [], $offer);
        }

        return $rows;
    }

    /**
     * @return list<array{id: string, name: string, code: string, signed_up: int, paid: int, commission_label: string, shops: list<array{name: string, email: string|null, paid: bool}>}>
     */
    public function salespeople(): array
    {
        $people = Salesperson::query()->orderBy('name')->get();
        $offer = $this->offer();
        $grouped = $this->childrenGrouped('referred_by_salesperson_id', $people->pluck('id')->all());

        return $people->map(function (Salesperson $person) use ($grouped, $offer) {
            $tally = $this->tally($grouped[$person->id] ?? [], $offer);

            return [
                'id' => $person->id,
                'name' => $person->name,
                'code' => $person->code,
                'signed_up' => $tally['signed_up'],
                'paid' => $tally['paid'],
                'commission_label' => $tally['commission_label'],
                'shops' => $tally['shops'],
            ];
        })->all();
    }

    /**
     * @param  list<string>  $parentIds
     * @return array<string, list<array{name: string, email: string|null, paid: bool}>>
     */
    private function childrenGrouped(string $column, array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $children = Business::query()
            ->with('user:id,email')
            ->whereIn($column, $parentIds)
            ->get(['id', 'user_id', 'name', $column]);

        $paid = array_flip($this->paidUserIds($children->pluck('user_id')->all()));
        $grouped = [];

        foreach ($children as $child) {
            $parentId = $child->getAttribute($column);
            $grouped[$parentId][] = [
                'name' => $child->name,
                'email' => $child->user?->email,
                'paid' => isset($paid[$child->user_id]),
            ];
        }

        return $grouped;
    }

    /**
     * @param  list<array{name: string, email: string|null, paid: bool}>  $shops
     * @param  array{skip: int, amount: int, amount_kobo: int, amount_label: string}  $offer
     * @return array{signed_up: int, paid: int, commission_label: string, shops: list<array{name: string, email: string|null, paid: bool}>}
     */
    private function tally(array $shops, array $offer): array
    {
        $paid = count(array_filter($shops, fn (array $shop) => $shop['paid']));
        $earning = max(0, $paid - $offer['skip']);

        return [
            'signed_up' => count($shops),
            'paid' => $paid,
            'commission_label' => $this->moneyLabel($earning * $offer['amount_kobo']),
            'shops' => $shops,
        ];
    }

    /**
     * @param  list<int|string>  $userIds
     * @return list<int|string>
     */
    private function paidUserIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return SubscriptionPayment::query()
            ->whereIn('user_id', $userIds)
            ->where('purpose', 'plan')
            ->where('status', 'success')
            ->whereNull('refunded_at')
            ->distinct()
            ->pluck('user_id')
            ->all();
    }

    private function storedInt(string $key, int $fallback): int
    {
        $value = Setting::valueFor($key);

        return is_numeric($value) ? (int) $value : $fallback;
    }

    private function moneyLabel(int $kobo): string
    {
        return '₦'.number_format($kobo / 100, 2, '.', ',');
    }

    private function freshCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $length = strlen($alphabet) - 1;

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $code = '';

            for ($index = 0; $index < 6; $index++) {
                $code .= $alphabet[random_int(0, $length)];
            }

            $taken = Business::query()->where('invite_code', $code)->exists()
                || Salesperson::query()->where('code', $code)->exists();

            if (! $taken) {
                return $code;
            }
        }

        throw new RuntimeException('Could not create an invite code.');
    }

    public function noteSignup(Business $business): void
    {
        $business->loadMissing(['referredByBusiness', 'referredBySalesperson']);

        if (is_string($business->referred_by_business_id)) {
            ReferralReward::query()->firstOrCreate(
                ['referred_business_id' => $business->id],
                [
                    'referrer_business_id' => $business->referred_by_business_id,
                    'status' => 'signed_up',
                ],
            );
            $host = $business->referredByBusiness?->name ?? 'A shop';
            $this->record(
                $business,
                'signed_up',
                "{$business->name} signed up with {$host}'s invite code.",
            );
        }

        if (is_string($business->referred_by_salesperson_id)) {
            $name = $business->referredBySalesperson?->name ?? 'A salesperson';
            $this->record(
                $business,
                'signed_up',
                "{$business->name} signed up with salesperson {$name}'s code.",
            );
        }
    }

    public function notePlanPaid(User $user): void
    {
        $business = Business::query()->where('user_id', $user->id)->first();

        if (! $business instanceof Business || ! is_string($business->referred_by_business_id)) {
            return;
        }

        $reward = ReferralReward::query()
            ->where('referred_business_id', $business->id)
            ->lockForUpdate()
            ->first();

        if (! $reward instanceof ReferralReward) {
            $reward = ReferralReward::query()->create([
                'referrer_business_id' => $business->referred_by_business_id,
                'referred_business_id' => $business->id,
                'status' => 'signed_up',
            ]);
        }

        if ($reward->status !== 'signed_up') {
            return;
        }

        $reward->forceFill([
            'status' => 'pending',
            'paid_at' => now(),
        ])->save();

        $host = Business::query()->whereKey($business->referred_by_business_id)->value('name');
        $hostName = is_string($host) && $host !== '' ? $host : 'A shop';
        $this->record(
            $business,
            'paid',
            "{$business->name} paid the monthly plan. {$hostName} can receive one free month after admin approval.",
            $reward->id,
        );
    }

    public function notePlanRefunded(User $user): void
    {
        $business = Business::query()->where('user_id', $user->id)->first();

        if (! $business instanceof Business) {
            return;
        }

        $reward = ReferralReward::query()
            ->where('referred_business_id', $business->id)
            ->lockForUpdate()
            ->first();

        if (! $reward instanceof ReferralReward) {
            return;
        }

        $stillPaid = SubscriptionPayment::query()
            ->where('user_id', $user->id)
            ->where('purpose', 'plan')
            ->where('status', 'success')
            ->whereNull('refunded_at')
            ->exists();

        if ($reward->status === 'approved') {
            $this->record(
                $business,
                'refunded',
                "{$business->name}'s plan payment was refunded after the free month was approved.",
                $reward->id,
            );

            return;
        }

        if ($reward->status === 'pending' && ! $stillPaid) {
            $reward->forceFill([
                'status' => 'signed_up',
                'paid_at' => null,
            ])->save();
            $this->record(
                $business,
                'refunded',
                "{$business->name}'s plan payment was refunded. The free month is no longer waiting for approval.",
                $reward->id,
            );
        }
    }

    public function approve(ReferralReward $reward, User $admin): void
    {
        if ($reward->status !== 'pending') {
            throw ValidationException::withMessages([
                'reward' => ['This free month is not waiting for approval.'],
            ]);
        }

        DB::transaction(function () use ($reward, $admin): void {
            $locked = ReferralReward::query()->whereKey($reward->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'reward' => ['This free month is not waiting for approval.'],
                ]);
            }

            $referrer = Business::query()->findOrFail($locked->referrer_business_id);
            $owner = User::query()->whereKey($referrer->user_id)->lockForUpdate()->firstOrFail();
            $days = (int) config('services.paystack.plan_days', 30);
            $until = $owner->subscribed_until;
            $base = $until !== null && $until->copy()->endOfDay()->isFuture()
                ? $until->copy()
                : now();
            $next = $base->addDays($days)->toDateString();

            $owner->forceFill([
                'subscription_status' => SubscriptionStatus::Subscribed,
                'subscribed_until' => $next,
            ])->save();

            $locked->forceFill([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $admin->id,
                'granted_until' => $next,
            ])->save();

            $referred = Business::query()->find($locked->referred_business_id);
            $this->record(
                $referred instanceof Business ? $referred : $referrer,
                'approved',
                "Admin approved one free month for {$referrer->name}, until {$next}.",
                $locked->id,
            );
        });
    }

    public function reject(ReferralReward $reward): void
    {
        if ($reward->status !== 'pending') {
            throw ValidationException::withMessages([
                'reward' => ['This free month is not waiting for approval.'],
            ]);
        }

        DB::transaction(function () use ($reward): void {
            $locked = ReferralReward::query()->whereKey($reward->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'reward' => ['This free month is not waiting for approval.'],
                ]);
            }

            $locked->forceFill(['status' => 'rejected'])->save();
            $referrer = Business::query()->find($locked->referrer_business_id);
            $referred = Business::query()->find($locked->referred_business_id);
            $host = $referrer?->name ?? 'A shop';

            if ($referred instanceof Business) {
                $this->record(
                    $referred,
                    'rejected',
                    "Admin declined the free month for {$host}.",
                    $locked->id,
                );
            }
        });
    }

    /**
     * @return array{months_pending: int, months_approved: int, activities: list<array{id: string, at: string|null, summary: string}>}
     */
    public function shopReward(Business $business): array
    {
        $rewards = ReferralReward::query()
            ->where('referrer_business_id', $business->id)
            ->get();

        $activities = ReferralActivity::query()
            ->where('referrer_business_id', $business->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return [
            'months_pending' => $rewards->where('status', 'pending')->count(),
            'months_approved' => $rewards->where('status', 'approved')->count(),
            'activities' => $activities->map(fn (ReferralActivity $row) => [
                'id' => $row->id,
                'at' => $row->created_at?->toIso8601String(),
                'summary' => $row->summary,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{rewards: list<array<string, mixed>>, activities: list<array<string, mixed>>}
     */
    public function adminRewards(): array
    {
        $rewards = ReferralReward::query()
            ->with(['referrer.user:id,email', 'referred.user:id,email'])
            ->orderByDesc('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (ReferralReward $reward) => [
                'id' => $reward->id,
                'status' => $reward->status,
                'referrer_name' => $reward->referrer?->name,
                'referrer_email' => $reward->referrer?->user?->email,
                'referred_name' => $reward->referred?->name,
                'referred_email' => $reward->referred?->user?->email,
                'paid_at' => $reward->paid_at?->toIso8601String(),
                'approved_at' => $reward->approved_at?->toIso8601String(),
                'granted_until' => $reward->granted_until?->toDateString(),
            ])
            ->values()
            ->all();

        $activities = ReferralActivity::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (ReferralActivity $row) => [
                'id' => $row->id,
                'kind' => $row->kind,
                'at' => $row->created_at?->toIso8601String(),
                'summary' => $row->summary,
            ])
            ->values()
            ->all();

        return [
            'rewards' => $rewards,
            'activities' => $activities,
        ];
    }

    private function record(Business $business, string $kind, string $summary, ?string $rewardId = null): void
    {
        if ($business->id === null) {
            return;
        }

        ReferralActivity::query()->create([
            'kind' => $kind,
            'referrer_business_id' => $business->referred_by_business_id,
            'salesperson_id' => $business->referred_by_salesperson_id,
            'referred_business_id' => $business->id,
            'reward_id' => $rewardId,
            'summary' => $summary,
        ]);
    }
}
