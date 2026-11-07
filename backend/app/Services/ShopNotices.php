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
        $phone = (string) config('services.support.whatsapp');

        $this->send(
            $user,
            "You're in! Welcome to TailorMate",
            "Hi {$user->name},\n\nThank you for choosing TailorMate. We built it for tailors and fashion designers like you, so you never lose a measurement, forget a payment or chase a customer again.\n\nWhat you can do with your account:\n- Save customers and their measurements safely\n- Track every job, deposit and balance\n- See exactly who owes you\n- Record your expenses to know your real profit\n- Message customers on WhatsApp when their outfit is ready\n\nYour first steps:\nLog in at tailormate.gitsystemng.com, add one customer, and create their first job. It takes less than 5 minutes.\n\nWe're here for you. If you get stuck or have an idea to make TailorMate better, reply to this email or WhatsApp us on {$phone}. We read every message.\n\nWelcome aboard,\nTailorMate by GITSYSTEM\n\nYour free trial runs until {$until}.\nTerms: {$this->appUrl()}/terms.pdf\nPrivacy notice: {$this->appUrl()}/privacy-notice.pdf",
        );
    }

    public function welcomeSubscriber(User $user): void
    {
        $until = $user->subscribed_until?->toDateString() ?? 'the end of the plan';
        $shop = $this->shopName($user);

        $this->send(
            $user,
            'Welcome. Your TailorMate plan is active',
            "Welcome, {$user->name}.\n\n{$shop} is on the paid TailorMate plan until {$until}. A reminder is sent 7 days before that date.\n\nTerms: {$this->appUrl()}/terms.pdf\nPrivacy notice: {$this->appUrl()}/privacy-notice.pdf\n\nOpen the app: {$this->appUrl()}",
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
        $soon = now()->addDays(7)->toDateString();
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
