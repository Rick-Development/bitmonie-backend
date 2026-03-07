https://api.busha.io/v1/customers

curl --request POST \
  --url https://api.busha.io/v1/customers \
  --header 'Authorization: Bearer RjlwR3JGR0NlTTowUkZOcUFIY0w3N3dtOEI4SEdFY2Fjd1RBaUVacUhIdlg1cTBVSnhVTVRCM1QzUWE=' \
  --header 'Content-Type: application/json' \
  --data '
{
  "email": "nwachukwupatrick069@gmail.com",
  "country_id": "NG",
  "phone": "+234 9069138889",
  "has_accepted_terms": true,
  "type": "individual",
  "birth_date": "09-04-2001",
  "address": {
    "country_id": "NG",
    "address_line_1": "3rd avenue",
    "city": "Calabar",
    "state": "Cross River",
    "address_line_2": "3rd avenue",
    "postal_code": "435101"
  },
  "first_name": "CHIBUIKE",
  "last_name": "NWACHUKWU",
  "middle_name": "Patrick"
}
'

{"status":"success","message":"Created customer successfully","data":{"address":{"address_line_1":"3rd avenue","address_line_2":"3rd avenue","city":"Calabar","country_id":"NG","postal_code":"435101","state":"Cross River"},"business_id":"BUS_zQGsgIQgjaBHmjqCIk1PC","country_id":"NG","created_at":"2026-02-04T01:06:07.37779697Z","deposit":true,"display_currency":"NGN","email":"nwachukwupatrick069@gmail.com","first_name":"CHIBUIKE","has_accepted_terms_of_service":true,"id":"CUS_IxrXk9urgsTTg","last_name":"NWACHUKWU","level":"0","middle_name":"Patrick","payout":true,"phone":"+234 9069138889","status":"inactive","type":"individual","updated_at":"2026-02-04T01:06:07.37779697Z"}}


> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Customers

> Learn how to create and manage customer profiles in Busha. Handle KYC, balances, and customer transactions.

Busha Business accounts extend beyond just managing your enterprise's financial flows. They offer a robust framework for programmatically overseeing and executing transactions on behalf of your end-customers — be they individual users or other businesses. This capability allows you to integrate a multi-layered financial service directly within your application, abstracting underlying complexities.

At its core, Customer Management introduces the concept of operating within a **customer context**. This means that while your Busha Business account remains the primary authenticated entity, you can designate specific transactions to occur on a particular end-customer's behalf, affecting their dedicated balances within your Busha ecosystem.

## Core Concept

Within Busha's Customer Management framework, an **end-customer** is an entity you define and manage under your Busha Business account. This distinct customer profile allows for:

* **Dedicated Balances:** Each end-customer can hold their separate fiat and crypto balances, ensuring clear segregation of funds.

* **Transaction Attribution:** All operations performed within a customer's context are accurately attributed to them, simplifying reconciliation and reporting for your business.

* **Contextual Operations:** Busha business owners can perform any transaction available to a standard Busha account (e.g., deposits and payouts) while specifying that the action is for, and impacts, a particular end-customer's balances. This is managed through explicit identification of the customer in API requests or via specific UI flows in the dashboard.

## Architectural Overview: Extending Your API Control

The Customer Management feature extends the existing Busha Business API, allowing you to programmatically control the lifecycle of your customers and their associated financial activities. This is achieved through dedicated customer management API endpoints and a mechanism to apply a customer context to general transaction endpoints.

### API-driven customer lifecycle

<Note>
  For API requests involving a customer, the `X-BU-PROFILE-ID` field is included
  in the request header. The value for this field is the customer ID for whom
  the request is performed on their behalf.
</Note>

**Example:**

```bash  theme={null}
curl -X POST https://api.busha.co/v1/transfers \
  -H "Authorization: Bearer YOUR_SECRET_API_KEY" \
  -H "X-BU-PROFILE-ID: CUS_abc123xyz789" \
  -H "Content-Type: application/json" \
  -d '{
    "source_currency": "BTC",
    "target_currency": "NGN",
    "source_amount": "0.001"
  }'
```

In this example, the transfer will be executed on behalf of the customer with ID `CUS_abc123xyz789`, affecting their balance rather than your main business account balance.

Your integration can manage customers through the following key API interactions:

#### Creating customer profiles

You can provision new individual or business customer profiles under your Busha Business account via a dedicated API endpoint (e.g., `POST /v1/customers`). This allows for automated onboarding processes directly from your application. Each successfully created customer will be assigned a unique `customer_id`.

#### Retrieving and listing customers

To manage your customer base, you can retrieve a list of all your registered customers (e.g., `GET /v1/customers`). This provides an overview of their profiles, enabling you to retrieve specific customer details as needed.

#### Executing transactions within a customer context

This is where the power of Customer Management is most evident. When performing a transaction via API (e.g., initiating a payout), you use your **Secret API Key** for authentication (proving your business's identity).

* To specify that the transaction is on behalf of one of your customers, you will include their unique `customer_id` in a designated **HTTP header** (e.g., `X-BU-PROFILE-ID: your_customer_id`).

* The Busha API will then process the request, impacting the balances and transaction history associated with that specific `customer_id`. The same underlying logic applies when performing actions via the Busha Dashboard.

## Benefits for Technical Integrators

* **Unified API Interaction:** Leverage a single set of Busha API credentials (`Secret API Key`, `X-BU-PROFILE-ID`) to manage both your own business's funds and those of your end-customers by simply varying the `customer_id` context.

* **Simplified Fund Segregation:** Busha handles the underlying complexity of maintaining separate balance accounts for each of your customers, reducing your development burden for ledger management.

* **Granular Transaction Attribution:** Every operation is precisely attributed to the correct end-customer, simplifying reconciliation, reporting, and audit trails for your platform.

* **Customizable User Journeys:** Design your application's frontend entirely to your specifications, while relying on Busha's robust backend for secure and compliant financial operations for your customers.

* **Automated Compliance:** One of the most significant advantages of managing your customers through Busha Business is the ability to offload the complexities of regulatory compliance. By integrating your customers into Busha, you can now rely on Busha's established and trusted infrastructure to handle critical compliance functions seamlessly:

  * **KYC (Know Your Customer):** We manage the verification processes for individual customers, ensuring their identity is confirmed in line with regulatory requirements.

  * **KYB (Know Your Business):** For your business customers, Busha handles the necessary due diligence to verify their legal entity status and operational legitimacy.

  * **KYT (Know Your Transaction):** Our systems continuously monitor and analyze transactions for suspicious activities, helping to detect and prevent financial crime.

  * **Travel Rule Compliance:** For crypto transfers that fall under the Travel Rule, Busha's infrastructure assists in collecting and transmitting required originator and beneficiary information, ensuring adherence to global anti-money laundering (AML) standards.

This comprehensive compliance coverage means you can focus on your core business offerings, confident that the intricate and ever-evolving regulatory landscape is being managed by a specialized and reliable partner.

## Next Steps

To begin integrating Customer Management into your application:

* [How to Create and Manage Customers via API](/guides/customers/create-individual): A practical guide to programmatically setting up customer profiles.
* [How to Initiate Transactions on Behalf of a Customer](/guides/customers/transactions-on-behalf): Learn the practical steps for executing financial operations in a customer's context.
* [Busha API Reference: Customer Endpoints](/api-reference/customers/list-customers): Explore the detailed specifications for creating, retrieving, and managing customer profiles.


> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Transfers

> Understand how to create and manage transfers in Busha. Execute quotes and move funds between accounts.

A **Transfer** in Busha Business is the execution of a previously created Quote. While a Quote describes the proposed terms of an asset transfer, a Transfer is the actual process of moving those assets between sources and destinations according to those terms.

Transfers are the core mechanism through which all asset movements happen on the Busha platform. This includes depositing funds, converting between currencies, or sending payouts to external accounts.

## The Relationship Between Quotes and Transfers

Understanding the relationship between Quotes and Transfers is fundamental to using Busha Business:

* **Quote**: Describes the proposed transaction (exchange rates, amounts, fees)

* **Transfer**: Executes the transaction and tracks its progress

### The workflow is always

1. Create a Quote to lock in rates and terms
2. Present the Quote details to your user for confirmation
3. Create a Transfer using the Quote ID to execute the transaction
4. Monitor the Transfer status to track completion

<Note>
  **A Quote must be created before a Transfer can be initiated.** The Quote
  locks in the exchange rate and transaction terms, ensuring that when you
  execute the Transfer, you get exactly what was quoted.
</Note>

## Core Components of a Transfer

When you create or retrieve a Transfer, the response contains several important components:

### Transfer Identification

* **`id`**: The unique identifier for the transfer (e.g., `TRF_abc123xyz`)
* **`quote_id`**: Reference to the Quote that defined the terms of this transfer
* **`reference`**: An additional reference identifier for tracking
* **`profile_id`**: The ID of the profile (business or customer) that owns this transfer

### Asset Details

* **`source_currency`**: The currency being sent (e.g., BTC, NGN)
* **`target_currency`**: The currency being received (e.g., USD, BTC)
* **`source_amount`**: The amount of source currency being transferred
* **`target_amount`**: The amount of target currency being received
* **`trade`**: The type of trade (`buy` or `sell`)

### Transfer Channels (`pay_in` and `pay_out`)

The `pay_in` and `pay_out` objects specify how assets move into and out of the transfer:

#### `pay_in` - Where the source assets come from

* Account balance
* Cryptocurrency address
* Bank account (temporary or permanent)
* Mobile money account

#### `pay_out` - Where the target assets go to

* Account balance
* Cryptocurrency address
* Bank account
* Mobile money account

These objects contain detailed information including addresses, account numbers, blockchain hashes (for crypto), and expiration times (for temporary accounts).

### Rate and Fees

* **`rate`**: The exchange rate object showing the conversion rate used, which currency pair, and the rate type (FIXED/FLOATING)
* **`fees`**: An array of fee objects detailing any charges applied to the transfer

### Status and Timeline

* **`status`**: The current status of the transfer (e.g., `pending`, `processing`, `completed`, `funds_refunded`)
* **`timeline`**: A detailed object showing the transfer's progress through various stages
* **`status_description`**: A human-readable description of the current status

## Transfer Status Lifecycle

Understanding the different statuses of your transfers helps you keep track of your funds and provide accurate information to your users. Here's a concise, tabulated view of what each status means:

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "20%", textAlign: "left", padding: "12px" }}>
        Status
      </th>

      <th style={{ width: "20%", textAlign: "left", padding: "12px" }}>Code</th>

      <th style={{ width: "45%", textAlign: "left", padding: "12px" }}>
        Description
      </th>

      <th style={{ width: "15%", textAlign: "left", padding: "12px" }}>
        Final for
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>Pending</td>

      <td style={{ padding: "12px" }}>
        <code>pending</code>
      </td>

      <td style={{ padding: "12px" }}>
        Transfer initiated; funds not yet received. Waiting for assets to
        arrive.
      </td>

      <td style={{ padding: "12px" }}>—</td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Processing</td>

      <td style={{ padding: "12px" }}>
        <code>processing</code>
      </td>

      <td style={{ padding: "12px" }}>
        Funds received and being handled; progressing to the next stage.
      </td>

      <td style={{ padding: "12px" }}>—</td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Cancelled</td>

      <td style={{ padding: "12px" }}>
        <code>cancelled</code>
      </td>

      <td style={{ padding: "12px" }}>
        Transfer cancelled (e.g., user cancelled, quote expired, validation
        failed); will not proceed.
      </td>

      <td style={{ padding: "12px" }}>—</td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Completed</td>

      <td style={{ padding: "12px" }}>
        <code>completed</code>
      </td>

      <td style={{ padding: "12px" }}>
        Transfer fully processed and finalized; assets reached their
        destination.
      </td>

      <td style={{ padding: "12px" }}>—</td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Funds Received</td>

      <td style={{ padding: "12px" }}>
        <code>funds\_received</code>
      </td>

      <td style={{ padding: "12px" }}>
        Payment successfully received for a deposit. Often the terminal step for
        simple deposits.
      </td>

      <td style={{ padding: "12px" }}>Deposits</td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Funds Converted</td>

      <td style={{ padding: "12px" }}>
        <code>funds\_converted</code>
      </td>

      <td style={{ padding: "12px" }}>
        Currency conversion completed (e.g., NGN→BTC, BTC→USD). Terminal for
        balance-to-balance conversions.
      </td>

      <td style={{ padding: "12px" }}>Conversions</td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Outgoing Payment Sent</td>

      <td style={{ padding: "12px" }}>
        <code>outgoing\_payment\_sent</code>
      </td>

      <td style={{ padding: "12px" }}>
        Payment initiated and sent on our end; funds are en route to the
        destination.
      </td>

      <td style={{ padding: "12px" }}>—</td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Funds Delivered</td>

      <td style={{ padding: "12px" }}>
        <code>funds\_delivered</code>
      </td>

      <td style={{ padding: "12px" }}>
        Funds reached the destination. Typically the final step for payouts and
        withdrawals.
      </td>

      <td style={{ padding: "12px" }}>Payouts/Withdrawals</td>
    </tr>
  </tbody>
</table>

## Transfer Completion Flow

Different types of transfers follow different paths through the status lifecycle. Here's how each type typically progresses:

### Deposits

A deposit will typically follow this flow and stop at **Funds Received**:

<img src="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=a742f792ce64c9b7fd4ab916b3b199f0" alt="Deposit Flow for Busha Transfer" style={{ display: "block", margin: "0 auto" }} data-og-width="1073" width="1073" data-og-height="546" height="546" data-path="images/deposit-flow.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=280&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=83865f4b81fb714b92cb128211df0d98 280w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=560&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=5293baf6efdded36e1ef4a398e151451 560w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=840&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=87d0774adf6b347d1b196def48fc67e4 840w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=1100&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=31b6c255f2908017e8345f7183bf6f73 1100w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=1650&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=c359080d2523b187beefdbc30aac372b 1650w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=2500&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=e75c8e0d4f16db3be99ac5b0b283cbbb 2500w" />

<p
  style={{
  textAlign: "center",
  marginTop: "8px",
  fontSize: "14px",
  color: "#6B7280",
}}
>
  Deposit Flow for Busha Transfer
</p>

### Conversions

A conversion will conclude with **Funds Converted** as its final status:

<img src="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=47091c0844c835f35007b2e9cd442d1b" alt="Conversion Flow from currency to currency" style={{ display: "block", margin: "0 auto" }} data-og-width="1073" width="1073" data-og-height="693" height="693" data-path="images/conversion-flow.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=280&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=7cfc4915c512306f29f03ac3aef35853 280w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=560&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=5307cad81e0e844eab7f276a5a70cf5d 560w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=840&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=6287a8f184054718aab0de9ab0f0873b 840w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=1100&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=ad86e8a8dd7cec46777569ae52c52125 1100w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=1650&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=c91132ad6d917f8893c0a37a50220b6a 1650w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=2500&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=0629fd18499a92aa135e7efcfdc824ff 2500w" />

<p
  style={{
  textAlign: "center",
  marginTop: "8px",
  fontSize: "14px",
  color: "#6B7280",
}}
>
  Conversion Flow from currency to currency
</p>

### Payouts / Withdrawals

A payout or withdrawal will reach its final status at **Funds Delivered**:

<img src="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=2825517d38906beae284d7e5c30dd2e5" alt="Withdrawal Flow on Busha" style={{ display: "block", margin: "0 auto" }} data-og-width="1073" width="1073" data-og-height="693" height="693" data-path="images/withdrawal-flow.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=280&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=53d321f27e5b5c6d5433497bd6aa642b 280w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=560&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=2854c16bbb8a80c465f4cc054238d3d9 560w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=840&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=ce8dcce1de405b198eb609545fd9df94 840w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=1100&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=1c993039de462840e1aefa99f26e253e 1100w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=1650&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=209926c4a924ac3dbd791d9a154e254d 1650w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=2500&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=e19bd2f1cc5a793a03e98464b734d2fc 2500w" />

<p
  style={{
  textAlign: "center",
  marginTop: "8px",
  fontSize: "14px",
  color: "#6B7280",
}}
>
  Withdrawal Flow on Busha
</p>

## Transfer Timeline

Every Transfer includes a `timeline` object that provides detailed insight into the transfer's progress. This object contains:

* **`total_steps`**: The total number of steps this transfer will go through
* **`current_step`**: Which step the transfer is currently on
* **`transfer_status`**: The current high-level status
* **`events`**: An array of timeline events showing the history of the transfer

Each event in the timeline includes:

* **`step`**: The step number
* **`done`**: Whether this step is completed
* **`status`**: The status at this step
* **`title`**: A short title for the step
* **`description`**: A detailed description of what happened
* **`timestamp`**: When this event occurred

This timeline is invaluable for providing detailed status updates to your users and for debugging any issues with transfers.

## Best Practices for Working with Transfers

### 1. Always Create a Quote First

Never attempt to move assets without first creating a Quote. The Quote ensures:

* Rate certainty for your users
* Fee transparency
* Validation that the transfer is possible
* Protection against rate fluctuations during user confirmation

### 2. Check Quote Expiration

Always check the `expires_at` field of your Quote and create the Transfer before it expires. If a Quote expires, create a new one with current rates.

### 3. Monitor Transfer Status

Use webhooks or polling to monitor transfer status and provide real-time updates to your users. The `timeline` object provides detailed progress information.

### 4. Handle Failures Gracefully

Transfers can fail for various reasons (insufficient funds, invalid accounts, network issues). Always check the transfer status and `status_description` for error details, and provide clear feedback to users.

### 5. Store Transfer IDs

Always store the Transfer ID returned when you create a transfer. You'll need this ID to:

* Check transfer status
* Provide support
* Reconcile transactions
* Generate reports


> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Quotes

> Learn how quotes work in Busha. Lock in exchange rates and fees before executing transactions.

At Busha, a **Quote** is the fundamental building block for nearly every financial operation that involves an asset transfer, such as currency conversions, deposits, or payouts. It precisely describes a proposed asset transfer between a **source** and a **destination**, ensuring transparency and accuracy before any funds move.

Think of a Quote as a dynamic, real-time snapshot of a proposed transaction. It locks in the terms, such as exchange rates and specific amounts, for a brief period, giving you certainty and control over the outcome of your financial operations.

## What is a Quote? The Core Concept

A Quote is a preliminary agreement that describes an asset transfer. Assets can move between:

* **Busha Balances:** Your internal multi-currency accounts on the Busha platform.
* **External Accounts:** Such as crypto wallet addresses, external bank accounts, or mobile money wallets.

Every Quote, at its most basic level, specifies four crucial values that define the proposed transfer:

* `source_currency`: The currency you're sending (e.g., BTC, NGN).
* `target_currency`: The currency you're receiving (e.g., NGN, USD).
* `source_amount`: The amount of `source_currency` to be sent.
* `target_amount`: The amount of `target_currency` to be received.
* `quote_currency`: The fiat currency (e.g., NGN, KES) that the user wants to use as a reference point for the value of their transaction. It's the currency in which the user *thinks* about the desired value of their crypto.
* `quote_amount`: The specific amount of the `quote_currency` that the user wishes to transact.

## Key Properties of a Busha Quote Explained

When you request a Quote from the Busha API, the response contains several key properties that detail the proposed transaction. Understanding these components is essential for working with Busha's transaction flow:

### Source, destination, and their amounts

A Quote always involves a `source_currency` (the asset being sent) and a `target_currency` (the asset being received).

**Crucially, when you request a Quote, you specify either the `source_amount` or the `target_amount`, but never both.**

