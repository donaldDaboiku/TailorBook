<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Mail\ShopNoticeMail;
use App\Models\ShopNotice;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ShopNotices
{
    public function welcomeTrial(User $user): void
    {
        $until = $user->subscribed_until?->toDateString() ?? 'the end of the trial';
        $shop = $this->shopName($user);

        $this->send(
            $user,
            'Welcome to TailorMate',
            "Welcome to TailorMate, {$user->name}.\n\n{$shop} can use TailorMate until {$until}. That is the free trial. After that day, subscribe in the app to keep customers, jobs, and payments open.\n\nOpen the app: {$this->appUrl()}",
        );
    }

    public function welcomeSubscriber(User $user): void
    {
        $until = $user->subscribed_until?->toDateString() ?? 'the end of the plan';
        $shop = $this->shopName($user);

        $this->send(
            $user,
            'Welcome. Your TailorMate plan is active',
            "Welcome, {$user->name}.\n\n{$shop} is on the paid TailorMate plan until {$until}.\n\nOpen the app: {$this->appUrl()}",
        );
    }

    public function renewalConfirmed(User $user): void
    {
        $until = $user->subscribed_until?->toDateString() ?? 'the new end date';
        $shop = $this->shopName($user);

        $this->send(
            $user,
            'Your TailorMate plan was renewed',
            "{$shop} is renewed until {$until}.\n\nOpen the app: {$this->appUrl()}",
        );
    }

    public function sendDue(): int
    {
        $today = now()->toDateString();
        $soon = now()->addDays(3)->toDateString();
        $sent = 0;

        User::query()
            ->where('role', UserRole::Tailor)
            ->whereNull('suspended_at')
            ->whereNotNull('subscribed_until')
            ->with('business')
            ->orderBy('id')
            ->each(function (User $user) use (&$sent, $today, $soon): void {
                $until = $user->subscribed_until?->toDateString();

                if ($until === null) {
                    return;
                }

                $access = $user->subscriptionAccessStatus();
                $shop = $this->shopName($user);

                if ($access === 'trial' && $until === $soon) {
                    $sent += $this->once($user, 'trial_ending', $until, 'Your TailorMate trial ends soon', "The free trial for {$shop} ends on {$until}. Subscribe in the app before that day to keep the shop open.\n\nOpen the app: {$this->appUrl()}");
                }

                if ($access === 'expired' && $user->subscription_status === SubscriptionStatus::Free && $until < $today) {
                    $sent += $this->once($user, 'trial_ended', $until, 'Your TailorMate trial has ended', "The free trial for {$shop} ended on {$until}. Subscribe in the app to open customers, jobs, and expenses again.\n\nOpen the app: {$this->appUrl()}");
                }

                if ($access === 'subscribed' && $until === $soon) {
                    $sent += $this->once($user, 'renewal_due', $until, 'Your TailorMate plan ends soon', "The paid plan for {$shop} ends on {$until}. Renew in the app before that day.\n\nOpen the app: {$this->appUrl()}");
                }

                if ($access === 'expired' && $user->subscription_status === SubscriptionStatus::Subscribed && $until < $today) {
                    $sent += $this->once($user, 'renewal_ended', $until, 'Your TailorMate plan has ended', "The paid plan for {$shop} ended on {$until}. Renew in the app to open the shop again.\n\nOpen the app: {$this->appUrl()}");
                }
            });

        return $sent;
    }

    private function once(User $user, string $kind, string $until, string $subject, string $body): int
    {
        $row = ShopNotice::query()->firstOrCreate([
            'user_id' => $user->id,
            'kind' => $kind,
            'notice_on' => $until,
        ]);

        if (! $row->wasRecentlyCreated) {
            return 0;
        }

        $this->send($user, $subject, $body);

        return 1;
    }

    private function send(User $user, string $subject, string $body): void
    {
        if (filter_var($user->email, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        try {
            Mail::to($user->email)->send(new ShopNoticeMail($subject, $body));
        } catch (Throwable $exception) {
            Log::warning('Shop notice email failed.', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function shopName(User $user): string
    {
        $name = $user->business?->name;

        return is_string($name) && trim($name) !== '' ? trim($name) : 'Your shop';
    }

    private function appUrl(): string
    {
        $callback = (string) config('services.paystack.callback_url');
        $base = preg_replace('#/\?billing=1$#', '', $callback);

        return is_string($base) && $base !== '' ? $base : $callback;
    }
}
