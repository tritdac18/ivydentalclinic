<?php
/**
 * PayMongo Webhook Debug Tool
 * This file helps identify why payments aren't being processed
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/services/payment_service.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>PayMongo Webhook Debug</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h2 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .info { color: blue; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #007bff; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        pre { background: #f4f4f4; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 12px; }
        .badge-pending { background: #ffc107; color: #000; }
        .badge-completed { background: #28a745; color: white; }
        .badge-paid { background: #28a745; color: white; }
        .badge-partial { background: #ffc107; color: #000; }
    </style>
</head>
<body>
    <h1>🔍 PayMongo Webhook Debug Tool</h1>
    
    <?php
    $conn = getDBConnection();
    
    // 1. Check Recent Payments
    echo '<div class="section">';
    echo '<h2>1. Recent PayMongo Payments (Last 24 Hours)</h2>';
    
    $stmt = $conn->prepare("
        SELECT 
            payment_id,
            payment_type,
            invoice_id,
            appointment_id,
            patient_id,
            amount,
            payment_method,
            transaction_id,
            payment_status,
            payment_date,
            created_at
        FROM tbl_payments
        WHERE payment_method = 'E-Wallet'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $payments = [];
    while ($row = $result->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt->close();
    
    if (empty($payments)) {
        echo '<p class="warning">⚠️ No PayMongo payments found in the last 24 hours</p>';
    } else {
        echo '<p class="success">✅ Found ' . count($payments) . ' payment(s)</p>';
        echo '<table>';
        echo '<tr><th>Payment ID</th><th>Type</th><th>Invoice ID</th><th>Amount</th><th>Transaction ID</th><th>Status</th><th>Created</th></tr>';
        foreach ($payments as $p) {
            $status_class = strtolower($p['payment_status']);
            echo '<tr>';
            echo '<td>' . htmlspecialchars($p['payment_id']) . '</td>';
            echo '<td>' . htmlspecialchars($p['payment_type']) . '</td>';
            echo '<td>' . ($p['invoice_id'] ? htmlspecialchars($p['invoice_id']) : 'N/A') . '</td>';
            echo '<td>₱' . number_format($p['amount'], 2) . '</td>';
            echo '<td>' . htmlspecialchars($p['transaction_id']) . '</td>';
            echo '<td><span class="badge badge-' . $status_class . '">' . htmlspecialchars($p['payment_status']) . '</span></td>';
            echo '<td>' . htmlspecialchars($p['created_at']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // 2. Check Pending Payments
    echo '<div class="section">';
    echo '<h2>2. Pending PayMongo Payments</h2>';
    
    $stmt = $conn->prepare("
        SELECT 
            payment_id,
            payment_type,
            invoice_id,
            appointment_id,
            patient_id,
            amount,
            transaction_id,
            payment_status,
            created_at
        FROM tbl_payments
        WHERE payment_method = 'E-Wallet'
        AND payment_status = 'Pending'
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $pending = [];
    while ($row = $result->fetch_assoc()) {
        $pending[] = $row;
    }
    $stmt->close();
    
    if (empty($pending)) {
        echo '<p class="info">ℹ️ No pending PayMongo payments</p>';
    } else {
        echo '<p class="warning">⚠️ Found ' . count($pending) . ' pending payment(s) that should be processed</p>';
        echo '<table>';
        echo '<tr><th>Payment ID</th><th>Type</th><th>Invoice ID</th><th>Amount</th><th>Transaction ID</th><th>Created</th><th>Days Old</th></tr>';
        foreach ($pending as $p) {
            $days_old = round((time() - strtotime($p['created_at'])) / 86400, 1);
            echo '<tr>';
            echo '<td>' . htmlspecialchars($p['payment_id']) . '</td>';
            echo '<td>' . htmlspecialchars($p['payment_type']) . '</td>';
            echo '<td>' . ($p['invoice_id'] ? htmlspecialchars($p['invoice_id']) : 'N/A') . '</td>';
            echo '<td>₱' . number_format($p['amount'], 2) . '</td>';
            echo '<td>' . htmlspecialchars($p['transaction_id']) . '</td>';
            echo '<td>' . htmlspecialchars($p['created_at']) . '</td>';
            echo '<td>' . $days_old . ' days</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // 3. Check Invoices with PayMongo Payments
    echo '<div class="section">';
    echo '<h2>3. Invoices with PayMongo Payments</h2>';
    
    $stmt = $conn->prepare("
        SELECT 
            i.invoice_id,
            i.patient_id,
            i.total_amount,
            i.payment_status as invoice_status,
            COUNT(p.payment_id) as payment_count,
            SUM(CASE WHEN p.payment_status = 'Completed' THEN p.amount ELSE 0 END) as total_paid,
            SUM(CASE WHEN p.payment_status = 'Pending' THEN p.amount ELSE 0 END) as total_pending
        FROM tbl_invoices i
        LEFT JOIN tbl_payments p ON i.invoice_id = p.invoice_id AND p.payment_method = 'E-Wallet'
        WHERE p.payment_id IS NOT NULL
        GROUP BY i.invoice_id
        ORDER BY i.invoice_id DESC
        LIMIT 20
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $invoices = [];
    while ($row = $result->fetch_assoc()) {
        $invoices[] = $row;
    }
    $stmt->close();
    
    if (empty($invoices)) {
        echo '<p class="info">ℹ️ No invoices with PayMongo payments found</p>';
    } else {
        echo '<p class="success">✅ Found ' . count($invoices) . ' invoice(s) with PayMongo payments</p>';
        echo '<table>';
        echo '<tr><th>Invoice ID</th><th>Total Amount</th><th>Paid</th><th>Pending</th><th>Invoice Status</th><th>Payment Count</th><th>Should Be</th></tr>';
        foreach ($invoices as $inv) {
            $total_paid = floatval($inv['total_paid']);
            $total_pending = floatval($inv['total_pending']);
            $total_amount = floatval($inv['total_amount']);
            $should_be = 'Unknown';
            if ($total_pending > 0) {
                $should_be = 'Partial or Pending';
            } elseif ($total_paid >= $total_amount - 0.01) {
                $should_be = '<span class="success">Paid</span>';
            } elseif ($total_paid > 0) {
                $should_be = 'Partial';
            } else {
                $should_be = 'Unpaid';
            }
            
            $status_match = ($inv['invoice_status'] === 'Paid' && $total_paid >= $total_amount - 0.01) || 
                           ($inv['invoice_status'] === 'Partial' && $total_paid > 0 && $total_paid < $total_amount) ||
                           ($inv['invoice_status'] === 'Unpaid' && $total_paid == 0);
            
            $row_class = $status_match ? '' : 'style="background: #ffe6e6;"';
            
            echo '<tr ' . $row_class . '>';
            echo '<td>' . htmlspecialchars($inv['invoice_id']) . '</td>';
            echo '<td>₱' . number_format($total_amount, 2) . '</td>';
            echo '<td>₱' . number_format($total_paid, 2) . '</td>';
            echo '<td>₱' . number_format($total_pending, 2) . '</td>';
            echo '<td><span class="badge badge-' . strtolower($inv['invoice_status']) . '">' . htmlspecialchars($inv['invoice_status']) . '</span></td>';
            echo '<td>' . htmlspecialchars($inv['payment_count']) . '</td>';
            echo '<td>' . $should_be . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // 4. Check Transaction ID Patterns
    echo '<div class="section">';
    echo '<h2>4. Transaction ID Analysis</h2>';
    
    $stmt = $conn->prepare("
        SELECT 
            transaction_id,
            COUNT(*) as count,
            SUM(CASE WHEN payment_status = 'Pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN payment_status = 'Completed' THEN 1 ELSE 0 END) as completed_count,
            MIN(created_at) as first_created,
            MAX(created_at) as last_created
        FROM tbl_payments
        WHERE payment_method = 'E-Wallet'
        AND transaction_id LIKE 'PM-LINK-%'
        GROUP BY transaction_id
        ORDER BY last_created DESC
        LIMIT 20
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt->close();
    
    if (empty($transactions)) {
        echo '<p class="warning">⚠️ No PM-LINK- transaction IDs found</p>';
    } else {
        echo '<p class="info">ℹ️ Found ' . count($transactions) . ' unique transaction ID(s)</p>';
        echo '<table>';
        echo '<tr><th>Transaction ID</th><th>Total Payments</th><th>Pending</th><th>Completed</th><th>First Created</th><th>Last Created</th></tr>';
        foreach ($transactions as $tx) {
            $has_pending = $tx['pending_count'] > 0;
            $row_class = $has_pending ? 'style="background: #fff3cd;"' : '';
            echo '<tr ' . $row_class . '>';
            echo '<td>' . htmlspecialchars($tx['transaction_id']) . '</td>';
            echo '<td>' . htmlspecialchars($tx['count']) . '</td>';
            echo '<td>' . htmlspecialchars($tx['pending_count']) . '</td>';
            echo '<td>' . htmlspecialchars($tx['completed_count']) . '</td>';
            echo '<td>' . htmlspecialchars($tx['first_created']) . '</td>';
            echo '<td>' . htmlspecialchars($tx['last_created']) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    }
    echo '</div>';
    
    // 5. Test Webhook URL
    echo '<div class="section">';
    echo '<h2>5. Webhook Configuration</h2>';
    
    $webhook_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                   '://' . $_SERVER['HTTP_HOST'] . 
                   dirname($_SERVER['PHP_SELF']) . 
                   '/payments/paymongo/webhook.php';
    
    echo '<p><strong>Webhook URL:</strong> <code>' . htmlspecialchars($webhook_url) . '</code></p>';
    
    // Check if webhook file exists
    $webhook_file = __DIR__ . '/payments/paymongo/webhook.php';
    if (file_exists($webhook_file)) {
        echo '<p class="success">✅ Webhook file exists</p>';
        echo '<p><strong>File size:</strong> ' . filesize($webhook_file) . ' bytes</p>';
        echo '<p><strong>Last modified:</strong> ' . date('Y-m-d H:i:s', filemtime($webhook_file)) . '</p>';
    } else {
        echo '<p class="error">❌ Webhook file not found at: ' . htmlspecialchars($webhook_file) . '</p>';
    }
    
    // Check log file
    $log_file = __DIR__ . '/logs/paymongo_webhook_incoming.log';
    if (file_exists($log_file)) {
        echo '<p class="success">✅ Log file exists</p>';
        echo '<p><strong>File size:</strong> ' . filesize($log_file) . ' bytes</p>';
        echo '<p><strong>Last modified:</strong> ' . date('Y-m-d H:i:s', filemtime($log_file)) . '</p>';
        
        // Show last 10 lines of log
        $log_lines = file($log_file);
        if ($log_lines) {
            $last_lines = array_slice($log_lines, -10);
            echo '<h3>Last 10 lines of log:</h3>';
            echo '<pre>' . htmlspecialchars(implode('', $last_lines)) . '</pre>';
        }
    } else {
        echo '<p class="warning">⚠️ Log file not found (may not have been created yet)</p>';
        echo '<p><strong>Expected location:</strong> ' . htmlspecialchars($log_file) . '</p>';
    }
    echo '</div>';
    
    // 6. Manual Payment Check
    echo '<div class="section">';
    echo '<h2>6. Manual Payment Status Check</h2>';
    echo '<p>Enter a transaction ID to check its status:</p>';
    echo '<form method="GET" action="">';
    echo '<input type="text" name="check_tx" placeholder="PM-LINK-xxxxx" style="padding: 8px; width: 300px;">';
    echo '<button type="submit" style="padding: 8px 15px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Check</button>';
    echo '</form>';
    
    if (isset($_GET['check_tx']) && !empty($_GET['check_tx'])) {
        $check_tx = $_GET['check_tx'];
        echo '<h3>Results for: ' . htmlspecialchars($check_tx) . '</h3>';
        
        $stmt = $conn->prepare("
            SELECT 
                payment_id,
                payment_type,
                invoice_id,
                appointment_id,
                patient_id,
                amount,
                payment_status,
                transaction_id,
                payment_date,
                created_at
            FROM tbl_payments
            WHERE transaction_id = ?
        ");
        $stmt->bind_param("s", $check_tx);
        $stmt->execute();
        $result = $stmt->get_result();
        $found = [];
        while ($row = $result->fetch_assoc()) {
            $found[] = $row;
        }
        $stmt->close();
        
        if (empty($found)) {
            echo '<p class="error">❌ No payments found with transaction ID: ' . htmlspecialchars($check_tx) . '</p>';
        } else {
            echo '<p class="success">✅ Found ' . count($found) . ' payment(s)</p>';
            echo '<table>';
            echo '<tr><th>Payment ID</th><th>Invoice ID</th><th>Amount</th><th>Status</th><th>Created</th><th>Payment Date</th></tr>';
            foreach ($found as $p) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($p['payment_id']) . '</td>';
                echo '<td>' . ($p['invoice_id'] ? htmlspecialchars($p['invoice_id']) : 'N/A') . '</td>';
                echo '<td>₱' . number_format($p['amount'], 2) . '</td>';
                echo '<td><span class="badge badge-' . strtolower($p['payment_status']) . '">' . htmlspecialchars($p['payment_status']) . '</span></td>';
                echo '<td>' . htmlspecialchars($p['created_at']) . '</td>';
                echo '<td>' . ($p['payment_date'] ? htmlspecialchars($p['payment_date']) : 'N/A') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }
    }
    echo '</div>';
    
    $conn->close();
    ?>
    
    <div class="section">
        <h2>7. Quick Actions</h2>
        <p><a href="?refresh=1" style="padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; display: inline-block;">🔄 Refresh Page</a></p>
        <p><small>Last updated: <?php echo date('Y-m-d H:i:s'); ?></small></p>
    </div>
</body>
</html>

