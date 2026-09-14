<?php
/**
 * 1nodes Payment Verification & Loading Hook for WHMCS
 * Triggers on viewinvoice.php when onenodes_status=1
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

use WHMCS\Database\Capsule;

add_hook('ClientAreaPageViewInvoice', 1, function ($vars) {
    if (empty($_GET['onenodes_status'])) {
        return;
    }

    $invoiceId = (int)($vars['invoiceid'] ?? ($_GET['id'] ?? 0));
    if ($invoiceId <= 0) {
        return;
    }

    // If invoice is already paid, no need to show loading
    $status = Capsule::table('tblinvoices')
        ->where('id', $invoiceId)
        ->value('status');

    if (strtolower((string)$status) === 'paid') {
        return;
    }

    $statusCheckUrl = 'modules/gateways/callback/onenodes_check_status.php?invoice_id=' . $invoiceId;

    $loadingHtml = '
        <div id="onenodes-loading-overlay">
            <div class="onenodes-box">
                <div class="onenodes-spinner">
                
            </div>
               
            <div class="onenodes-title">Checking and confirming payment...</div>
            
            <div class="onenodes-desc">Please wait a few moments, the transaction status is being queried.</div>
                <button type="button" class="onenodes-btn-dismiss" onclick="document.getElementById(\'onenodes-loading-overlay\').remove();">
                Close and view invoice
                </button>
            </div>
        </div>

        <style>
        #onenodes-loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 99999999;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Tahoma, "Segoe UI", sans-serif;
            direction: rtl;
            color: #ffffff;
        }
        .onenodes-box {
            text-align: center;
            background: #1e293b;
            padding: 35px 40px;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 8px 10px -6px rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 420px;
            width: 90%;
        }
        .onenodes-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255, 255, 255, 0.1);
            border-top-color: #3b82f6;
            border-radius: 50%;
            animation: onenodes-spin 0.8s linear infinite;
            margin: 0 auto 20px auto;
        }
        .onenodes-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #f8fafc;
        }
        .onenodes-desc {
            font-size: 13px;
            color: #94a3b8;
            margin-bottom: 22px;
            line-height: 1.6;
        }
        .onenodes-btn-dismiss {
            background: rgba(255, 255, 255, 0.08);
            color: #cbd5e1;
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 9px 20px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .onenodes-btn-dismiss:hover {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
        }
        
        @keyframes onenodes-spin {
            to { transform: rotate(360deg); }
        }
        </style>

<script>
(function() {
if (window.history && window.history.replaceState) {
var url = new URL(window.location.href);
url.searchParams.delete("onenodes_status");
window.history.replaceState({}, document.title, url.toString());
}

var maxAttempts = 50;
var attempts = 0;
var statusUrl = "' . addslashes($statusCheckUrl) . '";

function checkInvoiceStatus() {
attempts++;

if (attempts > maxAttempts) {
var overlay = document.getElementById("onenodes-loading-overlay");
if (overlay) {
overlay.innerHTML = \'<div class="onenodes-box"><div class="onenodes-title" style="color:#f59e0b;">Network confirmation is taking longer than expected</div><div class="onenodes-desc">Your payment will be recorded soon. Please refresh the page to check again.</div><button type="button" class="onenodes-btn-dismiss" onclick="location.reload();">Refresh page</button></div>\';
}
return;
}

fetch(statusUrl, {
method: "GET",
credentials: "same-origin",
cache: "no-store"
})
.then(function(res) {
if (!res.ok) {
throw new Error("Status check failed");
}
return res.json();
})
.then(function(data) {
if (data && data.success && data.paid === true) {
window.location.reload();
return;
}

setTimeout(checkInvoiceStatus, 3000);
})
.catch(function() {
setTimeout(checkInvoiceStatus, 3000);
});
}

setTimeout(checkInvoiceStatus, 2500);
})();
</script>
';

    echo $loadingHtml;
});
