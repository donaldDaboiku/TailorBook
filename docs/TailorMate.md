# TailorMate

## Overview

TailorMate is the phone app a fashion shop uses while the client is still standing there. One login is one shop. The shop keeps its own customers, measurements, jobs, payments, and expenses, and can message a client on WhatsApp.

The live app is [https://tailormate.gitsystemng.com](https://tailormate.gitsystemng.com). The public page is [https://gitsystemng.com/tailormate](https://gitsystemng.com/tailormate). There is no separate admin address. An admin signs in on the same page and sees the admin shops screen.

## Installation

The running site is already on Namecheap. These notes are for someone opening the project on a computer.

### Requirements

- PHP 8.4
- MySQL
- Node.js for the phone app

### Setup

The API lives in `backend`. The phone app lives in `frontend`. The public page lives in `TailorMate`.

Copy `backend/.env.example` to `backend/.env`, set the database and Paystack values, then run the migrations. Build the phone app with an empty `VITE_API_URL` when the app and the API share one hostname.

## Usage

### A shop

1. Open the app and create an account. The shop confirms it is an adult account and accepts the terms and the privacy notice.
2. The new shop can use TailorMate for 7 days.
3. Add a customer, then save measurements, a job, and a payment.
4. Open WhatsApp from the customer when the shop wants to message them.
5. Record shop expenses under Finance.
6. Use More for the shop name, phone, receipt text, logo, language, invite code, and subscription.

Languages in the app are English, Yoruba, Igbo, Hausa, and French.

### An admin

Sign in with an admin email. The screen lists shops, payments, reviews, feedback, and referrals. An admin does not pay for a shop plan.

Create another admin on the server with:

```bash
php artisan admin:create --name="Admin" --email=the-email@example.com
```

The command asks for a password. It does not send that password by email.

### Forgot password

On the sign-in screen, choose Forgot password and enter the account email. The reset link is sent only when the server mail settings are filled in. Creating an account does not send a confirmation email. The shop can sign in immediately.

## Configuration

### Trial and the monthly plan

A new shop receives 7 days, counted from the day it registers. Shops that already have an end date keep that date.

After the trial, the paid plan is ₦5,000 for 30 days. The shop pays inside the app with Paystack. When the payment is confirmed, the end date moves forward 30 days. A refund of that payment takes those 30 days back.

The amount and the length come from the server settings `PAYSTACK_PLAN_AMOUNT` (kobo, so 500000 is ₦5,000), `PAYSTACK_PLAN_DAYS`, and `TRIAL_DAYS`.

### How a payment is confirmed

The app does not trust the browser alone.

1. The shop taps Subscribe. The API starts a Paystack checkout and stores a pending payment.
2. Paystack takes the card.
3. Paystack sends the shop back to `/?billing=1` on the app, and the app asks the API to verify that reference.
4. Paystack also calls `https://tailormate.gitsystemng.com/api/billing/webhook`. The API accepts that call only when the Paystack signature matches.

In the Paystack dashboard, the webhook URL should be `https://tailormate.gitsystemng.com/api/billing/webhook`. The server `.env` needs `FRONTEND_URL=https://tailormate.gitsystemng.com` so the shop returns to the live app after paying.

The live site is using a real Paystack secret. Subscribe charges a real card.

### Catalogue

A shop can save styles. The free allowance is shared. When the shop is full, it can buy another pack of styles. That payment is separate from the monthly plan.

### Invite a shop

Every shop has a code under More → Invite a shop. A new shop can enter that code while registering. The referral counts after the new shop pays its first monthly plan. Five paid shops earn the inviting shop one free month, after an admin approves it. Every further five paid shops can earn another month. A refunded or rejected referral does not count. A shop cannot use its own code.

A salesperson code is created by an admin. It tracks signups and commission. It does not earn a free month. An admin can turn a salesperson code off.

## Troubleshooting

- **Can't reach the server.** The API is down, or PHP is not 8.4, or `vendor` is missing. `https://tailormate.gitsystemng.com/api/health` should return `{"status":"ok","app":"TailorMate"}`.
- **Subscribe does nothing or returns to the wrong site.** Check `FRONTEND_URL` and the Paystack keys on the server, then run `php artisan config:clear`.
- **Payment succeeded at Paystack but the shop is still on trial.** Confirm the webhook URL in Paystack, then sign in again. The app verifies the payment when it returns from Paystack.
- **Password reset says it sent a mail, but nothing arrives.** Set `MAIL_MAILER=sendmail` and a real `MAIL_FROM_ADDRESS` on the server.
- **The browser tab still shows an old icon.** Upload the new `index.html` and the matching file in `public/assets`, then refresh with Ctrl+F5.

## Examples

A shop pays for the first time on 6 November. The trial end date is replaced by a paid end date 30 days later. If that shop pays again before the end date, the new 30 days are added to the date it already has.

Five other shops register with that shop’s code and each pays the monthly plan. The admin sees one free month waiting. Approving it adds one calendar month to the inviting shop.

## Notes

Terms and the privacy notice are the PDF files linked from the app and from the public page. The contact on those documents is info@gitsystemng.com and WhatsApp +2348100197402.

Do not commit `.env`, database dumps, or Paystack keys.
