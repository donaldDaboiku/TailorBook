# TailorMate — How to Use

TailorMate is a phone-friendly app for fashion designers and tailors. Use it to save customers, take measurements, track jobs and payments, log expenses, and message customers on WhatsApp.

**Live app:** [https://tailormate-web.onrender.com](https://tailormate-web.onrender.com)

You need internet when you save anything. The first open after a quiet period may take up to a minute while the free server wakes up.

---

## 1. Open your shop (first time)

1. Open the app link on your phone or computer.
2. Tap **Create an account** (or the register option).
3. Enter:
   - Your name
   - Shop name
   - Shop phone
   - Email and password
   - Measurement unit (inches or centimetres)
4. Submit.

You now have one shop linked to your login.

### Sign in later

Use the same email and password. Sign out is under **More**.

---

## 2. The main tabs

| Tab | What it is for |
| --- | --- |
| **Home** | Quick actions: new customer, add expense |
| **Customers** | Search and open customer records |
| **Finance** | Money summary and expense list |
| **More** | Shop settings, your account, sign out |

---

## 3. Customers

### Add a customer

1. Tap **Customers** → **Add**, or **New customer** on Home.
2. Enter **name** and **phone** (required).
3. WhatsApp number defaults to the phone. Change it only if different.
4. Optional: email, gender, address, notes.
5. Save.

### Find a customer

On **Customers**, type a name or phone in the search box.

### Customer screen

On a customer you can:

- **Call** or open **WhatsApp**
- See **Money** (agreed, paid, outstanding)
- Add **jobs**, **payments**, and **measurements**
- Edit or archive the customer

**Archive** hides the customer. It does not wipe history from the database; they leave your active list.

---

## 4. Measurements

1. Open a customer → **Take** (under Measurements).
2. Choose a template: **Female**, **Male**, or **Child**.
3. Enter each body part. Use **+** / **−** for small steps (0.25 in or 0.5 cm).
4. Add fit notes if needed.
5. Save.

Tips:

- Unit follows your shop setting (**More** → Shop settings).
- Open a past set anytime. Use **Duplicate** to start a new set from an old one.
- Values are stored in centimetres internally and shown in your chosen unit.

---

## 5. Jobs and payments

Outstanding balance is always **calculated**: agreed amounts minus payments. Nothing stores a separate “balance” number.

### Add a job

1. Open a customer → **Add job**.
2. Enter title (e.g. Gown, Suit), agreed amount in naira, and date.
3. Save.

### Record a payment

1. Open a customer → **Record payment** (or **Pay** on a job).
2. Enter amount, date, and method (cash, bank transfer, POS, or other).
3. Optionally link the payment to a job.
4. Optional: reference and note.
5. Save.

Check **Money** on the customer:

- **Agreed** — total of active jobs  
- **Paid** — total of active payments  
- **Outstanding** — still owed  

Archive a job or payment if it was entered by mistake. Archived rows stop counting toward the balance.

---

## 6. WhatsApp messages

1. Open a customer → **WhatsApp**.
2. Pick a template:
   - **Hello**
   - **Balance reminder** (uses live outstanding amount)
   - **Outfit ready** (uses latest job title when available)
3. Edit the text if you want.
4. Tap **Open WhatsApp**.

Nigerian numbers like `0803…` are sent as `234803…`. WhatsApp must be installed on the device.

---

## 7. Expenses and Finance

### Add an expense

1. **Home** → **Add expense**, or **Finance** → **Add expense**.
2. Enter amount, category, method, date, and optional description/note.
3. Save.

Starter categories include fabric, transport, electricity, staff, rent, machine repair, accessories, food, marketing, and other.

### Finance tab

At the top you see:

- **Still owed** — total outstanding across all customers  
- **Today / This week / This month** — income (payments), expenses, and net  

Below that, expenses for the selected month, grouped by day. You can archive an expense if needed.

---

## 8. More (settings)

### Shop settings

Update:

- Shop name  
- Shop phone and WhatsApp  
- Measurement unit (inches / centimetres)  
- Currency (usually `NGN`)  

Tap **Save shop**.

### Measurement fields

Each shop can customise Female / Male / Child templates:

1. Open **More** → **Measurement fields**
2. Pick a template
3. Untick fields you do not need
4. Rename any field label (e.g. “Bust” → “Chest”)
5. Tap **Save fields**

Only enabled fields show when you take measurements.

### Account

Update your name or email. Tap **Save account**.

### Sign out

Use **Sign out** near the bottom of **More**.

### Delete account

1. Scroll to **Delete account**.
2. Enter your password.
3. Tap **Delete my account** and confirm.

This permanently removes your login, shop, customers, measurements, jobs, payments, and expenses. It cannot be undone. You need the correct password.

### Install on your phone (PWA)

TailorMate is installable, but phones do not auto-install it:

- **Android Chrome:** menu (⋮) → **Install app** or **Add to Home screen**
- **iPhone Safari:** Share → **Add to Home Screen**

After install it opens full-screen like an app. Saving still needs internet.

---

## 9. Everyday workflow (suggested)

1. New walk-in → **New customer** → take measurements.  
2. Agree a price → **Add job**.  
3. Deposit paid → **Record payment**.  
4. Buy fabric → **Add expense**.  
5. Reminder → customer → **WhatsApp** → Balance reminder.  
6. Outfit done → **WhatsApp** → Outfit ready.  
7. End of day → **Finance** to see today’s income, expenses, and net.

---

## 10. Tips and limits (current version)

- One shop per login.  
- Needs internet to save.  
- Free hosting may sleep; first load can be slow.  
- No photo gallery, staff logins, inventory, or offline sync queue yet.  
- Do not share your password. Each tailor should use their own account.

---

## 11. Admin (platform owner)

Admins are not shop accounts. They see a shops list after login.

### Create an admin (Render / server)

Set on the API service:

```text
ADMIN_EMAIL=you@example.com
ADMIN_PASSWORD=a-strong-password
ADMIN_NAME=Admin
```

Redeploy the API (startup creates/updates that admin), then sign in at the normal app URL with that email/password.

Locally:

```powershell
cd backend
php artisan admin:create --email=you@example.com --password=secret123 --name=Admin
```

### What admins see

- List of registered shops
- Owner name, email, phone
- Joined date and last login
- Search by shop, owner, email, or phone
- **Suspend** / **Activate** a shop (suspended shops cannot sign in; open sessions end)

Subscriptions / payments come in later steps.

---

## Need help?

If the app says **Can't reach the server**:

1. Wait one minute and refresh (server may be waking up).  
2. Confirm you can open [https://tailormate-api.onrender.com/api/health](https://tailormate-api.onrender.com/api/health) — it should show `"status":"ok"`.  
3. Hard refresh the app page (Ctrl+Shift+R on a computer).
