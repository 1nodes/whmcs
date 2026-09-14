# 1nodes WHMCS Gateway

Cryptocurrency payment gateway for WHMCS powered by 1nodes.

## Requirements

* WHMCS
* PHP 8.1+
* A <a href="https://1nodes.com/">1nodes</a> merchant account

## Features

* Cryptocurrency payments for WHMCS
* Automatic payment verification
* Callback support
* Automatic invoice status updates
* Secure callback signature verification using HMAC-SHA256
* Payment status checking
* Support for asynchronous payment confirmation

## Installation

1. Download or clone this repository.

2. Upload the following files to your WHMCS installation:

   ```text
   modules/gateways/onenodes.php
   modules/gateways/callback/onenodes.php
   includes/hooks/onenodes_status_checker.php
   ```

3. Make sure the final structure is:
   
   ```text
   WHMCS/
   ├── modules/
   │   └── gateways/
   │       ├── onenodes.php
   │       └── callback/
   │           └── onenodes.php
   |           └── onenodes_check_status.php
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

Your merchant credentials are available from your 1nodes merchant dashboard.

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

The **Secret Key** must be kept private and must never be exposed publicly or committed to source control.

## License

Proprietary software. All rights reserved.
