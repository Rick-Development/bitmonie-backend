# Webhook Integration & Reconciliation Notes

## Endpoints

- Quidax core: `POST /api/quidax/webhook`
- Quidax Ramp: `POST /api/quidax/ramp/webhook`
- Safe Haven: `POST /api/webhook`

Legacy endpoints remain active for backward compatibility:

- `POST /api/webhook/quidax`
- `POST /api/webhook/quidax-ramp`
- `POST /api/webhook/safehaven`

## Common Logging

Every webhook request is written to `webhook_event_logs` before processing. The log stores provider, endpoint, raw payload, decoded payload, headers, event name, event ID, reference, provider status, internal status, duplicate marker, processing status, HTTP status, and failure reason.

Processing changes are written to `webhook_reconciliation_logs`. These records show the provider, action, related local transaction, user, currency, amount, status before/after, balance before/after where available, and the webhook event that triggered the change.

Duplicate detection uses provider + transaction reference + mapped status + event name. A duplicate webhook is acknowledged with HTTP 200 and is not processed again.

## Payload Fields Observed / Expected

### Quidax Core

Expected top-level fields:

- `event`, `type`, or `event_type`
- `data`

Relevant `data` fields:

- `id`, `uuid`, `reference`, `transaction_reference`
- `status`, `state`
- `currency`, `currency_code`, `coin`
- `amount`, `value`, `confirmed_amount`
- `txid`, `tx_id`, `transaction_hash`, `hash`
- `user.id`, `user.email`, `user_id`
- withdrawal destination fields such as `fund_uid`, `address`, `recipient.details.address`, or `destination.address`

Deposit events are processed only when status maps to successful/confirmed/completed/done. Withdrawal events are mapped to initiated, processing, completed, or failed.

### Quidax Ramp

Expected top-level fields:

- `event`
- `data`

Relevant `data` fields:

- `merchant_reference`
- `mode` (`buy` or `sell`)
- `status`
- `public_id`, `reference`
- `fiat_deposit.status`
- `crypto_payout.status`
- `crypto_deposit.status`
- `fiat_payout.status`

Buy/ramp events update on-ramp transactions. Sell/ramp events update off-ramp transactions. Successful statuses map to completed locally; failed/rejected/cancelled statuses map to failed; processing/pending statuses remain pending/processing according to the existing ramp sync service.

### Safe Haven

Expected top-level fields:

- `type`
- `eventType`
- `data`

Relevant `data` fields:

- `paymentReference`, `sessionId`, `reference`, `_id`
- `status`, `responseCode`
- `type`
- `creditAccountNumber`, `accountNumber`, `realCreditAccountNumber`, `beneficiaryAccountNumber`
- `amount`, `fees`, `vat`, `stampDuty`
- `approvedAt`, `updatedAt`, `createdAt`
- `isReversed`

Only confirmed successful incoming settlement events change NGN balances. Pending, failed, rejected, reversed, malformed, or unsupported events are logged and acknowledged with HTTP 200 without changing balances.
