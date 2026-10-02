# Quidax Buy/Sell and Webhook Flow

## Sell/off-ramp

1. Mobile creates a sell quote/transaction through the initiate off-ramp endpoint.
2. Backend stores the local `ramp_transactions` row as `pending`; no crypto is moved yet.
3. Before confirmation, backend fetches the user's fresh Quidax wallet balance and withdrawal fee.
4. Backend calculates Quidax fee, BitMonie processor fee, and total amount with decimal-safe math.
5. Backend validates against spendable Quidax balance, excluding the current pending sell from reservations.
6. If the balance is enough, backend confirms the off-ramp with Quidax Ramp and marks the local transaction `processing`.
7. The queued sell job moves the total crypto amount from the user sub-account to the main account, then sends the sell amount to the Ramp deposit address.
8. Local status remains `processing` or `awaiting_payout` until Quidax/SafeHaven webhook or status sync confirms completion.

## Buy/on-ramp

1. Mobile creates a buy transaction through the initiate on-ramp endpoint.
2. Backend stores the local transaction as `pending`.
3. Backend confirms the transaction with Quidax Ramp to obtain fiat payment details.
4. Backend moves NGN via SafeHaven and marks the local buy as `confirmed` only after payment initiation succeeds.
5. Provider status/webhook sync updates later states; do not treat a quote or initiated transaction as successful crypto delivery.

## Wallet display

Wallet endpoints return provider balance plus reservation-aware fields:

- `provider_balance`: fresh Quidax wallet balance.
- `reserved_outgoing_balance`: local pending withdrawals/off-ramp reservations.
- `available_balance` and legacy `balance`: sellable/sendable balance after reservations.

Mobile should display `available_balance` or `balance` as the user-facing sellable balance, but final confirmation can still fail if Quidax balance changes before confirmation.

## Quidax deposit webhook

Backend listens on `POST /api/webhook/quidax` and sends deposit email only for successful/confirmed deposit events. Duplicate event IDs, transaction IDs, txids, or references are suppressed through `crypto_notification_logs`.

## Quidax withdrawal webhook

Backend listens on `POST /api/webhook/quidax` and updates local withdrawal state from webhook data:

- `initiated`
- `processing`
- `completed`
- `failed`

User emails are sent for initiated, processing, and completed. Admins are notified for failed withdrawals. Failed withdrawal webhooks never mark local withdrawals successful.
