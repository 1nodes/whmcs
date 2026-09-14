<?php

use WHMCS\Database\Capsule;

require_once __DIR__ . '/../../../init.php';

header('Content-Type: application/json; charset=utf-8');

$invoiceId = isset($_GET['invoice_id']) ? (int) $_GET['invoice_id'] : 0;

if ($invoiceId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid invoice id',
    ]);
    exit;
}

try {
    $invoice = Capsule::table('tblinvoices')
        ->select('id', 'status')
        ->where('id', $invoiceId)
        ->first();

    if (!$invoice) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Invoice not found',
        ]);
        exit;
    }

    $status = strtolower((string) $invoice->status);

    echo json_encode([
        'success' => true,
        'invoice_id' => (int) $invoice->id,
        'status' => (string) $invoice->status,
        'paid' => ($status === 'paid'),
    ]);
    exit;
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error',
    ]);
    exit;
}