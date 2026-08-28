# Payment providers

Family House Connect supports **any one** of Paystack, Flutterwave, or Stripe. Superadmins assign `platform.payments.view` / `platform.payments.manage`. Operators paste keys, save, then activate. Secrets are encrypted at rest and never returned by the API.

## Admin setup

1. Open **Admin → Settings → Payments** (`/admin/settings/payments`).
2. Choose **Paystack**, **Flutterwave**, or **Stripe**.
3. Paste secret key, public/publishable key, and webhook secret.
4. Save, then **Activate**.

API (global MFA + `platform.payments.*`):

- `GET/PUT /api/v1/admin/platform/payments`
- `POST/DELETE /api/v1/admin/platform/payments/activation`

## Checkout

- Member `POST /api/v1/user/payments/giving-intents` returns `client_payload.checkout_url` plus the public key.
- Web redirects to that URL. Mobile opens it with the system browser, then polls `GET /api/v1/user/payments/intents/{id}`.
- Public bootstrap: `GET /api/v1/payments/configuration` (public key only).

## Webhooks (signed)

Point the provider dashboard at:

- Paystack: `POST /api/v1/finance/webhooks/paystack` (`X-Paystack-Signature`)
- Flutterwave: `POST /api/v1/finance/webhooks/flutterwave` (`verif-hash`)
- Stripe: `POST /api/v1/finance/webhooks/stripe` (`Stripe-Signature`)

Verified `payment_succeeded` events reconcile the intent, write an immutable transaction, and issue a receipt.

Paid event registrations use `POST /api/v1/user/events/registrations/{id}/payment-intents` and the same hosted checkout + webhook path.

Until a provider is **activated**, giving and event fees remain governance-denied (unless `PAYMENT_GOVERNANCE_MODE` is set for local QA).

Mobile checkout sends `X-Client-Channel: mobile` so Paystack/Flutterwave return to `fhc://payments/success?id={intent}` when the custom scheme is registered. The app also polls the intent after returning from the browser.
