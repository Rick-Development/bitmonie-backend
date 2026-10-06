# Bitmonie / Cryptomart - Frontend Developer API Reference & Integration Guide

> **Confidential & Internal Developer Reference**  
> Direct URL Access: `/docs/api` (Interactive Scramble UI)  
> *Notice: This documentation is not linked on any public landing page navigation.*

---

## 1. Global API Standards & Architecture

### Base URLs
- **Local Development:** `http://127.0.0.1:8000/api`
- **Staging:** `https://staging-api.bitmonie.com/api`
- **Production:** `https://api.bitmonie.com/api`

### Mandatory Request Headers
Every request sent to the API must include the following headers:
```http
Accept: application/json
Content-Type: application/json
```
For authenticated endpoints, include the Bearer token returned during login/registration:
```http
Authorization: Bearer <access_token>
```

---

### Global Standard Response Schemas

#### 1. Standard Success Response (`200 OK` / `201 Created`)
```json
{
  "status": "success",
  "message": "Operation completed successfully.",
  "data": {
    "user": {
      "id": 1,
      "firstname": "John",
      "lastname": "Doe",
      "email": "john@example.com"
    }
  }
}
```

#### 2. Standard Validation Error Response (`422 Unprocessable Entity`)
```json
{
  "status": "error",
  "message": "The given data was invalid.",
  "errors": {
    "email": [
      "The email has already been taken."
    ],
    "password": [
      "The password must be at least 8 characters."
    ]
  }
}
```

#### 3. Standard Business Logic / Action Error Response (`400 Bad Request` / `403 Forbidden`)
```json
{
  "status": "error",
  "message": "Insufficient balance in your Flex Savings wallet.",
  "data": null
}
```

#### 4. Standard Unauthenticated Response (`401 Unauthorized`)
```json
{
  "status": "error",
  "message": "Unauthenticated or session has expired. Please log in again."
}
```

---

## 2. Authentication & Biometrics Module

### Flow Overview
1. **User Registration** -> Receive Passport Bearer Token & User profile.
2. **Email Verification** -> Check if `email_verified` is true; if not, request email OTP and verify.
3. **Set PIN** -> Set up a 4-digit transaction/login PIN.
4. **Biometric Enrollment** (Optional for Native Mobile):
   - Generate device keypair (FaceID/Fingerprint) on mobile device.
   - Send `public_key` and device identifiers to `/api/auth/biometric/register`.
   - On subsequent logins, sign a server/device challenge and send signature to `/api/auth/biometric/login`.

---

### Endpoints

#### 1. Register New User
- **Method & Path:** `POST /api/register`
- **Auth Required:** No

**Request Body:**
```json
{
  "firstname": "Jane",
  "lastname": "Doe",
  "email": "jane.doe@example.com",
  "mobile": "08012345678",
  "country": "Nigeria",
  "password": "SecurePassword123@",
  "password_confirmation": "SecurePassword123@",
  "referral_code": "REF8829",
  "agree": "on"
}
```

**Success Response (`201 Created`):**
```json
{
  "status": "success",
  "message": "Registration successful. Please verify your email.",
  "data": {
    "token": "1|eyJ0eXAiOiJKV1QiLC...",
    "user": {
      "id": 42,
      "firstname": "Jane",
      "lastname": "Doe",
      "email": "jane.doe@example.com",
      "mobile": "08012345678",
      "email_verified": false,
      "two_factor_verified": false,
      "kyc_tier": 0
    }
  }
}
```

**Error Responses:**
- `422 Unprocessable Entity`: Duplicate email or password mismatch.
  ```json
  {
    "status": "error",
    "message": "Validation error",
    "errors": { "email": ["The email has already been taken."] }
  }
  ```

---

#### 2. User Login
- **Method & Path:** `POST /api/login`
- **Auth Required:** No

**Request Body:**
```json
{
  "credentials": "jane.doe@example.com",
  "password": "SecurePassword123@"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "Login successful.",
  "data": {
    "token": "2|eyJ0eXAiOiJKV1QiLC...",
    "user": {
      "id": 42,
      "firstname": "Jane",
      "lastname": "Doe",
      "email": "jane.doe@example.com",
      "is_pin_set": true,
      "two_factor_enabled": false
    }
  }
}
```

