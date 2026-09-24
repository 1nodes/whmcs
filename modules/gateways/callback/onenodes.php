<?php

/**
 * 1nodes Payment Gateway Callback / Webhook Handler
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

use WHMCS\Database\Capsule;

header('Content-Type: application/json; charset=utf-8');

/**
 * Send JSON response and stop execution.
 */
function onenodes_json_reply(array $data, int $httpCode = 200): void
{
    http_response_code($httpCode);

    echo json_encode(
        $data,
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Gateway
|--------------------------------------------------------------------------
*/

$gatewayModuleName = 'onenodes';

$gatewayParams = getGatewayVariables($gatewayModuleName);

if (empty($gatewayParams['type'])) {
    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Module Not Activated',
    ], 500);
}


/*
|--------------------------------------------------------------------------
| HTTP Method
|--------------------------------------------------------------------------
*/

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Method not allowed',
    ], 405);
}


/*
|--------------------------------------------------------------------------
| Raw Body
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Signature MUST be calculated from the exact raw body.
|
*/

$rawInput = file_get_contents('php://input');

if ($rawInput === false || trim($rawInput) === '') {
    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Empty request body',
    ], 400);
}


/*
|--------------------------------------------------------------------------
| Secret Key
|--------------------------------------------------------------------------
*/

$secretKey = trim( (string) ($gatewayParams['secret_key'] ?? '') );

if ($secretKey === '') {
    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Webhook secret not configured',
    ], 500);
}


/*
|--------------------------------------------------------------------------
| Webhook Signature
|--------------------------------------------------------------------------
*/

$signature = '';

if (!empty($_SERVER['HTTP_X_WEBHOOK_SIGNATURE'])) {
    $signature = trim( (string) $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] );
}

if ($signature === '' && function_exists('getallheaders')) {

    $headers = getallheaders();

    foreach ($headers as $headerName => $headerValue) {

        if (
            strtolower((string) $headerName)
            === 'x-webhook-signature'
        ) {
            $signature = trim((string) $headerValue);
            break;
        }
    }
}

if ($signature === '') {
    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Missing signature',
    ], 400);
}


/*
|--------------------------------------------------------------------------
| Verify Signature
|--------------------------------------------------------------------------
*/

$expectedSignature = hash_hmac(
    'sha256',
    $rawInput,
    $secretKey
);

if ( ! hash_equals($expectedSignature, $signature) ) {

    logTransaction(
        $gatewayParams['name'],
        [
            'received_signature' => $signature,
            'payload'            => $rawInput,
        ],
        'Invalid Webhook Signature'
    );

    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Invalid signature',
    ], 401);
}



/*
|--------------------------------------------------------------------------
| Decode JSON
|--------------------------------------------------------------------------
*/

$data = json_decode( $rawInput, true);

if (!is_array($data)) {
    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Invalid JSON',
    ], 400);
}

/*
|--------------------------------------------------------------------------
| Validate Gateway
|--------------------------------------------------------------------------
*/

if (($data['gateway'] ?? '') !== '1nodes') {

    logTransaction($gatewayParams['name'], $data, 'Invalid gateway value');

    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Invalid gateway',
    ], 400);
}


/*
|--------------------------------------------------------------------------
| Validate Status
|--------------------------------------------------------------------------
*/

$status = strtolower( (string) ($data['status'] ?? '') );

if ($status !== 'paid') {

    logTransaction( $gatewayParams['name'], $data, 'Ignored webhook status: ' . $status);

    onenodes_json_reply([
        'status'  => 'success',
        'message' => 'Ignored',
    ], 200);
}


/*
|--------------------------------------------------------------------------
| Invoice ID
|--------------------------------------------------------------------------
*/

$invoiceId = (int) ($data['order_id'] ?? 0);

if ($invoiceId <= 0) {
    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Missing order_id',
    ], 400);
}


/*
|--------------------------------------------------------------------------
| Validate Invoice ID
|--------------------------------------------------------------------------
*/

checkCbInvoiceID( $invoiceId, $gatewayParams['name'] );


/*
|--------------------------------------------------------------------------
| Get Invoice
|--------------------------------------------------------------------------
*/

try {

    $invoice = \WHMCS\Billing\Invoice::find($invoiceId);

    $invoiceBalance = $invoice
        ? (float) $invoice->balance
        : null;

    if (
        $invoiceBalance === null
        || !is_finite($invoiceBalance)
        || $invoiceBalance <= 0
    ) {
        onenodes_json_reply([
            'status'  => 'success',
            'message' => 'Invoice has no payable balance',
        ], 200);
    }

} catch (\Throwable $exception) {

    logTransaction(
        $gatewayParams['name'],
        [
            'invoice_id' => $invoiceId,
            'error'      => $exception->getMessage(),
            'payload'    => $data,
        ],
        'Invoice lookup failed'
    );

    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Database error',
    ], 500);
}


if (!$invoice) {

    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Invoice not found',
    ], 404);
}


/*
|--------------------------------------------------------------------------
| Already Paid
|--------------------------------------------------------------------------
*/

if (strtolower((string) $invoice->status) === 'paid') {
    logTransaction(
        $gatewayParams['name'],
        [
            'invoice_id' => $invoiceId,
            'status'     => $invoice->status,
            'payload'    => $data,
        ],
        'Invoice already paid'
    );

    onenodes_json_reply([
        'status'  => 'success',
        'message' => 'Already processed',
    ], 200);
}


/*
|--------------------------------------------------------------------------
| Transaction ID
|--------------------------------------------------------------------------
*/