* If you specify `source_amount` (e.g., "I want to send 0.001 BTC"), Busha calculates the `target_amount` (how much NGN you'll receive) based on the current exchange rate.
* If you specify `target_amount` (e.g., "I want to receive 10,000 NGN"), Busha calculates the `source_amount` (how much BTC you need to send) based on the current exchange rate.

The Quote response will then provide both the calculated `source_amount` and `target_amount` values for the proposed transfer.

### Quote currency and quote amount

When you provide `quote_currency` and `quote_amount`, Busha takes on the responsibility of calculating either the `source_amount` (how much crypto you need to send) or the `target_amount` (how much crypto you will receive). This calculation is performed dynamically based on the current, real-time exchange rates between the specified `source_currency`, `target_currency`, and the `quote_currency`.

### Key benefits and purpose

1. **Simplified User Experience:** Users no longer need to manually calculate how much crypto they need to buy or sell to achieve a specific fiat value. They simply state the fiat value they're aiming for.

2. **Fiat-Centric Transactions:** This feature caters to users who think about their crypto transactions in terms of their local fiat currency. For example, instead of saying "I want to buy 0.005 BTC," they can say "I want to buy ₦50,000 worth of BTC."

3. **Automatic Amount Calculation:** The system handles the complex and often fluctuating nature of exchange rates, ensuring the user gets the correct amount of crypto for their desired fiat value, or vice versa.

4. **Inclusion of Fees (for external transfers):** When `pay_out` is an external address, the system will factor in network fees into its calculation, ensuring that the `quote_amount` is accurately reflected in the final crypto amount.

**Restrictions and Requirements:**

* `quote_currency` **must be fiat:** This parameter is strictly for national currencies (e.g., NGN, KES).
* `source_currency` **must be crypto when using** `quote_currency`/`quote_amount`: This implies that this feature is primarily for scenarios where users are converting fiat value into crypto, or using crypto to achieve a specific fiat value.
* `target_currency` **can be crypto or fiat:** This provides flexibility, allowing users to either receive crypto or convert their crypto to fiat, all while specifying their desired value in a fiat `quote_currency`.

### Transfer channels (`pay_in` and `pay_out`)

While the core of a Quote defines *what* is being transferred (currencies and amounts), the optional `pay_in` and `pay_out` objects specify *how* the transfer will be facilitated.

* `pay_in`: Provides details about where the source currency will be sent in from.
* `pay_out`: Provides details about the destination of the target currency.

Including these details in the Quote request allows Busha to accurately estimate costs and provide the correct instructions for the specific transfer method.

<Note>
  If either of the `pay_in` and `pay_out` objects are not included in the quote
  object; Busha assumes that the operation is to be executed from the balance.
</Note>

The transfer channels currently supported are:

* Cryptocurrency address
* Bank transfers **(Nigeria)**
* Mobile money **(Kenya)**

### The `pay_in` object

The fields in the `pay_in` object differ from transfer channel to transfer channel.

**Account balance**

```json  theme={null}
"pay_in": {
  "type": "balance"
}
```

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>Key</th>

      <th style={{ width: "50%", textAlign: "left", padding: "12px" }}>
        Description
      </th>

      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Value
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>
        <code>type</code>
      </td>

      <td style={{ padding: "12px" }}>Specifies the payment type.</td>

      <td style={{ padding: "12px" }}>
        <code>"balance"</code>
      </td>
    </tr>
  </tbody>
</table>

**Cryptocurrency channel**

```json  theme={null}
"pay_in": {
  "type": "address",
  "network": "BTC"
}
```

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>Key</th>

      <th style={{ width: "50%", textAlign: "left", padding: "12px" }}>
        Description
      </th>

      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Value
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>
        <code>type</code>
      </td>

      <td style={{ padding: "12px" }}>Specifies the payment type.</td>

      <td style={{ padding: "12px" }}>
        <code>"address"</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>network</code>
      </td>

      <td style={{ padding: "12px" }}>
        The blockchain network (e.g., Bitcoin, Ethereum).
      </td>

      <td style={{ padding: "12px" }}>
        <code>"BTC"</code>
      </td>
    </tr>
  </tbody>
</table>

**Bank transfer**

```json  theme={null}
"pay_in": {
  "type": "temporary_bank_account"
}
```

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>Key</th>

      <th style={{ width: "50%", textAlign: "left", padding: "12px" }}>
        Description
      </th>

      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Value
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>
        <code>type</code>
      </td>

      <td style={{ padding: "12px" }}>
        Specifies the payment type. Funds will be paid into a dynamically
        generated, temporary bank account.
      </td>

      <td style={{ padding: "12px" }}>
        <code>"temporary\_bank\_account"</code>
      </td>
    </tr>
  </tbody>
</table>

**Mobile money**

```json  theme={null}
"pay_in": {
  "type": "mobile_money",
  "phone": "+254 702288461"
}
```

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>Key</th>

      <th style={{ width: "50%", textAlign: "left", padding: "12px" }}>
        Description
      </th>

      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Value
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>
        <code>type</code>
      </td>

      <td style={{ padding: "12px" }}>Specifies the payment type.</td>

      <td style={{ padding: "12px" }}>
        <code>"mobile\_money"</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>phone</code>
      </td>

      <td style={{ padding: "12px" }}>
        The mobile number associated with the mobile money account for deposit.
      </td>

      <td style={{ padding: "12px" }}>
        <code>"+254 702288461"</code>
      </td>
    </tr>
  </tbody>
</table>

### The `pay_out` object

The fields in the `pay_out` object differ from transfer channel to transfer channel.

<Note>
  For mobile transfer and mobile money payouts, the recipient should be
  registered within your Busha business account.
</Note>

**Cryptocurrency**

```json  theme={null}
{
  "type": "address",
  "address": "tb1qzw4ynldc55lpkx3vcsk03susv9nwzj6qp78qsq",
  "network": "BTC",
  "memo": ""
}
```

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>Key</th>

      <th style={{ width: "50%", textAlign: "left", padding: "12px" }}>
        Description
      </th>

      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Value
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>
        <code>type</code>
      </td>

      <td style={{ padding: "12px" }}>Specifies the payout type.</td>

      <td style={{ padding: "12px" }}>
        <code>"address"</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>address</code>
      </td>

      <td style={{ padding: "12px" }}>
        The cryptocurrency address for the payout.
      </td>

      <td style={{ padding: "12px" }}>
        <code>"tb1qzw4ynldc55lpkx3vcsk03susv9nwzj6qp78qsq"</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>network</code>
      </td>

      <td style={{ padding: "12px" }}>
        The blockchain network (e.g., Bitcoin, Ethereum) for the payout.
      </td>

      <td style={{ padding: "12px" }}>
        <code>"BTC"</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>memo</code>
      </td>

      <td style={{ padding: "12px" }}>
        An optional field for networks that require a memo/tag/destination tag.
      </td>

      <td style={{ padding: "12px" }} />
    </tr>
  </tbody>
</table>

**Bank transfer**

```json  theme={null}
{
  "type": "bank_transfer",
  "recipient_id": "677bbf9c7cf061f23784555a"
}
```

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>Key</th>

      <th style={{ width: "50%", textAlign: "left", padding: "12px" }}>
        Description
      </th>

      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Value
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>
        <code>type</code>
      </td>

      <td style={{ padding: "12px" }}>Specifies the payout type.</td>

      <td style={{ padding: "12px" }}>
        <code>"bank\_transfer"</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>recipient\_id</code>
      </td>

      <td style={{ padding: "12px" }}>
        The ID of the pre-registered bank account recipient for the payout.
      </td>

      <td style={{ padding: "12px" }}>
        <code>"677bbf9c7cf061f23784555a"</code>
      </td>
    </tr>
  </tbody>
</table>

**Mobile money**

```json  theme={null}
{
  "type": "mobile_money",
  "recipient_id": "677bbf9c7cf061f23784555a"
}
```

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>Key</th>

      <th style={{ width: "50%", textAlign: "left", padding: "12px" }}>
        Description
      </th>

      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Value
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>
        <code>type</code>
      </td>

      <td style={{ padding: "12px" }}>Specifies the payout type.</td>

      <td style={{ padding: "12px" }}>
        <code>"mobile\_money"</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>recipient\_id</code>
      </td>

      <td style={{ padding: "12px" }}>
        The ID of the pre-registered mobile money recipient for the payout.
      </td>

      <td style={{ padding: "12px" }}>
        <code>"677bbf9c7cf061f23784555a"</code>
      </td>
    </tr>
  </tbody>
</table>

### Rate object

The `rate` object nested within the Quote response describes the precise exchange value used for the conversion between the `source_currency` and `target_currency` at that moment.

It typically includes the `pair` (e.g., `BTCNGN`), the `rate` itself, the `side` of the transaction (e.g., `buy` or `sell` from Busha's perspective), and the `type` of rate (e.g., `FIXED`). This `rate` is fundamental to how Busha validates and calculates the `source_amount` and `target_amount`.

### Fees object

The `fees` object within the Quote response represents any service charges or amounts that will be deducted from the `source_amount` (or sometimes the `target_amount`, depending on the transaction type) when the Quote is executed. This provides full transparency on transaction costs.

### Quote expiration (expires\_at)

**All Quotes expire!**

Every Quote object includes an `expires_at` timestamp. This specifies the exact time after which the Quote becomes invalid and can no longer be used to make any transfers.

Quotes typically have a short validity period (e.g., a few seconds to a few minutes) to account for volatility in market prices, especially for cryptocurrencies. If you attempt to execute a transaction with an expired Quote, the request will be executed at the current market price.

### Example of a Quote object

Here is a typical structure of a Quote object that you would receive from the Busha API. This example illustrates a proposed "sell" operation (where you're selling BTC to get NGN):

```json  theme={null}
{
  "status": "success",
  "message": "Created quote successfully",
  "data": {
    "id": "QUO_uNiw1CDqGrdIlK15N0bu1",
    "profile_id": "BUS_VI3eUQSbGBx4EQuQWYDU1",
    "source_currency": "BTC",
    "target_currency": "NGN",
    "source_amount": "0.00007051",
    "target_amount": "10050.58",
    "rate": {
      "product": "BTCNGN",
      "rate": "142541279.55",
      "side": "sell",
      "type": "FIXED",
      "source_currency": "BTC",
      "target_currency": "NGN"
    },
    "fees": [],
    "reference": "QUO_uNiw1CDqGrdIlK15N0bu1",
    "status": "pending",
    "expires_at": "2025-02-20T06:57:05.059512+01:00",
    "created_at": "2025-02-20T06:27:05.059464+01:00",
    "updated_at": "2025-02-20T06:27:05.059464+01:00"
  }
}
```

<Tip>
  This example represents a sell operation for BTC to your NGN Busha balance,
  where you specified the desired `target_amount` in NGN, and the API calculated
  the `source_amount` in BTC required.
</Tip>

## Why Quotes are the Backbone of Busha's Flow

The reason Quotes are fundamental to Busha's API is that **every transaction that involves an exchange or transfer of assets (e.g., currency conversion, deposit, payouts, buying/selling crypto) must begin with a Quote.**

By mandating a Quote first, Busha ensures:

* **Price Certainty:** You know the exact exchange rate, fees, and final amounts before committing to any transaction. This eliminates surprises.

* **Transparency:** All associated costs and conditions are clearly presented within the Quote object.

* **User Confirmation:** Your application can present the precise terms of a transaction to your end-user for their explicit confirmation before proceeding, enhancing trust and preventing errors.

* **Safety & Pre-validation:** A Quote acts as a real-time validation step, confirming that the proposed transaction is valid and executable under current market conditions and system availability, considering the specific transfer methods (`pay_in` and `pay_out`) if provided.




> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Transactions

> Track and monitor all transaction activity in Busha. View history, statuses, and transaction details.

A **Transaction** in Busha Business is a historical record of any financial activity that affects an account balance. While Transfers represent the process of moving assets, Transactions represent the ledger entries that record all balance changes, providing a complete, immutable history of financial activity.

## What is a Transaction?

A Transaction is a record of a completed financial event that has impacted a balance on the Busha platform. Transactions serve as the official ledger of all account activity, providing a historical record of all balance changes, an audit trail for compliance and reconciliation, user-facing statements for account activity, and reporting data for analytics and accounting.

### Transactions vs. transfers

It's important to understand the distinction between Transactions and Transfers:

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "20%", textAlign: "left", padding: "12px" }}>
        Aspect
      </th>

      <th style={{ width: "40%", textAlign: "left", padding: "12px" }}>
        Transfer
      </th>

      <th style={{ width: "40%", textAlign: "left", padding: "12px" }}>
        Transaction
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>Purpose</td>
      <td style={{ padding: "12px" }}>Process of moving assets</td>
      <td style={{ padding: "12px" }}>Record of balance change</td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Lifecycle</td>

      <td style={{ padding: "12px" }}>
        Has multiple statuses (pending, processing, completed)
      </td>

      <td style={{ padding: "12px" }}>Always completed</td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Timing</td>

      <td style={{ padding: "12px" }}>
        Created when initiated, updated until complete
      </td>

      <td style={{ padding: "12px" }}>Created only when finalized</td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Use Case</td>
      <td style={{ padding: "12px" }}>Track asset movement in progress</td>
      <td style={{ padding: "12px" }}>View historical account activity</td>
    </tr>
  </tbody>
</table>

## Core Components of a Transaction

Every Transaction contains these essential pieces of information:

### Identification

* **`id`**: Unique identifier for the transaction
* **`reference`**: A tracking reference
* **`user_id`**: The user who owns this transaction
* **`profile_id`**: The profile (business or customer) the transaction belongs to

### Amount and value

* **`amount`**: How much was transacted
* **`currency`**: Which currency (BTC, NGN, USDT, etc.)
* **`rate`**: The exchange rate used
* **`is_fiat`**: Boolean indicating if the currency is fiat
* **`is_credit`**: Boolean indicating if this adds or removes from balance

### Balance information

The transaction response includes a complete snapshot of the account balance:

* **`total_balance`**: Total balance after the transaction
* **`available_balance`**: Balance available for immediate use
* **`pending_balance`**: Funds pending confirmation
* **`hold`**: Funds temporarily held (e.g., for open orders)
* **`savings`**: Funds in savings/locked accounts

This balance snapshot helps you reconcile accounts and verify that transactions were processed correctly.

### Transaction metadata

The `meta` object contains transaction-specific details:

#### Balance snapshot

Shows balance state after the transaction:

```json  theme={null}
{
  "balance": {
    "total": "610000",
    "available": "610000"
  }
}
```

#### Amount breakdown

Detailed breakdown of amounts:

```json  theme={null}
{
  "amounts": {
    "fee": "100",
    "amount_paid": "200100",
    "amount_added": "200000"
  }
}
```

#### Fee details

Fee information:

```json  theme={null}
{
  "fee": {
    "amount": "100",
    "currency": "NGN"
  }
}
```

#### Price information

Transaction pricing:

```json  theme={null}
{
  "price": {
    "amount": "1",
    "currency": "NGN"
  }
}
```

### Source/destination details

For deposits and withdrawals:

```json  theme={null}
{
  "source": {
    "type": "bank_transfer",
    "account_name": "John Doe",
    "account_number": "0320484721"
  }
}
```

### Crypto transaction details

For blockchain transactions:

* **`address`**: Blockchain address
* **`blockchain_url`**: Link to view transaction on blockchain explorer
* **`confirmations`**: Number of blockchain confirmations received
* **`required_confirmations`**: Number of confirmations needed

### Conversion details

For buy, sell, and convert transactions:

* **`credit`**: What was added to the balance
* **`debit`**: What was removed from the balance (e.g., "USDT 10")
* **`rate`**: The conversion rate used (e.g., "NGN 1474.86")
* **`fiat_value`**: Fiat equivalent value
* **`fiat_currency`**: The fiat currency for valuation

## Transaction Types

Transactions are categorized by type to make filtering and reporting easier:

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Transaction Type
      </th>

      <th style={{ width: "75%", textAlign: "left", padding: "12px" }}>
        Description
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>
        <strong>Deposits</strong>
      </td>

      <td style={{ padding: "12px" }}>
        Incoming deposits to Busha balances from external sources.
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <strong>Buys</strong>
      </td>

      <td style={{ padding: "12px" }}>
        Cryptocurrency purchase transactions where fiat currency was used to
        acquire crypto.
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <strong>Sells</strong>
      </td>

      <td style={{ padding: "12px" }}>
        Cryptocurrency sale transactions where crypto was converted to fiat
        currency.
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <strong>Converts</strong>
      </td>

      <td style={{ padding: "12px" }}>
        Currency conversion transactions within Busha balances (crypto-to-crypto
        or crypto-to-fiat).
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <strong>Withdrawals</strong>
      </td>

      <td style={{ padding: "12px" }}>
        Outgoing withdrawals from Busha balances to external accounts.
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <strong>Fees</strong>
      </td>

      <td style={{ padding: "12px" }}>
        Fee transactions for various operations like deposits, withdrawals,
        conversions, and other services.
      </td>
    </tr>
  </tbody>
</table>



> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Multi-Currency Accounts

> Manage multiple currencies in a single account. Support NGN, KES, USDT, BTC, and more in your application.

Busha's Multi-Currency Accounts (MCAs) empower businesses to seamlessly manage funds across various global currencies. This functionality is designed to streamline your financial operations, simplify cross-border transactions, and provide your customers with a smooth cross-border payment experience.

At its core, a Multi-Currency Account allows your business to hold, receive, and send funds in different currencies from a single, unified platform, eliminating the complexities often associated with cross-border banking.

## Why Multi-Currency Accounts Matter

In today's global economy, businesses often deal with customers, suppliers, and partners operating in diverse currencies. Multi-Currency Accounts are crucial because they:

* **Reduce Conversion Costs:** Minimize fees and potential losses from frequent currency conversions by holding funds in their native currency.
* **Improve Cash Flow Management:** Gain a consolidated view of your finances across all currencies, enabling better financial planning and liquidity management.
* **Enhance Customer Experience:** Offer your customers the convenience of paying in their preferred currency, simplifying transactions and fostering trust.
* **Streamline Operations:** Consolidate international financial operations into a single, easy-to-manage system.

## Key Features of Busha Multi-Currency Accounts

Busha's MCA solution comes with robust features designed for global financial flexibility:

### Flexible Account Creation

Busha enables you to easily and instantly create dedicated accounts for multiple currencies, supporting your global transactions effortlessly. This allows you to manage various currencies (e.g., USD, EUR, GHS, NGN, BTC, ETH) all under one unified Busha platform, streamlining your international financial operations.

### Real-Time Balances

Busha provides instantaneous visibility into your account balances across all supported currencies, allowing you to stay informed and in control of your finances. You can track individual currency accounts or view consolidated totals in real-time, ensuring you always have an up-to-date view of your financial position.

### Seamless Currency Conversion

Busha empowers you to effortlessly convert funds between supported currencies within your accounts at competitive rates. This capability helps you optimize capital management, reduce friction, and manage international payments efficiently, giving you the flexibility to move funds where they're needed most.

### Comprehensive Reporting

Busha provides access to detailed transaction histories and account activity through robust reporting tools, ensuring full transparency across all your currency accounts. This comprehensive reporting simplifies reconciliation, auditing, and financial planning for your business, giving you the insights needed to make informed decisions.

## What's Next?

Now that you understand the benefits and features of Busha's Multi-Currency Accounts, you can learn how to manage them:

* [How-to Guide: Manage Your Account Balances](/guides/balance/manage-balance-accounts): Learn how to retrieve and interact with your multi-currency balances via the API.
* [Reference: Supported Currency Pairs](/api-reference/pairs/list-trading-pairs): Get a comprehensive list of all currency pairs supported for conversions.
* [API Reference: Multi-Currency Accounts Endpoints](/api-reference/currencies/retrieve-supported-currencies): Dive into the technical specifications for all MCA-related API calls.


> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Compliance

> Understand Busha's compliance requirements. KYC, AML policies, and regulatory guidelines for your integration.

Busha, like other financial institutions, operates under strict regulatory requirements designed to combat financial crime and ensure a secure financial ecosystem. These requirements mandate that we verify the identity of all our customers, whether they are individuals or businesses.

<Note>
  In a hurry? See the summarized requirements for each business types [in this
  table](#required-documents-by-business-entity-type).
</Note>

## Regulatory Pillars

* **Anti-Money Laundering (AML) Regulations:** These laws are designed to prevent the use of financial systems for illicit activities like money laundering, which involves disguising illegally obtained funds as legitimate income.

* **Know Your Customer (KYC) Requirements:** KYC is a critical part of AML, requiring us to identify and verify the identity of our customers. This helps us understand who we are doing business with and assess potential risks.

* **Counter-Terrorism Financing (CTF) Laws:** These laws aim to disrupt the flow of funds to terrorist organizations.

* **Local Financial Regulations:** In addition to international standards, Busha adheres to specific financial regulations in Nigeria and Kenya, where we operate.

## Impact on Your Transactions

These regulations directly influence how you interact with Busha. To ensure compliance and the security of our platform:

* **Mandatory Verification:** All customers must undergo and pass the verification process before they can conduct any transactions.
* **Document Submission:** Providing the required identification and business documents is not optional; it's a mandatory step in activating your account.
* **Transaction Limits:** Your verification status determines the transaction limits applied to your account. Fully verified accounts typically have higher limits.
* **Account Activation:** If verification fails or documents are not submitted, your account cannot be fully activated for transactions.

## KYC Requirements for Individual Customers

To verify individual customers, Busha collects specific identity documents and performs a proof-of-identity step. The exact requirements vary slightly by country to align with local regulations and common identification methods.

### Nigeria

* **Primary ID:** You can provide one of the following: a National Identification Number (NIN) slip, or a national passport.
* **Proof of Identity:** A selfie video is required to confirm that the person providing the documents is indeed the account holder.

### Kenya

* **Primary ID:** A National ID card is required.
* **Proof of Identity:** Similar to Nigeria, a selfie video is needed for proof of identity.

### The Verification Process

The individual customer verification process follows a clear sequence:

1. **Document Provision:** You submit the required primary ID and selfie video through the Busha platform.
2. **Document Review:** Our compliance team reviews the submitted documents for authenticity, validity, and adherence to technical requirements.
3. **Identity Verification:** Once the documents are verified, your identity is confirmed.
4. **Account Activation:** Upon successful identity verification, your account is fully activated, allowing you to conduct transactions within your designated limits.

### Technical Requirements for Document Submission

When submitting your documents, please ensure they meet the following technical specifications to facilitate a smooth verification process:

#### Via Dashboard

* **Format:** Accepted file formats include PDF, JPG, JPEG, and PNG
* **File Size:** Each file should not exceed 4MB
* **Clarity:** Photos of your primary ID must be clear, well-lit, and easily readable with no glare or obstructions your selfie video should clearly show your facial features, allowing for easy identification

#### Via the API

* **Format:** All files (images and videos) must be submitted in Base64 format
* **File Size:** Each file should not exceed 4MB
* **Clarity:** Same clarity requirements as dashboard submissions apply

## Requirements for Business Customers (KYB - Know Your Business)

For businesses, the verification process, often referred to as Know Your Business (KYB), is more comprehensive due to the complexity of business structures and ownership. This ensures that Busha understands the nature of the business, its ownership, and its operational legitimacy.

Busha offers tiered verification levels, each with specific document requirements and transaction limits.

### Tier 1 Verification (Basic Business Verification)

Tier 1 verification allows you to access basic business features with standard transaction limits.

#### Required Documents

* **Certificate of Incorporation**: Legal proof that the business is officially registered.
* **Corporate Registry Extract**: A document from the relevant corporate registry detailing key information about the business (e.g., directors, registered address).

#### Transaction Limits

| Business Type                      | Account Type | Daily Limit | Monthly Limit |
| :--------------------------------- | :----------- | ----------: | ------------: |
| Business Names/Sole Proprietorship | Main         |   \$100,000 |   \$3,000,000 |
| Business Names/Sole Proprietorship | Sub-Account  |   \$100,000 |   \$3,000,000 |
| Limited Liability Company          | Main         | \$1,000,000 |  \$20,000,000 |
| Limited Liability Company          | Sub-Account  | \$1,000,000 |  \$20,000,000 |

<Note>
  There is no Tier 2 verification for Business Names/Sole Proprietorships.
</Note>

### Tier 2 Verification (Unlimited Transaction Limits)

Tier 2 verification removes all transaction limits and unlocks full platform capabilities. This applies to **Limited Liability Companies only**.

#### Required Documents (in addition to Tier 1 documents)

* **Memorandum of Association Articles (Memart)**: A legal document outlining the company’s objectives and how it will achieve them.
* **Corporate Structure Chart**: A visual representation of the ownership and management hierarchy of the business.
* **Board Resolution**: Official minutes or a statement from the company’s board of directors authorizing specific actions or individuals.
* **Anti-Money Laundering Policy**: The business’s internal policy for preventing money laundering and other financial crimes.
* **Regulatory Licenses**: Any specific licenses required for the business to operate in its industry (e.g., financial services license, trading license).
* **Proof of Wealth**: Documentation demonstrating the source of the business’s funds.
* **Proof of Address**: Documents confirming the official registered address of the business.

#### Transaction Limits

| Business Type             | Account Type | Daily Limit | Monthly Limit |
| :------------------------ | :----------- | :---------- | :------------ |
| Limited Liability Company | Main         | Unlimited   | Unlimited     |
| Limited Liability Company | Sub-Account  | Unlimited   | Unlimited     |

### The Verification Process

The business verification process mirrors the individual process but with a focus on comprehensive business information:

1. **Document Provision:** The business provides all the required corporate and legal documents for the desired verification tier.
2. **Document Review:** Our compliance team meticulously reviews each document for authenticity, completeness, and adherence to legal standards.
3. **Identity Verification:** Once the business's identity and its key stakeholders are verified, the KYB process is completed.
4. **Account Activation:** Upon successful verification, the business account is activated at the appropriate tier level, enabling it to conduct transactions on the Busha platform.

#### Via The Dashboard

* **Format:** Accepted file formats include PDF, JPG, JPEG, and PNG
* **File Size:** The maximum file size per document is 4MB
* **Validity:** All submitted documents must be official, current, and not expired

#### Via The API

* **Format:** All files must be in Base64 format
* **File Size:** The maximum file size per document is 4MB
* **Validity:** All submitted documents must be official, current, and not expired

## Understanding Business Entity Types

The specific onboarding requirements for businesses can vary significantly based on their legal structure. Understanding these different entity types is crucial for preparing the correct documentation.

### Sole Proprietorships

**Definition:** Businesses owned and run by a single individual.

**Liability:** The owner has unlimited personal liability for business debts.

**Simplicity:** This is typically the simplest business structure to set up.

**Required Documents:** Business License, Trade Name Registration (DBA - "Doing Business As"), Tax registration documents.

### General Partnerships (GP)

* **Definition:** Two or more individuals share ownership and management responsibilities.
* **Liability:** All partners have unlimited personal liability.
* **Required Documents:** Partnership Agreement, Business License listing all partners, Trade Name Registration (DBA) listing all partners.

### Limited Partnerships (LP)

* **Definition:** Include both general partners (with unlimited liability and management control) and limited partners (with limited liability and no management control).
* **Required Documents:** Certificate of Limited Partnership (filed with government), Limited Partnership Agreement, Registration documents.

### Limited Liability Partnerships (LLP)

* **Definition:** Partners have limited personal liability, typically protecting them from the actions of other partners. Common in professional services.
* **Required Documents:** Certificate of Limited Liability Partnership, LLP Agreement, Registration documents.

### Corporations

**Definition:** A separate legal entity from its owners (shareholders).

**Liability:** Shareholders have limited liability, meaning their personal assets are generally protected from business debts.

**Required Documents:** Articles of Incorporation (filed with government), Certificate of Incorporation, Corporate Bylaws, Shareholder Agreements.

### Limited Liability Companies (LLC)

**Definition:** A hybrid structure combining the limited liability of a corporation with the pass-through taxation and flexibility of a partnership.

**Liability:** Members have limited liability.

**Management:** Offers a flexible management structure.

**Required Documents:** Articles of Organization (filed with government), Operating Agreement, Membership Certificates, Registration documents.

### Nonprofit Organizations

**Definition:** Established for charitable, educational, religious, or social purposes, rather than for profit.

**Tax Status:** Can apply for tax-exempt status.

**Ownership:** No individual beneficial shareholders.

**Required Documents:** Articles of Incorporation, Certificate of Incorporation, Tax-exempt status application (if applicable), Bylaws.

### Foundations

**Definition:** Entities established to hold and manage assets for charitable or philanthropic purposes.

**Governance:** Governed by a board of trustees or directors.

**Ownership:** No individual beneficial shareholders.

**Required Documents:** Articles of Incorporation, Certificate of Incorporation, Foundation Charter or Trust Deed, Board Resolutions.

### Trusts

**Definition:** A legal arrangement where a trustee holds assets for the benefit of beneficiaries.

**Management:** Managed according to the specific terms outlined in a trust agreement.

**Variety:** Various types exist for different purposes (e.g., living trusts, testamentary trusts).

**Required Documents:** Trust Deed (or Trust Agreement), Certificate of Trust, Notarized Trustee's Affidavit, Trust registration documents.

## Required Documents by Business Entity Type

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "30%", textAlign: "left", padding: "12px" }}>
        Business Entity Type
      </th>

      <th style={{ width: "50%", textAlign: "left", padding: "12px" }}>
        Required Documents
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>Sole Proprietorships</td>

      <td style={{ padding: "12px" }}>
        <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
          <li>Business License</li>
          <li>Trade Name Registration (DBA - "Doing Business As")</li>
          <li>Tax registration documents</li>
        </ul>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>General Partnerships (GP)</td>

      <td style={{ padding: "12px" }}>
        <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
          <li>Partnership Agreement</li>
          <li>Business License listing all partners</li>
          <li>Trade Name Registration (DBA) listing all partners</li>
        </ul>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Limited Partnerships (LP)</td>

      <td style={{ padding: "12px" }}>
        <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
          <li>Certificate of Limited Partnership (filed with government)</li>
          <li>Limited Partnership Agreement</li>
          <li>Registration documents</li>
        </ul>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Limited Liability Partnerships (LLP)</td>

      <td style={{ padding: "12px" }}>
        <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
          <li>Certificate of Limited Liability Partnership</li>
          <li>LLP Agreement</li>
          <li>Registration documents</li>
        </ul>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Corporations</td>

      <td style={{ padding: "12px" }}>
        <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
          <li>Articles of Incorporation (filed with government)</li>
          <li>Certificate of Incorporation</li>
          <li>Corporate Bylaws</li>
          <li>Shareholder Agreements</li>
        </ul>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Limited Liability Companies (LLC)</td>

      <td style={{ padding: "12px" }}>
        <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
          <li>Articles of Organization (filed with government)</li>
          <li>Operating Agreement</li>
          <li>Membership Certificates</li>
          <li>Registration documents</li>
        </ul>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Nonprofit Organizations</td>

      <td style={{ padding: "12px" }}>
        <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
          <li>Articles of Incorporation</li>
          <li>Certificate of Incorporation</li>
          <li>Tax-exempt status application (if applicable)</li>
          <li>Bylaws</li>
        </ul>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Foundations</td>

      <td style={{ padding: "12px" }}>
        <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
          <li>Articles of Incorporation</li>
          <li>Certificate of Incorporation</li>
          <li>Foundation Charter or Trust Deed</li>
          <li>Board Resolutions</li>
        </ul>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Trusts</td>

      <td style={{ padding: "12px" }}>
        <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
          <li>Trust Deed (or Trust Agreement)</li>
          <li>Certificate of Trust</li>
          <li>Notarized Trustee's Affidavit</li>
          <li>Trust registration documents</li>
        </ul>
      </td>
    </tr>
  </tbody>
</table>


> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Process Payouts

> Send payouts to bank accounts and mobile money.

This guide will walk you through the process of programmatically making withdrawals (Off-Ramp) from your Busha Business crypto balances to fiat destinations like bank accounts or mobile money wallets. Payouts, like all transfers, require a Quote to define the transaction terms and a pre-configured Recipient to specify the destination.

<Tip>
  **What You'll Achieve:**

  1. Understand the prerequisites of creating a recipient for payouts.
  2. Generate a specific Quote for a crypto-to-fiat payout.
  3. Execute the payout transfer from your Busha crypto balance to fiat account.
  4. Monitor the status of your payout.
</Tip>

## Prerequisites

Before you begin, ensure you have:

* A **Busha Business Account** and **Secret API Key** (from the [Quick Start Tutorial](/guides/getting-started/quick-start)).
* An understanding of **API Environments** (Sandbox vs. Production) and their base URLs (from the [Make Your First Request Guide](/guides/getting-started/make-first-request)).
* A conceptual understanding of **Quotes** (from the [Understanding Quotes](/overview/quotes) Overview).
* Familiarity with creating basic quotes (from the [How to Create Your First Quote Guide](/guides/quotes/create-first-quote)).
* An existing **recipient ID** for the bank account or mobile money wallet you wish to send funds to. If you don't have one, please refer to the [How to Create Recipients Guide](/guides/recipients/create-and-manage).

<Note>
  For payout requests involving a customer, the `X-BU-PROFILE-ID` field should
  be included in the request header, and its value should be set to the customer
  ID for whom the request is performed on their behalf.
</Note>

## How to Process Crypto to Fiat Payouts

<Steps>
  <Step title="Create or Identify a Recipient">
    For `bank_transfer` or `mobile_money` payouts, Busha requires an existing `recipient_id` within the `pay_out` object of your quote. This ensures that the destination account has been previously verified and is associated with your profile, streamlining the payout process.

    If you haven't already, you must create a recipient for the specific bank account or mobile money wallet you intend to send funds to.

    For detailed instructions on creating and managing recipients, please refer to the [How to Create and Manage Recipients Guide](/guides/recipients/create-and-manage).

    Once you have a `recipient_id`, your `pay_out` object will look similar to this example:

    ```json  theme={null}
    "pay_out": {
      "type": "bank_transfer", // or "mobile_money"
      "recipient_id": "677bbf9c7cf061f23784555a" // Replace your actual recipient ID
    }
    ```
  </Step>

  <Step title="Create a Payout Quote">
    With your `recipient_id` in hand, the next step is to create a Quote for your payout. This quote will specify the cryptocurrency you're withdrawing from (`source_currency`), the fiat currency the recipient will receive (`target_currency`), the amount, and importantly, the `pay_out` object with the recipient details.

    To create a payout quote:

    1. Open your terminal or command prompt.
    2. Construct a `POST` request to the `/v1/quotes` endpoint.
    3. Set the type to "withdrawal".
    4. Specify `source_currency` (e.g., USDT), `target_currency` (e.g., NGN), and either `source_amount` or `target_amount` (e.g., `target_amount: "100"` NGN the recipient should receive).
    5. Include the `pay_out` object with the type and `recipient_id` you prepared in Step 1.
    6. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_KEY`.

    ```bash  theme={null}
    curl -i -X POST \
      https://YOUR_BASE_URL/v1/quotes \
      -H 'Authorization: Bearer YOUR_SECRET_KEY' \
      -H 'Content-Type: application/json' \
      -d '{
        "source_currency": "USDT",
        "target_currency": "NGN",
        "target_amount": "100",
        "pay_out": {
          "type": "bank_transfer",
          "recipient_id": "677bbf9c7cf061f23784555a"
        }
      }'
    ```

    **Expected Quote Response**

    A successful response will return a Quote object, detailing the `id` of the quote, the calculated `source_amount` (how much crypto you'll need to send if you provided `target_amount`), the rate, associated fees, and the `expires_at` timestamp. Importantly, the `pay_out` object will include `recipient_details` confirming the destination account information.

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created quote successfully",
      "data": {
        "id": "QUO_mprvCPMCfm3K2qSnzbWj7",
        "profile_id": "BUS_tg6yujbZ1nMu5BLQkPGGO",
        "source_currency": "USDT",
        "target_currency": "NGN",
        "source_amount": "100",
        "target_amount": "168876",
        "rate": {
          "product": "USDTNGN",
          "rate": "1690.76",
          "side": "sell",
          "type": "FIXED",
          "source_currency": "USDT",
          "target_currency": "NGN"
        },
        "fees": [
          {
            "amount": {
              "amount": "200",
              "currency": "NGN"
            },
            "name": "Fees",
            "type": "FIXED"
          }
        ],
        "pay_out": {
          "recipient_details": {
            "account_name": "SOSANYA DICKSON OLUMIDE",
            "account_number": "2109328188",
            "bank_name": "UNITED BANK FOR AFRICA",
            "country": "NG"
          },
          "recipient_id": "677bbf9c7cf061f2b784555a",
          "type": "bank_transfer"
        },
        "reference": "QUO_mprvCPMCfm3K2qSnzbWj7",
        "status": "pending",
        "expires_at": "2025-02-20T10:58:19.540052923Z",
        "created_at": "2025-02-20T10:28:19.540025003Z",
        "updated_at": "2025-02-20T10:28:19.540025003Z"
      }
    }
    ```
  </Step>

  <Step title="Create the Payout Transfer">
    This is the final step where you initiate the actual withdrawal from your Busha crypto balance to the specified fiat recipient. You do this by creating a Transfer using the `quote_id` obtained in Step 2.

    To create the payout transfer:

    1. Use the `POST` request below to the `/v1/transfers` endpoint.
    2. Include the `quote_id` obtained from Step 2 in the request body.
    3. Replace `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN` with your actual details.

    ```bash  theme={null}
    $ curl -i -X POST \
      https://YOUR_BASE_URL/v1/transfers \
      -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
      -H 'Content-Type: application/json' \
      -d '{
        "quote_id": "QUO_Nm2EBRxmuHGdTyGnVNDUt"
      }'
    ```

    **Expected Transfer Response**

    A successful response will return a Transfer object, containing the same information as the quote, plus the `id` of the transfer (e.g., `TRF_tYZ1y5bmXv4N5IhXSMbWJ`) and its current status. For payouts, the status will typically start as `pending` and change as the payout is processed.

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created transfer successfully",
      "data": {
        "id": "TRF_tYZ1y5bmXv4N5IhXSMbWJ",
        "profile_id": "BUS_tg6yujbZ1nMu5BLQkPGGO",
        "quote_id": "QUO_mprvCPMCfm3K2qSnzbWj7",
        "source_currency": "USDT",
        "target_currency": "NGN",
        "source_amount": "100",
        "target_amount": "168876",
        "rate": {
          "product": "USDTNGN",
          "rate": "1690.76",
          "side": "sell",
          "type": "FIXED",
          "source_currency": "USDT",
          "target_currency": "NGN"
        },
        "fees": [
          {
            "amount": {
              "amount": "200",
              "currency": "NGN"
            },
            "name": "Fees",
            "type": "FIXED"
          }
        ],
        "pay_out": {
          "recipient_details": {
            "account_name": "SOSANYA DICKSON OLUMIDE",
            "account_number": "2109328188",
            "bank_name": "UNITED BANK FOR AFRICA",
            "country": "NG"
          },
          "recipient_id": "677bbf9c7cf061f2b784555a",
          "type": "bank_transfer"
        },
        "status": "pending",
        "created_at": "2025-02-20T10:28:42.376910852Z",
        "updated_at": "2025-02-20T10:28:42.376910905Z"
      }
    }
    ```
  </Step>

  <Step title="Monitor Payout Status">
    For payouts, it's crucial to monitor the transfer's status to confirm successful delivery of funds to the recipient.

    To monitor payout status:

    * **Webhooks (Recommended):** Set up a webhook endpoint to receive real-time notifications from Busha when the transfer status changes (e.g., from `pending` to `completed` or `failed`). This is the most efficient method for real-time updates. Refer to the [How to Set Up Webhooks Guide](/guides/webhooks/setup) for detailed instructions.

    * **Polling (Less Recommended):** Periodically GET the transfer status using the transfer `id` (`TRF_tYZ1y5bmXv4N5IhXSMbWJ` in the example). While possible, this is less efficient and can lead to rate limiting if done too frequently.

    ```bash  theme={null}
    curl -X GET "YOUR_BASE_URL/v1/transfers/TRF_tYZ1y5bmXv4N5IhXSMbWJ" -H "Authorization: Bearer {YOUR_SECRET_API_KEY}"
    ```

    **Expected Transfer Statuses**

    * **pending:** Transfer initiated, awaiting user bank transfer.
    * **processing:** This means the funds has been received, and is being handled.
    * **funds\_delivered:** Funds have been successfully received and credited to your Busha balance.
    * **cancelled:** The transfer has been cancelled, and will not continue.
  </Step>
</Steps>

## Troubleshooting

**Common Payout Issues:**

* **"Quote expired" during transfer creation:** Always generate a fresh quote immediately before attempting to create the transfer.
* **"Insufficient balance":** Ensure your Busha account has enough `source_currency` to cover the `source_amount` in the quote.
* **Invalid Recipient ID:** Double-check that the `recipient_id` in your `pay_out` object is correct and active. An invalid ID will cause the transfer to fail.
* **Payout delayed:** Bank transfers can sometimes take longer than crypto transfers due to banking hours or processing times. Monitor status via webhooks. If prolonged, contact Busha support.
* **`401 Unauthorized`:** Verify your Authorization header and API key.

## What's Next?

Now that you know how to make payouts, you can explore other transaction types or manage recipients:

* [How to Create and Manage Recipient Guide](/guides/recipients/create-and-manage): Essential for managing your payout destinations.
* [How to Process Fiat Deposits Guide](/guides/deposits/process-fiat-deposits)
* [How to Process Crypto Deposits Guide](/guides/deposits/process-crypto-deposits)
* [How to Perform Balance-to-Balance Conversions Guide](/guides/balance/convert-balance-to-balance)
* [Quotes API Reference](/api-reference/quotes/list-quotes)
* [Transfers API Reference](/api-reference/transfers/list-transfers)
> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Process Crypto Payouts

> Send cryptocurrency payouts to blockchain addresses.

This guide will walk you through the process of programmatically making crypto withdrawals (Off-Ramp) from your Busha Business crypto balances. Payouts, like all transfers, require a Quote to define the transaction terms and a pre-configured Recipient to specify the destination.

<Tip>
  **What You'll Achieve:**

  1. Understand the prerequisites of creating a recipient for payouts.
  2. Generate a specific Quote for a crypto-to-crypto payout.
  3. Execute the payout transfer from your Busha crypto balance to your crypto wallet.
  4. Monitor the status of your payout.
</Tip>

## Prerequisites

Before you begin, ensure you have:

* A **Busha Business Account** and **Secret API Key** (from the [Quick Start Tutorial](/guides/getting-started/quick-start)).
* An understanding of **API Environments** (Sandbox vs. Production) and their base URLs (from the [Make Your First Request Guide](/guides/getting-started/make-first-request)).
* A conceptual understanding of **Quotes** (from the [Understanding Quotes](/overview/quotes) Overview).
* Familiarity with creating basic quotes (from the [How to Create Your First Quote Guide](/guides/quotes/create-first-quote)).

<Note>
  For payout requests involving a customer, the `X-BU-PROFILE-ID` field should
  be included in the request header, and its value should be set to the customer
  ID for whom the request is performed on their behalf.
</Note>

## How to Process Crypto to Crypto Payouts

<Steps>
  <Step title="Prepare Your Quote Object">
    For crypto payouts, Busha requires a valid address, network and memo (for networks that mandate a memo for cryptocurrency transactions) in the `pay_out` object of your quote.

    Your `pay_out` object should look like this:

    ```json  theme={null}
    "pay_out": {
      "type": "address",
      "address": "tb1qzw4ynldc55lpkx3vcsk03susv9nwzj6qp78qsq",
      "network": "BTC"
    }
    ```

    <Danger>The network type must be in uppercase, e.g., BTC, USDT, TRX.</Danger>
  </Step>

  <Step title="Create a Payout Quote">
    The next step is to create a Quote for your payout. This quote will specify the cryptocurrency you're withdrawing from (`source_currency`), the cryptocurrency the recipient will receive (`target_currency`), the amount, and importantly, the `pay_out` object with the recipient details.

    To create a payout quote:

    1. Open your terminal or command prompt.
    2. Construct a `POST` request to the `/v1/quotes` endpoint.
    3. Set the type to "withdrawal".
    4. Specify `source_currency` (e.g., BTC), `target_currency` (e.g., BTC), and either `source_amount` or `target_amount` (e.g., `target_amount: "100"` the recipient should receive).
    5. Include the `pay_out` object you prepared in Step 1.
    6. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_KEY`.

    ```bash  theme={null}
    $ curl -i -X POST https://YOUR_BASE_URL/v1/quotes \
      -H 'Authorization: Bearer YOUR_SECRET_KEY' \
      -H 'Content-Type: application/json' \
      -d '{
        "source_currency": "BTC",
        "target_currency": "btc",
        "target_amount": "0.0001",
        "pay_out": {
          "type": "address",
          "address": "tb1qzw4ynldc55lpkx3vcsk03susv9nwzj6qp78qsq",
          "network": "BTC"
        }
      }'
    ```

    <Info>
      For sandbox requests, you can obtain test addresses from any of the providers
      listed in the Test Addresses reference.
    </Info>

    **Expected Quote Response**

    A successful response will return a Quote object, detailing the `id` of the quote, the calculated `source_amount` (how much crypto you'll need to send if you provided `target_amount`), the rate, associated fees, and the `expires_at` timestamp. Importantly, the `pay_out` object will include recipient\_details confirming the destination account information.

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created quote successfully",
      "data": {
        "id": "QUO_mZrlSnIFzGkA",
        "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
        "source_currency": "BTC",
        "target_currency": "BTC",
        "source_amount": "0.0001",
        "target_amount": "0.0001",
        "rate": {
          "product": "",
          "rate": "1",
          "side": "sell",
          "type": "FIXED",
          "source_currency": "BTC",
          "target_currency": "BTC"
        },
        "fees": [],
        "pay_out": {
          "address": "tb1qzw4ynldc55lpkx3vcsk03susv9nwzj6qp78qsq",
          "network": "BTC",
          "type": "address"
        },
        "reference": "QUO_mZrlSnIFzGkA",
        "status": "pending",
        "created_at": "2025-06-18T16:58:12.762193733Z",
        "updated_at": "2025-06-18T16:58:12.762193733Z"
      }
    }
    ```
  </Step>

  <Step title="Create the Payout Transfer">
    This is the final step where you initiate the actual withdrawal from your Busha crypto balance to the specified crypto wallet. You do this by creating a Transfer using the `quote_id` obtained in Step 2.

    To create the payout transfer:

    1. Use the `POST` request below to the `/v1/transfers` endpoint.
    2. Include the `quote_id` obtained from Step 2 in the request body.
    3. Replace `YOUR_BASE_URL` and `YOUR_SECRET_KEY` with your actual details.

    ```bash  theme={null}
    $ curl -i -X POST https://YOUR_BASE_URL/v1/transfers \
      -H 'Authorization: Bearer YOUR_SECRET_KEY' \
      -H 'Content-Type: application/json' \
      -d '{
        "quote_id": "QUO_mZrlSnIFzGkA"
      }'
    ```

    **Expected Transfer Response**

    A successful response will return a Transfer object, containing the same information as the quote, plus the `id` of the transfer (e.g., `TRF_tYZ1y5bmXv4N5IhXSMbWJ`) and its current status. For payouts, the status will typically start as `pending` and change as the payout is processed.

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created transfer successfully",
      "data": {
        "id": "TRF_4VJeKDm6Ow2V",
        "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
        "quote_id": "QUO_mZrlSnIFzGkA",
        "description": "Sold BTC",
        "sub_description": "For BTC",
        "source_currency": "BTC",
        "target_currency": "BTC",
        "source_amount": "0.0001",
        "target_amount": "0.0001",
        "rate": {
          "rate": "1",
          "side": "sell",
          "type": "FIXED",
          "source_currency": "BTC",
          "target_currency": "BTC"
        },
        "fees": [],
        "pay_out": {
          "address": "tb1qzw4ynldc55lpkx3vcsk03susv9nwzj6qp78qsq",
          "network": "BTC",
          "type": "address"
        },
        "status": "pending",
        "timeline": {
          "total_steps": 0,
          "current_step": 0,
          "transfer_status": "",
          "events": []
        },
        "created_at": "2025-06-18T17:00:37.695327321Z",
        "updated_at": "2025-06-18T17:00:37.695327373Z"
      }
    }
    ```
  </Step>

  <Step title="Monitor Payout Status">
    For payouts, it's crucial to monitor the transfer's status to confirm successful delivery of funds to the recipient.

    To monitor payout status:

    * **Webhooks (Recommended):** Set up a webhook endpoint to receive real-time notifications from Busha when the transfer status changes (e.g., from `pending` to `completed` or `failed`). This is the most efficient method for real-time updates. Refer to the [How to Set Up Webhooks Guide](/guides/webhooks/setup) for detailed instructions.

    * **Polling (Less Recommended):** Periodically GET the transfer status using the transfer `id` (`TRF_tYZ1y5bmXv4N5IhXSMbWJ` in the example). While possible, this is less efficient and can lead to rate limiting if done too frequently.

    ```bash  theme={null}
    curl -X GET "YOUR_BASE_URL/v1/transfers/TRF_4VJeKDm60w2V" -H "Authorization: Bearer {YOUR_SECRET_API_KEY}"
    ```

    **Expected Transfer Statuses**

    * **pending:** Transfer initiated, awaiting user bank transfer.
    * **processing:** This means the funds has been received, and is being handled.
    * **funds\_delivered:** Funds have been successfully received and credited to your Busha balance.
    * **cancelled:** The transfer has been cancelled, and will not continue.
  </Step>
