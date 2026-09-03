<?php

/**
 * 1nodes Payment Gateway
 *
 * WHMCS Payment Gateway Module
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}


/**
 * Gateway metadata.
 */
function onenodes_MetaData()
{
    return [
        'DisplayName' => '1nodes',
        'APIVersion'  => '1.1',
    ];
}


/**
 * Gateway configuration.
 */
function onenodes_config()
{
    return [
        'FriendlyName' => [
            'Type'  => 'System',
            'Value' => '1nodes',
        ],

        'merchant_key' => [
            'FriendlyName' => 'Merchant Key',
            'Type'         => 'text',
            'Size'         => '60',
            'Description'  => 'Enter your 1nodes Merchant Key.',
        ],

        'secret_key' => [
            'FriendlyName' => 'Secret Key',
            'Type'         => 'text',
            'Size'         => '60',
            'Description'  => 'Enter your 1nodes Secret Key.',
        ],
    ];
}

function onenodes_link($params)
{
    if (strtolower((string) ($params['status'] ?? '')) === 'paid') {
        return '';
    }

    $invoiceId = $params['invoiceid'];

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $sessionKey = 'onenodes_pay_url_' . $invoiceId;

    if (!empty($_SESSION[$sessionKey]['url']) && (time() - $_SESSION[$sessionKey]['time']) < 1800) {
        $paymentUrl = $_SESSION[$sessionKey]['url'];
        return '<form method="get" action="' . htmlspecialchars($paymentUrl, ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="submit" value="' . htmlspecialchars($params['langpaynow'] ?? 'Pay Now', ENT_QUOTES, 'UTF-8') . '" class="btn btn-success btn-block" />'
            . '</form>';
    }

    $merchantKey = $params['merchant_key'] ?? '';
    $apiUrl      = 'https://1nodes.com/wp-json/v1/api/create-payment';
    $amount      = $params['amount'];
    $systemUrl   = rtrim($params['systemurl'], '/') . '/';

    $returnUrl   = $systemUrl . 'viewinvoice.php?id=' . $invoiceId . '&onenodes_status=1';
    $callbackUrl = $systemUrl . 'modules/gateways/callback/onenodes.php';

    $payload = [
        'amount'     => (string) $amount,
        'order_id'   => (string) $invoiceId,
        'callback'   => $callbackUrl,
        'return_url' => $returnUrl,
    ];


    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $merchantKey,
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,

        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return '<div class="alert alert-danger">Payment Gateway Error: ' . htmlspecialchars($curlError, ENT_QUOTES, 'UTF-8') . '</div>';
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return '<div class="alert alert-danger">Invalid response from payment gateway.</div>';
    }

    $paymentUrl = $data['data']['checkout_url'] ?? null;

    if (in_array($httpCode, [200, 201], true) && !empty($paymentUrl)) {
        $_SESSION[$sessionKey] = [
            'url'  => $paymentUrl,
            'time' => time(),
        ];

        return '<form method="get" action="' . htmlspecialchars($paymentUrl, ENT_QUOTES, 'UTF-8') . '">'
            . '<input type="submit" value="' . htmlspecialchars($params['langpaynow'] ?? 'Pay Now', ENT_QUOTES, 'UTF-8') . '" class="btn btn-success btn-block" />'
            . '</form>';
    }

    $errorMessage = $data['message'] ?? 'Failed to initiate payment. Please contact support.';
    return '<div class="alert alert-danger">' . htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') . '</div>';
}