$transactionId = trim( (string) ($data['payment_id'] ?? '') );

if ($transactionId === '') {
    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Missing payment_id',
    ], 400);
}



/*
|--------------------------------------------------------------------------
| Prevent Duplicate Transaction
|--------------------------------------------------------------------------
*/


checkCbTransID($transactionId);

/*
|--------------------------------------------------------------------------
| WHMCS Amount
|--------------------------------------------------------------------------
|
| 1nodes sends a webhook only after the payment is fully paid.
| Therefore fiat_amount represents the amount of this 1nodes payment.
|
*/
$paymentAmount = $data['fiat_amount'] ?? null;

if (
    $paymentAmount === null
    || !is_numeric($paymentAmount)
    || !is_finite((float) $paymentAmount)
    || (float) $paymentAmount <= 0
) {
    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Invalid payment amount',
    ], 400);
}

$paymentAmount = (float) $paymentAmount;

if (
    $invoiceBalance === null
    || !is_finite($invoiceBalance)
    || $invoiceBalance <= 0
) {
    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Invoice has no payable balance',
    ], 409);
}

if ($paymentAmount > $invoiceBalance) {

    logTransaction(
        $gatewayParams['name'],
        [
            'invoice_id'      => $invoiceId,
            'transaction_id'  => $transactionId,
            'fiat_amount'     => $paymentAmount,
            'invoice_balance' => $invoiceBalance,
            'payload'         => $data,
        ],
        'Payment amount exceeds invoice balance'
    );

    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Payment amount exceeds invoice balance',
    ], 409);
}

$whmcsAmount = number_format(
    $paymentAmount,
    8,
    '.',
    ''
);

/*
|--------------------------------------------------------------------------
| Log Webhook
|--------------------------------------------------------------------------
*/

logTransaction(
    $gatewayParams['name'],
    [
        'invoice_id'        => $invoiceId,
        'transaction_id'    => $transactionId,
        'payment_id'        => $data['payment_id'] ?? '',
        'asset'             => $data['asset'] ?? '',
        'crypto_amount'     => $data['total_paid'] ?? '',
        'merchant_received' => $data['merchant_received'] ?? '',
        'overpaid_amount'   => $data['overpaid_amount'] ?? '',
        'remaining_amount'  => $data['remaining_amount'] ?? '',
        'tax'               => $data['tax'] ?? '',
        'paid_at'           => $data['paid_at'] ?? '',
        'payment_state'     => $data['payment_state'] ?? '',
        'tx_ids'            => $data['tx_ids'] ?? [],
        'whmcs_amount'      => $whmcsAmount,
        'fiat_amount'       => $data['fiat_amount'] ?? '',
        'whmcs_invoice_balance' => $invoiceBalance,
    ],
    'Webhook Received'
);


/*
|--------------------------------------------------------------------------
| Mark Invoice As Paid
|--------------------------------------------------------------------------
*/


try {
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $whmcsAmount,
        0.00,
        $gatewayModuleName
    );
} catch (\Throwable $exception) {
    logTransaction(
        $gatewayParams['name'],
        [
            'invoice_id'     => $invoiceId,
            'transaction_id' => $transactionId,
            'error'          => $exception->getMessage(),
            'payload'        => $data,
        ],
        'Payment registration failed'
    );

    onenodes_json_reply([
        'status'  => 'error',
        'message' => 'Payment registration failed',
    ], 500);
}


try {

    $txIds = !empty($data['tx_ids']) && is_array($data['tx_ids'])
        ? implode(', ', array_map('strval', $data['tx_ids']))
        : '';


    $invoiceNote = sprintf(
        "1nodes Payment\nPayment ID: %s\nAsset: %s\nAmount Paid: %s\nTransaction ID(s): %s",
        $data['payment_id'] ?? '',
        $data['asset'] ?? '',
        $data['total_paid'] ?? '',
        $txIds
    );



    $existingNotes = trim((string) ($invoice->notes ?? ''));

    $newNotes = $existingNotes !== ''
        ? ($existingNotes . "\n\n" . $invoiceNote)
        : $invoiceNote;

    Capsule::table('tblinvoices')
        ->where('id', $invoiceId)
        ->update([
            'notes' => $newNotes,
        ]);

    Capsule::table('tblaccounts')
        ->where('invoiceid', $invoiceId)
        ->where('transid', $transactionId)
        ->update([
            'description' => sprintf(
                '1nodes Crypto (%s) | TxIDs: %s',
                $data['asset'] ?? 'CRYPTO',
                $txIds
            ),
        ]);


} catch (\Throwable $exception) {
    logTransaction(
        $gatewayParams['name'],
        [
            'invoice_id'     => $invoiceId,
            'transaction_id' => $transactionId,
            'error'          => $exception->getMessage(),
        ],
        'Payment registered but metadata update failed'
    );
}




/*
|--------------------------------------------------------------------------
| Log Completed Payment
|--------------------------------------------------------------------------
*/

logTransaction(
    $gatewayParams['name'],
    [
        'invoice_id'     => $invoiceId,
        'transaction_id' => $transactionId,
        'whmcs_amount'   => $whmcsAmount,
        'asset'          => $data['asset'] ?? '',
        'crypto_amount'  => $data['total_paid'] ?? '',
        'tx_ids'         => $data['tx_ids'] ?? [],
    ],
    'Payment Completed'
);


/*
|--------------------------------------------------------------------------
| Response
|--------------------------------------------------------------------------
*/


onenodes_json_reply([
    'status'  => 'success',
    'message' => 'Invoice marked as paid',
], 200);