</Steps>

## Troubleshooting

**Common Payout Issues:**

* **"Quote expired" during transfer creation:** Always generate a fresh quote immediately before attempting to create the transfer.
* **"Insufficient balance":** Ensure your Busha account has enough `source_currency` to cover the `source_amount` in the quote.
* **`401 Unauthorized`:** Verify your Authorization header and API key.

## What's Next?

Now that you know how to make payouts, you can explore other transaction types or manage recipients:

* [How to Process Fiat Payouts](/guides/payouts/process-payouts)
* [How to Perform Balance-to-Balance Conversions Guide](/guides/balance/convert-balance-to-balance)
* [Quotes API Reference](/api-reference/quotes/list-quotes)
* [Transfers API Reference](/api-reference/transfers/list-transfers)
> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Manage Balance Accounts

> View and manage multi-currency balances. Check NGN, KES, USDT, BTC, and other currency balances.

Balance accounts on Busha are designed to hold specific currencies, making them a crucial component for managing your multi-currency transactions. This guide will walk you through the process of programmatically creating new balance accounts and retrieving existing ones via the Busha API.

<Tip>
  **What You'll Achieve:**

  1. Learn how to create a new balance account for a specific currency
  2. Understand how to retrieve a list of all your existing balance accounts
  3. Confirm successful API interactions for managing currency balances
</Tip>

## Prerequisites

Before you begin, ensure you have:

* A **Busha Business Account** and **Secret API Key** (from the [Quick Start Tutorial](/guides/getting-started/quick-start))
* An understanding of the **API Environments** (Sandbox vs. Production) and their respective base URLs (from the [Make Your First Request Guide](/guides/getting-started/make-first-request)).

## Managing Your Balance Accounts

<Steps>
  <Step title="Identify Supported Currencies">
    Before creating a balance account, you must know which currencies Busha currently supports. This ensures your requests are valid.

    To find supported currencies:

    * Refer to the Supported Currencies Reference for the most up-to-date list of available currency codes.
    * When presenting a currency options to your customers, ensure you only display those returned by Busha's API or listed in our documentation to prevent invalid requests.
  </Step>

  <Step title="Retrieve All Your Balance Accounts">
    To view a comprehensive list of all existing balance accounts associated with your business, use a GET request. This is useful for displaying current balances or iterating through available currencies.

    To retrieve balance accounts:

    1. Open your terminal or command prompt
    2. Use the `GET` request below, replacing the placeholders:
       * `YOUR_BASE_URL`: Use your chosen environment's base URL.
       * `{YOUR_SECRET_API_KEY}`: Your actual Secret API Key.

    ```shell  theme={null}
    $ curl --request GET \
      --url YOUR_BASE_URL/v1/balances \
      --header 'Authorization: Bearer {YOUR_SECRET_API_KEY}' \
      --header 'Content-Type: application/json'
    ```

    **Expected Response**

    A successful response will return a JSON object containing an array of all your balance accounts.

    ```json  theme={null}
    {
      "status": "success",
      "message": "Fetched balances successfully",
      "data": [
        {
          "id": "96da4899-ff99-4821-88df-5182b8d37300",
          "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "user_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "currency": "GHS",
          "name": "Cedi",
          "type": "fiat",
          "available": {
            "amount": "0",
            "currency": "GHS"
          },
          "pending": {
            "amount": "0",
            "currency": "GHS"
          },
          "total": {
            "amount": "0",
            "currency": "GHS"
          }
        },
        {
          "id": "05320e5e-a8ff-48e4-ac0b-23f7b1aa14d7",
          "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "user_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "currency": "KES",
          "name": "Shilling",
          "type": "fiat",
          "available": {
            "amount": "0",
            "currency": "KES"
          },
          "pending": {
            "amount": "0",
            "currency": "KES"
          },
          "total": {
            "amount": "0",
            "currency": "KES"
          }
        },
        {
          "id": "960ab09d-cd55-4118-8b6e-c12b340efec3",
          "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "user_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "currency": "NGN",
          "name": "Naira",
          "type": "fiat",
          "available": {
            "amount": "0",
            "currency": "NGN"
          },
          "pending": {
            "amount": "0",
            "currency": "NGN"
          },
          "total": {
            "amount": "0",
            "currency": "NGN"
          }
        },
        {
          "id": "1ebeabb4-6e4f-449f-8c0f-18a9c9d9187a",
          "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "user_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "currency": "USD",
          "name": "US Dollar",
          "type": "fiat",
          "available": {
            "amount": "0",
            "currency": "USD"
          },
          "pending": {
            "amount": "0",
            "currency": "USD"
          },
          "total": {
            "amount": "0",
            "currency": "USD"
          }
        },
        {
          "id": "bc1dfeae-055e-4d4e-a583-0c8030e5a1cd",
          "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "user_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "currency": "BTC",
          "name": "Bitcoin",
          "type": "crypto",
          "available": {
            "amount": "0",
            "currency": "BTC"
          },
          "pending": {
            "amount": "0",
            "currency": "BTC"
          },
          "total": {
            "amount": "0",
            "currency": "BTC"
          }
        },
        {
          "id": "b8dbf5e4-4d1f-4489-81fe-2a5e1e9816e1",
          "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "user_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "currency": "ETH",
          "name": "Ethereum",
          "type": "crypto",
          "available": {
            "amount": "0",
            "currency": "ETH"
          },
          "pending": {
            "amount": "0",
            "currency": "ETH"
          },
          "total": {
            "amount": "0",
            "currency": "ETH"
          }
        },
        {
          "id": "ec5744b6-ab06-4180-b354-e672b9a5f30a",
          "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "user_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "currency": "USDC",
          "name": "USD Coin",
          "type": "crypto",
          "available": {
            "amount": "0",
            "currency": "USDC"
          },
          "pending": {
            "amount": "0",
            "currency": "USDC"
          },
          "total": {
            "amount": "0",
            "currency": "USDC"
          }
        },
        {
          "id": "fb891621-0b15-45f5-9b81-ea3995cbf891",
          "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "user_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "currency": "USDT",
          "name": "USD Token",
          "type": "crypto",
          "available": {
            "amount": "0",
            "currency": "USDT"
          },
          "pending": {
            "amount": "0",
            "currency": "USDT"
          },
          "total": {
            "amount": "0",
            "currency": "USDT"
          }
        }
      ],
      "pagination": {
        "current_entries_size": 8
      }
    }
    ```
  </Step>
</Steps>

## Troubleshooting

* **`401 Unauthorized Request`:** Double-check your API key.

## What's Next?

Now that you know how to retrieve balance accounts, you can:

* Explore all related endpoints and data structures in detail: [Multi-Currency Accounts API Reference](/api-reference/balances/list-all-balances)
> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Convert Between Balances

> Convert funds between currencies in your Busha account. Swap NGN to USDT, BTC to KES, and more.

This guide will show you how to programmatically convert one cryptocurrency into another within your Busha Business account (e.g., USDT to BTC). All conversions on Busha are processed as transfers, which always begin with a Quote to define the conversion terms.

<Tip>
  **What You'll Achieve:**

  1. Understand the specific quote requirements for crypto conversions.
  2. Generate a conversion quote.
  3. Execute the crypto-to-crypto conversion transfer.
  4. Confirm the status of the conversation.
</Tip>

## Prerequisites

Before you begin, ensure you have:

* A **Busha Business Account** and **Secret API Key** (from the [Quick Start Tutorial](/guides/getting-started/quick-start))
* An understanding of the **API Environments** (Sandbox vs. Production) and their respective base URLs (from the [Make Your First Request Guide](/guides/getting-started/make-first-request)).
* A conceptual understanding of **Quotes** (from the [Understanding Quotes](/overview/quotes) Overview).
* Familiarity with creating basic quotes (from the [How to Create Your First Quote](/guides/quotes/create-first-quote) Guide).

<Note>
  For conversion requests involving a customer, the `X-BU-PROFILE-ID` field
  should be included in the request header, and its value should be set to the
  customer ID for whom the request is performed on their behalf.
</Note>

## How to Convert Crypto to Crypto

<Steps>
  <Step title="Create a Conversion Quote">
    The first step in any conversion is to create a Quote. This specifies the `source_currency` (what you're converting from) and `target_currency` (what you're converting to). You must provide either the `source_amount` or the `target_amount`, but not both.

    To create a conversion quote:

    1. Open your terminal or command prompt.
    2. Construct a `POST` request to the `/v1/quotes` endpoint.
    3. Specify the `source_currency`, `target_currency`, and either `source_amount` (as shown below) or `target_amount`. Make sure to set the type to "convert".
    4. Replace `YOUR_BASE_URL` with your chosen environment's URL, `YOUR_SECRET_TOKEN` with your actual key, and `BUS_YOK8tp5Zga01qOKEsqp07` with your actual `X-BU-PROFILE-ID`.

    ```bash  theme={null}
    $ curl -i -X POST \
      https://YOUR_BASE_URL/v1/quotes \
      -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
      -H 'Content-Type: application/json' \
      -H 'X-BU-PROFILE-ID: BUS_YOK8tp5Zga01qOKEsqp07' \
      -d '{
        "source_currency": "USDT",
        "target_currency": "BTC",
        "source_amount": "20"
      }'
    ```

    **Expected Quote Response**

    A successful response will return a Quote object containing the `id`, calculated `target_amount` (if you provided `source_amount`), the rate for the conversion, and its `expires_at` timestamp. Note the `id` of this quote (e.g., `QUO_Nm2EBRxmuHGdTyGnVNDUt`), as you'll need it for the next step.
  </Step>

  <Step title="Create the Conversion Transfer">
    Once you have a valid Quote ID, you can proceed to execute the conversion by creating a transfer. This action consumes the quote and initiates the actual asset exchange.

    To create the conversion transfer:

    1. Use the `POST` request below to the `/v1/transfers` endpoint.
    2. Include the `quote_id` obtained from Step 1 in the request body.
    3. Replace `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN` with your actual details.

    ```bash  theme={null}
    $ curl -i -X POST \
      https://YOUR_BASE_URL/v1/transfers \
      -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
      -H 'Content-Type: application/json' \
      -d '{
        "quote_id": "QUO_Nm2EBRxmuHGdTyGnVNDUt"
      }'
    ```

    **Expected Transfer Response**

    A successful response indicates that the transfer has been initiated. For conversions, the transaction is typically processed very quickly, often immediately. The response will include a `id` (e.g., `TRF_oBoehqd2KVt1wcadB8wz5`) and the initial status of the transfer (likely `pending` or `completed` almost instantly).
  </Step>

  <Step title="Confirm Transfer">
    Although conversion transfers are usually processed immediately, it's good practice to confirm their final status. You can do this by retrieving the transfer details using its ID.

    To confirm the transfer status:

    1. Use the `GET` request below to the `/v1/transfers/{id}` endpoint.
    2. Replace `{id}` with the actual `transfer_id` you received in Step 2.
    3. Replace `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN` with your actual details.

    ```bash  theme={null}
    $ curl -i -X GET \
      https://YOUR_BASE_URL/v1/transfers/TRF_oBoehqd2KVt1wcadB8wz5 \
      -H 'Authorization: Bearer YOUR_SECRET_TOKEN'
    ```

    **Expected Statuses**

    * **completed:** The conversion was successful, and your `target_currency` balance has been updated.
    * **failed:** The conversion failed (e.g., insufficient balance, quote expired before execution).
    * **pending:** (Less common for conversions, but possible briefly) The transfer is still being processed.

    For real-time updates, setting up webhooks is recommended, as they will notify you automatically when the transfer status changes.
  </Step>
</Steps>

<Info>
  A trade is a type of conversion that represents a buy or sell action from or to Busha Fiat Wallet against a Busha crypto asset.

  A trade works just the same as a conversion, except that either of the `source_currency` and `target_currency` could be a fiat currency (e.g., NGN, KES).
</Info>

## Troubleshooting

**Common Conversion Issues:**

* **"Quote expired" during transfer creation:** Always generate a fresh quote immediately before attempting to create the transfer.
* **"Insufficient balance":** Ensure your Busha account has enough `source_currency` to cover the `source_amount` in the quote.
* **Invalid currency pair:** Double-check that Busha supports direct conversion between your chosen `source_currency` and `target_currency`.
* **401 Unauthorized:** Verify your Authorization header and API key.

## What's Next?

Now that you understand balance-to-balance conversions, you can explore other transaction types:

* [How to Create Your First Quote Guide](/guides/quotes/create-first-quote)
* [How to Process Fiat Deposits Guide](/guides/deposits/process-fiat-deposits)
* [How to Process Crypto Deposits Guide](/guides/deposits/process-crypto-deposits)
* [Quotes API Reference](/api-reference/quotes/list-quotes)
* [Transfers API Reference](/api-reference/transfers/list-transfers)



> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Create and Manage Recipients

> Set up payout recipients for bank accounts and mobile money. Manage recipient details and verification.

Recipients are pre-defined payment accounts (such as bank accounts, mobile money wallets, or crypto addresses) that act as destinations for your payouts via the Busha API. By creating and managing recipients, you streamline your off-ramp processes, ensuring funds are sent to verified and correct destinations quickly and efficiently.

<Tip>
  **What You'll Achieve:**

  1. Understand what recipients are and why they are necessary for payouts.
  2. Learn how to dynamically fetch the required fields for creating a recipient based on country and currency.
</Tip>

## Prerequisites

Before you begin, ensure you have:

* A **Busha Business Account** and **Secret API Key** (from the [Quick Start Tutorial](/guides/getting-started/quick-start))
* An understanding of the **API Environments** (Sandbox vs. Production) and their respective base URLs (from the [Make Your First Request Guide](/guides/getting-started/make-first-request)).

## Recipient Types

Busha operates a multi-currency account system and accomodate recipients for each currency type. Currently, Busha supports the creation of recipients for:

* Nigerian bank accounts
* US bank accounts
* UK bank accounts
* M-Pesa mobile money (Kenya)
* MTN Mobile Money
* Cryptocurrency addresses

The recipient type is an important field in the request body object. Each recipient type have their unique fields outlined in the table below.

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>Type</th>

      <th style={{ width: "20%", textAlign: "left", padding: "12px" }}>
        Category
      </th>

      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Description
      </th>

      <th style={{ width: "30%", textAlign: "left", padding: "12px" }}>
        Required Fields
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>
        <code>ngn\_bank</code>
      </td>

      <td style={{ padding: "12px" }}>bank</td>
      <td style={{ padding: "12px" }}>Nigerian bank account</td>

      <td style={{ padding: "12px" }}>
        <code>bank\_name</code>, <code>bank\_code</code>,{" "}
        <code>account\_number</code>, <code>account\_name</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>usd\_bank</code>
      </td>

      <td style={{ padding: "12px" }}>bank</td>
      <td style={{ padding: "12px" }}>US bank account</td>

      <td style={{ padding: "12px" }}>
        <code>entity\_type</code>, <code>transfer\_type</code>,{" "}
        <code>account\_name</code>, <code>bank\_name</code> + routing fields
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>gbp\_bank</code>
      </td>

      <td style={{ padding: "12px" }}>bank</td>
      <td style={{ padding: "12px" }}>UK bank account</td>

      <td style={{ padding: "12px" }}>
        <code>entity\_type</code>, <code>account\_name</code>,{" "}
        <code>bank\_name</code>, <code>sort\_code</code>,{" "}
        <code>account\_number</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>mpesa\_mobile\_money</code>
      </td>

      <td style={{ padding: "12px" }}>mobile\_money</td>
      <td style={{ padding: "12px" }}>M-Pesa (Kenya)</td>

      <td style={{ padding: "12px" }}>
        <code>phone\_number</code>, <code>account\_name</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>mtn\_mobile\_money</code>
      </td>

      <td style={{ padding: "12px" }}>mobile\_money</td>
      <td style={{ padding: "12px" }}>MTN Mobile Money</td>

      <td style={{ padding: "12px" }}>
        <code>phone\_number</code>, <code>account\_name</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>
        <code>crypto</code>
      </td>

      <td style={{ padding: "12px" }}>crypto</td>
      <td style={{ padding: "12px" }}>Cryptocurrency wallet</td>

      <td style={{ padding: "12px" }}>
        <code>network</code>, <code>crypto\_address</code>,{" "}
        <code>account\_name</code>, optional <code>memo</code>
      </td>
    </tr>
  </tbody>
</table>

The recipient's request body varies from type to type. That is, the request body for a Nigerian bank account `ngn_bank`, will be different from that of a US bank account `usd_bank`.

In the following sections, we will create a recipient for each type and outline the required fields for each.

## Nigerian Bank Account

<Note>
  Nigerian bank account recipients require a `bank_code` field. You can retrieve your bank's code by sending a GET request to the `/banks` endpoint":

  ```shell  theme={null}
  $ curl -i -X GET https://api.sandbox.busha.so/v1/banks
  ```
</Note>

The request body required to create a Nigerian bank account recipient is:

```json  theme={null}
{
  "currency": "NGN", // Recipient's bank currency
  "country_code": "NG", // Recipient's country code
  "type": "ngn_bank", // Recipient type
  "bank_name": "OPAY", // Recipient's bank name
  "bank_code": "100004", // Recipient's bank code
  "account_number": "9000000000", // Recipient's account number
  "account_name": "BUSHA DIGITAL TECHNOLOGY" // Recipient's account name
}
```

To create a recipient:

1. Open your terminal or command prompt.
2. Construct a `POST` request to the `/v1/recipients` endpoint.
3. In the request body, update the following fields with your recipient's details:
   * `bank_name`
   * `bank_code`
   * `account_number`
   * `account_name`
4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN` with your actual details.

```shell  theme={null}
$ curl -i -X POST \
  https://YOUR_BASE_URL/v1/recipients \
  -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
  "currency": "NGN",
  "country_code": "NG",
  "type": "ngn_bank",
  "bank_name": "OPAY",
  "bank_code": "100004",
  "account_number": "9000000000",
  "account_name": "BUSHA DIGITAL TECHNOLOGY"
}'
```

**Expected Response**

Upon successful creation, the API will return a response containing the details of the newly created recipient, including its unique `id`.

```json  theme={null}
{
  "status": "success",
  "message": "Recipient created successfully",
  "data": {
    "id": "6877ac708725d76db48eebff",
    "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
    "account_name": "BUSHA DIGITAL TECHNOLOGY",
    "account_number": "9169277397",
    "active": true,
    "bank_code": "100004",
    "bank_name": "OPAY",
    "category": "bank",
    "country_code": "NG",
    "currency": "NGN",
    "object": "recipients",
    "owned_by_customer": true,
    "type": "ngn_bank"
  }
}
```

<Note>
  This `id` is crucial and will be reused in several cases, especially when
  making a payout into that specific payment account, as demonstrated in the
  [How to Make Payouts Guide](/guides/payouts/process-payouts).
</Note>

## US Bank Account (ACH)

The request body required to create a US bank account (`usd_bank`) recipient with a transfer type `ach` is:

```json  theme={null}
{
  "type": "usd_bank", // Recipient type
  "entity_type": "personal", // Recipient entity type
  "transfer_type": "ach", // Recipient transfer type, ach
  "account_name": "Jane Smith", // Recipient account name
  "bank_name": "Chase Bank", // Recipient bank name
  "routing_number": "021000021", // Recipient routing number
  "account_number": "9876543210" // Recipient account number
}
```

To create a recipient:

1. Open your terminal or command prompt.
2. Construct a `POST` request to the `/v1/recipients` endpoint.
3. In the request body, update the following fields with your recipient's details:
   * `entity_type` : This can be `personal` or `business` depending on the recipient's bank entity type.
   * `account_number`
   * `bank_name`
   * `routing_number`
   * `account_name`
4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN` with your actual details.

```shell  theme={null}
$ curl -i -X POST \
  https://YOUR_BASE_URL/v1/recipients \
  -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
  "type": "usd_bank",
  "entity_type": "personal",
  "transfer_type": "ach",
  "account_name": "Jane Smith",
  "bank_name": "Chase Bank",
  "routing_number": "021000021",
  "account_number": "9876543210"
}'
```

**Expected Response**

Upon successful creation, the API will return a response containing the details of the newly created recipient, including its unique `id`.

```json  theme={null}
{
  "status": "success",
  "message": "Recipient created successfully",
  "data": {
    "id": "6877afc18725d76db48eec02",
    "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
    "account_name": "Jane Smith",
    "account_number": "9876543210",
    "active": true,
    "bank_name": "Chase Bank",
    "category": "bank",
    "country_code": "US",
    "currency": "USD",
    "entity_type": "personal",
    "object": "recipients",
    "owned_by_customer": true,
    "routing_number": "021000021",
    "transfer_type": "ach",
    "type": "usd_bank"
  }
}
```

<Note>
  This `id` is crucial and will be reused in several cases, especially when
  making a payout into that specific payment account, as demonstrated in the
  [How to Make Payouts Guide](/guides/payouts/process-payouts).
</Note>

## US Bank Account (Wire)

The request body required to create a US bank account (`usd_bank`) recipient with a transfer type `wire` is:

```json  theme={null}
{
  "type": "usd_bank", // Recipient type
  "entity_type": "business", // Recipient entity type
  "transfer_type": "wire", // Recipient transfer type, wire
  "account_name": "Tech Corp LLC", // Recipient's account name
  "bank_name": "Bank of America", // Recipient's bank name
  "routing_number": "026009593", // Recipient's routing number
  "account_number": "1122334455" // Recipient's account number
}
```

To create a recipient:

1. Open your terminal or command prompt.
2. Construct a `POST` request to the `/v1/recipients` endpoint.
3. In the request body, update the following fields with your recipient's details:
   * `entity_type` : This can be `personal` or `business` depending on the recipient's bank entity type.
   * `account_number`
   * `bank_name`
   * `routing_number`
   * `account_name`
   * `swift_code`
4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN` with your actual details.

```shell  theme={null}
$ curl -i -X POST \
  https://YOUR_BASE_URL/v1/recipients \
  -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
  "type": "usd_bank",
  "entity_type": "business",
  "transfer_type": "wire",
  "account_name": "Tech Corp LLC",
  "bank_name": "Bank of America",
  "routing_number": "026009593",
  "account_number": "1234567890",
  "swift_code": "BOFAUS3N"
}'
```

**Expected Response**

Upon successful creation, the API will return a response containing the details of the newly created recipient, including its unique `id`.

```json  theme={null}
{
  "status": "success",
  "message": "Recipient created successfully",
  "data": {
    "id": "6877b0a08725d76db48eec04",
    "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
    "account_name": "Tech Corp LLC",
    "account_number": "1234567890",
    "active": true,
    "bank_name": "Bank of America",
    "category": "bank",
    "country_code": "US",
    "currency": "USD",
    "entity_type": "business",
    "object": "recipients",
    "owned_by_customer": true,
    "routing_number": "026009593",
    "swift_code": "BOFAUS3N",
    "transfer_type": "wire",
    "type": "usd_bank"
  }
}
```

<Note>
  This `id` is crucial and will be reused in several cases, especially when
  making a payout into that specific payment account, as demonstrated in the
  [How to Make Payouts Guide](/guides/payouts/process-payouts).
</Note>

## US Bank Account (SWIFT)

The request body required to create a US bank account (`usd_bank`) recipient with a transfer type of `swift` is:

```json  theme={null}
{
  "type": "usd_bank", // Recipient type
  "entity_type": "business", // Recipient entity type
  "transfer_type": "swift", // Recipient transfer type, swift
  "account_name": "Global Corp Ltd", // Recipient's account name
  "iban": "GB29NWBK60161331926819", // Recipient's IBAN
  "swift_code": "CHASUS33XXX", // Recipient's SWIFT code
  "bank_name": "Bank of International Transfers", // Recipient's bank name
  "recipient_address": "123 Hauptstrasse, Berlin, 10115, Germany", // Recipient's address
  "intermediary_bank_name": "Intermediary Global Bank", // Recipient's intermediary bank name
  "intermediary_bank_address": "456 Avenue of Banks, Zurich", // Recipient's intermediary bank address
  "intermediary_swift_code": "CHASUS33XXX" // Recipient's intermediary swift code
}
```

To create a recipient:

1. Open your terminal or command prompt.
2. Construct a `POST` request to the `/v1/recipients` endpoint.
3. In the request body, update the following fields with your recipient's details:
   * `entity_type` : This can be `personal` or `business` depending on the recipient's bank entity type.
   * `account_name`
   * `iban`
   * `swift_code`
   * `bank_name`
   * `recipient_address`
   * `intermediary_bank_name`
   * `intermediary_bank_address`
   * `intermediary_swift_code`
4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN` with your actual details.

```shell  theme={null}
$ curl -i -X POST \
  https://YOUR_BASE_URL/v1/recipients \
  -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
  "type": "usd_bank",
  "entity_type": "business",
  "transfer_type": "swift",
  "account_name": "Global Corp Ltd",
  "iban": "GB29NWBK60161331926819",
  "swift_code": "CHASUS33XXX",
  "bank_name": "Bank of International Transfers",
  "recipient_address": "123 Hauptstrasse, Berlin, 10115, Germany",
  "intermediary_bank_name": "Intermediary Global Bank",
  "intermediary_bank_address": "456 Avenue of Banks, Zurich",
  "intermediary_swift_code": "CHASUS33XXX"
}'
```

**Expected Response**

Upon successful creation, the API will return a response containing the details of the newly created recipient, including its unique `id`.

```json  theme={null}
{
  "status": "success",
  "message": "Recipient created successfully",
  "data": {
    "id": "6877bd038725d76db48eec0f",
    "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
    "account_name": "Global Corp Ltd",
    "active": true,
    "bank_name": "Bank of International Transfers",
    "category": "bank",
    "country_code": "US",
    "currency": "USD",
    "entity_type": "business",
    "iban": "GB29NWBK60161331926818",
    "intermediary_bank_address": "456 Avenue of Banks, Zurich",
    "intermediary_bank_name": "Intermediary Global Bank",
    "intermediary_swift_code": "CHASUS33XXX",
    "object": "recipients",
    "owned_by_customer": true,
    "recipient_address": "123 Hauptstrasse, Berlin, 10115, Germany",
    "swift_code": "CHASUS33XXX",
    "transfer_type": "swift",
    "type": "usd_bank"
  }
}
```

<Note>
  This `id` is crucial and will be reused in several cases, especially when
  making a payout into that specific payment account, as demonstrated in the
  [How to Make Payouts Guide](/guides/payouts/process-payouts).
</Note>

## M-Pesa Mobile Money

The request body required to create an M-Pesa mobile money (`mpesa_mobile_money`) recipient is:

```json  theme={null}
{
  "currency_id": "KES", // Currency ID
  "country_id": "KE", // Country ID, Kenya
  "type": "mpesa_mobile_money", // Recipient type
  "phone_number": "+254712345678", // Recipient's phone number (Mobile money ID)
  "account_name": "Samuel Kiprotich" // Recipient's account name
}
```

To create a recipient:

1. Open your terminal or command prompt.
2. Construct a `POST` request to the `/v1/recipients` endpoint.
3. In the request body, update the following fields with your recipient's details:
   * `account_name`
   * `phone_number`
4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN` with your actual details.

```shell  theme={null}
$ curl -i -X POST \
  https://YOUR_BASE_URL/v1/recipients \
  -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
  "type": "mpesa_mobile_money",
  "account_name": "Samuel Kiprotich",
  "phone_number": "+254712345678"
}'
```

**Expected Response**

Upon successful creation, the API will return a response containing the details of the newly created recipient, including its unique `id`.

```json  theme={null}
{
  "status": "success",
  "message": "Recipient created successfully",
  "data": {
    "id": "6877b15d8725d76db48eec08",
    "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
    "account_name": "Samuel Kiprotich",
    "active": true,
    "category": "mobile_money",
    "country_code": "KE",
    "currency": "KES",
    "object": "recipients",
    "owned_by_customer": true,
    "phone_number": "+254712345678",
    "type": "mpesa_mobile_money"
  }
}
```

<Note>
  This `id` is crucial and will be reused in several cases, especially when
  making a payout into that specific payment account, as demonstrated in the
  [How to Make Payouts Guide](/guides/payouts/process-payouts).
</Note>

## Bitcoin

The request body required to create a cryptocurrency recipient on the Bitcoin network:

```json  theme={null}
{
  "type": "crypto", // Recipient type
  "account_name": "Coco", // Recipient account name
  "network": "BTC", // Recipient network
  "address": "bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh" // Recipient Bitcoin address
}
```

To create a recipient:

1. Open your terminal or command prompt.
2. Construct a `POST` request to the `/v1/recipients` endpoint.
3. In the request body, update the following fields with your recipient's details:
   * `account_name` : The name to identify the Bitcoin address stored.
   * `address`
4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN` with your actual details.

```shell  theme={null}
$ curl -i -X POST \
  https://YOUR_BASE_URL/v1/recipients \
  -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
  "type": "crypto",
  "account_name": "Coco",
  "network": "BTC",
  "address": "bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh"
}'
```

**Expected Response**

Upon successful creation, the API will return a response containing the details of the newly created recipient, including its unique `id`.

```json  theme={null}
{
  "status": "success",
  "message": "Recipient created successfully",
  "data": {
    "id": "6877bbf68725d76db48eec0d",
    "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
    "active": true,
    "address": "bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlw",
    "category": "crypto",
    "network": "BTC",
    "object": "recipients",
    "owned_by_customer": false,
    "type": "crypto"
  }
}
```

<Note>
  This `id` is crucial and will be reused in several cases, especially when
  making a payout into that specific payment account, as demonstrated in the
  [How to Make Payouts Guide](/guides/payouts/process-payouts).
</Note>

## USDT (ETH)

The request body required to create a cryptocurrency recipient (USDT) on the Ethereum network:

```json  theme={null}
{
  "type": "crypto", // Recipient type
  "account_name": "Coco", // Recipient account name
  "network": "ETH", // Recipient network
  "address": "0x742d35Cc6543C4532f5D2b8d9a2b2A1234567881" // Recipient address
}
```

To create a recipient:

1. Open your terminal or command prompt.
2. Construct a `POST` request to the `/v1/recipients` endpoint.
3. In the request body, update the following fields with your recipient's details:
   * `account_name` : The name to identify the USDT address stored.
   * `address`
4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN` with your actual details.

```shell  theme={null}
$ curl -i -X POST \
  https://YOUR_BASE_URL/v1/recipients \
  -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
  "type": "crypto",
  "account_name": "Coco",
  "network": "ETH",
  "address": "0x742d35Cc6543C4532f5D2b8d9a2b2A1234567881"
}'
```

**Expected Response**

Upon successful creation, the API will return a response containing the details of the newly created recipient, including its unique `id`.

```json  theme={null}
{
  "status": "success",
  "message": "Recipient created successfully",
  "data": {
    "id": "6877ec5c985bb54f0f2f9543",
    "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
    "active": true,
    "address": "0x742d35Cc6543C4532f5D2b8d9a2b2A1234567881",
    "category": "crypto",
    "network": "ETH",
    "object": "recipients",
    "owned_by_customer": true,
    "type": "crypto"
  }
}
```

<Note>
  This `id` is crucial and will be reused in several cases, especially when
  making a payout into that specific payment account, as demonstrated in the
  [How to Make Payouts Guide](/guides/payouts/process-payouts).
</Note>

## STELLAR LUMENS

The request body required to create a cryptocurrency recipient on the XLM network:

```json  theme={null}
{
  "type": "crypto", // Recipient type
  "account_name": "My Stellar Wallet", // Recipient's account name
  "network": "XLM", // Recipient's network, XLM
  "address": "GAHK7EEG2WWHVKDNT4CEQFZGKF2LGDSW2IVM4S5DP42RBW3K6BTODB4A", // Recipient's address
  "memo": "123456789" // Recipient's memo
}
```

To create a recipient:

1. Open your terminal or command prompt.
2. Construct a `POST` request to the `/v1/recipients` endpoint.
3. In the request body, update the following fields with your recipient's details:
   * `account_name` : The identifier for the recipient details.
   * `address`
   * `memo`
4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN` with your actual details.

