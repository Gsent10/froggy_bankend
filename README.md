# Froggy API — Laravel Backend

The REST API powering the Froggy Mobile app. Built with Laravel and Laravel Sanctum, it handles customer authentication, multi-currency wallet management, top-ups, and wallet-to-wallet transfers — with idempotency protection on all financial transactions.

---

## Features

- **Authentication** — Register, OTP email verification, login, forgot/reset password, logout (Sanctum token-based)
- **Multi-currency wallets** — Create and list wallets; each wallet is tied to a currency code (NGN, GBP, USD, GHS, KES)
- **Top-up** — Credit a wallet via a mock payment gateway; idempotency key prevents duplicate charges on retry
- **Wallet-to-wallet transfer** — Debit one wallet and credit another in a single database transaction; idempotency-protected
- **Activity & transaction history** — Per-wallet and all-wallet feeds with structured records for every financial event
- **Global error handling** — Consistent JSON error responses; 422 for validation, 404 for missing resources, 500 for unexpected failures

---

## API Overview

```
POST   /api/register
POST   /api/verify-otp
POST   /api/resend-otp
POST   /api/login
POST   /api/forgot-password

# Protected (Bearer token required)
POST   /api/reset-password
POST   /api/logout
GET    /api/dashboard
GET    /api/wallets
POST   /api/wallets
GET    /api/wallets/{id}
GET    /api/wallets/activity/{code}
GET    /api/wallets/transactions/{code}
POST   /api/wallets/topup
POST   /api/wallets/transfer
GET    /api/activity
GET    /api/logs
```

---

The API will be available at `http://127.0.0.1:8000`.

---

## Idempotency

The top-up and transfer endpoints use an `idempotency_key` (UUID v4) to prevent duplicate financial operations. If the same key is sent twice, the second request returns the original transaction result without modifying the wallet balance. The Flutter app generates a fresh UUID per form load, so retries on network failure are safe.