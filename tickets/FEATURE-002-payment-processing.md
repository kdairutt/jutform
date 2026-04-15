# FEATURE-002: Payment gateway integration

**Priority:** Medium  
**Type:** New feature

## Description

Finance wants to charge premium features through a third-party payment gateway. The gateway uses a two-step signing protocol. Your task is to implement the payment endpoint and persist the result.

The gateway API is available at `http://payment-gateway` (already running as a Docker service). The API key is stored in `app_config` under the key `payment_api_key`.

## Endpoint

`POST /api/payments`

Request body:

```json
{"form_id": 1, "amount": 49.99}
```

## Protocol — Read Carefully and Implement Exactly as Specified

**Step 1 — Fetch a one-time salt:**

```
GET http://payment-gateway/salt
Authorization: Bearer {api_key}

Response: {"salt": "a3f9c2..."}
```

**Step 2 — Submit the payment with a signed hash:**

Build the hash input by concatenating with pipe separators:

```
hash_input = user_id . "|" . payment_amount . "|" . datetime_utc
```

where `datetime_utc` is the current UTC datetime formatted as `Y-m-d H:i:s`.

Compute the hash:

```
hash = hash('sha256', hash_input . salt)
```

Then submit:

```
POST http://payment-gateway/charge
Authorization: Bearer {api_key}
Content-Type: application/json

{
  "hash": "{hash}",
  "user_id": {user_id},
  "amount": {payment_amount},
  "datetime": "{datetime_utc}"
}

Response (success):  {"transaction_id": "txn_abc123", "status": "approved"}
Response (failure):  {"status": "declined", "reason": "insufficient_funds"}
```

After the gateway responds — whether approved or declined — write the result to the `payments` table.

## What is Provided

- Payment gateway Docker service already running and accessible at `http://payment-gateway`
- `payments` table already exists:

```sql
CREATE TABLE payments (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  user_id         INT NOT NULL,
  amount          DECIMAL(10,2) NOT NULL,
  transaction_id  VARCHAR(64) NULL,
  status          ENUM('approved', 'declined', 'error') NOT NULL,
  gateway_hash    VARCHAR(64) NOT NULL,
  paid_at         DATETIME NOT NULL
);
```

- `app_config` already contains an entry with key `payment_api_key`

## Acceptance Criteria

- Successful charge returns `HTTP 200` with `{"transaction_id": "...", "status": "approved"}`.
- Declined charge returns `HTTP 402` with `{"status": "declined", "reason": "..."}`.
- Both approved and declined outcomes are persisted in the `payments` table with the correct `status`.
- The `gateway_hash` column contains the SHA-256 hash sent to the gateway.
- If the gateway is unreachable, return `HTTP 503` with an error message.

## Notes

Implement the API contract only on the backend.