```shell  theme={null}
$ curl -i -X POST \
  https://YOUR_BASE_URL/v1/recipients \
  -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
  "type": "crypto",
  "account_name": "My Stellar Wallet",
  "network": "XLM",
  "address": "GAHK7EEG2WWHVKDNT4CEQFZGKF2LGDSW2IVM4S5DP42RBW3K6BTODB4A",
  "memo": "123456789"
}'
```

**Expected Response**

Upon successful creation, the API will return a response containing the details of the newly created recipient, including its unique `id`.

```json  theme={null}
{
  "status": "success",
  "message": "Recipient created successfully",
  "data": {
    "id": "6877b18e8725d76db48eec0a",
    "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
    "active": true,
    "address": "GAHK7EEG2WWHVKDNT4CEQFZGKF2LGDSW2IVM4S5DP42RBW3K6BTODB4A",
    "category": "crypto",
    "memo": "123456789",
    "network": "XLM",
    "object": "recipients",
    "owned_by_customer": true,
    "type": "crypto"
  }
}
```

<Note>
  This `id` is crucial and will be reused in several cases, especially when
  making a payout into that specific payment account, as demonstrated in the
  [How to Make Payouts Guide](/guides/payouts/process-payouts).
</Note>

## Retrieve and List Recipients

After creating recipients, you'll often need to retrieve their details or get a list of all recipients associated with your profile. This allows you to manage your payout destinations and ensure you're using the correct `recipient_id` for transactions.

**Retrieve a Specific Recipient**

You can fetch the details of a single recipient if you know its unique `id`.

To retrieve a specific recipient:

1. Open your terminal or command prompt.
2. Construct a `GET` request to the `/v1/recipients/{id}` endpoint, replacing `{id}` with the actual recipient ID.
3. Ensure your `Authorization` and `YOUR_SECRET_TOKEN` headers are included.
4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN`.

```bash  theme={null}
$ curl -i -X GET \
  'https://api.busha.co/v1/recipients/{id}' \
  -H 'Authorization: Bearer YOUR_SECRET_TOKEN'
```

**Expected Response:**

A successful response will return a single recipient object, identical in structure to the one received during creation, containing all its details.

```json  theme={null}
{
  "status": "success",
  "message": "Recipient retrieved successfully",
  "data": {
    "id": "6877afc18725d76db48eec02",
    "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
    "user_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
    "active": true,
    "country_id": "US",
    "created_at": "2025-07-16T13:57:21.150561Z",
    "fields": [
      {
        "display_name": "currency",
        "is_copyable": true,
        "is_visible": false,
        "name": "currency",
        "required": false,
        "value": "USD"
      },
      {
        "display_name": "bank_name",
        "is_copyable": true,
        "is_visible": true,
        "name": "bank_name",
        "required": true,
        "value": "Chase Bank"
      },
      {
        "display_name": "entity_type",
        "is_copyable": true,
        "is_visible": true,
        "name": "entity_type",
        "required": true,
        "value": "personal"
      },
      {
        "display_name": "account_name",
        "is_copyable": true,
        "is_visible": true,
        "name": "account_name",
        "required": true,
        "value": "Jane Smith"
      },
      {
        "display_name": "country_code",
        "is_copyable": true,
        "is_visible": false,
        "name": "country_code",
        "required": false,
        "value": "US"
      },
      {
        "display_name": "transfer_type",
        "is_copyable": true,
        "is_visible": true,
        "name": "transfer_type",
        "required": true,
        "value": "ach"
      },
      {
        "display_name": "account_number",
        "is_copyable": true,
        "is_visible": true,
        "name": "account_number",
        "required": false,
        "value": "9876543210"
      },
      {
        "display_name": "routing_number",
        "is_copyable": true,
        "is_visible": true,
        "name": "routing_number",
        "required": false,
        "value": "021000021"
      }
    ],
    "legal_entity_type": "personal",
    "object": "recipients",
    "owned_by_customer": true,
    "type": "usd_bank",
    "updated_at": "2025-07-16T13:57:21.150561Z",
    "currency_id": "USD"
  }
}
```

**List All Recipients**

To get an overview of all the recipients you have created, you can make a `GET` request to the `/v1/recipients` endpoint without specifying an ID.

To list all recipients:

1. Open your terminal or command prompt.
2. Construct a `GET` request to the `/v1/recipients` endpoint.
3. Ensure your `Authorization` header is included.
4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN`.

```bash  theme={null}
$ curl -i -X GET 'https://YOUR_BASE_URL/v1/recipients' \
  -H 'Authorization: Bearer YOUR_SECRET_TOKEN'
```

**Expected Response:**

A successful response will return a list (an array) of all recipient objects associated with your profile. This allows you to programmatically manage your available payout destinations.

```json  theme={null}
{
  "status": "success",
  "message": "Recipients retrieved successfully",
  "pagination": {
    "current_entries_size": 10,
    "next_cursor": "eyJhbmNob3IiOiIyMDI1LTA3LTE2VDEzOjU3OjIxLjE1MDU2MVoiLCJhbmNob3JfaWQiOiJjcmVhdGVkX2F0Iiwic2Vjb25kYXJ5X2FuY2hvcl9pZCI6ImlkIiwiZGlyZWN0aW9uIjoibmV4dCIsInBvc2l0aW9uIjowLCJzb3J0X29yZGVyIjoiZGVzYyIsImxpbWl0IjoxMH0="
  },
  "data": [
    {
      "id": "6877ec5c985bb54f0f2f9543",
      "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
      "user_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
      "active": true,
      "created_at": "2025-07-16T18:15:56.073128Z",
      "fields": [
        {
          "display_name": "address",
          "is_copyable": true,
          "is_visible": true,
          "name": "address",
          "required": true,
          "value": "0x742d35Cc6543C4532f5D2b8d9a2b2A1234567881"
        },
        {
          "display_name": "network",
          "is_copyable": true,
          "is_visible": true,
          "name": "network",
          "required": true,
          "value": "ETH"
        }
      ],
      "legal_entity_type": "personal",
      "object": "recipients",
      "owned_by_customer": true,
      "type": "crypto",
      "updated_at": "2025-07-16T18:15:56.073128Z"
    },
    {
      "id": "6877e78e985bb54f0f2f9541",
      "profile_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
      "user_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
      "active": true,
      "country_id": "KE",
      "created_at": "2025-07-16T17:55:26.935875Z",
      "fields": [
        {
          "display_name": "currency",
          "is_copyable": true,
          "is_visible": false,
          "name": "currency",
          "required": false,
          "value": "KES"
        },
        {
          "display_name": "account_name",
          "is_copyable": true,
          "is_visible": true,
          "name": "account_name",
          "required": true,
          "value": "Samuel Kiprotich"
        },
        {
          "display_name": "country_code",
          "is_copyable": true,
          "is_visible": false,
          "name": "country_code",
          "required": false,
          "value": "KE"
        },
        {
          "display_name": "phone_number",
          "is_copyable": true,
          "is_visible": true,
          "name": "phone_number",
          "required": true,
          "value": "+254712345678"
        }
      ],
      "legal_entity_type": "personal",
      "object": "recipients",
      "owned_by_customer": true,
      "type": "mpesa_mobile_money",
      "updated_at": "2025-07-16T17:55:26.935875Z",
      "currency_id": "KES"
    }
  ]
}
```

## What's Next?

Now that you know how to create, retrieve, and list recipients, you can use them in your transaction flows:

* [How to Process Payouts to Recipients](/guides/payouts/process-payouts)
* [The Recipient Object](/guides/reference/recipients)
* [Recipients API Reference](/api-reference/recipients/list-recipients): For full details on all available fields and endpoints
> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Create an Individual Customer

> Create customer profiles for individual users. Handle KYC and identity verification.

This guide provides a technical walkthrough on programmatically creating an individual customer account within your Busha Business account using the Busha API. By automating customer creation, you can streamline your onboarding processes and prepare to perform transactions on behalf of your customers.

<Tip>
  **What You'll Achieve:**

  1. Understand the basic structure for creating a customer.
  2. Learn how to structure API requests for individual customer types.
  3. Successfully create and verify customer accounts programmatically.
</Tip>

## Prerequisites

Before you begin, ensure you have:

* A **Busha Business Account** and **Secret API Key** (from the [Quick Start Tutorial](/guides/getting-started/quick-start)).
* An understanding of **API Environments** (Sandbox vs. Production) and their base URLs (from the [Make Your First Request Guide](/guides/getting-started/make-first-request)).

## Creating an Individual Customer Account

<Steps>
  <Step title="Understand Customer Creation Parameters">
    The essential parameters for creating an individual customer type are:

    <table style={{ width: "100%", borderCollapse: "collapse" }}>
      <thead>
        <tr>
          <th style={{ width: "30%", textAlign: "left", padding: "12px" }}>
            Parameter
          </th>

          <th style={{ width: "15%", textAlign: "left", padding: "12px" }}>Type</th>

          <th style={{ width: "55%", textAlign: "left", padding: "12px" }}>
            Description
          </th>
        </tr>
      </thead>

      <tbody>
        <tr>
          <td style={{ padding: "12px" }}>
            <code>email</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The individual customer's email address.
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>has\_accepted\_terms</code>
          </td>

          <td style={{ padding: "12px" }}>boolean</td>

          <td style={{ padding: "12px" }}>
            A flag indicating whether the individual has accepted your platform's
            (or Busha's) terms and conditions.
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>type</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The type of customer. Must be <code>individual</code> for this customer
            type.
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>country\_id</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The ISO 3166-1 alpha-2 country code representing the individual's
            primary country of residence.
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>phone</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The individual's phone number, including the international country code
            (e.g., +234 8012345678).
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>birth\_date</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The individual's date of birth, typically required for identity
            verification. Format: DD-MM-YYYY.
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>first\_name</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>
          <td style={{ padding: "12px" }}>The individual customer's first name.</td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>middle\_name</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The individual customer's middle name (optional).
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>last\_name</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>
          <td style={{ padding: "12px" }}>The individual customer's last name.</td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>address</code>
          </td>

          <td style={{ padding: "12px" }}>object</td>

          <td style={{ padding: "12px" }}>
            The individual's physical address details. See the Address Object
            Parameters table below for nested fields.
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>identifying\_information</code>
          </td>

          <td style={{ padding: "12px" }}>array</td>

          <td style={{ padding: "12px" }}>
            An array of objects describing the individual's identification documents
            (e.g., passport, national ID).
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>documents</code>
          </td>

          <td style={{ padding: "12px" }}>array</td>

          <td style={{ padding: "12px" }}>
            An array of objects referencing uploaded documents for various purposes
            (e.g., selfie video for verification).
          </td>
        </tr>
      </tbody>
    </table>

    For the `address` field in the customer creation object, the fields required are:

    <table style={{ width: "100%", borderCollapse: "collapse" }}>
      <thead>
        <tr>
          <th style={{ width: "30%", textAlign: "left", padding: "12px" }}>
            Parameter
          </th>

          <th style={{ width: "15%", textAlign: "left", padding: "12px" }}>Type</th>

          <th style={{ width: "55%", textAlign: "left", padding: "12px" }}>
            Description
          </th>
        </tr>
      </thead>

      <tbody>
        <tr>
          <td style={{ padding: "12px" }}>
            <code>city</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>
          <td style={{ padding: "12px" }}>The city component of the address.</td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>state</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The state or region component of the address.
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>county</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The county or district component of the address (optional).
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>country\_id</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The ISO 3166-1 alpha-2 country code of the address.
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>address\_line\_1</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>
          <td style={{ padding: "12px" }}>The first line of the street address.</td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>address\_line\_2</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The second line of the street address (optional).
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>province</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The province component of the address (optional).
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>postal\_code</code>
          </td>

          <td style={{ padding: "12px" }}>string</td>

          <td style={{ padding: "12px" }}>
            The postal or ZIP code of the address.
          </td>
        </tr>
      </tbody>
    </table>

    **Know Your Customer (KYC)**

    Optionally, you can choose to upload your customer's KYC documents at the point of creation in the `identifying_information` and `documents` array fields.

    <Note>
      Files uploaded for KYC must be in Base64 format and have a file size less than
      4MB.
    </Note>

    The `identifying_information` array expects the object(s) in this format:

    ```json  theme={null}
    "identifying_information": [
        {
          "type": "national-id",
          "number": "12345678901",
          "country": "NG",
          "image_front": "{{base64 string}}",
          "image_back": "{{base64 string}}"
        }
    ]
    ```

    For individual customers, the KYC documents required, depending on your country, are:

    <table style={{ width: "100%", borderCollapse: "collapse" }}>
      <thead>
        <tr>
          <th style={{ width: "30%", textAlign: "left", padding: "12px" }}>
            Country
          </th>

          <th style={{ width: "70%", textAlign: "left", padding: "12px" }}>
            Documents Required
          </th>
        </tr>
      </thead>

      <tbody>
        <tr>
          <td style={{ padding: "12px" }}>Nigeria</td>

          <td style={{ padding: "12px" }}>
            <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
              <li>NIN slip and selfie video</li>
              <li>National passport and selfie video</li>
              <li>Driver's license and selfie video</li>
            </ul>
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>Kenya</td>

          <td style={{ padding: "12px" }}>
            <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
              <li>National ID and selfie video</li>
            </ul>
          </td>
        </tr>
      </tbody>
    </table>

    The document array expects the object(s) in this format:

    ```json  theme={null}
    "documents": [
        {
          "purposes": ["selfie_video"],
          "file": "{{base64 video data}}"
        }
    ]
    ```

    For a full list of acceptable documents, please refer to the [Compliance Guide](/overview/compliance).
  </Step>

  <Step title="Create a Customer (Without KYC Documents)">
    Individual customers refer to persons who engage with your services. Start with basic information and add KYC documents later.

    To create an individual customer without KYC documents:

    1. Open your terminal or command prompt.
    2. Use the `POST` request below to the `/v1/customers` endpoint.
    3. Replace `YOUR_BASE_URL` with your chosen environment's URL and `{YOUR_SECRET_KEY}` with your actual key.

    ```shell  theme={null}
        $ curl -i -X POST https://YOUR_BASE_URL/v1/customers \
          -H 'Authorization: Bearer {YOUR_SECRET_KEY}' \
          -H 'Content-Type: application/json' \
          -d '{
            "email": "customer.basic@gmail.com",
            "has_accepted_terms": true,
            "type": "individual",
            "country_id": "NG",
            "phone": "+234 8012345678",
            "birth_date": "24-12-2000",
            "address": {
              "city": "Lagos",
              "state": "Lagos",
              "country_id": "NG",
              "address_line_1": "10 Allen Avenue",
              "postal_code": "100001"
            },
            "first_name": "John",
            "last_name": "Doe"
          }'
    ```

    **Expected Response:**

    A successful response will return a Customer object with `status: "inactive"` until KYC is completed.

    <Note>
      The default status of a customer is `inactive` until they complete their Know-Your-Customer (KYC) process.
    </Note>

    ```json  theme={null}
        {
          "status": "success",
          "message": "Created customer successfully",
          "data": {
            "address": {
              "address_line_1": "10 Allen Avenue",
              "city": "Lagos",
              "country_id": "NG",
              "postal_code": "100001",
              "state": "Lagos"
            },
            "business_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
            "country_id": "NG",
            "created_at": "2026-01-27T09:23:09.317273Z",
            "deposit": true,
            "display_currency": "NGN",
            "email": "customer.basic@gmail.com",
            "first_name": "John",
            "has_accepted_terms_of_service": true,
            "id": "CUS_IL2Qf2pEoNADZ",
            "last_name": "Doe",
            "level": "0",
            "payout": true,
            "phone": "+234 8012345678",
            "status": "inactive",
            "type": "individual",
            "updated_at": "2026-01-27T09:23:09.317273Z"
          }
        }
    ```
  </Step>

  <Step title="Create a Customer (With KYC Documents)">
    Create a verified customer by including KYC documents at creation.

    To create a customer with supporting KYC documents:

    1. Open your terminal or command prompt.
    2. Use the `POST` request below to the `/v1/customers` endpoint.
    3. Replace `YOUR_BASE_URL` with your chosen environment's URL and `{YOUR_SECRET_KEY}` with your actual key.

    ```shell  theme={null}
        $ curl -i -X POST https://YOUR_BASE_URL/v1/customers \
          -H 'Authorization: Bearer {YOUR_SECRET_KEY}' \
          -H 'Content-Type: application/json' \
          -d '{
            "email": "jacob.verified@gmail.com",
            "first_name": "Jacob",
            "last_name": "Zuma",
            "has_accepted_terms": true,
            "type": "individual",
            "country_id": "NG",
            "phone": "+234 8087654321",
            "birth_date": "24-12-1990",
            "address": {
              "city": "Lagos",
              "state": "Lagos",
              "country_id": "NG",
              "address_line_1": "15 Victoria Island",
              "postal_code": "100001"
            },
            "identifying_information": [
              {
                "type": "national-id",
                "number": "12345678901",
                "country": "NG",
                "image_front": "{{base64 encoded image}}",
                "image_back": "{{base64 encoded image}}"
              }
            ],
            "documents": [
              {
                "purposes": ["selfie_video"],
                "file": "{{base64 encoded video}}"
              }
            ]
          }'
    ```

    **Expected Response:**

    ```json  theme={null}
        {
          "status": "success",
          "message": "Created customer successfully",
          "data": {
            "address": {
              "address_line_1": "15 Victoria Island",
              "city": "Lagos",
              "country_id": "NG",
              "postal_code": "100001",
              "state": "Lagos"
            },
            "business_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
            "country_id": "NG",
            "created_at": "2026-01-27T09:23:09.317273Z",
            "deposit": true,
            "display_currency": "NGN",
            "email": "jacob.verified@gmail.com",
            "first_name": "Jacob",
            "has_accepted_terms_of_service": true,
            "id": "CUS_Ikdb49NLsnlYU",
            "last_name": "Zuma",
            "level": "0",
            "payout": true,
            "phone": "+234 8087654321",
            "status": "inactive",
            "type": "individual",
            "updated_at": "2026-01-27T09:23:09.317273Z"
          }
        }
    ```

    <Info>
      The status remains `inactive` until the documents are verified. Call the verify endpoint to submit for verification.
    </Info>
  </Step>

  <Step title="Verify the Customer">
    After creating a customer with KYC documents, verify them to activate their account.

    To verify a customer:

    1. Open your terminal or command prompt.
    2. Use the `POST` request below to the `/v1/customers/{customer_id}/verify` endpoint.
    3. Replace `{customer_id}` with the customer ID from the previous response.
    4. Replace `YOUR_BASE_URL` with your chosen environment's URL and `{YOUR_SECRET_KEY}` with your actual key.

    ```shell  theme={null}
        $ curl -i -X POST https://YOUR_BASE_URL/v1/customers/CUS_Ikdb49NLsnlYU/verify \
          -H 'Authorization: Bearer {YOUR_SECRET_KEY}'
    ```

    **Expected Response:**

    A successful verification will return:

    ```json  theme={null}
        {
          "status": "success",
          "message": "Customer verified successfully"
        }
    ```

    <Note>
      After verification, check the customer status. It will change from `inactive` to `in_review` or `active`.
    </Note>

    **Common Errors:**

    * **`profile_kyc_verification`:** KYC documents are missing or incomplete (e.g., no selfie video)

    If you receive this error, update the customer with the missing documents using `PUT /v1/customers/{customer_id}`, then retry verification.
  </Step>
</Steps>

## Complete Example: Create and Verify Customer

Here's a complete workflow to create and verify an individual customer:

**Step 1: Create customer with KYC documents**

```shell  theme={null}
curl -X POST https://api.sandbox.busha.so/v1/customers \
  -H 'Authorization: Bearer YOUR_SECRET_KEY' \
  -H 'Content-Type: application/json' \
  -d '{
    "email": "verified.customer@gmail.com",
    "first_name": "Test",
    "last_name": "Customer",
    "has_accepted_terms": true,
    "type": "individual",
    "country_id": "NG",
    "phone": "+234 8012345678",
    "birth_date": "15-06-1990",
    "address": {
      "city": "Lagos",
      "state": "Lagos",
      "country_id": "NG",
      "address_line_1": "10 Allen Avenue",
      "postal_code": "100001"
    },
    "identifying_information": [
      {
        "type": "national-id",
        "number": "12345678901",
        "country": "NG",
        "image_front": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==",
        "image_back": "iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=="
      }
    ],
    "documents": [
      {
        "purposes": ["selfie_video"],
        "file": "data:video/mp4;base64,AAAAIGZ0eXBpc29tAAACAGlzb21pc28yYXZjMW1wNDEAAAAIZnJlZQAAAu1tZGF0AAACrQYF"
      }
    ]
  }'
```

**Step 2: Verify the customer**

```shell  theme={null}
curl -X POST https://api.sandbox.busha.so/v1/customers/CUS_xxx/verify \
  -H 'Authorization: Bearer YOUR_SECRET_KEY'
```

**Step 3: Check customer status**

```shell  theme={null}
curl -X GET https://api.sandbox.busha.so/v1/customers/CUS_xxx \
  -H 'Authorization: Bearer YOUR_SECRET_KEY'
```

Expected status: `"status": "in_review"` or `"status": "active"`

<Info>
  **Customer Status Flow:**

  1. `inactive` - Default status after creation
  2. `in_review` - After calling verify endpoint with valid documents
  3. `active` - After verification approval (automatic in sandbox, manual review in production)
</Info>

***

## Troubleshooting

* **400 Bad Request / 422 Unprocessable Entity:** Review your request body to ensure all required fields are present and correctly formatted.
* **401 Unauthorized:** Verify that your Secret API Key is correct and included in the header.
* **Email validation error:** Use real email domains like `@gmail.com` instead of generic domains like `@example.com`.
* **`profile_kyc_verification` error during verification:** Ensure all required documents (ID images and selfie video) are uploaded before calling verify.

## What's Next?

Now that you can programmatically create and verify individual customers, you can proceed to manage them and perform transactions on their behalf:

* [How to Create a Business Customer](/guides/customers/create-business): Learn how to create a business customer in your Busha business account.
* [How to Verify Customer's Identity (KYC/KYB)](/guides/customers/verify-identity): Learn more about the verification process and required documents.
* [How to Initiate Transactions on Behalf of a Customer](/guides/customers/transactions-on-behalf): Understand how to use the `customer_id` to perform operations for your customers.
* [Webhook Events](/guides/webhooks/webhook-events): Learn about customer verification webhook events.



> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Retrieve Customers

> Fetch customer profiles and details. Access KYC status, balances, and transaction history.

After creating customers, you'll often need to retrieve their details or get a list of all customers associated with your business account. This allows you to manage operations and ensure you're using the correct `customer_id` for transactions.

This guide provides a technical walkthrough on retrieving the customers associated with your Busha business account via the Busha API

## Prerequisites

Before you begin, ensure you have:

* A **Busha Business Account** and **Secret API Key** ([from the Quick Start Tutorial](/guides/getting-started/quick-start)).
* An understanding of **API Environments** (Sandbox vs. Production) and their base URLs (from the [Make Your First Request Guide](/guides/getting-started/make-first-request)).

## How to Retrieve Customer Information

<Steps>
  <Step title="Retrieve a Specific Customer">
    You can fetch the details of a single customer from their ID.

    To retrieve a specific customer:

    1. Open your terminal or command prompt.
    2. Construct a `GET` request to the `/v1/customers/{customer_id}` endpoint, replacing `{customer_id}` with the actual customer ID.
    3. Ensure your `Authorization` and `YOUR_SECRET_TOKEN` headers are included.
    4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN`.

    ```shell  theme={null}
    $ curl -i -X GET "https://YOUR_BASE_URL/v1/customers/{id}" \
      -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
      -H 'Content-Type: application/json'
    ```

    **Expected Response:**

    A successful response will return a single customer object, identical in structure to the one received during creation, containing all its details:

    ```json  theme={null}
    {
      "status": "success",
      "message": "Fetched customer successfully",
      "data": {
        "address": {
          "address_line_1": "RT Lawal",
          "address_line_2": "",
          "city": "Lekki",
          "country_id": "NG",
          "county": "Mombasa",
          "postal_code": "12345",
          "province": "province",
          "state": "Lagos"
        },
        "business_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
        "country_id": "NG",
        "created_at": "2025-06-25T22:08:08.978994Z",
        "deposit": true,
        "display_currency": "NGN",
        "email": "trybusha@busha.so",
        "first_name": "Busha",
        "has_accepted_terms_of_service": true,
        "id": "CUS_IL2Qf2pEoNADZ",
        "last_name": "Mascot",
        "middle_name": "Crypto",
        "payout": true,
        "phone": "+234 8012345678",
        "status": "inactive",
        "type": "individual",
        "updated_at": "2025-06-25T22:08:08.978994Z"
      }
    }
    ```
  </Step>

  <Step title="Retrieve All Customers">
    To get an overview of all the customers you have created, you can make a `GET` request to the `/v1/customers` endpoint without specifying an ID.

    To list all customers:

    1. Open your terminal or command prompt.
    2. Construct a `GET` request to the `/v1/customers` endpoint.
    3. Ensure your `Authorization` header is included.
    4. Replace placeholders like `YOUR_BASE_URL` and `YOUR_SECRET_TOKEN`.

    ```shell  theme={null}
    $ curl -i -X GET 'https://YOUR_BASE_URL/v1/customers' \
      -H 'Authorization: Bearer YOUR_SECRET_TOKEN'
    ```

    **Expected Response**

    A successful response will return a list (an array) of all recipient objects associated with your account.

    ```json  theme={null}
    {
      "status": "success",
      "message": "Fetched customers successfully",
      "data": [
        {
          "address": {
            "address_line_1": "RT Lawal",
            "address_line_2": "",
            "city": "Lekki",
            "country_id": "NG",
            "county": "Mombasa",
            "postal_code": "12345",
            "province": "province",
            "state": "Lagos"
          },
          "business_id": "BUS_jlKUYwF9z1ynQZ98bWbaP",
          "country_id": "NG",
          "created_at": "2025-06-25T22:08:08.978994Z",
          "deposit": true,
          "display_currency": "NGN",
          "email": "trybusha@busha.co",
          "first_name": "Busha",
          "has_accepted_terms_of_service": true,
          "id": "CUS_IL2Qf2pEoNADZ",
          "last_name": "Mascot",
          "middle_name": "Crypto",
          "payout": true,
          "phone": "+234 8012345678",
          "status": "inactive",
          "type": "individual",
          "updated_at": "2025-06-25T22:08:08.978994Z"
        }
      ],
      "pagination": {
        "current_entries_size": 1,
        "previous_cursor": "MDAwMS0wMS0wMVQwMDowMDowMFo="
      }
    }
    ```
  </Step>
</Steps>

## Troubleshooting

* **404 Not Found:** Verify that the customer ID is correct.
* **401 Unauthorized:** Verify that your Secret API Key is correct and included in the header.

## What's Next?

Now that you can retrieve customers, you can proceed to manage them and perform transactions on their behalf:

* [How to Verify Customer's Identity (KYC/KYB)](/guides/customers/verify-identity): Learn how to conduct and verify the identity of your customer to allow them full access to Busha's operations.
* [How to Initiate Transactions on Behalf of a Customer](/guides/customers/transactions-on-behalf): Understand how to use the `customer_id` to perform operations for your customers.


> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Verify Customer Identity

> Implement KYC verification for customers. Collect documents and verify identity information.

This guide provides a technical walkthrough on programmatically verifying a customer account using the Busha API.

<Tip>
  **What You'll Achieve:**

  1. Update customer profile with KYC/KYB documents.
  2. Successfully verify your customer identity.
  3. Monitor verification status changes.
</Tip>

## Prerequisites

Before you begin, ensure you have:

* A **Busha Business Account** and **Secret API Key** (from the [Quick Start Tutorial](/guides/getting-started/quick-start)).
* An understanding of **API Environments** (Sandbox vs. Production) and their base URLs (from the [Make Your First Request Guide](/guides/getting-started/make-first-request)).
* A customer account created (see [Create Individual Customer](/guides/customers/create-individual) or [Create Business Customer](/guides/customers/create-business)).

## Verifying Customer Identity (KYC/KYB)

<Steps>
  <Step title="Submit Customer KYC/KYB Documents">
    <Warning>
      Skip this step if verification documents were attached when creating the
      customer.
    </Warning>

    The core requirement for verifying a customer is the completed upload of identification documents.

    <Note>
      Files uploaded for KYC/KYB must be in Base64 format and have a file size less than 4MB.
    </Note>

    **Individual customer**

    For individual customers, the KYC documents required, depending on your country, are:

    <table style={{ width: "100%", borderCollapse: "collapse" }}>
      <thead>
        <tr>
          <th style={{ width: "30%", textAlign: "left", padding: "12px" }}>
            Country
          </th>

          <th style={{ width: "70%", textAlign: "left", padding: "12px" }}>
            Documents Required
          </th>
        </tr>
      </thead>

      <tbody>
        <tr>
          <td style={{ padding: "12px" }}>Nigeria</td>

          <td style={{ padding: "12px" }}>
            <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
              <li>NIN slip and selfie video</li>
              <li>National passport and selfie video</li>
              <li>Driver's license and selfie video</li>
            </ul>
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>Kenya</td>

          <td style={{ padding: "12px" }}>
            <ul style={{ margin: "8px 0", paddingLeft: "20px" }}>
              <li>National ID and selfie video</li>
            </ul>
          </td>
        </tr>
      </tbody>
    </table>

    To update an individual customer with their verification documents:

    1. Open your terminal or command prompt.
    2. Use the `PUT` request below to the `/v1/customers/{customer_id}` endpoint.
    3. Replace `{customer_id}` with the customer ID.
    4. Replace `YOUR_BASE_URL` with your chosen environment's URL and `{YOUR_SECRET_KEY}` with your actual key.
    5. Replace the values in the `identifying_information` and `documents` array with the customer documents.

    ```shell  theme={null}
    $ curl -i -X PUT https://YOUR_BASE_URL/v1/customers/{customer_id} \
      -H 'Authorization: Bearer {YOUR_SECRET_KEY}' \
      -H 'Content-Type: application/json' \
      -d '{
        "email": "customer@gmail.com",
        "first_name": "John",
        "last_name": "Doe",
        "has_accepted_terms": true,
        "type": "individual",
        "country_id": "NG",
        "phone": "+234 8012345678",
        "birth_date": "15-06-1990",
        "address": {
          "city": "Lagos",
          "state": "Lagos",
          "country_id": "NG",
          "address_line_1": "10 Allen Avenue",
          "postal_code": "100001"
        },
        "identifying_information": [
          {
            "type": "national-id",
            "number": "12345678901",
            "country": "NG",
            "image_front": "{{base64 string}}",
            "image_back": "{{base64 string}}"
          }
        ],
        "documents": [
          {
            "purposes": ["selfie_video"],
            "file": "{{base64 video data}}"
          }
        ]
      }'
    ```

    **Business customer**

    For business customers, the KYB documents and sections required are:

    **Documents:**

    * Certificate of Incorporation
    * Corporate registry extract
    * Memorandum of Association articles (memart)
    * Corporate structure chart
    * Board resolution
    * Anti-money laundering policy
    * Regulatory licenses
    * Proof of wealth
    * Proof of address

    **Required Sections:**

    * **Business Owners** - Directors and beneficial owners information
    * **Business Transaction** - Expected transaction patterns and volumes
    * **Business Registration** - Legal and regulatory details

    To update a business customer with complete KYB information:

    1. Open your terminal or command prompt.
    2. Use the `PUT` request below to the `/v1/customers/{customer_id}` endpoint.
    3. Replace `{customer_id}` with the customer ID.
    4. Replace `YOUR_BASE_URL` with your chosen environment's URL and `{YOUR_SECRET_KEY}` with your actual key.
    5. Include all required sections and documents.

    ```shell  theme={null}
    $ curl -i -X PUT https://YOUR_BASE_URL/v1/customers/{customer_id} \
      -H 'Authorization: Bearer {YOUR_SECRET_KEY}' \
      -H 'Content-Type: application/json' \
      -d '{
        "email": "business@gmail.com",
        "has_accepted_terms": true,
        "type": "business",
        "country_id": "NG",
        "phone": "+234 8012345678",
        "business_name": "ABC Corporation",
        "business_industry": "BIN_C4UvTYR5V8jsOx5LmwQ",
        "business_incorporation_date": "2015-06-15",
        "documents": [
          {
            "purposes": ["certificate_of_incorporation"],
            "file": "{{base64 string}}"
          }
        ],
        "business_owners": [
          {
            "first_name": "John",
            "last_name": "Owner",
            "role": ["director"],
            "percentage_ownership": 100,
            "is_pep": false,
            "nationality": "NG",
            "bvn": "12345678901"
          }
        ],
        "business_transaction": {
          "purpose": "international trade",
          "monthly_transaction_value": "above_1m_usd",
          "monthly_transaction_count": "100_to_200",
          "client_transaction_status": "self-owned",
          "api_access_needed": true,
          "api_integration_url": "https://yourbusiness.com"
        },
        "business_registration": {
          "business_type": "type_registered_company",
          "business_structure": "limited_liability_company",
          "business_regulation_status": "regulated",
          "registration_number": "RC1234567",
          "tax_identification_number": "TIN1234567",
          "corporate_group_status": "standalone_company",
          "exchange_listing_status": "not_listed_on_exchange",
          "license_number": "LIC123456"
        }
      }'
    ```
  </Step>

  <Step title="Verify the Customer">
    After uploading all required documents and information, call the verify endpoint to submit the customer for verification.

    To verify the customer:

    1. Open your terminal or command prompt.
    2. Use the `POST` request below to the `/v1/customers/{customer_id}/verify` endpoint.
    3. Replace `{customer_id}` with the customer ID.
    4. Replace `YOUR_BASE_URL` with your chosen environment's URL and `{YOUR_SECRET_KEY}` with your actual key.

    ```shell  theme={null}
        $ curl -i -X POST "https://YOUR_BASE_URL/v1/customers/{customer_id}/verify" \
          -H 'Authorization: Bearer {YOUR_SECRET_KEY}'
    ```

    **Expected Response**

    A successful response indicates the verification request has been submitted:

    ```json  theme={null}
        {
          "status": "success",
          "message": "Customer verified successfully"
        }
    ```

    <Info>
      After calling the verify endpoint, the customer status will change from `inactive` to `in_review`. You'll receive webhook notifications as the verification progresses.
    </Info>
  </Step>

  <Step title="Check Verification Status">
    Monitor the customer's verification status by retrieving their details:

    ```shell  theme={null}
        $ curl -i -X GET "https://YOUR_BASE_URL/v1/customers/{customer_id}" \
          -H 'Authorization: Bearer {YOUR_SECRET_KEY}'
    ```

    **Customer Status Values:**

    * `inactive` - Customer created but not yet verified
    * `in_review` - Verification documents submitted and under review
    * `active` - Customer verified and can perform transactions
    * `rejected` - Verification failed (check rejection reason)

    **Example Response:**

    ```json  theme={null}
        {
          "status": "success",
          "message": "Fetched customer successfully",
          "data": {
            "id": "CUS_Ikdb49NLsnlYU",
            "email": "customer@gmail.com",
            "first_name": "John",
            "last_name": "Doe",
            "type": "individual",
            "status": "in_review",
            "level": "0",
            "created_at": "2026-01-27T09:23:09.317273Z",
            "updated_at": "2026-01-27T09:26:34.537881Z"
          }
        }
    ```
  </Step>

  <Step title="Monitor Verification via Webhooks (Recommended)">
    Set up webhooks to receive real-time notifications about verification status changes. See [Webhook Events](/guides/webhooks/webhook-events) for available customer verification events:

    * `customer.verification.in_review` - Verification submitted
    * `customer.verification.active` - Verification approved
    * `customer.verification.rejected` - Verification failed
    * `customer.verification.inactive` - Verification reverted

    **Example Webhook Payload:**

    ```json  theme={null}
        {
          "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
          "event": "customer.verification.in_review",
          "data": {
            "id": "CUS_Ikdb49NLsnlYU",
            "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
            "business_name": "",
            "first_name": "John",
            "last_name": "Doe",
            "email": "customer@gmail.com",
            "type": "individual",
            "status": "in_review",
            "level": "0",
            "created_at": "2026-01-27T09:23:09.317273Z"
          }
        }
    ```

    Learn how to set up webhooks in the [Webhooks Guide](/guides/webhooks/setup).
  </Step>
</Steps>

## Common Verification Errors

**Individual Customers:**

```json  theme={null}
{
  "error": {
    "name": "profile_kyc_verification",
    "message": "KYC document does not contain a selfie video"
  }
}
```

**Solution:** Ensure the `documents` array includes a selfie video with `purposes: ["selfie_video"]`.

**Business Customers:**

```json  theme={null}
{
  "error": {
    "name": "missing_section",
    "message": "Owners section must be completed before final submission"
  }
}
```

**Solution:** Add the `business_owners` array with at least one owner/director.

```json  theme={null}
{
  "error": {
    "name": "missing_section",
    "message": "Transaction section must be completed before final submission"
  }
}
```

**Solution:** Add the `business_transaction` object with transaction details.

## Troubleshooting

* **`400 Bad Request / 422 Unprocessable Entity`:** Review your request body to ensure all required fields are present and correctly formatted.
* **`401 Unauthorized`:** Verify that your Secret API Key is correct and included in the header.
* **`missing_section` errors:** For business customers, ensure all three sections (owners, transaction, registration) are complete before verification.
* **`profile_kyc_verification` errors:** For individual customers, ensure all required documents (ID images and selfie video) are uploaded.

## What's Next?

Now that you can programmatically verify customers, you can proceed to perform transactions on their behalf:

* [How to Initiate Transactions on Behalf of a Customer](/guides/customers/transactions-on-behalf): Understand how to use the `customer_id` to perform operations for your customers.
* [Webhook Events](/guides/webhooks/webhook-events): Learn about all customer verification webhook events.
* [Monitor Verification Status](/guides/customers/check-status): Track customer verification progress.
> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Initiate Transactions on Behalf of Customers

> Process deposits, payouts, and conversions for your customers.

Businesses can perform any transaction on behalf of a customer that the customer could perform themselves. To do this, the **customer's profile ID** must be included in the header. This ensures the transaction is correctly attributed to the customer rather than the business's account.

<Note>
  For transaction requests involving a customer, the `X-BU-PROFILE-ID` field
  should be included in the request header, and its value should be set to the
  customer ID for whom the request is performed on their behalf.
</Note>

This guide will walk you through the process of programmatically accepting fiat currency deposits (e.g., NGN, KES) into your customer's Busha Business fiat balances. Fiat deposits are typically facilitated through the generation of temporary bank accounts.

<Tip>
  **What You'll Achieve:**

  1. Understand the specific quote requirements for fiat deposits.
  2. Generate a deposit quote that provisions a temporary bank account.
  3. Finalize the deposit transfer to obtain the temporary bank account details.
  4. Learn how to instruct your customer to make the deposit.
  5. Monitor the status of fiat deposits.
</Tip>

## Prerequisites

Before you begin, ensure you have:

* A Busha Business Account and Secret API Key (from the [Quick Start Tutorial](../getting-started/quick-start)).

* An understanding of API Environments (Sandbox vs. Production) and their base URLs (from the [Make Your First Request Guide](../getting-started/make-first-request)).

* A conceptual understanding of Quotes (from the [Quotes Overview](/overview/quotes)).

* Familiarity with creating basic quotes (from the [How to Create Your First Quote](/guides/quotes/create-first-quote) Guide)

* A customer account.

## Processing Transactions on Behalf of Customers

<Steps>
  <Step title="Get a Quote for the Fiat Deposit">
    Fiat deposits are initiated by creating a Quote, where the `source_currency` and `target_currency` are the same fiat currency (e.g., NGN to NGN). The key here is the `pay_in` object, which specifies that you intend to use a `temporary_bank_account` for the deposit. This informs Busha to prepare for generating such an account upon transfer finalization.

    **To get a fiat deposit quote:**

    1. Open your terminal or command prompt.

    2. Construct a POST request to the `/v1/quotes` endpoint.

    3. Specify the `source_currency` and `target_currency` (both being the fiat currency you expect to receive), the `source_amount` the customer intends to deposit, and a `pay_in` object with `type: "temporary_bank_account"`.

    4. Replace `{customer_id}` with the customer ID.

    5. Replace `YOUR_BASE_URL` with your chosen environment's URL and `YOUR_SECRET_TOKEN` with your actual key.

    ```bash  theme={null}
    $ curl -i -X POST \
      https://YOUR_BASE_URL/v1/quotes \
      -H 'X-BU-PROFILE-ID: {customer_id}' \
      -H 'Authorization: Bearer YOUR_SECRET_TOKEN' \
      -H 'Content-Type: application/json' \
      -d '{
        "source_currency": "NGN",
        "target_currency": "NGN",
        "source_amount": "10000",
        "pay_in": {
          "type": "temporary_bank_account"
        }
      }'
    ```

    A successful response will return a standard Quote object, similar to what you've seen before.

    <Note>
      Note the `id` of the returned Quote (e.g., `QUO_vxcF2svmjMbxDp4T5dcD8`), as
      you will need this ID for the next step.
    </Note>

    The `pay_in` object in the quote response will mirror what you sent in the request.
  </Step>

  <Step title="Finalize the Deposit Transfer (Generate Temporary Bank Account)">
    After obtaining a valid Quote for a fiat deposit, you finalize the transfer. This crucial step is where Busha *generates* the temporary bank account details that your customer will transfer funds into. The transfer object is initiated using the `POST /v1/transfers` endpoint.

    **To finalize the deposit transfer:**

    1. Use the POST request below to the `/v1/transfers` endpoint.
    2. Include the `quote_id` obtained from Step 1.
    3. Replace `{customer_id}` with the customer ID.
    4. Replace `YOUR_BASE_URL` and `YOUR_SECRET_KEY` with your actual details.

    ```bash  theme={null}
    $ curl -i -X POST \
      https://YOUR_BASE_URL/v1/transfers \
      -H 'X-BU-PROFILE-ID: {customer_id}' \
      -H 'Authorization: Bearer YOUR_SECRET_KEY' \
      -H 'Content-Type: application/json' \
      -d '{
    "quote_id": "QUO_vxcF2svmjMbxDp4T5dcD8"

    }'

    ```

    **Sample Response with Generated Temporary Account Number:**

    A successful response will return a Transfer object. The generated temporary bank account number and other recipient details will be located within the `data.pay_in.recipient_details` object of this response.

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created transfer successfully",
      "data": {
        "id": "TRF_LJB2GQb55Cs98LbpqgRMx",
        "profile_id": "CUS_BqpKZiPXPo3Vz",
        "quote_id": "QUO_vxcF2svmjMbxDp4T5dcD8",
        "source_currency": "NGN",
        "target_currency": "NGN",
        "source_amount": "10000",
        "target_amount": "9900",
        "rate": {
          "rate": "1",
          "side": "sell",
          "type": "FIXED",
          "source_currency": "NGN",
          "target_currency": "NGN"
        },
        "fees": [
          {
            "amount": {
              "amount": "100",
              "currency": "NGN"
            },
            "name": "payment gateway fee",
            "type": "FIXED"
          }
        ],
        "pay_in": {
          "expires_at": "2025-02-21T10:46:56.232278869Z",
          "recipient_details": {
            "account_name": "Payaza(Business 1 Business)",
            "account_number": "7000384620",
            "bank_name": "78 FINANCE COMPANY LIMITED",
            "email": "dickson@busha.co"
          },
          "type": "temporary_bank_account"
        },
        "status": "pending",
        "created_at": "2025-02-21T10:16:54.40130914Z",
        "updated_at": "2025-02-21T10:16:54.401309215Z"
      }
    }
    ```
  </Step>

  <Step title="Instruct Your User and Monitor Deposit Status">
    Once you have the temporary bank account details, your application should display these to the customer, along with clear instructions to make the bank transfer. After the customer initiates the transfer, you'll need to monitor its status to confirm when the funds have been successfully deposited into your Busha balance.

    **To monitor deposit status:**

    * **Webhooks (Recommended):** Set up a webhook endpoint to receive real-time notifications from Busha when the transfer status changes. This is the most efficient method for real-time updates.

      * Refer to the How to Set Up Webhook Guide for detailed instructions

    * **Polling (Less Recommended):** Periodically GET the transfer status using the transfer `id` (`TRF_LJB2GQb55Cs98LbpqgRMx` in the example). While possible, this is less efficient and can lead to rate limiting if done too frequently.

    **Expected Transfer Statuses:**

    * `pending`: Transfer initiated, awaiting user bank transfer.
    * `completed`: Funds have been successfully received and credited to your Busha balance.
    * `failed`: The deposit failed (e.g., transfer not received within timeframe, invalid details)
    * `reversed`: The deposit was processed but later reversed.
  </Step>