**Error Responses:**
- `401 Unauthorized`: Invalid credentials.
  ```json
  {
    "status": "error",
    "message": "Invalid login credentials provided."
  }
  ```

---

#### 3. Biometric Registration (Device Binding)
- **Method & Path:** `POST /api/auth/biometric/register`
- **Auth Required:** Yes (`Bearer <token>`)

**Request Body:**
```json
{
  "device_id": "iPhone14,2-UUID-A8F9B2",
  "public_key": "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA...",
  "device_name": "iPhone 13 Pro"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "Biometric authentication registered successfully."
}
```

---

#### 4. Biometric Login (Challenge Verification)
- **Method & Path:** `POST /api/auth/biometric/login`
- **Auth Required:** No

**Request Body:**
```json
{
  "device_id": "iPhone14,2-UUID-A8F9B2",
  "signature": "dGVzdC1zaWduYXR1cmUtZGF0YS12YWxpZGF0aW9u",
  "timestamp": 1728234900
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "Biometric login successful.",
  "data": {
    "token": "3|eyJ0eXAiOiJKV1QiLC...",
    "user": {
      "id": 42,
      "firstname": "Jane",
      "lastname": "Doe"
    }
  }
}
```

---

## 3. KYC & Identity Verification Module

### Tier Levels:
- **Tier 1 (Basic):** BVN / NIN verification with OTP confirmation.
- **Tier 2 (Intermediate):** Address verification & ID card document upload (Passport / Driver's License / NIN Slip).
- **Tier 3 (Advanced):** Proof of residence (Utility Bill) & source of funds.

---

### Endpoints

#### 1. Fetch User KYC Tier & Limits
- **Method & Path:** `GET /api/kyc/user-tier`
- **Auth Required:** Yes

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "data": {
    "current_tier": 1,
    "status": "verified",
    "daily_transaction_limit": 500000,
    "maximum_balance": 2000000,
    "requirements_for_next_tier": [
      "government_id",
      "utility_bill"
    ]
  }
}
```

---

#### 2. Tier 1 BVN Verification Initiation
- **Method & Path:** `POST /api/kyc/tier1/initiate`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "id_type": "bvn",
  "id_number": "22233344455",
  "date_of_birth": "1994-06-15"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "OTP has been sent to the phone number linked with your BVN.",
  "data": {
    "verification_session_id": "v_sess_994821a0",
    "masked_phone": "+23480******78"
  }
}
```

**Error Response (`400 Bad Request`):**
```json
{
  "status": "error",
  "message": "BVN date of birth does not match account profile date of birth."
}
```

---

#### 3. Tier 1 OTP Verification & Upgrade
- **Method & Path:** `POST /api/kyc/tier1/verify`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "verification_session_id": "v_sess_994821a0",
  "otp": "123456"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "Tier 1 KYC verification successful.",
  "data": {
    "tier": 1,
    "status": "approved"
  }
}
```

---

## 4. Wallet & Dedicated Virtual Accounts Module

### Step-by-Step Flow:
1. When a user is verified, an automatic NGN Dedicated Virtual Account (SafeHaven / Payscribe) is provisioned.
2. Frontend displays Account Number, Bank Name (e.g. SafeHaven MFB / Wema Bank), and Account Name for bank transfers.
3. Once the user sends money from their bank app, the backend receives a webhook, credits the wallet, and pushes an FCM notification.

---

### Endpoints

#### 1. Fetch Wallets Overview
- **Method & Path:** `GET /api/user/wallets`
- **Auth Required:** Yes

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "data": {
    "fiat_wallets": [
      {
        "currency": "NGN",
        "symbol": "₦",
        "balance": "145000.50",
        "virtual_account": {
          "bank_name": "SafeHaven Microfinance Bank",
          "account_number": "9012345678",
          "account_name": "Jane Doe / Bitmonie"
        }
      },
      {
        "currency": "USD",
        "symbol": "$",
        "balance": "350.00"
      }
    ]
  }
}
```

---

