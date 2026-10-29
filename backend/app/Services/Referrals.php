<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Salesperson;
use App\Models\Setting;
use App\Models\SubscriptionPayment;
use Illuminate\Support\Collection;
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
}