</Steps>

## Troubleshooting Common Fiat Deposit Issues

**"Quote expired" during transfer finalization:** Always create a fresh quote immediately before attempting to finalize the transfer.

**Deposit not reflecting:** Check the transfer status via API/webhooks. If still `pending` after an extended period, contact Busha support with the `TRF_` ID.

**Temporary account expires:** Ensure the user makes the transfer before the `pay_in.expires_at` time shown in the transfer response. If expired, you may need to initiate a new deposit flow.

## What's Next?

Now that you know how to process fiat deposits, consider:

* [How to Process Crypto Deposits Guide](/guides/deposits/process-crypto-deposits)
* [How to Process Payouts Guide](/guides/payouts/process-payouts)
* Exploring all relevant API endpoints: [Quotes API Reference](/api-reference/quotes/list-quotes), [Transfers API Reference](/api-reference/transfers/list-transfers)



> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Set Up Webhooks

> Configure webhooks to receive real-time notifications. Monitor deposits, payouts, and transaction status.

When you make a transfer request, you typically have to wait for a payment to be sent into/out of the platform before that transfer can be updated as done. This can be achieved by either polling the GET Transfer API or by listening to a webhook URL.

A webhook URL is simply a POST endpoint that the Busha API server sends updates to. The URL needs to receive and parse a JSON request and return a 200 OK response.

<Check>
  Webhook is the recommended way to listen for updates for a transfer request as
  the polling alternative will not be reliable due to poor connection on the
  client side, which you do not have control over.
</Check>

## Prerequisites

Before you start, make sure you have:

* A Busha Business Account (from the [Quick Start Guide](/guides/getting-started/quick-start)).

## How to setup Webhooks

<Steps>
  <Step title="Create A Webhook URL">
    Before you can receive webhook events from Busha, you need a dedicated URL where these events will be sent. This URL acts as the listener for all notifications your application needs to process. You have two primary options for setting up this endpoint:

    **For Production Environments (Your Application)**

    For a live, robust integration, you will need to create a specific endpoint within your own application's backend. This endpoint should be:

    * **Publicly Accessible:** It must be a live URL that Busha's servers can reach over the internet (e.g., `https://your-domain.com/busha-webhook`). Localhost URLs will not work.

    * **Configured to Receive POST Requests:** Your endpoint should be designed to listen for and process incoming HTTP POST requests, as this is how Busha will deliver webhook payloads.

    * **Designed for Processing:** This endpoint should contain the logic to verify the webhook signature (for security) and then process the event data according to your application's needs.

    **For Testing and Development (e.g., [Webhook.site](https://webhook.site))**

    If you are just getting started, testing your integration, or inspecting webhook payloads, you can use a temporary third-party service to generate a quick webhook URL:

    * **Using Webhook.site:** Navigate to [webhook.site](https://webhook.site). The site will instantly provide you with a unique, temporary URL.

    * **Functionality:** Any POST requests sent to this URL will be immediately displayed on the page, allowing you to inspect the payload, headers, and other details.

    * **Important Note:** URLs generated by services like Webhook.site are for testing and development purposes only and should never be used in a production environment due to their temporary nature and lack of security controls.
  </Step>

  <Step title="Create A New Webhook">
    After you have created your webhook URL, you need to visit the developer tools section on your dashboard to register the URL. **Settings > Developer Tools > Webhooks**

    <img src="https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/dev-tools-webhooks.png?fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=10ffe3264ddadd625d0650d78bb6ddd4" alt="Profile > Developer tools > Webhooks" data-og-width="2880" width="2880" data-og-height="1620" height="1620" data-path="images/dev-tools-webhooks.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/dev-tools-webhooks.png?w=280&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=cd4f87a7b42f65bedcef147aafa6d822 280w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/dev-tools-webhooks.png?w=560&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=e3e91f928f6559b48f9d1bea517847f8 560w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/dev-tools-webhooks.png?w=840&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=779a07ac9fd16c5be9bcff08dbb2cb73 840w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/dev-tools-webhooks.png?w=1100&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=21e8df51ab0d6e4634bbdceed55edf20 1100w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/dev-tools-webhooks.png?w=1650&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=c63cfe2d59ed2d831cd9bccc71eb6642 1650w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/dev-tools-webhooks.png?w=2500&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=3c6751e2246f4c514407f8dcaaa0aeb0 2500w" />

    Click on the "Create a new Webhook" button. You should see an input form like this:

    <img src="https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/new-webhook.png?fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=8854246105e77e40f455e89bd4ff7e5d" alt="Create webhook form" data-og-width="2880" width="2880" data-og-height="1620" height="1620" data-path="images/new-webhook.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/new-webhook.png?w=280&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=e9799915f2c7f9bbedffce5e2ca4a472 280w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/new-webhook.png?w=560&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=2a3908ea87434e18fb73c6130f7ae20e 560w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/new-webhook.png?w=840&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=b0da9ae26c2b9501d51fe57c1751681d 840w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/new-webhook.png?w=1100&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=7534f8347350039456c4da7fa0d257c0 1100w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/new-webhook.png?w=1650&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=f6fb2cc9cc1397da5660af5b9779607b 1650w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/new-webhook.png?w=2500&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=3f37613f77d6590be63d243acd3fe2b3 2500w" />

    Enter a name for your webhook and include the full path to the webhook URL you created in the previous step then select the events you want to subscribe to, click on the "Create webhook" button, then copy your secret key.

    <img src="https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/secret-key.png?fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=86c9569af843021d5551264f25f4d955" alt="Webhook secret key" data-og-width="1102" width="1102" data-og-height="640" height="640" data-path="images/secret-key.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/secret-key.png?w=280&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=6a2335e506d50dfc9a8d5b4511ce3de7 280w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/secret-key.png?w=560&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=68dbddc4f096fa24b9a0640546358928 560w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/secret-key.png?w=840&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=683e506a02d3927db4bc1c3476963fa4 840w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/secret-key.png?w=1100&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=c2d4ded8c945b05392b9a08532abd4f2 1100w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/secret-key.png?w=1650&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=05fc06584f5b17cb50350cc027931926 1650w, https://mintcdn.com/busha-36f167ef/mmdaasXcCEYjyuVA/images/secret-key.png?w=2500&fit=max&auto=format&n=mmdaasXcCEYjyuVA&q=85&s=850136b7113b805070ea4184ea91c1dd 2500w" />

    <Warning>
      Ensure to copy your Webhook Secret Key and truly keep it secret. This secret
      is important and will be used to validate that every request made to your
      webhook URL is genuinely from Busha.
    </Warning>
  </Step>

  <Step title="Listen for Events">
    **Verifying Webhook Signature**

    To calculate and verify the `x-bu-signature`, perform a HMAC hash of the request body using the Webhook secret as the key.

    For example:

    ```go  theme={null}
        func ValidateHMACChecksum(body []byte, checksum string) (bool, error) {
            mac := hmac.New(sha256.New, []byte(hmacSecret))
            mac.Write(body)
            expectedMAC := mac.Sum(nil)
            actualMAC, err := base64.StdEncoding.DecodeString(checksum)
            if err != nil {
               return false, fmt.Errorf("failed to decode checksum: %w", err)
            }
            return hmac.Equal(actualMAC, expectedMAC), nil
        }
    ```

    **Webhooks receive the following events from Busha:**

    <table style={{ width: "100%", borderCollapse: "collapse" }}>
      <thead>
        <tr>
          <th style={{ width: "40%", textAlign: "left", padding: "12px" }}>
            Event Types
          </th>

          <th style={{ width: "60%", textAlign: "left", padding: "12px" }}>
            Description
          </th>
        </tr>
      </thead>

      <tbody>
        <tr>
          <td style={{ padding: "12px" }}>
            <code>transfer.pending</code>
          </td>

          <td style={{ padding: "12px" }}>
            A new Transfer has been initiated and is pending
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>transfer.processing</code>
          </td>

          <td style={{ padding: "12px" }}>A transfer is processing.</td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>transfer.cancelled</code>
          </td>

          <td style={{ padding: "12px" }}>
            A transfer has been cancelled by the user.
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>transfer.funds\_received</code>
          </td>

          <td style={{ padding: "12px" }}>
            A deposit transfer has been received successfully.
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>transfer.funds\_converted</code>
          </td>

          <td style={{ padding: "12px" }}>
            An internal conversion transfer has been completed
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>transfer.outgoing\_payment\_sent</code>
          </td>

          <td style={{ padding: "12px" }}>
            A payout transfer to a recipient or external wallet address has been
            sent to the beneficiary and awaiting confirmation of receipt.
          </td>
        </tr>

        <tr>
          <td style={{ padding: "12px" }}>
            <code>transfer.funds\_delivered</code>
          </td>

          <td style={{ padding: "12px" }}>
            A payout transfer has been confirmed as received by the beneficiary
            successfully.
          </td>
        </tr>
      </tbody>
    </table>

    **Sample Event Payload:**

    ```json  theme={null}
    {
      "business_id": "BUS_tg6yujbZ1nMu5BLQkPGGO",
      "event": "transfer.funds_converted",
      "data": {
        "id": "TRF_BaAUvCTlZCt3hu3OO4u8P",
        "profile_id": "BUS_tg6yujbZ1nMu5BLQkPGGO",
        "quote_id": "QUO_5CnpikeQJEKQoDcnOsdkU",
        "source_currency": "NGN",
        "target_currency": "USDT",
        "source_amount": "10000",
        "target_amount": "5.728032",
        "rate": {
          "product": "USDTNGN",
          "rate": "1745.8",
          "side": "buy",
          "type": "FIXED",
          "source_currency": "NGN",
          "target_currency": "USDT"
        },
        "fees": [],
        "status": "completed",
        "created_at": "2025-02-21T09:55:20.852345Z",
        "updated_at": "2025-02-21T09:55:22.365451Z"
      }
    }
    ```
  </Step>
</Steps>

## Transfer Status Development

The following section aims to show how a transfer can develop from one status to another. This will show you understand how to properly handle and anticipate webhook events.

**Transfer.Pending:**

This is the initial status for all transfers. All transfers start as pending, and a pending transfer can morph into only two next statuses:

* transfer.cancelled
* transfer.processing

<img src="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-pending.png?fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=af5121aec4fb7888a6567e465186e2ed" alt="Pending status flow diagram" data-og-width="1073" width="1073" data-og-height="546" height="546" data-path="images/transfer-pending.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-pending.png?w=280&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=e6cbfb2ada5ba03ce83256d44e3f72a7 280w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-pending.png?w=560&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=8e6d47f3a40956c4db1e6842945120ee 560w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-pending.png?w=840&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=80773d2f96e73572f7cc37a7b4f722e1 840w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-pending.png?w=1100&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=d5221377f8316f2edf9a34c9d1131c88 1100w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-pending.png?w=1650&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=d0531d2812a32c81609d771c3e3b106a 1650w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-pending.png?w=2500&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=26c88dd2768f6b2ee97a04a77cddc8a1 2500w" />

**Transfer.Cancelled:**

A cancelled transfer means that the transfer was unable to continue and can not begin processing. Hence, this is a final state that can not further change.

**Transfer.Processing:**

A processing transfer means that the transfer has started work and is already making the money movement from the destination to the source. A processing transfer can change into any of these statuses depending on the transfer type:

* transfer.outgoing\_payment\_sent
* transfer.funds\_received
* transfer.funds\_converted

<img src="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-processing.png?fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=108cb24d6345e38008711bebb548ef3c" alt="Processing status flow diagram" data-og-width="1073" width="1073" data-og-height="546" height="546" data-path="images/transfer-processing.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-processing.png?w=280&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=cab3f5e47394e2ad4cd09de7140c75b2 280w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-processing.png?w=560&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=d4e42cbb2fc6b66f75dd318004969f2c 560w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-processing.png?w=840&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=632bb81e732accc419a0d76b206bfaea 840w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-processing.png?w=1100&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=7c96bf3723c1f2c9e6f09996a422e5c3 1100w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-processing.png?w=1650&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=3ef270fdc3db2788397970f6dee512be 1650w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-processing.png?w=2500&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=6da2990e2061a9b7a7ed49e6524aa819 2500w" />

**Transfer.OutgoingPaymentSent:**

An `outgoing_payment_sent` status represents any transfer that involves sending money out of the Busha platform to an external bank account or an external wallet address. It is not a final status; the outgoing\_payment\_sent status can morph into a final `funds_delivered` status

<img src="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-payment-sent.png?fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=039cf2145541c770aed9fcd453d05383" alt="Outgoing payment status flow diagram" style={{ display: 'block', margin: '0 auto' }} data-og-width="505" width="505" data-og-height="546" height="546" data-path="images/transfer-payment-sent.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-payment-sent.png?w=280&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=103681be485e12cd0cdcb364074ec14d 280w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-payment-sent.png?w=560&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=4ddccad8d301f57f47ca45445d11d5d7 560w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-payment-sent.png?w=840&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=cba9d8c587313527243d3d3e45cb1bc7 840w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-payment-sent.png?w=1100&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=84abd70ffd8b072716489efb297375f0 1100w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-payment-sent.png?w=1650&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=bb0fde9c73141bb0caad88654afaaac5 1650w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-payment-sent.png?w=2500&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=cd3b080f54bc4bb7d558b11ed99cd515 2500w" />

**Funds.Delivered:**

This is a final status that communicates that a payout transfer has been delivered successfully to the specified destination.

**Funds.Received:**

The `funds_received` status communicates that a Deposit into a Busha Balance has been successful. This could be a final status for all deposit transfers; however, in a scenario where the source currency is not the same as the target currency, then this transfer will further change into a `funds_converted` status.

**Funds.Converted:**

The `funds_converted` status communicates that a conversion transfer has been done successfully. This is also the same status that communicated the successful Buy/Sell Trade.

## Transfer Status Change Map

<img src="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-status-change-map.png?fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=796679f3847dbcfc2f644897f1c3d3ed" alt="Transfer Status Change Map" data-og-width="1073" width="1073" data-og-height="546" height="546" data-path="images/transfer-status-change-map.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-status-change-map.png?w=280&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=354240c69b31a24861c0e60873654bb0 280w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-status-change-map.png?w=560&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=d832d27dd739c3c4e04a698a3d8e611c 560w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-status-change-map.png?w=840&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=095d5fabc02b107119f3ebd06c588f16 840w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-status-change-map.png?w=1100&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=a00079044da3d0051ee3ce747439db0b 1100w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-status-change-map.png?w=1650&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=240e2d5d3cc5aadb5b32bed76b84bb8b 1650w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/transfer-status-change-map.png?w=2500&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=396ac4c5e25431377cded70f6cf8f7d3 2500w" />

**Conversions and Trades(Buy/Sell) Status Change:**

```
pending → processing → funds_converted
```

**Payouts Status Change:**

```
pending → processing → outgoing_payment_sent → funds_delivered
```

**Deposit Status Change:**

```
pending → processing → funds_received
```

**On-Ramp (External Fiat → Crypto):**

```
pending → processing → funds_received → funds_converted
```

> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Webhook Events

> Real-time notifications for transfers, deposits, conversions, payouts, payment requests, and customer verification in your Busha integration.

Busha sends webhook notifications to your server when events happen on your account. Use webhooks to automate workflows and keep your application in sync with Busha.

## Why Use Webhooks?

Webhooks eliminate the need to poll the API repeatedly instead, Busha notifies you instantly when something happens.

**Common use cases:**

* Confirm order fulfillment after payment
* Update order status in your database
* Send customer receipts automatically
* Trigger inventory updates
* Notify customers of payment status
* Automate customer verification workflows

***

## Customer Events

Customer events track the lifecycle of customer accounts, including creation, updates, and verification status changes.

### customer.created

**Sent when:** A new customer account is created.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "customer.created",
    "data": {
      "id": "CUS_Ikdb49NLsnlYU",
      "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "business_name": "",
      "first_name": "John",
      "last_name": "Doe",
      "email": "customer@gmail.com",
      "type": "individual",
      "status": "inactive",
      "level": "0",
      "created_at": "2026-01-27T09:23:09.317273Z"
    }
  }
  ```
</Accordion>

***

### customer.updated

**Sent when:** Customer account information is updated (e.g., address, phone number, documents).

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "customer.updated",
    "data": {
      "id": "CUS_Ikdb49NLsnlYU",
      "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "first_name": "John",
      "last_name": "Doe",
      "email": "customer@gmail.com",
      "phone": "+234 8087654321",
      "type": "individual",
      "status": "inactive",
      "level": "0",
      "created_at": "2026-01-27T09:23:09.317273Z",
      "updated_at": "2026-01-27T09:25:15.123456Z"
    }
  }
  ```
</Accordion>

***

### customer.verification.in\_review

**Sent when:** Customer verification documents have been submitted and are under review.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "customer.verification.in_review",
    "data": {
      "id": "CUS_Ikdb49NLsnlYU",
      "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "business_name": "",
      "first_name": "John",
      "last_name": "Doe",
      "email": "customer@gmail.com",
      "type": "individual",
      "status": "in_review",
      "level": "0",
      "created_at": "2026-01-27T09:23:09.317273Z",
      "updated_at": "2026-01-27T09:26:34.537881Z"
    }
  }
  ```
</Accordion>

***

### customer.verification.active

**Sent when:** Customer verification has been approved and the account is now active.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "customer.verification.active",
    "data": {
      "id": "CUS_Ikdb49NLsnlYU",
      "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "business_name": "",
      "first_name": "John",
      "last_name": "Doe",
      "email": "customer@gmail.com",
      "type": "individual",
      "status": "active",
      "level": "1",
      "created_at": "2026-01-27T09:23:09.317273Z",
      "updated_at": "2026-01-27T10:15:20.456789Z"
    }
  }
  ```
</Accordion>

***

### customer.verification.rejected

**Sent when:** Customer verification has been rejected due to invalid or incomplete documents.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "customer.verification.rejected",
    "data": {
      "id": "CUS_Ikdb49NLsnlYU",
      "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "business_name": "",
      "first_name": "John",
      "last_name": "Doe",
      "email": "customer@gmail.com",
      "type": "individual",
      "status": "rejected",
      "level": "0",
      "rejection_reason": "Invalid identification documents provided",
      "created_at": "2026-01-27T09:23:09.317273Z",
      "updated_at": "2026-01-27T10:30:45.789012Z"
    }
  }
  ```
</Accordion>

***

### customer.verification.inactive

**Sent when:** Customer verification status has been reverted to inactive (e.g., due to compliance issues or account suspension).

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "customer.verification.inactive",
    "data": {
      "id": "CUS_Ikdb49NLsnlYU",
      "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "business_name": "",
      "first_name": "John",
      "last_name": "Doe",
      "email": "customer@gmail.com",
      "type": "individual",
      "status": "inactive",
      "level": "0",
      "created_at": "2026-01-27T09:23:09.317273Z",
      "updated_at": "2026-01-27T11:00:00.123456Z"
    }
  }
  ```
</Accordion>

***

## Transfer Events

Transfer events track the complete lifecycle of all fund movements on Busha, including:

* **Deposits** - Receiving crypto or fiat into your Busha account
* **Conversions** - Converting between currencies (crypto-to-fiat, fiat-to-crypto, crypto-to-crypto)
* **Payouts** - Sending funds to bank accounts or external wallets

### transfer.pending

**Sent when:** A new transfer is created and awaiting funds.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "transfer.pending",
    "data": {
      "id": "TRF_HGjjAUDo1pti",
      "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "quote_id": "QUO_0qoz7fXtHRFd",
      "description": "Sold BTC",
      "sub_description": "For NGN",
      "source_currency": "BTC",
      "target_currency": "NGN",
      "source_amount": "0.00036606",
      "target_amount": "50001.27",
      "trade": "sell",
      "rate": {
        "product": "BTCNGN",
        "rate": "136593125.15",
        "side": "sell",
        "type": "FIXED",
        "source_currency": "BTC",
        "target_currency": "NGN"
      },
      "fees": [],
      "pay_in": {
        "address": "tb1qnltjz527u6pxvcavqwrrja6znyrgd9c2pcl7qu",
        "expires_at": "2025-12-10T11:35:59.175239Z",
        "network": "BTC",
        "type": "address"
      },
      "status": "pending",
      "timeline": {
        "total_steps": 0,
        "current_step": 0,
        "transfer_status": "",
        "events": []
      },
      "created_at": "2025-12-10T10:35:59.108130515Z",
      "updated_at": "2025-12-10T10:35:59.108130584Z"
    }
  }
  ```
</Accordion>

***

### transfer.processing

**Sent when:** Funds have been received and are being actively processed.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "transfer.processing",
    "data": {
      "id": "TRF_Bz6i6C5HaS7t",
      "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "source_currency": "BTC",
      "target_currency": "NGN",
      "source_amount": "0.0001841",
      "target_amount": "25000.17",
      "status": "processing",
      "created_at": "2025-12-05T13:12:57.582284Z",
      "updated_at": "2025-12-05T13:13:30.123456Z"
    }
  }
  ```
</Accordion>

***

### transfer.funds\_received

**Sent when:** Funds have been successfully received into your Busha account. This is an intermediate status before conversion.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "transfer.funds_received",
    "data": {
      "id": "TRF_5Vel81eg7kuz",
      "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "quote_id": "QUO_K7viFhMDuYE4",
      "description": "Bought BTC",
      "sub_description": "With USDT",
      "source_currency": "USDT",
      "target_currency": "BTC",
      "source_amount": "16.80378556",
      "target_amount": "0.00018",
      "trade": "buy",
      "rate": {
        "product": "BTCUSDT",
        "rate": "90825.65",
        "side": "buy",
        "type": "FIXED",
        "source_currency": "USDT",
        "target_currency": "BTC"
      },
      "fees": [],
      "pay_in": {
        "address": "0x28730e094b58cb4e8B57549fC451aaB3423Ba4B7",
        "expires_at": "2025-12-05T14:31:33.610518Z",
        "network": "BSC",
        "type": "address",
        "blockchain_hash": "busha_579b948e43caf0f89c4edfc4572425a38564ac708b9bfba177bd395d49076c53",
        "blockchain_url": "https://testnet-explorer.binance.org/tx/busha_579b948e43caf0f89c4edfc4572425a38564ac708b9bfba177bd395d49076c53",
        "from_address": "busha/groot/USDT/PYT_FpqPiXPvLa6T"
      },
      "status": "funds_received",
      "timeline": {
        "total_steps": 0,
        "current_step": 0,
        "transfer_status": "",
        "events": []
      },
      "created_at": "2025-12-05T13:31:33.537625Z",
      "updated_at": "2025-12-05T13:32:42.685331Z"
    }
  }
  ```
</Accordion>

***

### transfer.funds\_converted

**Sent when:** Currency conversion is complete (crypto-to-fiat, fiat-to-crypto, or crypto-to-crypto). This is the final status for conversion transfers.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "transfer.funds_converted",
    "data": {
      "id": "TRF_5Vel81eg7kuz",
      "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "quote_id": "QUO_K7viFhMDuYE4",
      "description": "Bought BTC",
      "sub_description": "With USDT",
      "source_currency": "USDT",
      "target_currency": "BTC",
      "source_amount": "16.80378556",
      "target_amount": "0.00018",
      "trade": "buy",
      "rate": {
        "product": "BTCUSDT",
        "rate": "90825.65",
        "side": "buy",
        "type": "FIXED",
        "source_currency": "USDT",
        "target_currency": "BTC"
      },
      "fees": [],
      "pay_in": {
        "address": "0x28730e094b58cb4e8B57549fC451aaB3423Ba4B7",
        "expires_at": "2025-12-05T14:31:33.610518Z",
        "network": "BSC",
        "type": "address",
        "blockchain_hash": "busha_579b948e43caf0f89c4edfc4572425a38564ac708b9bfba177bd395d49076c53",
        "blockchain_url": "https://testnet-explorer.binance.org/tx/busha_579b948e43caf0f89c4edfc4572425a38564ac708b9bfba177bd395d49076c53",
        "from_address": "busha/groot/USDT/PYT_FpqPiXPvLa6T"
      },
      "status": "funds_converted",
      "timeline": {
        "total_steps": 0,
        "current_step": 0,
        "transfer_status": "",
        "events": []
      },
      "created_at": "2025-12-05T13:31:33.537625Z",
      "updated_at": "2025-12-05T13:32:42.836738Z"
    }
  }
  ```