#### 2. Fetch Account Statement / Transaction History
- **Method & Path:** `GET /api/statement?page=1&per_page=15&type=all`
- **Query Params:**
  - `type`: `all`, `credit`, `debit`, `savings`, `crypto`
  - `start_date`: `YYYY-MM-DD`
  - `end_date`: `YYYY-MM-DD`

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "data": {
    "transactions": [
      {
        "trx_id": "TRX-20261006-8812",
        "type": "credit",
        "category": "bank_transfer_deposit",
        "amount": "25000.00",
        "charge": "0.00",
        "currency": "NGN",
        "status": "completed",
        "remark": "Deposit via NGN Virtual Account",
        "created_at": "2026-10-06T14:22:10Z"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 4,
      "total_items": 58
    }
  }
}
```

---

## 5. Piggyvest-Style Savings & Wealth Module

### Products Available:
1. **Flex Savings:** High-liquidity flexible savings wallet with daily interest accrual and instant free withdrawals.
2. **SafeLock (Locked Savings):** Lock funds for fixed durations (30, 90, 180, 365 days) for higher guaranteed interest (up to 15% p.a.). Early break subject to a fee/interest forfeiture.
3. **Target Savings:** Group or solo goal-oriented savings (e.g. Rent, Vacation, Wedding) with automated schedule deductions.
4. **USDT EasyEarn:** Crypto stablecoin savings earning interest paid daily in USDT.

---

### Endpoints

#### 1. Flex Savings - Deposit Funds
- **Method & Path:** `POST /api/v1/savings/flex/deposit`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "amount": 20000,
  "source": "main_wallet",
  "pin": "1234"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "₦20,000 deposited to Flex Savings successfully.",
  "data": {
    "flex_balance": "55000.00",
    "interest_rate_pa": 10.5,
    "accrued_interest": "120.45"
  }
}
```

**Error Response (`400 Bad Request`):**
```json
{
  "status": "error",
  "message": "Insufficient balance in your Main Wallet."
}
```

---

#### 2. Flex Savings - Withdraw Funds
- **Method & Path:** `POST /api/v1/savings/flex/withdraw`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "amount": 10000,
  "destination": "main_wallet",
  "pin": "1234"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "₦10,000 moved to Main Wallet.",
  "data": {
    "flex_balance": "45000.00",
    "main_wallet_balance": "60000.00"
  }
}
```

---

#### 3. SafeLock - Create Fixed Term Lock
- **Method & Path:** `POST /api/v1/savings/safelock/create`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "title": "House Rent 2027",
  "amount": 150000,
  "duration_days": 180,
  "pay_interest_upfront": true,
  "pin": "1234"
}
```

**Success Response (`201 Created`):**
```json
{
  "status": "success",
  "message": "SafeLock plan created successfully.",
  "data": {
    "safelock_id": "SL-99201",
    "title": "House Rent 2027",
    "principal": "150000.00",
    "interest_rate": 13.0,
    "expected_interest": "9616.44",
    "maturity_date": "2027-04-04T00:00:00Z",
    "status": "active"
  }
}
```

---

#### 4. SafeLock - Emergency Break / Early Liquidation
- **Method & Path:** `POST /api/v1/savings/safelock/break`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "safelock_id": "SL-99201",
  "pin": "1234"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "SafeLock plan liquidated. Early break penalty of 2.5% applied.",
  "data": {
    "refunded_amount": "146250.00",
    "penalty_fee": "3750.00"
  }
}
```

---

## 6. Virtual Cards Module (Payscribe / Sudo)

### Features:
- Create Dollar or Naira Virtual MasterCards / Visa cards.
- Instant card funding from wallet and withdrawals back to wallet.
- Fetch card PAN, CVV, and expiration date securely.
- Card freezing/unfreezing and termination.

---

### Endpoints

#### 1. Create Virtual Card
- **Method & Path:** `POST /api/payscribe/create-card`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "currency": "USD",
  "card_type": "mastercard",
  "initial_funding_amount": 15,
  "card_holder_name": "Jane Doe",
  "pin": "1234"
}
```

**Success Response (`201 Created`):**
```json
{
  "status": "success",
  "message": "Virtual card created successfully.",
  "data": {
    "card_id": "crd_881920ae",
    "masked_pan": "5399 •••• •••• 4120",
    "currency": "USD",
    "balance": "15.00",
    "status": "active",
    "created_at": "2026-10-06T14:30:00Z"
  }
}
```

---

