# 1nodes WHMCS Gateway

Cryptocurrency payment gateway for WHMCS powered by [1nodes](https://1nodes.com/).

Accept crypto payments through the standard 1nodes gateway or use the non-custodial option to receive payments directly to your own wallet.

## Requirements

* WHMCS
* PHP 8.1+
* A [1nodes](https://1nodes.com/) merchant account

## Features

* Cryptocurrency payments for WHMCS
* Automatic payment verification
* Callback support
* Automatic WHMCS invoice status updates
* Standard percentage-based payment option
* Non-custodial payment option
* Direct-to-wallet payments using XPUB
* Monthly subscription option for non-custodial payments
* Secure callback signature verification using HMAC-SHA256
* Payment status checking
* Support for asynchronous payment confirmation

## Payment Options

1nodes gives merchants two ways to accept cryptocurrency payments.

### Standard Gateway

Use the standard 1nodes payment gateway with percentage-based transaction fees. Payments are processed through 1nodes and automatically verified, while the corresponding WHMCS invoice is updated when the payment is confirmed.

### Non-Custodial Payments

The non-custodial option is designed for merchants who prefer to receive crypto directly in their own wallet.

Using XPUB, payment addresses can be generated for customer transactions without giving 1nodes access to your private keys. Payments go directly to the merchant's wallet rather than being held by 1nodes.

This option uses a monthly subscription instead of percentage-based transaction fees.

> **Important:** Never share your wallet seed phrase or private keys. 1nodes does not need them to process non-custodial payments.

## Installation

1. Download or clone this repository.

2. Upload the following files to your WHMCS installation:

   ```text
   modules/gateways/onenodes.php
   modules/gateways/callback/onenodes.php
   modules/gateways/callback/onenodes_check_status.php
   includes/hooks/onenodes_status_checker.php
   ```

3. Make sure the final structure is:

   ```text
   WHMCS/
   ├── modules/
   │   └── gateways/
   │       ├── onenodes.php
   │       └── callback/
   │           ├── onenodes.php
   │           └── onenodes_check_status.php
   │
   └── includes/
       └── hooks/
           └── onenodes_status_checker.php
   ```

4. Log in to the WHMCS administrator area.

5. Go to:

   **Configuration → System Settings → Payment Gateways**

6. Activate **1nodes**.

7. Configure your 1nodes merchant credentials.

## Configuration

After activating the gateway, configure the required credentials in:

**Configuration → System Settings → Payment Gateways → 1nodes**

The required credentials are:

* **Merchant Key**
* **Secret Key**

Your merchant credentials are available from your [1nodes merchant dashboard](https://1nodes.com/).

The payment model and non-custodial settings, when available for your account, are managed through your 1nodes account.

## Payment Flow

When a customer selects 1nodes to pay a WHMCS invoice:

1. WHMCS creates the payment request through the 1nodes gateway.
2. The customer is provided with the cryptocurrency payment details.
3. The payment is monitored and verified.
4. 1nodes sends a signed callback notification when the payment is confirmed.
5. The gateway verifies the callback signature.
6. The corresponding WHMCS invoice is updated automatically.

If confirmation is still pending, the included payment status checker can continue checking the payment status asynchronously.

For non-custodial payments, funds are sent directly to the merchant's wallet using addresses derived through the XPUB-based payment setup.

## Non-Custodial Payments and XPUB

The non-custodial option allows merchants to receive supported cryptocurrency payments directly to their own wallet.

Payment addresses are generated using an XPUB-based setup. This allows 1nodes to handle payment detection and verification without requiring access to the merchant's private keys.

With non-custodial payments:

* Payments go directly to the merchant's wallet addresses.
* 1nodes does not hold the merchant's funds.
* The merchant keeps control of their private keys.
* Private keys and wallet recovery phrases are not required by the WHMCS gateway.
* Payment verification and WHMCS invoice updates are handled automatically.

Never provide your private key or wallet recovery phrase to 1nodes or enter them into the WHMCS gateway.

## Callback

1nodes uses a callback endpoint to notify WHMCS when a cryptocurrency payment has been successfully completed.

The callback endpoint is:

```text
https://your-whmcs-domain.com/modules/gateways/callback/onenodes.php
```

Callback requests are authenticated using an **HMAC-SHA256** signature generated with your **Secret Key**.

The signature is verified before processing the callback to ensure that payment status updates originate from 1nodes and have not been tampered with.

## Payment Status

After receiving a successful payment notification, the gateway automatically updates the corresponding WHMCS invoice status.

The included status checker also supports asynchronous payment confirmation when the payment status has not yet been finalized.

## Security

All callback requests are verified using **HMAC-SHA256** before any payment status update is processed.

The **Merchant Key** and **Secret Key** should be kept private and must never be exposed publicly or committed to source control.

For non-custodial payments, 1nodes does not require access to your wallet private keys or recovery phrase.

Never share your wallet seed phrase or private keys.

## Support

For help with installation, configuration, or payments, visit [1nodes.com](https://1nodes.com/).

## License

Proprietary software. All rights reserved.