</Accordion>

***

### transfer.completed

**Sent when:** Transfer has been successfully completed. This is the final status for successful transfers.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "transfer.completed",
    "data": {
      "id": "TRF_Bz6i6C5HaS7t",
      "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "quote_id": "QUO_yPfvBpxstyjp",
      "description": "Sold BTC",
      "sub_description": "For NGN",
      "source_currency": "BTC",
      "target_currency": "NGN",
      "source_amount": "0.0001841",
      "target_amount": "25000.17",
      "trade": "sell",
      "rate": {
        "product": "BTCNGN",
        "rate": "135796707.37",
        "side": "sell",
        "type": "FIXED",
        "source_currency": "BTC",
        "target_currency": "NGN"
      },
      "fees": [],
      "status": "completed",
      "timeline": {
        "total_steps": 2,
        "current_step": 2,
        "transfer_status": "completed",
        "events": [
          {
            "step": 1,
            "done": true,
            "status": "funds_received",
            "title": "Crypto Received",
            "description": "Funded from Balance"
          },
          {
            "step": 2,
            "done": true,
            "status": "completed",
            "title": "Transfer Completed",
            "description": "Funds have been successfully processed"
          }
        ]
      },
      "created_at": "2025-12-05T13:12:57.582284Z",
      "updated_at": "2025-12-05T13:14:38.243849Z"
    }
  }
  ```
</Accordion>

***

### transfer.failed

**Sent when:** Transfer fails due to an error (insufficient funds, network issues, invalid recipient details, etc.).

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "transfer.failed",
    "data": {
      "id": "TRF_failed123",
      "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "source_currency": "BTC",
      "target_currency": "NGN",
      "source_amount": "0.0001",
      "target_amount": "15000",
      "status": "failed",
      "error": {
        "code": "insufficient_funds",
        "message": "Insufficient balance to complete transfer"
      },
      "created_at": "2025-12-05T10:00:00.000000Z",
      "updated_at": "2025-12-05T10:05:00.000000Z"
    }
  }
  ```
</Accordion>

***

### transfer.cancelled

**Sent when:** Transfer is cancelled (due to user cancellation or system cancellation).

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "transfer.cancelled",
    "data": {
      "id": "TRF_aKn8Z3Cmtc8a",
      "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "source_currency": "USDC",
      "target_currency": "NGN",
      "source_amount": "16.91200347",
      "target_amount": "25000",
      "status": "cancelled",
      "created_at": "2025-12-04T12:06:47.648775Z",
      "updated_at": "2025-12-04T12:36:47.648775Z"
    }
  }
  ```
</Accordion>

***

### transfer.funds\_not\_delivered

**Sent when:** Funds were received but could not be delivered to the final destination (e.g., invalid bank account, network issues during payout).

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "transfer.funds_not_delivered",
    "data": {
      "id": "TRF_notDelivered789",
      "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "source_currency": "NGN",
      "target_currency": "NGN",
      "source_amount": "5000",
      "target_amount": "5000",
      "status": "funds_not_delivered",
      "pay_out": {
        "type": "bank_transfer",
        "recipient_details": {
          "account_name": "John Doe",
          "account_number": "0123456789",
          "bank_name": "First Bank of Nigeria"
        }
      },
      "error": {
        "code": "invalid_account",
        "message": "Bank account details are invalid or account is closed"
      },
      "created_at": "2025-12-05T14:20:00.000000Z",
      "updated_at": "2025-12-05T14:25:00.000000Z"
    }
  }
  ```
</Accordion>

***

### transfer.funds\_refunded

**Sent when:** Funds are refunded to the balance following a failed transfer.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_5XUsDLKxHTapLHMhlMmcM",
    "event": "transfer.funds_refunded",
    "data": {
      "id": "TRF_X0YjV4Z36JW3",
      "profile_id": "BUS_5XUsDLKxHTapLHMhlMmcM",
      "quote_id": "QUO_9sxWKHbsJEJ2",
      "description": "Sold BTC",
      "sub_description": "For BTC",
      "source_currency": "BTC",
      "target_currency": "BTC",
      "source_amount": "0.000075",
      "target_amount": "0.00007",
      "rate": {
        "rate": "1",
        "side": "sell",
        "type": "FIXED",
        "source_currency": "BTC",
        "target_currency": "BTC"
      },
      "fees": [
        {
          "amount": {
            "amount": "0.000005",
            "currency": "BTC"
          },
          "name": "network fee",
          "type": "FIXED"
        }
      ],
      "pay_out": {
        "address": "tb1ql8xvg2adnt0j5vxzgg7n00zmaf4y65wegpvzcl",
        "network": "BTC",
        "type": "address"
      },
      "status": "funds_refunded",
      "timeline": {
        "total_steps": 0,
        "current_step": 0,
        "transfer_status": "",
        "events": []
      },
      "created_at": "2026-02-03T15:01:20.239458Z",
      "updated_at": "2026-02-03T15:07:36.608114Z"
    }
  }
  ```
</Accordion>

## Payment Request Events

Payment request events track the lifecycle of payment links created through the Busha Business API. These are typically used for e-commerce integrations, invoicing, and customer payment flows.

### payment\_request.pending

**Sent when:** Payment request is awaiting customer payment.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "payment_request.pending",
    "data": {
      "id": "PAYR_oIPbFOXogbjW",
      "additional_info": {
        "email": "ajibola@busha.co",
        "name": "Jibola Obi",
        "phone_number": "",
        "source": ""
      },
      "created_at": "2025-12-10T10:35:59.220244444Z",
      "expires_at": "2025-12-10T11:35:59.175239Z",
      "fees": [],
      "merchant_info": {
        "email": "ajibola@busha.co",
        "name": "Liverppol"
      },
      "pay_in": {
        "address": "tb1qnltjz527u6pxvcavqwrrja6znyrgd9c2pcl7qu",
        "expires_at": "2025-12-10T11:35:59.175239Z",
        "network": "BTC",
        "type": "address"
      },
      "rate": {
        "product": "BTCNGN",
        "rate": "136593125.15",
        "side": "sell",
        "type": "FIXED",
        "source_currency": "BTC",
        "target_currency": "NGN"
      },
      "reference": "PAYR_oIPbFOXogbjW",
      "source_amount": "0.00036606",
      "status": "pending",
      "target_amount": "50001.27",
      "timeline": {
        "total_steps": 0,
        "current_step": 0,
        "transfer_status": "",
        "events": []
      },
      "updated_at": "2025-12-10T10:35:59.220244497Z",
      "source_currency": "BTC",
      "target_currency": "NGN"
    }
  }
  ```
</Accordion>

***

### payment\_request.processing

**Sent when:** Payment has been received and is being processed.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "payment_request.processing",
    "data": {
      "id": "PAYR_NwSBgvVszPvA",
      "additional_info": {
        "email": "ajibola@busha.co",
        "name": "Jibola Obi",
        "phone_number": "+2348012345678",
        "source": "checkout_page"
      },
      "created_at": "2025-12-09T09:51:03.422936437Z",
      "merchant_info": {
        "email": "ajibola@busha.co",
        "name": "Liverpool"
      },
      "pay_in": {
        "address": "GDHHXPUSZK2OYHEVAP7GG6MTDWY2VQ4V5JBTIKFEV526RT6QYF23BSXD",
        "network": "XLM",
        "type": "address",
        "blockchain_hash": "abc123def456...",
        "blockchain_url": "https://stellarchain.io/tx/abc123def456..."
      },
      "reference": "PAYR_NwSBgvVszPvA",
      "source": "payment_link",
      "source_amount": "6.76480139",
      "source_id": "58HgbtNDA34Z",
      "status": "processing",
      "target_amount": "10000",
      "source_currency": "USDC",
      "target_currency": "NGN",
      "updated_at": "2025-12-09T10:10:30.123456Z"
    }
  }
  ```
</Accordion>

***

### payment\_request.completed

**Sent when:** Customer successfully completes payment via payment link.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "payment_request.completed",
    "data": {
      "id": "PAYR_InpIYLNIivEr",
      "additional_info": {
        "email": "ajibola@busha.co",
        "name": "Jibola Obiwole",
        "phone_number": "",
        "source": ""
      },
      "created_at": "2025-12-05T13:28:02.698909Z",
      "expires_at": "2025-12-05T14:28:02.611403Z",
      "fees": [],
      "merchant_info": {
        "email": "ajibola@busha.co",
        "name": "Liverppol"
      },
      "pay_in": {
        "address": "0x817e43857CBFdCf48F2b35E86570031E46D8Db11",
        "expires_at": "2025-12-05T14:28:02.611403Z",
        "network": "BSC",
        "type": "address"
      },
      "rate": {
        "product": "USDTNGN",
        "rate": "1487.76",
        "side": "sell",
        "type": "FIXED",
        "source_currency": "USDT",
        "target_currency": "NGN"
      },
      "reference": "PAYR_InpIYLNIivEr",
      "source_amount": "16.80378556",
      "status": "completed",
      "target_amount": "25000",
      "timeline": {
        "total_steps": 3,
        "current_step": 3,
        "transfer_status": "funds_converted",
        "events": [
          {
            "step": 1,
            "done": true,
            "status": "pending",
            "title": "Transfer Started",
            "description": "Waiting for your USDT deposit",
            "timestamp": "2025-12-05T13:29:00.723079Z"
          },
          {
            "step": 2,
            "done": true,
            "status": "funds_received",
            "title": "Crypto Received",
            "description": "We received your USDT Deposit",
            "timestamp": "2025-12-05T13:29:00.723079Z"
          },
          {
            "step": 3,
            "done": true,
            "status": "funds_converted",
            "title": "Funds Converted",
            "description": "Money has been added to your wallet.",
            "timestamp": "2025-12-05T13:29:00.853277Z"
          }
        ]
      },
      "updated_at": "2025-12-05T13:29:01.974712Z",
      "source_currency": "USDT",
      "target_currency": "NGN"
    }
  }
  ```
</Accordion>

***

### payment\_request.expired

**Sent when:** Payment link expires without being completed.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "payment_request.expired",
    "data": {
      "id": "PAYR_oIPbFOXogbjW",
      "additional_info": {
        "email": "ajibola@busha.co",
        "name": "Jibola Obi",
        "phone_number": "",
        "source": ""
      },
      "created_at": "2025-12-10T10:35:59.220244Z",
      "expires_at": "2025-12-10T11:35:59.175239Z",
      "fees": [],
      "merchant_info": {
        "email": "ajibola@busha.co",
        "name": "Liverppol"
      },
      "pay_in": {
        "address": "tb1qnltjz527u6pxvcavqwrrja6znyrgd9c2pcl7qu",
        "expires_at": "2025-12-10T11:35:59.175239Z",
        "network": "BTC",
        "type": "address"
      },
      "rate": {
        "product": "BTCNGN",
        "rate": "136593125.15",
        "side": "sell",
        "type": "FIXED",
        "source_currency": "BTC",
        "target_currency": "NGN"
      },
      "reference": "PAYR_oIPbFOXogbjW",
      "source_amount": "0.00036606",
      "status": "expired",
      "target_amount": "50001.27",
      "timeline": {
        "total_steps": 1,
        "current_step": 1,
        "transfer_status": "cancelled",
        "events": [
          {
            "step": 1,
            "done": true,
            "status": "pending",
            "title": "Transfer Started",
            "description": "Waiting for your BTC deposit",
            "timestamp": "2025-12-10T11:36:00.281594Z"
          }
        ]
      },
      "updated_at": "2025-12-10T12:30:51.466553Z",
      "source_currency": "BTC",
      "target_currency": "NGN"
    }
  }
  ```
</Accordion>

***

### payment\_request.failed

**Sent when:** Payment request fails due to an error.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "payment_request.failed",
    "data": {
      "id": "PAYR_failed456",
      "additional_info": {
        "email": "customer@example.com",
        "name": "Jane Smith",
        "phone_number": "+2348098765432",
        "source": "checkout"
      },
      "created_at": "2025-12-09T08:00:00.000000Z",
      "fees": [],
      "merchant_info": {
        "email": "merchant@busha.co",
        "name": "Liverpool"
      },
      "reference": "PAYR_failed456",
      "source": "payment_link",
      "source_amount": "16.91200347",
      "source_id": "ORDER_789",
      "status": "failed",
      "target_amount": "25000",
      "source_currency": "USDT",
      "target_currency": "NGN",
      "error": {
        "code": "insufficient_funds",
        "message": "Customer sent insufficient funds to complete payment"
      },
      "updated_at": "2025-12-09T08:30:00.123456Z"
    }
  }
  ```
</Accordion>

***

### payment\_request.cancelled

**Sent when:** Payment request is manually cancelled by merchant or customer before completion.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "payment_request.cancelled",
    "data": {
      "id": "PAYR_Cancelled456",
      "additional_info": {
        "email": "customer@example.com",
        "name": "Michael Johnson",
        "phone_number": "+2347012345678",
        "source": "email_invoice"
      },
      "created_at": "2025-12-09T07:30:00.000000Z",
      "cancelled_at": "2025-12-09T08:15:00.123456Z",
      "cancellation_reason": "Customer requested cancellation",
      "fees": [],
      "merchant_info": {
        "email": "merchant@busha.co",
        "name": "Liverpool"
      },
      "reference": "PAYR_Cancelled456",
      "source": "payment_link",
      "source_amount": "0.00033",
      "source_id": "INV_2025_001",
      "status": "cancelled",
      "target_amount": "50000",
      "source_currency": "BTC",
      "target_currency": "NGN",
      "rate": {
        "product": "BTCNGN",
        "rate": "151515151.52",
        "side": "sell",
        "type": "FIXED",
        "source_currency": "BTC",
        "target_currency": "NGN"
      },
      "timeline": {
        "total_steps": 0,
        "current_step": 0,
        "transfer_status": "",
        "events": []
      },
      "updated_at": "2025-12-09T08:15:00.123456Z"
    }
  }
  ```
</Accordion>

***

## Ramp Events

Ramp events track on/off-ramp transactions where users convert between fiat and cryptocurrency through Busha's ramp service.

### ramp.transfer.pending

**Sent when:** A ramp transfer (fiat to crypto or crypto to fiat) has been initiated and is awaiting processing.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "ramp.transfer.pending",
    "data": {
      "id": "RAMP_TRF_abc123",
      "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "ramp_type": "on_ramp",
      "source_currency": "NGN",
      "target_currency": "USDT",
      "source_amount": "50000",
      "target_amount": "33.78",
      "status": "pending",
      "rate": {
        "product": "NGNUSDT",
        "rate": "1480.00",
        "side": "buy",
        "type": "FIXED"
      },
      "created_at": "2025-12-09T12:00:00.000000Z"
    }
  }
  ```
</Accordion>

***

### ramp.transfer.completed

**Sent when:** Ramp transfer has been successfully completed and funds have been delivered.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "ramp.transfer.completed",
    "data": {
      "id": "RAMP_TRF_abc123",
      "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "ramp_type": "on_ramp",
      "source_currency": "NGN",
      "target_currency": "USDT",
      "source_amount": "50000",
      "target_amount": "33.78",
      "status": "completed",
      "rate": {
        "product": "NGNUSDT",
        "rate": "1480.00",
        "side": "buy",
        "type": "FIXED"
      },
      "created_at": "2025-12-09T12:00:00.000000Z",
      "completed_at": "2025-12-09T12:05:30.123456Z"
    }
  }
  ```
</Accordion>

***

### ramp.transfer.failed

**Sent when:** Ramp transfer fails due to an error.

<Accordion title="View sample event">
  ```json  theme={null}
  {
    "business_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
    "event": "ramp.transfer.failed",
    "data": {
      "id": "RAMP_TRF_failed789",
      "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
      "ramp_type": "off_ramp",
      "source_currency": "BTC",
      "target_currency": "NGN",
      "source_amount": "0.001",
      "target_amount": "135796.71",
      "status": "failed",
      "error": {
        "code": "bank_account_invalid",
        "message": "Recipient bank account details are invalid"
      },
      "created_at": "2025-12-09T12:00:00.000000Z",
      "failed_at": "2025-12-09T12:03:00.123456Z"
    }
  }
  ```
</Accordion>

***

## Setting Up Webhooks

To configure webhooks in your Busha account and implement webhook handlers, see our [Webhooks Setup Guide](/guides/webhooks/setup) for detailed instructions.

## See Also

* [Verify Customer Identity](/guides/customers/verify-identity) - Learn about customer verification flow
* [Create Individual Customer](/guides/customers/create-individual) - Set up individual customers
* [Create Business Customer](/guides/customers/create-business) - Set up business customers

***



> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Addresses, Banks, and Mobile Money

> Reference for banks, mobile money providers, and crypto addresses in supported countries.

## Banks

We provide all available banks from our currently supported countries with the list of banks API. You can fetch all banks with a simple GET request:

```bash  theme={null}
$ curl https://api.busha.co/v1/banks
```

A **bank account** is held by a bank or financial institution, allowing users to deposit, withdraw, and transfer funds. Bank accounts are integrated to facilitate **fiat currency transactions**, enabling users to move money between their traditional bank and their crypto wallet.

## Bank Account Verification for Payouts

* **Linking a Bank Account:** Users can add and verify their **local bank account** as a destination for payout.

Verify Bank Accounts with the resolve bank account endpoint before initiating a payout:

```bash  theme={null}
$ curl https://api.busha.co/v1/recipients/resolve-bank-account \
  --request POST \
  --header 'Content-Type: application/json' \
  --data '{
  "currency_id": "NGN",
  "country_id": "NG",
  "channel": "mobile_money",
  "bank_code": "000013",
  "account_number": "0123456789"
}'
```

## Addresses

Addresses functions like a bank account number but is specific to cryptocurrencies. Each blockchain network (Bitcoin, Ethereum, Solana, etc.) has its own format for crypto addresses.

For example:

* **Bitcoin (BTC) Address:** `bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf82t9`
* **Ethereum (ETH) Address:** `0x89205A3A3b2A69De6Dbf7f01ED13B2108B2c43e7`

### Generating Wallet Addresses:

* When a user creates an account on Busha, a unique crypto address is assigned to their wallet for each supported cryptocurrency.
* These addresses are generated using blockchain standards like **BIP-44** (for Bitcoin and similar assets) or **ERC-20** (for Ethereum-based tokens).

### Receiving Digital Tokens:

* Users can share their crypto address with others to receive payments or token transfers.
* The sender initiates a transaction by entering the recipient's **crypto address** in their wallet and confirming the transfer.
* The blockchain processes the transaction and updates the balance once confirmed.

### Verifying an Address for Payout:

To specify a cryptocurrency destination address when purchasing crypto via the Busha API, validate the address using a separate API request prior to the purchase transaction. This ensures the address is valid for the selected cryptocurrency and network.

```bash  theme={null}
$ curl https://api.busha.co/v1/validate \
  --request POST \
  --header 'Accept: */*' \
  --header 'Content-Type: application/json' \
  --data '{
  "type": "address",
  "address": "tb1qj4263506wyu8khr22dwce0agk8lhyjgy269rxr",
  "currency": "BTC",
  "network": "BTC",
  "memo": "memo"
}'
```
> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Currency Pairs

> See available conversions between fiat and cryptocurrency.

Pairs in the Busha API represent the exchange rates between two currencies. This can be any of the following exchanges:

* Fiat-to-Fiat
* Fiat-to-Crypto
* Crypto-to-Crypto

Pairs resource allows you to anticipate the following about a currency pair:

* Current buy/sell price
* Minimum and maximum buy/sell amount
* Buy/sell supported
* Floating point decimal for the base and target currency

A single pair object looks like this:

```json  theme={null}
{
  "id": "ETHUSDT",
  "base": "ETH",
  "counter": "USDT",
  "type": "crypto",
  "buy_price": {
    "amount": "2686.63",
    "currency": "ETH"
  },
  "sell_price": {
    "amount": "2686.58",
    "currency": "USDT"
  },
  "is_buy_supported": true,
  "is_sell_supported": true,
  "min_buy_amount": {
    "amount": "0.00447",
    "currency": "ETH"
  }
}
```

## List Pairs

To list all pairs, you can make an unauthenticated request to the Pairs endpoint:

```bash  theme={null}
$ curl https://api.busha.co/v1/pairs
```

## Filter Pairs by Type

The pairs API allows filtering the list of pairs by type. The three allowed filter type values are:

* fiat
* crypto
* stablecoins

Here is an example of a filter by type:

```bash  theme={null}
$ curl https://api.busha.co/v1/pairs?type=crypto
```

## Filter Pairs by Currency

You can also filter pairs by any of the supported currencies on the Busha API.

```bash  theme={null}
$ curl https://api.busha.co/v1/pairs?currency=NGN
```

## Filter by Type and Currency

Combining the two examples above, you can filter for all fiat pairs type for NGN currency like so:

```bash  theme={null}
$ curl https://api.busha.co/v1/pairs?currency=NGN&type=fiat
```
> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Supported Currencies

> Complete list of supported fiat and cryptocurrencies. NGN, KES, USDT, BTC, ETH, and more.

This section provides a categorized overview of the **fiat** and **cryptocurrencies** supported on the Busha platform, indicating their deposit, withdrawal, and ramp capabilities.

## Fiat Currencies

These are the traditional government-issued currencies that Busha supports for various operations.

<table style={{ width: '105%', borderCollapse: 'collapse', overflowX: 'auto' }}>
  <thead>
    <tr>
      <th style={{ width: '12%', textAlign: 'left', padding: '12px' }}>Code</th>
      <th style={{ width: '20%', textAlign: 'left', padding: '12px' }}>Name</th>

      <th style={{ width: '15%', textAlign: 'left', padding: '12px' }}>
        Display Name
      </th>

      <th style={{ width: '13%', textAlign: 'left', padding: '12px' }}>
        Deposit Supported
      </th>

      <th style={{ width: '13%', textAlign: 'left', padding: '12px' }}>
        Withdrawal Supported
      </th>

      <th style={{ width: '13%', textAlign: 'left', padding: '12px' }}>
        Ramp Buy Supported
      </th>

      <th style={{ width: '13%', textAlign: 'left', padding: '12px' }}>
        Ramp Sell Supported
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: '12px' }}>GHS</td>
      <td style={{ padding: '12px' }}>Cedi</td>
      <td style={{ padding: '12px' }}>GHS</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>KES</td>
      <td style={{ padding: '12px' }}>Shilling</td>
      <td style={{ padding: '12px' }}>KES</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>NGN</td>
      <td style={{ padding: '12px' }}>Naira</td>
      <td style={{ padding: '12px' }}>NGN</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>USD</td>
      <td style={{ padding: '12px' }}>US Dollar</td>
      <td style={{ padding: '12px' }}>USD</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>
  </tbody>
</table>

## Cryptocurrencies

These are digital currencies supported on the Busha platform.

<table style={{ width: '105%', borderCollapse: 'collapse', overflowX: 'auto' }}>
  <thead>
    <tr>
      <th style={{ width: '8%', textAlign: 'left', padding: '12px' }}>Code</th>
      <th style={{ width: '15%', textAlign: 'left', padding: '12px' }}>Name</th>

      <th style={{ width: '10%', textAlign: 'left', padding: '12px' }}>
        Display Name
      </th>

      <th style={{ width: '10%', textAlign: 'left', padding: '12px' }}>
        Deposit Supported
      </th>

      <th style={{ width: '12%', textAlign: 'left', padding: '12px' }}>
        Withdrawal Supported
      </th>

      <th style={{ width: '25%', textAlign: 'left', padding: '12px' }}>
        Networks
      </th>

      <th style={{ width: '10%', textAlign: 'left', padding: '12px' }}>
        Ramp Buy Supported
      </th>

      <th style={{ width: '10%', textAlign: 'left', padding: '12px' }}>
        Ramp Sell Supported
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: '12px' }}>ADA</td>
      <td style={{ padding: '12px' }}>Cardano</td>
      <td style={{ padding: '12px' }}>ADA</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>ALICE</td>
      <td style={{ padding: '12px' }}>My Neighbor Alice</td>
      <td style={{ padding: '12px' }}>ALICE</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>BNB</td>
      <td style={{ padding: '12px' }}>Binance Blockchain</td>
      <td style={{ padding: '12px' }}>BNB</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>BNB-BEP20 (BSC)</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>BTC</td>
      <td style={{ padding: '12px' }}>Bitcoin</td>
      <td style={{ padding: '12px' }}>BTC</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>BTC (BTC)</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>BTT</td>
      <td style={{ padding: '12px' }}>BitTorrent</td>
      <td style={{ padding: '12px' }}>BTT</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>CNGN</td>
      <td style={{ padding: '12px' }}>CNGN</td>
      <td style={{ padding: '12px' }}>cNGN</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>DOGE</td>
      <td style={{ padding: '12px' }}>Dogecoin</td>
      <td style={{ padding: '12px' }}>DOGE</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>DOGS</td>
      <td style={{ padding: '12px' }}>Dogs</td>
      <td style={{ padding: '12px' }}>DOGS</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>ETH</td>
      <td style={{ padding: '12px' }}>Ethereum</td>
      <td style={{ padding: '12px' }}>ETH</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>ETH-BASE (BASE), ETH (ETH)</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>LTC</td>
      <td style={{ padding: '12px' }}>Litecoin</td>
      <td style={{ padding: '12px' }}>LTC</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>LTC (LTC)</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>LUNA</td>
      <td style={{ padding: '12px' }}>Terra</td>
      <td style={{ padding: '12px' }}>LUNA</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>MATIC</td>
      <td style={{ padding: '12px' }}>MATIC</td>
      <td style={{ padding: '12px' }}>MATIC</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>MC</td>
      <td style={{ padding: '12px' }}>MC Token</td>
      <td style={{ padding: '12px' }}>MC</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>MC-ERC20 (ETH)</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>NGNT</td>
      <td style={{ padding: '12px' }}>Naira Token</td>
      <td style={{ padding: '12px' }}>NGNT</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>OLE</td>
      <td style={{ padding: '12px' }}>Ole</td>
      <td style={{ padding: '12px' }}>OLE</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>POL</td>
      <td style={{ padding: '12px' }}>Pol</td>
      <td style={{ padding: '12px' }}>POL</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>POL (MATIC)</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>RMT</td>
      <td style={{ padding: '12px' }}>SureRemit</td>
      <td style={{ padding: '12px' }}>RMT</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>SHIB</td>
      <td style={{ padding: '12px' }}>SHIBA INU</td>
      <td style={{ padding: '12px' }}>SHIB</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>SHIB-ERC20 (ETH)</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>SLP</td>
      <td style={{ padding: '12px' }}>Smooth Love Potion</td>
      <td style={{ padding: '12px' }}>SLP</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>SOL</td>
      <td style={{ padding: '12px' }}>Solana</td>
      <td style={{ padding: '12px' }}>SOL</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>SOL (SOL)</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>TON</td>
      <td style={{ padding: '12px' }}>Toncoin</td>
      <td style={{ padding: '12px' }}>TON</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>TON (TON)</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>TRUMP</td>
      <td style={{ padding: '12px' }}>Official Trump</td>
      <td style={{ padding: '12px' }}>TRUMP</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>TRUMP (SOL)</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>TRX</td>
      <td style={{ padding: '12px' }}>Tron</td>
      <td style={{ padding: '12px' }}>TRX</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>TRX (TRX)</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>TSC</td>
      <td style={{ padding: '12px' }}>TestCoin</td>
      <td style={{ padding: '12px' }}>TSC</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>ULD</td>
      <td style={{ padding: '12px' }}>Unlighted</td>
      <td style={{ padding: '12px' }}>ULD</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>N/A</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>USDC</td>
      <td style={{ padding: '12px' }}>USD Coin</td>
      <td style={{ padding: '12px' }}>USDC</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>

      <td style={{ padding: '12px' }}>
        USDC-BASE (BASE), USDC-ERC20 (ETH), USDC-TRC20 (TRX), USDC-XLM (XLM)
      </td>

      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>USDT</td>
      <td style={{ padding: '12px' }}>USD Token</td>
      <td style={{ padding: '12px' }}>USDT</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>

      <td style={{ padding: '12px' }}>
        USDT-BEP20 (BSC), USDT-ERC20 (ETH), USDT-TRC20 (TRX), USDT-XPL(Plasma)
      </td>

      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>XLM</td>
      <td style={{ padding: '12px' }}>Stellar</td>
      <td style={{ padding: '12px' }}>XLM</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>XLM (XLM)</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>

    <tr>
      <td style={{ padding: '12px' }}>XRP</td>
      <td style={{ padding: '12px' }}>Ripple</td>
      <td style={{ padding: '12px' }}>XRP</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>Yes</td>
      <td style={{ padding: '12px' }}>XRP (XRP)</td>
      <td style={{ padding: '12px' }}>No</td>
      <td style={{ padding: '12px' }}>No</td>
    </tr>
  </tbody>
</table>

### Further References

* **[API Reference: Retrieve a Specific Currency](/api-reference/currencies/retrieve-details-of-a-specific-currency)**
* **[API Reference: Retrieve a List of Supported Currencies](/api-reference/currencies/retrieve-supported-currencies)**
> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Transfer Status

> Understand all transfer statuses on Busha.

Understanding the different statuses of your transfers on Busha can help you keep track of your funds. Here's a breakdown of what each status means:

## Core Transfer Statuses

**Pending:** Your transfer has been initiated, but we haven't received the funds yet. Think of this as the first step where you've told us about your transfer.

**Processing:** We've successfully received your funds, and they are now being actively handled. This means we're working on getting your transfer to its next stage.

**Cancelled:** The transfer has been cancelled and will not proceed. This could happen for various reasons, such as user cancellation or if the transfer couldn't be completed.

## Specific Transfer Statuses

**Funds Received** (`funds_received`): This status specifically indicates that we have successfully received your payment for a deposit. For a simple deposit into your Busha account, this is the final step.

**Funds Converted** (`funds_converted`): If your transfer involves currency conversion (e.g., Naira to USD), this status means the conversion has been successfully completed. For transfers that only involve a conversion, this is the final status.

**Outgoing Payment Sent** (`outgoing_payment_sent`): This status indicates that the payment for your transfer has been initiated and is currently being processed on our end, ready to be sent out.

**Funds Delivered** (`funds_delivered`): This is typically the final status for transfers that involve sending funds out (like a payout). It means the transfer is fully completed, and the funds have reached their destination.

## Transfer Completion Flow

Here's how different types of transfers typically conclude:

### Deposits

A deposit will typically stop at **Funds Received**.

<img src="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=a742f792ce64c9b7fd4ab916b3b199f0" alt="Deposit Flow for Busha Transfer" style={{ display: "block", margin: "0 auto" }} data-og-width="1073" width="1073" data-og-height="546" height="546" data-path="images/deposit-flow.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=280&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=83865f4b81fb714b92cb128211df0d98 280w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=560&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=5293baf6efdded36e1ef4a398e151451 560w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=840&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=87d0774adf6b347d1b196def48fc67e4 840w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=1100&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=31b6c255f2908017e8345f7183bf6f73 1100w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=1650&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=c359080d2523b187beefdbc30aac372b 1650w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/deposit-flow.png?w=2500&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=e75c8e0d4f16db3be99ac5b0b283cbbb 2500w" />

<p
  style={{
  textAlign: "center",
  marginTop: "8px",
  fontSize: "14px",
  color: "#6B7280",
}}
>
  Deposit Flow for Busha Transfer
</p>

### Conversions

A conversion will conclude with **Funds Converted** as its final status.

<img src="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=47091c0844c835f35007b2e9cd442d1b" alt="Conversion Flow from currency to currency" style={{ display: "block", margin: "0 auto" }} data-og-width="1073" width="1073" data-og-height="693" height="693" data-path="images/conversion-flow.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=280&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=7cfc4915c512306f29f03ac3aef35853 280w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=560&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=5307cad81e0e844eab7f276a5a70cf5d 560w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=840&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=6287a8f184054718aab0de9ab0f0873b 840w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=1100&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=ad86e8a8dd7cec46777569ae52c52125 1100w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=1650&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=c91132ad6d917f8893c0a37a50220b6a 1650w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/conversion-flow.png?w=2500&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=0629fd18499a92aa135e7efcfdc824ff 2500w" />

<p
  style={{
  textAlign: "center",
  marginTop: "8px",
  fontSize: "14px",
  color: "#6B7280",
}}
>
  Conversion Flow from currency to currency
</p>

### Payouts

A payout or withdrawal will reach its final status at **Funds Delivered**.

<img src="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=2825517d38906beae284d7e5c30dd2e5" alt="Withdrawal Flow on Busha" style={{ display: "block", margin: "0 auto" }} data-og-width="1073" width="1073" data-og-height="693" height="693" data-path="images/withdrawal-flow.png" data-optimize="true" data-opv="3" srcset="https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=280&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=53d321f27e5b5c6d5433497bd6aa642b 280w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=560&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=2854c16bbb8a80c465f4cc054238d3d9 560w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=840&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=ce8dcce1de405b198eb609545fd9df94 840w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=1100&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=1c993039de462840e1aefa99f26e253e 1100w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=1650&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=209926c4a924ac3dbd791d9a154e254d 1650w, https://mintcdn.com/busha-36f167ef/gBxYGMQrF1F-qJM7/images/withdrawal-flow.png?w=2500&fit=max&auto=format&n=gBxYGMQrF1F-qJM7&q=85&s=e19bd2f1cc5a793a03e98464b734d2fc 2500w" />

<p
  style={{
  textAlign: "center",
  marginTop: "8px",
  fontSize: "14px",
  color: "#6B7280",
}}
>
  Withdrawal Flow on Busha
</p>



> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Customer Deposits

> Accept fiat and crypto deposits using temporary bank accounts and addresses.

This example shows you how to accept deposits into your Busha account via fiat (temporary bank accounts) or crypto (deposit addresses).

## Use Cases

* Customer funding their wallet
* Accepting payments from users
* Processing incoming transfers
* Topping up account balances

## Fiat Deposit via Temporary Bank Account

<Steps>
  <Step title="Get a Quote for the Deposit">
    Create a quote to generate a temporary bank account:

    ```bash  theme={null}
        curl -X POST https://api.sandbox.busha.so/v1/quotes \
          -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
          -H "Content-Type: application/json" \
          -d '{
            "source_currency": "NGN",
            "target_currency": "NGN",
            "source_amount": "50000",
            "pay_in": {
              "type": "temporary_bank_account"
            }
          }'
    ```

    **Response:**

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created quote successfully",
      "data": {
        "id": "QUO_vxcF2svmjMbxDp4T5dcD8",
        "profile_id": "BUS_tg6yujbZ1nMu5BLQkPGGO",
        "source_currency": "NGN",
        "target_currency": "NGN",
        "source_amount": "50000",
        "target_amount": "49500",
        "rate": {
          "rate": "1",
          "side": "sell",
          "type": "FIXED",
          "source_currency": "NGN",
          "target_currency": "NGN"
        },
        "fees": [
          {
            "amount": {
              "amount": "500",
              "currency": "NGN"
            },
            "name": "payment gateway fee",
            "type": "FIXED"
          }
        ],
        "pay_in": {
          "type": "temporary_bank_account"
        },
        "status": "pending",
        "expires_at": "2025-02-21T10:46:56.232278869Z",
        "created_at": "2025-02-21T10:16:54.40130914Z",
        "updated_at": "2025-02-21T10:16:54.401309215Z"
      }
    }
    ```
  </Step>

  <Step title="Finalize the Transfer to Get Bank Account Details">
    Use the quote ID to generate the temporary bank account:

    ```bash  theme={null}
        curl -X POST https://api.sandbox.busha.so/v1/transfers \
          -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
          -H "Content-Type: application/json" \
          -d '{
            "quote_id": "QUO_vxcF2svmjMbxDp4T5dcD8"
          }'
    ```

    **Response:**

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created transfer successfully",
      "data": {
        "id": "TRF_LJB2GQb55Cs98LbpqgRMx",
        "profile_id": "BUS_tg6yujbZ1nMu5BLQkPGGO",
        "quote_id": "QUO_vxcF2svmjMbxDp4T5dcD8",
        "source_currency": "NGN",
        "target_currency": "NGN",
        "source_amount": "50000",
        "target_amount": "49500",
        "rate": {
          "rate": "1",
          "side": "sell",
          "type": "FIXED",
          "source_currency": "NGN",
          "target_currency": "NGN"
        },
        "fees": [
          {
            "amount": {
              "amount": "500",
              "currency": "NGN"
            },
            "name": "payment gateway fee",
            "type": "FIXED"
          }
        ],
        "pay_in": {
          "expires_at": "2025-02-21T10:46:56.232278869Z",
          "recipient_details": {
            "account_name": "Payaza(Business 1 Business)",
            "account_number": "7000384620",
            "bank_name": "78 FINANCE COMPANY LIMITED",
            "email": "support@busha.co"
          },
          "type": "temporary_bank_account"
        },
        "status": "pending",
        "created_at": "2025-02-21T10:16:54.40130914Z",
        "updated_at": "2025-02-21T10:16:54.401309215Z"
      }
    }
    ```
  </Step>

  <Step title="Display Bank Details to Customer">
    Show the temporary bank account details to your customer:

    **Account Details:**

    * Bank Name: 78 FINANCE COMPANY LIMITED
    * Account Number: 7000384620
    * Account Name: Payaza(Business 1 Business)
    * Amount to Send: 50,000 NGN
    * Expires: 2025-02-21 at 10:46 AM

    Customer transfers money to this account from their bank app.
  </Step>

  <Step title="Monitor Deposit Status">
    Check the transfer status to confirm when funds are received:

    ```bash  theme={null}
        curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_LJB2GQb55Cs98LbpqgRMx \
          -H "Authorization: Bearer YOUR_SECRET_TOKEN"
    ```

    **Possible Statuses:**

    * `pending` - Awaiting customer's bank transfer
    * `processing` - Funds received, being processed
    * `funds_received` - Successfully credited to your balance
    * `cancelled` - Transfer cancelled
  </Step>
</Steps>

***

## Crypto Deposit via Address

<Steps>
  <Step title="Get a Quote for Crypto Deposit">
    Create a quote to generate a crypto deposit address:

    ```bash  theme={null}
        curl -X POST https://api.sandbox.busha.so/v1/quotes \
          -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
          -H "Content-Type: application/json" \
          -d '{
            "source_currency": "USDT",
            "target_currency": "USDT",
            "source_amount": "100",
            "pay_in": {
              "type": "address",
              "network": "TRX"
            }
          }'
    ```

    **Response:**

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created quote successfully",
      "data": {
        "id": "QUO_GxYibGxTIN5c",
        "profile_id": "BUS_9rDAqqREdmMQcQj3zsRlL",
        "source_currency": "USDT",
        "target_currency": "USDT",
        "source_amount": "100",
        "target_amount": "100",
        "rate": {
          "product": "",
          "rate": "1",
          "side": "sell",
          "type": "FIXED",
          "source_currency": "USDT",
          "target_currency": "USDT"
        },
        "fees": [],
        "pay_in": {
          "network": "TRX",
          "type": "address"
        },
        "pay_out": {
          "type": "balance"
        },
        "reference": "QUO_GxYibGxTIN5c",
        "status": "pending",
        "created_at": "2025-10-30T14:35:16.487382045Z",
        "updated_at": "2025-10-30T14:35:16.487382045Z"
      }
    }
    ```
  </Step>

  <Step title="Create Transfer to Get Deposit Address">
    Use the quote ID to generate the crypto deposit address:

    ```bash  theme={null}
        curl -X POST https://api.sandbox.busha.so/v1/transfers \
          -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
          -H "Content-Type: application/json" \
          -d '{
            "quote_id": "QUO_GxYibGxTIN5c"
          }'
    ```

    **Response:**

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created transfer successfully",
      "data": {
        "id": "TRF_abc123xyz",
        "profile_id": "BUS_9rDAqqREdmMQcQj3zsRlL",
        "quote_id": "QUO_GxYibGxTIN5c",
        "source_currency": "USDT",
        "target_currency": "USDT",
        "source_amount": "100",
        "target_amount": "100",
        "rate": {
          "product": "",
          "rate": "1",
          "side": "sell",
          "type": "FIXED",
          "source_currency": "USDT",
          "target_currency": "USDT"
        },
        "fees": [],
        "pay_in": {
          "type": "address",
          "address": "TXYZabc123def456ghi789jkl",
          "network": "TRX",
          "memo": null
        },
        "status": "pending",
        "created_at": "2025-10-30T14:35:20.123456Z",
        "updated_at": "2025-10-30T14:35:20.123456Z"
      }
    }
    ```
  </Step>

  <Step title="Display Deposit Address to Customer">
    Show the crypto deposit details to your customer:

    **Deposit Instructions:**

    * Network: TRX (Tron)
    * Address: TXYZabc123def456ghi789jkl
    * Asset: USDT
    * Amount: 100 USDT (minimum)

    Customer sends USDT to this address from their wallet.
  </Step>

  <Step title="Monitor Deposit Status">
    Check the transfer status to confirm when crypto is received:

    ```bash  theme={null}
        curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_abc123xyz \
          -H "Authorization: Bearer YOUR_SECRET_TOKEN"
    ```

    **Possible Statuses:**

    * `pending` - Awaiting blockchain confirmation
    * `processing` - Transaction confirmed, processing deposit
    * `funds_received` - Crypto credited to balance
    * `cancelled` - Deposit cancelled
  </Step>
</Steps>

***

## Deposit for a Customer

To process deposits on behalf of a customer, include the customer's profile ID in the header:

```bash  theme={null}
curl -X POST https://api.sandbox.busha.so/v1/quotes \
  -H "X-BU-PROFILE-ID: CUSTOMER_PROFILE_ID" \
  -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "source_currency": "NGN",
    "target_currency": "NGN",
    "source_amount": "50000",
    "pay_in": {
      "type": "temporary_bank_account"
    }
  }'