#### 2. Reveal Sensitive Card Details (PAN, CVV, Expiry)
- **Method & Path:** `POST /api/payscribe/card-details/{card_id}`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "pin": "1234"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "data": {
    "card_id": "crd_881920ae",
    "card_pan": "5399420011224120",
    "cvv": "481",
    "expiration_month": "09",
    "expiration_year": "2029",
    "billing_address": {
      "street": "1200 S Figueroa St",
      "city": "Los Angeles",
      "state": "CA",
      "zip": "90015",
      "country": "US"
    }
  }
}
```

---

#### 3. Freeze / Unfreeze Card
- **Method & Path:** `POST /api/payscribe/freeze-card/{card_id}` (or `/unfreeze-card/{card_id}`)
- **Auth Required:** Yes

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "Card status updated to frozen."
}
```

---

## 7. Utility & Bill Payments Module (Payscribe)

### Step-by-Step Flow:
1. **Validate Recipient:** Before debiting the user, validate the meter number / smartcard number / phone network.
2. **Execute Payment:** Submit bill payment with transaction PIN.
3. **Display Token/Receipt:** Return purchased token / confirmation to the user.

---

### Endpoints

#### 1. Buy Airtime
- **Method & Path:** `POST /api/payscribe/airtime/purchase`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "network": "MTN",
  "phone": "08031234567",
  "amount": 1000,
  "pin": "1234"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "₦1,000 MTN Airtime sent successfully to 08031234567.",
  "data": {
    "reference": "PS-AIR-99210",
    "cashback_earned": "20.00"
  }
}
```

---

#### 2. Electricity - Validate Meter Number
- **Method & Path:** `POST /api/payscribe/electricity/validate`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "service": "ikeja-electric",
  "meter_number": "01011500293",
  "meter_type": "prepaid"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "data": {
    "customer_name": "ADEMOLA JOHNSON",
    "meter_number": "01011500293",
    "address": "12 Olumo Street, Ikeja, Lagos",
    "minimum_purchase": 1000
  }
}
```

---

#### 3. Electricity - Purchase Token
- **Method & Path:** `POST /api/payscribe/electricity/pay`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "service": "ikeja-electric",
  "meter_number": "01011500293",
  "meter_type": "prepaid",
  "amount": 5000,
  "phone": "08031234567",
  "pin": "1234"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "Electricity token purchased successfully.",
  "data": {
    "token": "4920-1192-3849-2019-4820",
    "units": "54.8 kWh",
    "amount": "5000.00",
    "reference": "PS-ELEC-882910"
  }
}
```

---

## 8. Crypto Trading & Ramp Module (Quidax / Busha)

### Endpoints

#### 1. Fetch Crypto Deposit Address
- **Method & Path:** `GET /api/user/quidax/fetch-payment-address?currency=usdt&network=trc20`
- **Auth Required:** Yes

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "data": {
    "currency": "USDT",
    "network": "TRC20",
    "address": "TX9vC84X1yJ...gK2",
    "qr_code_url": "https://api.bitmonie.com/qr/TX9vC84X1yJ...gK2",
    "minimum_deposit": "10.00"
  }
}
```

---

#### 2. Instant Crypto Swap Quotation
- **Method & Path:** `POST /api/user/quidax/create-swap-quotation`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "from_currency": "usdt",
  "to_currency": "ngn",
  "from_amount": 100
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "data": {
    "quotation_id": "QUO-991204-TRX",
    "from_currency": "USDT",
    "from_amount": "100.00",
    "to_currency": "NGN",
    "to_amount": "154000.00",
    "rate": "1540.00",
    "expires_in_seconds": 30
  }
}
```

---

#### 3. Execute Swap
- **Method & Path:** `POST /api/user/quidax/swap`
- **Auth Required:** Yes

**Request Body:**
```json
{
  "quotation_id": "QUO-991204-TRX",
  "pin": "1234"
}
```

**Success Response (`200 OK`):**
```json
{
  "status": "success",
  "message": "Swap executed successfully.",
  "data": {
    "transaction_id": "SWP-20261006-0012",
    "from_amount": "100.00 USDT",
    "to_amount": "154000.00 NGN",
    "status": "completed"
  }
}
```

---

## 9. Developer Testing Checklist for Frontend
- [ ] Save Bearer token securely in Encrypted SecureStore / Keychain.
- [ ] Implement interceptors to attach `Authorization: Bearer <token>` and auto-refresh on 401.
- [ ] For form validations, map the `errors` object directly to individual input error hints.
- [ ] Handle offline states and display toast notifications with `message` string from API error responses.