```

The deposit will be credited to the customer's account instead of your business account.

***

## Supported Networks for Crypto Deposits

* **USDT**: TRX (Tron), ERC20 (Ethereum), BEP20 (BSC), POLYGON
* **BTC**: BTC (Bitcoin)
* **ETH**: ETH (Ethereum)
* **Other cryptos**: Check supported networks via the Pairs API

***

## Important Notes

### Fiat Deposits

* Temporary bank accounts expire after 30 minutes
* Always check the `expires_at` field
* If expired, create a new quote and transfer
* Fees are deducted from the deposited amount

### Crypto Deposits

* Always specify the correct network
* Deposits require blockchain confirmations
* Minimum deposit amounts apply per asset
* Wrong network = lost funds (unrecoverable)

***

## Learn More

* [Process Fiat Deposits Guide](/guides/deposits/process-fiat-deposits) - Detailed fiat deposit guide
* [Process Crypto Deposits Guide](/guides/deposits/process-crypto-deposits) - Detailed crypto deposit guide
* [Understanding Quotes](/overview/quotes) - How quotes work
* [Webhooks Setup](/guides/webhooks/setup) - Monitor deposits in real-time
* [Initiate Transactions on Behalf of Customers](/guides/customers/transactions-on-behalf) - Customer transactions




> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Customer Payouts

> Process crypto-to-fiat payouts to bank accounts and mobile money.

This example shows you how to process payouts from crypto to fiat bank accounts or mobile money wallets.

## Use Cases

* Vendor payments from marketplace
* Salary disbursements in local currency
* Cash out crypto to bank account
* Mobile money withdrawals

## Crypto to Fiat Payout

<Tabs>
  <Tab title="Nigeria (NGN)">
    ### Bank Transfer Payout

    <Steps>
      <Step title="Create a Bank Recipient">
        First, create a recipient for the Nigerian bank account:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/recipients \
                  -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
                  -H "X-BU-VERSION: 2025-07-11" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "currency": "NGN",
                    "country_code": "NG",
                    "type": "ngn_bank",
                    "bank_name": "UNITED BANK FOR AFRICA",
                    "bank_code": "033",
                    "account_number": "2109328188",
                    "account_name": "SOSANYA DICKSON OLUMIDE"
                  }'
        ```

        Save the `recipient_id` from the response.
      </Step>

      <Step title="Create a Payout Quote">
        Create a quote specifying how much crypto to convert and send:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "USDT",
                    "target_currency": "NGN",
                    "source_amount": "100",
                    "pay_out": {
                      "type": "bank_transfer",
                      "recipient_id": "677bbf9c7cf061f23784555a"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_mprvCPMCfm3K2qSnzbWj7",
              "source_currency": "USDT",
              "target_currency": "NGN",
              "source_amount": "100",
              "target_amount": "168876",
              "rate": {
                "product": "USDTNGN",
                "rate": "1690.76",
                "side": "sell",
                "type": "FIXED"
              },
              "fees": [
                {
                  "amount": {
                    "amount": "200",
                    "currency": "NGN"
                  },
                  "name": "Fees",
                  "type": "FIXED"
                }
              ],
              "pay_out": {
                "recipient_details": {
                  "account_name": "SOSANYA DICKSON OLUMIDE",
                  "account_number": "2109328188",
                  "bank_name": "UNITED BANK FOR AFRICA",
                  "country": "NG"
                },
                "recipient_id": "677bbf9c7cf061f23784555a",
                "type": "bank_transfer"
              },
              "expires_at": "2025-02-20T10:58:19.540052923Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Create the Payout Transfer">
        Finalize the payout using the quote ID:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_mprvCPMCfm3K2qSnzbWj7"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_tYZ1y5bmXv4N5IhXSMbWJ",
              "source_currency": "USDT",
              "target_currency": "NGN",
              "source_amount": "100",
              "target_amount": "168876",
              "pay_out": {
                "recipient_details": {
                  "account_name": "SOSANYA DICKSON OLUMIDE",
                  "account_number": "2109328188",
                  "bank_name": "UNITED BANK FOR AFRICA",
                  "country": "NG"
                },
                "type": "bank_transfer"
              },
              "status": "pending"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Monitor Payout Status">
        Check the transfer status to confirm delivery:

        ```bash  theme={null}
                curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_tYZ1y5bmXv4N5IhXSMbWJ \
                  -H "Authorization: Bearer YOUR_SECRET_TOKEN"
        ```

        **Possible Statuses:**

        * `pending` - Payout initiated
        * `processing` - Funds being processed
        * `funds_delivered` - Successfully delivered to bank account
        * `cancelled` - Payout cancelled
      </Step>
    </Steps>
  </Tab>

  <Tab title="Kenya (KES)">
    ### M-Pesa Mobile Money Payout

    <Steps>
      <Step title="Create an M-Pesa Recipient">
        First, create a recipient for the M-Pesa mobile money wallet:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/recipients \
                  -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
                  -H "X-BU-VERSION: 2025-07-11" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "type": "mpesa_mobile_money",
                    "account_name": "Samuel Kiprotich",
                    "phone_number": "254712345678"
                  }'
        ```

        Save the `recipient_id` from the response.
      </Step>

      <Step title="Create a Payout Quote">
        Create a quote specifying how much crypto to convert and send:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "USDT",
                    "target_currency": "KES",
                    "source_amount": "10",
                    "pay_out": {
                      "type": "mobile_money",
                      "recipient_id": "6923ca7f32faa00bb0932c78"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_F5I5r0Mhnrpa",
              "source_currency": "USDT",
              "target_currency": "KES",
              "source_amount": "10",
              "target_amount": "1236.3",
              "rate": {
                "product": "USDTKES",
                "rate": "129.63",
                "side": "sell",
                "type": "FIXED"
              },
              "fees": [
                {
                  "amount": {
                    "amount": "60",
                    "currency": "KES"
                  },
                  "name": "Fees",
                  "type": "TIERED"
                }
              ],
              "pay_out": {
                "recipient_details": {
                  "account_name": "Samuel Kiprotich",
                  "country_code": "KE",
                  "currency": "KES",
                  "phone_number": "254712345678"
                },
                "recipient_id": "6923ca7f32faa00bb0932c78",
                "type": "mobile_money"
              },
              "expires_at": "2025-11-24T03:32:06.233668598Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Create the Payout Transfer">
        Finalize the payout using the quote ID:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_F5I5r0Mhnrpa"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_GbqoYfHekk9f",
              "source_currency": "USDT",
              "target_currency": "KES",
              "source_amount": "10",
              "target_amount": "1236.3",
              "pay_out": {
                "recipient_details": {
                  "account_name": "Samuel Kiprotich",
                  "country_code": "KE",
                  "currency": "KES",
                  "phone_number": "254712345678"
                },
                "type": "mobile_money"
              },
              "status": "pending"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Monitor Payout Status">
        Check the transfer status to confirm delivery:

        ```bash  theme={null}
                curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_GbqoYfHekk9f \
                  -H "Authorization: Bearer YOUR_SECRET_TOKEN"
        ```

        **Possible Statuses:**

        * `pending` - Payout initiated
        * `processing` - Funds being processed
        * `funds_delivered` - Successfully delivered to M-Pesa wallet
        * `cancelled` - Payout cancelled
      </Step>
    </Steps>
  </Tab>
</Tabs>

***

## Payout for a Customer

To process payouts on behalf of a customer, include the customer's profile ID:

```bash  theme={null}
curl -X POST https://api.sandbox.busha.so/v1/quotes \
  -H "X-BU-PROFILE-ID: CUSTOMER_PROFILE_ID" \
  -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "source_currency": "USDT",
    "target_currency": "NGN",
    "source_amount": "100",
    "pay_out": {
      "type": "bank_transfer",
      "recipient_id": "677bbf9c7cf061f23784555a"
    }
  }'
```

The payout will be processed from the customer's balance.

***

## Payout Methods by Country

<table style={{ width: "100%", borderCollapse: "collapse" }}>
  <thead>
    <tr>
      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Country
      </th>

      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Currency
      </th>

      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Method
      </th>

      <th style={{ width: "25%", textAlign: "left", padding: "12px" }}>
        Recipient Type
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td style={{ padding: "12px" }}>Nigeria</td>
      <td style={{ padding: "12px" }}>NGN</td>
      <td style={{ padding: "12px" }}>Bank transfer</td>

      <td style={{ padding: "12px" }}>
        <code>ngn\_bank</code>
      </td>
    </tr>

    <tr>
      <td style={{ padding: "12px" }}>Kenya</td>
      <td style={{ padding: "12px" }}>KES</td>
      <td style={{ padding: "12px" }}>M-Pesa mobile money</td>

      <td style={{ padding: "12px" }}>
        <code>mpesa\_mobile\_money</code>
      </td>
    </tr>
  </tbody>
</table>

***

## Important Notes

* Recipients must be created before payouts
* Quotes expire after 30 minutes
* Fees are deducted from the payout amount
* Use webhooks for real-time status updates
* Bank transfers typically complete within minutes
* Mobile money transfers are usually instant

***

## Learn More

* [Process Payouts Guide](/guides/payouts/process-payouts) - Detailed payout guide
* [Create Recipients](/guides/recipients/create-and-manage) - Recipient management
* [Understanding Quotes](/overview/quotes) - How quotes work
* [Webhooks Setup](/guides/webhooks/setup) - Monitor payouts in real-time
* [Initiate Transactions on Behalf of Customers](/guides/customers/transactions-on-behalf) - Customer transactions


> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Processing Markups

> Add custom markup fees to transactions and generate revenue.

This example shows you how to add custom markup fees on top of Busha's infrastructure costs.

## Use Cases

* Charging service fees on transactions
* Adding profit margins to conversions

## How Markups Work

To add your own fees, use a two-step approach:

1. **Customer Deposit**: Create a quote for the total amount (base + markup) with a `pay_in` object
2. **Actual Conversion**: After funds are received, create a second quote for only the base amount with a `pay_out` object

The difference between what the customer pays and what you spend on the conversion remains in your balance as profit.

***

## Example: Markup on USDT Purchase

A customer wants to buy USDT worth 5,000 NGN. You want to charge a 10% markup (500 NGN profit).

<Steps>
  <Step title="Create Quote for Customer Deposit">
    Charge the customer 5,500 NGN (5,000 NGN + 500 NGN markup):

    ```bash  theme={null}
        curl -X POST https://api.sandbox.busha.so/v1/quotes \
          -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
          -H "Content-Type: application/json" \
          -d '{
            "source_currency": "NGN",
            "target_currency": "NGN",
            "source_amount": "5500",
            "pay_in": {
              "type": "temporary_bank_account"
            }
          }'
    ```

    **Response:**

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created quote successfully",
      "data": {
        "id": "QUO_depositWith5500",
        "source_currency": "NGN",
        "target_currency": "NGN",
        "source_amount": "5500",
        "target_amount": "5500",
        "pay_in": {
          "type": "temporary_bank_account"
        },
        "status": "pending"
      }
    }
    ```
  </Step>

  <Step title="Finalize Deposit Transfer">
    Generate the temporary bank account for customer deposit:

    ```bash  theme={null}
        curl -X POST https://api.sandbox.busha.so/v1/transfers \
          -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
          -H "Content-Type: application/json" \
          -d '{
            "quote_id": "QUO_depositWith5500"
          }'
    ```

    **Response:**

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created transfer successfully",
      "data": {
        "id": "TRF_customerDeposit",
        "quote_id": "QUO_depositWith5500",
        "source_amount": "5500",
        "pay_in": {
          "recipient_details": {
            "account_name": "Payaza(Business 1 Business)",
            "account_number": "7000384620",
            "bank_name": "78 FINANCE COMPANY LIMITED"
          },
          "type": "temporary_bank_account"
        },
        "status": "pending"
      }
    }
    ```

    Customer deposits 5,500 NGN to this bank account.
  </Step>

  <Step title="Monitor Transfer Status">
    If webhooks is setup, you'll receive a notification when the deposit is complete. The customer's funds are now in your balance and ready for conversion.

    ```json  theme={null}
        {
          "event": "transfer.funds_received",
          "data": {
            "transfer_id": "TRF_customerDeposit",
            "source_amount": "5500",
            "source_currency": "NGN",
            "status": "funds_received"
          }
        }
    ```

    Your balance now has 5,500 NGN.
  </Step>

  <Step title="Create Quote for Actual Conversion">
    Convert only 5,000 NGN to USDT (keeping 500 NGN as profit):

    ```bash  theme={null}
        curl -X POST https://api.sandbox.busha.so/v1/quotes \
          -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
          -H "Content-Type: application/json" \
          -d '{
            "source_currency": "NGN",
            "target_currency": "USDT",
            "source_amount": "5000",
            "pay_out": {
              "type": "balance"
            }
          }'
    ```

    **Response:**

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created quote successfully",
      "data": {
        "id": "QUO_actualConversion",
        "source_currency": "NGN",
        "target_currency": "USDT",
        "source_amount": "5000",
        "target_amount": "3.12",
        "rate": {
          "rate": "1602.56",
          "side": "buy",
          "source_currency": "NGN",
          "target_currency": "USDT"
        },
        "pay_out": {
          "type": "balance"
        }
      }
    }
    ```
  </Step>

  <Step title="Execute Conversion Transfer">
    Finalize the conversion:

    ```bash  theme={null}
        curl -X POST https://api.sandbox.busha.so/v1/transfers \
          -H "Authorization: Bearer YOUR_SECRET_TOKEN" \
          -H "Content-Type: application/json" \
          -d '{
            "quote_id": "QUO_actualConversion"
          }'
    ```

    **Result:**

    * Customer receives 3.12 USDT
    * You spent 5,000 NGN from your balance
    * 500 NGN remains in your balance as profit
  </Step>
</Steps>

***

## Example: Sell USDT for NGN with Markup

A customer wants to sell 50 USDT for NGN. You want to charge a 5% markup.

<Steps>
  <Step title="Get a Quote">
    Create a quote to see how much NGN the customer will receive:

    ```bash  theme={null}
        curl -X POST https://api.sandbox.busha.so/v1/quotes \
          -H "Authorization: Bearer YOUR_SECRET_KEY" \
          -H "Content-Type: application/json" \
          -d '{
            "source_currency": "USDT",
            "target_currency": "NGN",
            "source_amount": "50"
          }'
    ```

    **Response:**

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created quote successfully",
      "data": {
        "id": "QUO_8xTKmNpR2vWq",
        "profile_id": "BUS_9rDAqqREdmMQcQj3zsRlL",
        "source_currency": "USDT",
        "target_currency": "NGN",
        "source_amount": "50",
        "target_amount": "74487",
        "rate": {
          "product": "USDTNGN",
          "rate": "1489.74",
          "side": "sell",
          "type": "FIXED",
          "source_currency": "USDT",
          "target_currency": "NGN"
        },
        "fees": [],
        "reference": "QUO_8xTKmNpR2vWq",
        "status": "pending",
        "expires_at": "2025-11-27T14:43:26.906019648Z",
        "created_at": "2025-11-27T14:13:26.905993939Z"
      }
    }
    ```

    **Calculate your markup:**

    * Base amount: ₦74,487
    * 5% markup: ₦3,724
    * Customer receives: ₦70,763 (₦74,487 - ₦3,724)
    * You keep: ₦3,724 as profit
  </Step>

  <Step title="Execute the Sale">
    Use the quote ID to finalize the conversion:

    ```bash  theme={null}
        curl -X POST https://api.sandbox.busha.so/v1/transfers \
          -H "Authorization: Bearer YOUR_SECRET_KEY" \
          -H "Content-Type: application/json" \
          -d '{
            "quote_id": "QUO_8xTKmNpR2vWq"
          }'
    ```

    **Response:**

    ```json  theme={null}
    {
      "status": "success",
      "message": "Created transfer successfully",
      "data": {
        "id": "TRF_2mKpVxNzQwYr",
        "profile_id": "BUS_9rDAqqREdmMQcQj3zsRlL",
        "quote_id": "QUO_8xTKmNpR2vWq",
        "description": "Sold USDT",
        "sub_description": "For NGN",
        "source_currency": "USDT",
        "target_currency": "NGN",
        "source_amount": "50",
        "target_amount": "74487",
        "trade": "sell",
        "rate": {
          "product": "USDTNGN",
          "rate": "1489.74",
          "side": "sell",
          "type": "FIXED"
        },
        "fees": [],
        "status": "pending",
        "created_at": "2025-11-27T14:14:06.134616492Z"
      }
    }
    ```
  </Step>

  <Step title="Apply Your Markup">
    **Your transaction breakdown:**

    * 50 USDT converted to ₦74,487 (from Busha)
    * Customer paid: ₦70,763 (after your 5% markup)
    * Your profit: ₦3,724

    You would then send ₦70,763 to the customer via bank transfer or their preferred payout method, keeping ₦3,724 as your service fee.
  </Step>
</Steps>

***

## Learn More

* [Customer Deposits](/examples/customer-deposits) - Accept fiat and crypto deposits
* [Sell Crypto for Fiat](/examples/crypto-to-fiat) - Sell cryptocurrency for fiat
* [Webhooks Setup](/guides/webhooks/setup) - Monitor transaction status
* [Understanding Quotes](/overview/quotes) - How quotes work


> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Buy Crypto with Fiat

> Buy cryptocurrency with fiat currency using Busha's API.

This example shows you how to buy cryptocurrency (USDT, BTC, etc.) with fiat currency (NGN, KES) using Busha's API.

## Use Cases

* Buy Bitcoin with local currency
* Buy stablecoins for international payments
* Purchase crypto from local currency balance

## Example: Buy Crypto with Fiat

<Tabs>
  <Tab title="NGN to USDT">
    ### Buy USDT with 50,000 NGN via Bank Transfer

    <Steps>
      <Step title="Get a Quote">
        Create a quote with temporary bank account funding:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "NGN",
                    "target_currency": "USDT",
                    "source_amount": "50000",
                    "pay_in": {
                      "type": "temporary_bank_account"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_gCcLjJ0P0uEc",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "source_currency": "NGN",
              "target_currency": "USDT",
              "source_amount": "50000",
              "target_amount": "33.48543819",
              "rate": {
                "product": "USDTNGN",
                "rate": "1490.2",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "NGN",
                "target_currency": "USDT"
              },
              "fees": [
                {
                  "amount": {
                    "amount": "100",
                    "currency": "NGN"
                  },
                  "name": "payment gateway fee",
                  "type": "FIXED"
                }
              ],
              "pay_in": {
                "type": "temporary_bank_account"
              },
              "pay_out": {
                "type": "balance"
              },
              "reference": "QUO_gCcLjJ0P0uEc",
              "status": "pending",
              "expires_at": "2026-01-23T11:34:17.175170741Z",
              "created_at": "2026-01-23T11:04:17.175146315Z",
              "updated_at": "2026-01-23T11:04:17.175146315Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Create the Transfer">
        Use the quote ID to get temporary bank details:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_gCcLjJ0P0uEc"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_JSaOYnl7Sla0",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_gCcLjJ0P0uEc",
              "description": "Bought USDT",
              "sub_description": "With NGN",
              "source_currency": "NGN",
              "target_currency": "USDT",
              "source_amount": "50000",
              "target_amount": "33.48543819",
              "trade": "buy",
              "rate": {
                "product": "USDTNGN",
                "rate": "1490.2",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "NGN",
                "target_currency": "USDT"
              },
              "fees": [
                {
                  "amount": {
                    "amount": "100",
                    "currency": "NGN"
                  },
                  "name": "payment gateway fee",
                  "type": "FIXED"
                }
              ],
              "pay_in": {
                "expires_at": "2026-01-23T11:37:32.289498887Z",
                "recipient_details": {
                  "account_name": "BIBAGE TECHNOLOGIES LTD || Liverppol Business",
                  "account_number": "0327318166",
                  "bank_code": "090614",
                  "bank_name": "Aella Microfinance Bank",
                  "email": "ajibola@busha.co"
                },
                "type": "temporary_bank_account"
              },
              "status": "pending",
              "timeline": {
                "total_steps": 0,
                "current_step": 0,
                "transfer_status": "",
                "events": []
              },
              "created_at": "2026-01-23T11:07:31.197428968Z",
              "updated_at": "2026-01-23T11:07:31.197429043Z"
            }
          }
          ```
        </Accordion>

        **Important:** Transfer exactly ₦50,000 to the provided bank account before the expiration time. The USDT will be credited to your balance once payment is confirmed.
      </Step>

      <Step title="Check Transfer Status">
        Monitor the transfer to confirm the purchase completed successfully:

        ```bash  theme={null}
                curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_JSaOYnl7Sla0 \
                  -H "Authorization: Bearer YOUR_SECRET_KEY"
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Fetched transfer successfully",
            "data": {
              "id": "TRF_JSaOYnl7Sla0",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_gCcLjJ0P0uEc",
              "description": "Bought USDT",
              "sub_description": "With NGN",
              "source_currency": "NGN",
              "target_currency": "USDT",
              "source_amount": "50000",
              "target_amount": "33.48543819",
              "trade": "buy",
              "rate": {
                "product": "USDTNGN",
                "rate": "1490.2",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "NGN",
                "target_currency": "USDT"
              },
              "fees": [
                {
                  "amount": {
                    "amount": "100",
                    "currency": "NGN"
                  },
                  "name": "payment gateway fee",
                  "type": "FIXED"
                }
              ],
              "pay_in": {
                "expires_at": "2026-01-23T11:37:32.289498887Z",
                "recipient_details": {
                  "account_name": "BIBAGE TECHNOLOGIES LTD || Liverppol Business",
                  "account_number": "0327318166",
                  "bank_code": "090614",
                  "bank_name": "Aella Microfinance Bank",
                  "email": "ajibola@busha.co"
                },
                "type": "temporary_bank_account"
              },
              "status": "funds_converted",
              "timeline": {
                "total_steps": 3,
                "current_step": 3,
                "transfer_status": "funds_converted",
                "events": [
                  {
                    "step": 1,
                    "done": true,
                    "status": "pending",
                    "title": "Transfer Started",
                    "description": "Waiting for your NGN payment",
                    "timestamp": "2026-01-23T11:07:34.553579Z"
                  },
                  {
                    "step": 2,
                    "done": true,
                    "status": "funds_received",
                    "title": "Payment Received",
                    "description": "We received your NGN Payment",
                    "timestamp": "2026-01-23T11:07:34.553579Z"
                  },
                  {
                    "step": 3,
                    "done": true,
                    "status": "funds_converted",
                    "title": "Funds Converted",
                    "description": "Your USDT has been added to your wallet.",
                    "timestamp": "2026-01-23T11:07:34.65765Z"
                  }
                ]
              },
              "created_at": "2026-01-23T11:07:31.197428Z",
              "updated_at": "2026-01-23T11:07:35.404245Z"
            }
          }
          ```
        </Accordion>
      </Step>
    </Steps>
  </Tab>

  <Tab title="KES to BTC">
    ### Buy BTC with 1,000 KES

    <Steps>
      <Step title="Get a Quote">
        First, create a quote to see the exchange rate and how much crypto you'll receive:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "KES",
                    "target_currency": "BTC",
                    "source_amount": "1000",
                    "pay_in": {
                      "type": "balance"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_a5D3oflYiz78",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "source_currency": "KES",
              "target_currency": "BTC",
              "source_amount": "1000",
              "target_amount": "0.00008471",
              "rate": {
                "product": "BTCKES",
                "rate": "11803984.7",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "KES",
                "target_currency": "BTC"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "reference": "QUO_a5D3oflYiz78",
              "status": "pending",
              "expires_at": "2025-11-20T16:00:35.039961155Z",
              "created_at": "2025-11-20T15:30:35.039935771Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Create the Transfer">
        Use the quote ID to finalize the purchase:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_a5D3oflYiz78"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_vvTf73YOwNFD",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_a5D3oflYiz78",
              "description": "Bought BTC",
              "sub_description": "With KES",
              "source_currency": "KES",
              "target_currency": "BTC",
              "source_amount": "1000",
              "target_amount": "0.00008471",
              "trade": "buy",
              "rate": {
                "product": "BTCKES",
                "rate": "11803984.7",
                "side": "buy",
                "type": "FIXED"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "pending",
              "timeline": {
                "total_steps": 0,
                "current_step": 0,
                "transfer_status": "",
                "events": []
              },
              "created_at": "2025-11-20T15:33:06.12036014Z",
              "updated_at": "2025-11-20T15:33:06.12036014Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Check Transfer Status">
        Monitor the transfer to confirm the purchase completed successfully:

        ```bash  theme={null}
                curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_vvTf73YOwNFD \
                  -H "Authorization: Bearer YOUR_SECRET_KEY"
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Fetched transfer successfully",
            "data": {
              "id": "TRF_vvTf73YOwNFD",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_a5D3oflYiz78",
              "description": "Bought BTC",
              "sub_description": "With KES",
              "source_currency": "KES",
              "target_currency": "BTC",
              "source_amount": "1000",
              "target_amount": "0.00008471",
              "trade": "buy",
              "rate": {
                "product": "BTCKES",
                "rate": "11803984.7",
                "side": "buy",
                "type": "FIXED"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "funds_converted",
              "timeline": {
                "total_steps": 2,
                "current_step": 2,
                "transfer_status": "funds_converted",
                "events": [
                  {
                    "step": 1,
                    "done": true,
                    "status": "funds_received",
                    "title": "Payment Received",
                    "description": "Funded from Balance"
                  },
                  {
                    "step": 2,
                    "done": true,
                    "status": "funds_converted",
                    "title": "Funds Converted",
                    "description": "Your BTC has been added to your wallet."
                  }
                ]
              },
              "created_at": "2025-11-20T15:33:06.12036Z",
              "updated_at": "2025-11-20T15:33:07.456789Z"
            }
          }
          ```
        </Accordion>
      </Step>
    </Steps>
  </Tab>
</Tabs>

***

## Other Currency Combinations

**Buy ETH with NGN**

```bash  theme={null}
curl -X POST https://api.sandbox.busha.so/v1/quotes \
  -H "Authorization: Bearer YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "source_currency": "NGN",
    "target_currency": "ETH",
    "source_amount": "100000",
    "pay_in": {
      "type": "balance"
    }
  }'
```

**Buy ETH with KES**

```bash  theme={null}
curl -X POST https://api.sandbox.busha.so/v1/quotes \
  -H "Authorization: Bearer YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "source_currency": "KES",
    "target_currency": "ETH",
    "source_amount": "5000",
    "pay_in": {
      "type": "balance"
    }
  }'
```

***

## Learn More

* [Understanding Quotes](/overview/quotes) - Deep dive into how quotes work
* [Create Your First Quote](/guides/quotes/create-first-quote) - Step-by-step guide
* [Supported Currencies](/guides/reference/supported-currencies) - See all available currency pairs
* [Transfer Status Reference](/guides/reference/transfer-status) - All possible transfer statuses


> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# Sell Crypto for Fiat

> Sell cryptocurrency for fiat currency using Busha's API.

This example shows you how to sell cryptocurrency (USDT, BTC, ETH, etc.) for fiat currency (NGN, KES) using Busha's API.

## Use Cases

* Cash out crypto to local bank account
* Pay vendors in local currency from crypto balance
* Sell stablecoins for local currency to cover expenses

## Example: Sell Crypto for Fiat

<Tabs>
  <Tab title="USDT to NGN">
    ### Sell 100 USDT for NGN

    <Steps>
      <Step title="Get a Quote">
        Create a quote to see the exchange rate and how much fiat you'll receive:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "USDT",
                    "target_currency": "NGN",
                    "source_amount": "100",
                    "pay_in": {
                      "type": "address",
                      "network": "TRX"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_KfCZEaXJD10W",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "source_currency": "USDT",
              "target_currency": "NGN",
              "source_amount": "100",
              "target_amount": "147386",
              "rate": {
                "product": "USDTNGN",
                "rate": "1473.86",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USDT",
                "target_currency": "NGN"
              },
              "fees": [],
              "pay_in": {
                "network": "TRX",
                "type": "address"
              },
              "pay_out": {
                "type": "balance"
              },
              "reference": "QUO_KfCZEaXJD10W",
              "status": "pending",
              "expires_at": "2026-01-23T11:35:48.561357552Z",
              "created_at": "2026-01-23T11:05:48.561332107Z",
              "updated_at": "2026-01-23T11:05:48.561332107Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Execute the Sale">
        Use the quote ID to get the crypto deposit address:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_KfCZEaXJD10W"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_6qPeJdrePrj6",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_KfCZEaXJD10W",
              "description": "Sold USDT",
              "sub_description": "For NGN",
              "source_currency": "USDT",
              "target_currency": "NGN",
              "source_amount": "100",
              "target_amount": "147386",
              "trade": "sell",
              "rate": {
                "product": "USDTNGN",
                "rate": "1473.86",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USDT",
                "target_currency": "NGN"
              },
              "fees": [],
              "pay_in": {
                "address": "TDbX2dG8s9fLNg4YBGPsoAHFzzYgY5df57",
                "expires_at": "2026-01-23T12:08:54.983548Z",
                "network": "TRX",
                "type": "address"
              },
              "status": "pending",
              "timeline": {
                "total_steps": 0,
                "current_step": 0,
                "transfer_status": "",
                "events": []
              },
              "created_at": "2026-01-23T11:08:54.912314446Z",
              "updated_at": "2026-01-23T11:08:54.912314516Z"
            }
          }
          ```
        </Accordion>

        **Important:** Send exactly 100 USDT (TRC20) to the address `TDbX2dG8s9fLNg4YBGPsoAHFzzYgY5df57` before the expiration time. The NGN will be credited to your balance once the deposit is confirmed on-chain.
      </Step>

      <Step title="Check Transfer Status">
        Monitor the transfer to confirm the sale completed successfully:

        ```bash  theme={null}
                curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_6qPeJdrePrj6 \
                  -H "Authorization: Bearer YOUR_SECRET_KEY"
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Fetched transfer successfully",
            "data": {
              "id": "TRF_6qPeJdrePrj6",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_KfCZEaXJD10W",
              "description": "Sold USDT",
              "sub_description": "For NGN",
              "source_currency": "USDT",
              "target_currency": "NGN",
              "source_amount": "100",
              "target_amount": "147386",
              "trade": "sell",
              "rate": {
                "product": "USDTNGN",
                "rate": "1473.86",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USDT",
                "target_currency": "NGN"
              },
              "fees": [],
              "pay_in": {
                "address": "TDbX2dG8s9fLNg4YBGPsoAHFzzYgY5df57",
                "expires_at": "2026-01-23T12:08:54.983548Z",
                "network": "TRX",
                "type": "address"
              },
              "status": "pending",
              "timeline": {
                "total_steps": 3,
                "current_step": 1,
                "transfer_status": "pending",
                "events": [
                  {
                    "step": 1,
                    "done": true,
                    "status": "pending",
                    "title": "Transfer Started",
                    "description": "Waiting for your USDT deposit",
                    "timestamp": "2026-01-23T11:08:54.9123Z"
                  },
                  {
                    "step": 2,
                    "done": false,
                    "status": "funds_received",
                    "title": "Crypto Received",
                    "description": "We received your USDT Deposit",
                    "timestamp": "2026-01-23T11:08:54.9123Z"
                  },
                  {
                    "step": 3,
                    "done": false,
                    "status": "funds_converted",
                    "title": "Funds Converted",
                    "description": "Money has been added to your wallet.",
                    "timestamp": "2026-01-23T11:08:54.912309Z"
                  }
                ]
              },
              "created_at": "2026-01-23T11:08:54.912314Z",
              "updated_at": "2026-01-23T11:08:54.912314Z"
            }
          }
          ```
        </Accordion>
      </Step>
    </Steps>
  </Tab>

  <Tab title="USDT to KES">
    ### Sell 10 USDT for KES

    <Steps>
      <Step title="Get a Quote">
        Create a quote to see the exchange rate and how much fiat you'll receive:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "USDT",
                    "target_currency": "KES",
                    "source_amount": "10",
                    "pay_in": {
                      "type": "balance"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_VmvpRemZACRy",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "source_currency": "USDT",
              "target_currency": "KES",
              "source_amount": "10",
              "target_amount": "1305",
              "rate": {
                "product": "USDTKES",
                "rate": "130.5",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USDT",
                "target_currency": "KES"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "reference": "QUO_VmvpRemZACRy",
              "status": "pending",
              "expires_at": "2025-11-20T15:37:26.674151054Z",
              "created_at": "2025-11-20T15:07:26.67398174Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Execute the Sale">
        Use the quote ID to finalize the sale:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_VmvpRemZACRy"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_5d7yGiWuNUZP",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_VmvpRemZACRy",
              "description": "Sold USDT",
              "sub_description": "For KES",
              "source_currency": "USDT",
              "target_currency": "KES",
              "source_amount": "10",
              "target_amount": "1305",
              "trade": "sell",
              "rate": {
                "product": "USDTKES",
                "rate": "130.5",
                "side": "sell",
                "type": "FIXED"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "pending",
              "timeline": {
                "total_steps": 0,
                "current_step": 0,
                "transfer_status": "",
                "events": []
              },
              "created_at": "2025-11-20T15:11:04.27887877Z",
              "updated_at": "2025-11-20T15:11:04.27887877Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Check Transfer Status">
        Monitor the transfer to confirm the sale completed successfully:

        ```bash  theme={null}
                curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_5d7yGiWuNUZP \
                  -H "Authorization: Bearer YOUR_SECRET_KEY"
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Fetched transfer successfully",
            "data": {
              "id": "TRF_5d7yGiWuNUZP",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_VmvpRemZACRy",
              "description": "Sold USDT",
              "sub_description": "For KES",
              "source_currency": "USDT",
              "target_currency": "KES",
              "source_amount": "10",
              "target_amount": "1305",
              "trade": "sell",
              "rate": {
                "product": "USDTKES",
                "rate": "130.5",
                "side": "sell",
                "type": "FIXED"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "funds_converted",
              "timeline": {
                "total_steps": 2,
                "current_step": 2,
                "transfer_status": "funds_converted",
                "events": [
                  {
                    "step": 1,
                    "done": true,
                    "status": "funds_received",
                    "title": "Crypto Received",
                    "description": "Funded from Balance"
                  },
                  {
                    "step": 2,
                    "done": true,
                    "status": "funds_converted",
                    "title": "Funds Converted",
                    "description": "Money has been added to your wallet."
                  }
                ]
              },
              "created_at": "2025-11-20T15:11:04.27887Z",
              "updated_at": "2025-11-20T15:11:05.456789Z"
            }
          }
          ```
        </Accordion>
      </Step>
    </Steps>
  </Tab>
</Tabs>

***

## Other Currency Combinations

**Sell BTC for NGN**

```bash  theme={null}
curl -X POST https://api.sandbox.busha.so/v1/quotes \
  -H "Authorization: Bearer YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "source_currency": "BTC",
    "target_currency": "NGN",
    "source_amount": "0.001",
    "pay_in": {
      "type": "balance"
    }
  }'
```

**Sell ETH for KES**

```bash  theme={null}
curl -X POST https://api.sandbox.busha.so/v1/quotes \
  -H "Authorization: Bearer YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "source_currency": "ETH",
    "target_currency": "KES",
    "source_amount": "0.01",
    "pay_in": {
      "type": "address",
      "network": "ETH"
    }
  }'
```

***

## Learn More

* [Understanding Quotes](/overview/quotes) - Deep dive into how quotes work
* [Process Payouts](/guides/payouts/process-payouts) - Step-by-step payout guide
* [Supported Currencies](/guides/reference/supported-currencies) - See all available currency pairs
* [Transfer Status Reference](/guides/reference/transfer-status) - All possible transfer statuses




> ## Documentation Index
> Fetch the complete documentation index at: https://docs.busha.io/llms.txt
> Use this file to discover all available pages before exploring further.

# USD Conversions

> Buy and sell USD with fiat or cryptocurrency using Busha's API.

This example shows you how to buy and sell USD with either fiat currency or cryptocurrency using Busha's API.

## Use Cases

* Convert local currency to USD
* Convert stablecoins to USD
* Convert USD to local currency
* International payments
* USD savings and liquidity management

***

## Buy USD

### Buy USD with Fiat

<Tabs>
  <Tab title="NGN to USD">
    ### Buy USD with 50,000 NGN via Bank Transfer

    <Steps>
      <Step title="Get a Quote">
        Create a quote with temporary bank account funding:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "NGN",
                    "target_currency": "USD",
                    "source_amount": "50000",
                    "pay_in": {
                      "type": "temporary_bank_account"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_gCcLjJ0P0uEc",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "source_currency": "NGN",
              "target_currency": "USD",
              "source_amount": "50000",
              "target_amount": "33.48543819",
              "rate": {
                "product": "USDNGN",
                "rate": "1490.2",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "NGN",
                "target_currency": "USD"
              },
              "fees": [
                {
                  "amount": {
                    "amount": "100",
                    "currency": "NGN"
                  },
                  "name": "payment gateway fee",
                  "type": "FIXED"
                }
              ],
              "pay_in": {
                "type": "temporary_bank_account"
              },
              "pay_out": {
                "type": "balance"
              },
              "reference": "QUO_gCcLjJ0P0uEc",
              "status": "pending",
              "expires_at": "2026-01-23T11:34:17.175170741Z",
              "created_at": "2026-01-23T11:04:17.175146315Z",
              "updated_at": "2026-01-23T11:04:17.175146315Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Create the Transfer">
        Use the quote ID to get temporary bank details:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_gCcLjJ0P0uEc"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_JSaOYnl7Sla0",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_gCcLjJ0P0uEc",
              "description": "Bought USD",
              "sub_description": "With NGN",
              "source_currency": "NGN",
              "target_currency": "USD",
              "source_amount": "50000",
              "target_amount": "33.48543819",
              "trade": "buy",
              "rate": {
                "product": "USDNGN",
                "rate": "1490.2",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "NGN",
                "target_currency": "USD"
              },
              "fees": [
                {
                  "amount": {
                    "amount": "100",
                    "currency": "NGN"
                  },
                  "name": "payment gateway fee",
                  "type": "FIXED"
                }
              ],
              "pay_in": {
                "expires_at": "2026-01-23T11:37:32.289498887Z",
                "recipient_details": {
                  "account_name": "BIBAGE TECHNOLOGIES LTD || Liverppol Business",
                  "account_number": "0327318166",
                  "bank_code": "090614",
                  "bank_name": "Aella Microfinance Bank",
                  "email": "ajibola@busha.co"
                },
                "type": "temporary_bank_account"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "pending",
              "timeline": {
                "total_steps": 0,
                "current_step": 0,
                "transfer_status": "",
                "events": []
              },
              "created_at": "2026-01-23T11:07:31.197428968Z",
              "updated_at": "2026-01-23T11:07:31.197429043Z"
            }
          }
          ```
        </Accordion>

        **Important:** Transfer exactly ₦50,000 to the provided bank account before the expiration time. The USD will be credited to your balance once payment is confirmed.
      </Step>

      <Step title="Check Transfer Status">
        Monitor the transfer to confirm the purchase completed successfully:

        ```bash  theme={null}
                curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_JSaOYnl7Sla0 \
                  -H "Authorization: Bearer YOUR_SECRET_KEY"
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Fetched transfer successfully",
            "data": {
              "id": "TRF_JSaOYnl7Sla0",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_gCcLjJ0P0uEc",
              "description": "Bought USD",
              "sub_description": "With NGN",
              "source_currency": "NGN",
              "target_currency": "USD",
              "source_amount": "50000",
              "target_amount": "33.48543819",
              "trade": "buy",
              "rate": {
                "product": "USDNGN",
                "rate": "1490.2",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "NGN",
                "target_currency": "USD"
              },
              "fees": [
                {
                  "amount": {
                    "amount": "100",
                    "currency": "NGN"
                  },
                  "name": "payment gateway fee",
                  "type": "FIXED"
                }
              ],
              "pay_in": {
                "expires_at": "2026-01-23T11:37:32.289498887Z",
                "recipient_details": {
                  "account_name": "BIBAGE TECHNOLOGIES LTD || Liverppol Business",
                  "account_number": "0327318166",
                  "bank_code": "090614",
                  "bank_name": "Aella Microfinance Bank",
                  "email": "ajibola@busha.co"
                },
                "type": "temporary_bank_account"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "funds_converted",
              "timeline": {
                "total_steps": 3,
                "current_step": 3,
                "transfer_status": "funds_converted",
                "events": [
                  {
                    "step": 1,
                    "done": true,
                    "status": "pending",
                    "title": "Transfer Started",
                    "description": "Waiting for your NGN payment",
                    "timestamp": "2026-01-23T11:07:34.553579Z"
                  },
                  {
                    "step": 2,
                    "done": true,
                    "status": "funds_received",
                    "title": "Payment Received",
                    "description": "We received your NGN Payment",
                    "timestamp": "2026-01-23T11:07:34.553579Z"
                  },
                  {
                    "step": 3,
                    "done": true,
                    "status": "funds_converted",
                    "title": "Funds Converted",
                    "description": "Your USD has been added to your wallet.",
                    "timestamp": "2026-01-23T11:07:34.65765Z"
                  }
                ]
              },
              "created_at": "2026-01-23T11:07:31.197428Z",
              "updated_at": "2026-01-23T11:07:35.404245Z"
            }
          }
          ```
        </Accordion>
      </Step>
    </Steps>
  </Tab>
</Tabs>

#### Other Examples

**Buy USD with NGN from Balance**

```bash  theme={null}
curl -X POST https://api.sandbox.busha.so/v1/quotes \
  -H "Authorization: Bearer YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "source_currency": "NGN",
    "target_currency": "USD",
    "source_amount": "50000",
    "pay_in": {
      "type": "balance"
    }
  }'
```

***

### Buy USD with Crypto

<Tabs>
  <Tab title="USDT to USD">
    ### Convert 100 USDT to USD from Balance

    <Steps>
      <Step title="Get a Quote">
        Create a quote to see the exchange rate:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "USDT",
                    "target_currency": "USD",
                    "source_amount": "100",
                    "pay_in": {
                      "type": "balance"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_zqGjq5VI4Dyu",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "source_currency": "USDT",
              "target_currency": "USD",
              "source_amount": "100",
              "target_amount": "99.6",
              "rate": {
                "product": "USDTUSD",
                "rate": "0.996",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USDT",
                "target_currency": "USD"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "reference": "QUO_zqGjq5VI4Dyu",
              "status": "pending",
              "expires_at": "2026-01-20T09:48:40.822804422Z",
              "created_at": "2026-01-20T09:18:40.822779472Z",
              "updated_at": "2026-01-20T09:18:40.822779472Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Create the Transfer">
        Use the quote ID to finalize the conversion:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_zqGjq5VI4Dyu"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_O7MHNJocNQaN",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_zqGjq5VI4Dyu",
              "description": "Sold USDT",
              "sub_description": "For USD",
              "source_currency": "USDT",
              "target_currency": "USD",
              "source_amount": "100",
              "target_amount": "99.6",
              "trade": "sell",
              "rate": {
                "product": "USDTUSD",
                "rate": "0.996",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USDT",
                "target_currency": "USD"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "pending",
              "timeline": {
                "total_steps": 0,
                "current_step": 0,
                "transfer_status": "",
                "events": []
              },
              "created_at": "2026-01-20T09:33:01.915628818Z",
              "updated_at": "2026-01-20T09:33:01.915628873Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Check Transfer Status">
        Monitor the transfer to confirm completion:

        ```bash  theme={null}
                curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_O7MHNJocNQaN \
                  -H "Authorization: Bearer YOUR_SECRET_KEY"
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Fetched transfer successfully",
            "data": {
              "id": "TRF_O7MHNJocNQaN",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_zqGjq5VI4Dyu",
              "description": "Sold USDT",
              "sub_description": "For USD",
              "source_currency": "USDT",
              "target_currency": "USD",
              "source_amount": "100",
              "target_amount": "99.6",
              "trade": "sell",
              "rate": {
                "product": "USDTUSD",
                "rate": "0.996",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USDT",
                "target_currency": "USD"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "funds_converted",
              "timeline": {
                "total_steps": 2,
                "current_step": 2,
                "transfer_status": "funds_converted",
                "events": [
                  {
                    "step": 1,
                    "done": true,
                    "status": "funds_received",
                    "title": "Crypto Received",
                    "description": "Funded from Balance"
                  },
                  {
                    "step": 2,
                    "done": true,
                    "status": "funds_converted",
                    "title": "Funds Converted",
                    "description": "Your USD has been added to your wallet."
                  }
                ]
              },
              "created_at": "2026-01-20T09:33:01.915628Z",
              "updated_at": "2026-01-20T09:33:02.456789Z"
            }
          }
          ```
        </Accordion>
      </Step>
    </Steps>
  </Tab>

  <Tab title="USDC to USD">
    ### Convert 100 USDC to USD via Crypto Deposit

    <Steps>
      <Step title="Get a Quote">
        Create a quote with crypto deposit address:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "USDC",
                    "target_currency": "USD",
                    "source_amount": "100",
                    "pay_in": {
                      "type": "address",
                      "network": "TRX"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_7so9hb74wusK",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "source_currency": "USDC",
              "target_currency": "USD",
              "source_amount": "100",
              "target_amount": "99.6",
              "rate": {
                "product": "USDCUSD",
                "rate": "0.996",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USDC",
                "target_currency": "USD"
              },
              "fees": [],
              "pay_in": {
                "network": "TRX",
                "type": "address"
              },
              "pay_out": {
                "type": "balance"
              },
              "reference": "QUO_7so9hb74wusK",
              "status": "pending",
              "expires_at": "2026-01-20T15:38:44.561315603Z",
              "created_at": "2026-01-20T15:08:44.56129334Z",
              "updated_at": "2026-01-20T15:08:44.56129334Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Create the Transfer">
        Use the quote ID to get the deposit address:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_7so9hb74wusK"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_UELotkEEfes6",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_7so9hb74wusK",
              "description": "Sold USDC",
              "sub_description": "For USD",
              "source_currency": "USDC",
              "target_currency": "USD",
              "source_amount": "100",
              "target_amount": "99.6",
              "trade": "sell",
              "rate": {
                "product": "USDCUSD",
                "rate": "0.996",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USDC",
                "target_currency": "USD"
              },
              "fees": [],
              "pay_in": {
                "address": "TTc5HBAEETmqPTarVUU3xLB3fY7b1YxNzm",
                "expires_at": "2026-01-20T16:10:01.640438Z",
                "network": "TRX",
                "type": "address"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "pending",
              "timeline": {
                "total_steps": 0,
                "current_step": 0,
                "transfer_status": "",
                "events": []
              },
              "created_at": "2026-01-20T15:10:01.555002867Z",
              "updated_at": "2026-01-20T15:10:01.555002935Z"
            }
          }
          ```
        </Accordion>

        **Important:** Send exactly 100 USDC (TRC20) to the address `TTc5HBAEETmqPTarVUU3xLB3fY7b1YxNzm` before the expiration time. The USD will be credited to your balance once the deposit is confirmed on-chain.
      </Step>
    </Steps>
  </Tab>
</Tabs>

#### Other Examples

**Buy USD with USDC from Balance**

```bash  theme={null}
curl -X POST https://api.sandbox.busha.so/v1/quotes \
  -H "Authorization: Bearer YOUR_SECRET_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "source_currency": "USDC",
    "target_currency": "USD",
    "source_amount": "100",
    "pay_in": {
      "type": "balance"
    }
  }'
```

***

## Sell USD

### Sell USD for Fiat

<Tabs>
  <Tab title="USD to NGN">
    ### Convert 50 USD to NGN

    <Steps>
      <Step title="Get a Quote">
        Create a quote to see the exchange rate:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "USD",
                    "target_currency": "NGN",
                    "source_amount": "50",
                    "pay_in": {
                      "type": "balance"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_6T9lPmqMSPPt",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "source_currency": "USD",
              "target_currency": "NGN",
              "source_amount": "50",
              "target_amount": "80363.5",
              "rate": {
                "product": "USDNGN",
                "rate": "1607.27",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USD",
                "target_currency": "NGN"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "reference": "QUO_6T9lPmqMSPPt",
              "status": "pending",
              "expires_at": "2026-01-20T09:56:01.045425406Z",
              "created_at": "2026-01-20T09:26:01.045403632Z",
              "updated_at": "2026-01-20T09:26:01.045403632Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Create the Transfer">
        Use the quote ID to finalize the conversion:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_6T9lPmqMSPPt"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_qaZ4QmI5ahSw",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_6T9lPmqMSPPt",
              "description": "Sold USD",
              "sub_description": "For NGN",
              "source_currency": "USD",
              "target_currency": "NGN",
              "source_amount": "50",
              "target_amount": "80363.5",
              "trade": "sell",
              "rate": {
                "product": "USDNGN",
                "rate": "1607.27",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USD",
                "target_currency": "NGN"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "pending",
              "timeline": {
                "total_steps": 0,
                "current_step": 0,
                "transfer_status": "",
                "events": []
              },
              "created_at": "2026-01-20T09:33:40.957016939Z",
              "updated_at": "2026-01-20T09:33:40.957017017Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Check Transfer Status">
        Monitor the transfer to confirm completion:

        ```bash  theme={null}
                curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_qaZ4QmI5ahSw \
                  -H "Authorization: Bearer YOUR_SECRET_KEY"
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Fetched transfer successfully",
            "data": {
              "id": "TRF_qaZ4QmI5ahSw",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_6T9lPmqMSPPt",
              "description": "Sold USD",
              "sub_description": "For NGN",
              "source_currency": "USD",
              "target_currency": "NGN",
              "source_amount": "50",
              "target_amount": "80363.5",
              "trade": "sell",
              "rate": {
                "product": "USDNGN",
                "rate": "1607.27",
                "side": "sell",
                "type": "FIXED",
                "source_currency": "USD",
                "target_currency": "NGN"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "funds_converted",
              "timeline": {
                "total_steps": 2,
                "current_step": 2,
                "transfer_status": "funds_converted",
                "events": [
                  {
                    "step": 1,
                    "done": true,
                    "status": "funds_received",
                    "title": "Payment Received",
                    "description": "Funded from Balance"
                  },
                  {
                    "step": 2,
                    "done": true,
                    "status": "funds_converted",
                    "title": "Funds Converted",
                    "description": "Your NGN has been added to your wallet."
                  }
                ]
              },
              "created_at": "2026-01-20T09:33:40.957016Z",
              "updated_at": "2026-01-20T09:33:42.123456Z"
            }
          }
          ```
        </Accordion>
      </Step>
    </Steps>
  </Tab>
</Tabs>

***

### Sell USD for Crypto

<Tabs>
  <Tab title="USD to USDT">
    ### Convert 50 USD to USDT

    <Steps>
      <Step title="Get a Quote">
        Create a quote to see the exchange rate:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "USD",
                    "target_currency": "USDT",
                    "source_amount": "50",
                    "pay_in": {
                      "type": "balance"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_g0k6EIlv4ZH6",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "source_currency": "USD",
              "target_currency": "USDT",
              "source_amount": "50",
              "target_amount": "49.800796",
              "rate": {
                "product": "USDTUSD",
                "rate": "1.004",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "USD",
                "target_currency": "USDT"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "reference": "QUO_g0k6EIlv4ZH6",
              "status": "pending",
              "expires_at": "2026-01-20T10:00:31.485131911Z",
              "created_at": "2026-01-20T09:30:31.485109051Z",
              "updated_at": "2026-01-20T09:30:31.485109051Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Create the Transfer">
        Use the quote ID to finalize the conversion:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_g0k6EIlv4ZH6"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_4vpxeM3IzcuV",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_g0k6EIlv4ZH6",
              "description": "Bought USDT",
              "sub_description": "With USD",
              "source_currency": "USD",
              "target_currency": "USDT",
              "source_amount": "50",
              "target_amount": "49.800796",
              "trade": "buy",
              "rate": {
                "product": "USDTUSD",
                "rate": "1.004",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "USD",
                "target_currency": "USDT"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "pending",
              "timeline": {
                "total_steps": 0,
                "current_step": 0,
                "transfer_status": "",
                "events": []
              },
              "created_at": "2026-01-20T09:34:13.772974486Z",
              "updated_at": "2026-01-20T09:34:13.772974539Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Check Transfer Status">
        Monitor the transfer to confirm completion:

        ```bash  theme={null}
                curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_4vpxeM3IzcuV \
                  -H "Authorization: Bearer YOUR_SECRET_KEY"
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Fetched transfer successfully",
            "data": {
              "id": "TRF_4vpxeM3IzcuV",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_g0k6EIlv4ZH6",
              "description": "Bought USDT",
              "sub_description": "With USD",
              "source_currency": "USD",
              "target_currency": "USDT",
              "source_amount": "50",
              "target_amount": "49.800796",
              "trade": "buy",
              "rate": {
                "product": "USDTUSD",
                "rate": "1.004",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "USD",
                "target_currency": "USDT"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "funds_converted",
              "timeline": {
                "total_steps": 2,
                "current_step": 2,
                "transfer_status": "funds_converted",
                "events": [
                  {
                    "step": 1,
                    "done": true,
                    "status": "funds_received",
                    "title": "Payment Received",
                    "description": "Funded from Balance"
                  },
                  {
                    "step": 2,
                    "done": true,
                    "status": "funds_converted",
                    "title": "Funds Converted",
                    "description": "Your USDT has been added to your wallet."
                  }
                ]
              },
              "created_at": "2026-01-20T09:34:13.772974Z",
              "updated_at": "2026-01-20T09:34:15.456789Z"
            }
          }
          ```
        </Accordion>
      </Step>
    </Steps>
  </Tab>

  <Tab title="USD to USDC">
    ### Convert 50 USD to USDC

    <Steps>
      <Step title="Get a Quote">
        Create a quote to see the exchange rate:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/quotes \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "source_currency": "USD",
                    "target_currency": "USDC",
                    "source_amount": "50",
                    "pay_in": {
                      "type": "balance"
                    }
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created quote successfully",
            "data": {
              "id": "QUO_HAouYZS608yv",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "source_currency": "USD",
              "target_currency": "USDC",
              "source_amount": "50",
              "target_amount": "49.800796",
              "rate": {
                "product": "USDCUSD",
                "rate": "1.004",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "USD",
                "target_currency": "USDC"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "reference": "QUO_HAouYZS608yv",
              "status": "pending",
              "expires_at": "2026-01-20T10:00:55.785832316Z",
              "created_at": "2026-01-20T09:30:55.785806405Z",
              "updated_at": "2026-01-20T09:30:55.785806405Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Create the Transfer">
        Use the quote ID to finalize the conversion:

        ```bash  theme={null}
                curl -X POST https://api.sandbox.busha.so/v1/transfers \
                  -H "Authorization: Bearer YOUR_SECRET_KEY" \
                  -H "Content-Type: application/json" \
                  -d '{
                    "quote_id": "QUO_HAouYZS608yv"
                  }'
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Created transfer successfully",
            "data": {
              "id": "TRF_5ALpSQdyQlpO",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_HAouYZS608yv",
              "description": "Bought USDC",
              "sub_description": "With USD",
              "source_currency": "USD",
              "target_currency": "USDC",
              "source_amount": "50",
              "target_amount": "49.800796",
              "trade": "buy",
              "rate": {
                "product": "USDCUSD",
                "rate": "1.004",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "USD",
                "target_currency": "USDC"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "pending",
              "timeline": {
                "total_steps": 0,
                "current_step": 0,
                "transfer_status": "",
                "events": []
              },
              "created_at": "2026-01-20T09:34:50.233122674Z",
              "updated_at": "2026-01-20T09:34:50.233122727Z"
            }
          }
          ```
        </Accordion>
      </Step>

      <Step title="Check Transfer Status">
        Monitor the transfer to confirm completion:

        ```bash  theme={null}
                curl -X GET https://api.sandbox.busha.so/v1/transfers/TRF_5ALpSQdyQlpO \
                  -H "Authorization: Bearer YOUR_SECRET_KEY"
        ```

        <Accordion title="View Response">
          ```json  theme={null}
          {
            "status": "success",
            "message": "Fetched transfer successfully",
            "data": {
              "id": "TRF_5ALpSQdyQlpO",
              "profile_id": "BUS_CQr0jPzGGzmn1uW5W7OVs",
              "quote_id": "QUO_HAouYZS608yv",
              "description": "Bought USDC",
              "sub_description": "With USD",
              "source_currency": "USD",
              "target_currency": "USDC",
              "source_amount": "50",
              "target_amount": "49.800796",
              "trade": "buy",
              "rate": {
                "product": "USDCUSD",
                "rate": "1.004",
                "side": "buy",
                "type": "FIXED",
                "source_currency": "USD",
                "target_currency": "USDC"
              },
              "fees": [],
              "pay_in": {
                "type": "balance"
              },
              "pay_out": {
                "type": "balance"
              },
              "status": "funds_converted",
              "timeline": {
                "total_steps": 2,
                "current_step": 2,
                "transfer_status": "funds_converted",
                "events": [
                  {
                    "step": 1,
                    "done": true,
                    "status": "funds_received",
                    "title": "Payment Received",
                    "description": "Funded from Balance"
                  },
                  {
                    "step": 2,
                    "done": true,
                    "status": "funds_converted",
                    "title": "Funds Converted",
                    "description": "Your USDC has been added to your wallet."
                  }
                ]
              },
              "created_at": "2026-01-20T09:34:50.233122Z",
              "updated_at": "2026-01-20T09:34:51.789456Z"
            }
          }
          ```
        </Accordion>
      </Step>
    </Steps>
  </Tab>
</Tabs>

***

## Learn More

* [Understanding Quotes](/overview/quotes) - Deep dive into how quotes work
* [Create Your First Quote](/guides/quotes/create-first-quote) - Step-by-step guide
* [Supported Currencies](/guides/reference/supported-currencies) - See all available currency pairs
* [Transfer Status Reference](/guides/reference/transfer-status) - All possible transfer statuses
