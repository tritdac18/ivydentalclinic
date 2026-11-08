<?php
/**
 * PayMongo Test Page
 * Tests if PayMongo is working and shows detailed errors
 */

require_once 'config/db.php';
require_once 'config/paymongo_config.php';
require_once 'services/payment_service.php';

echo "<h1>PayMongo Integration Test</h1>";
echo "<pre>";

// Test 1: Check if PayMongo is configured
echo "=== Test 1: Configuration ===\n";
echo "Secret Key: " . (defined('PAYMONGO_SECRET_KEY') ? (substr(PAYMONGO_SECRET_KEY, 0, 10) . "...") : "NOT SET") . "\n";
echo "Public Key: " . (defined('PAYMONGO_PUBLIC_KEY') ? (substr(PAYMONGO_PUBLIC_KEY, 0, 10) . "...") : "NOT SET") . "\n";
echo "Configured: " . (isPayMongoConfigured() ? "YES" : "NO") . "\n\n";

// Test 2: Try to create a test payment link
echo "=== Test 2: Create Test Payment Link ===\n";
try {
    $paymentService = new PaymentService();
    $testAmount = 100; // ₱100
    $testDescription = "Test Payment Link";
    $testMetadata = ['test' => true];
    
    echo "Creating payment link for ₱100...\n";
    $result = $paymentService->createPaymentLink($testAmount, $testDescription, $testMetadata);
    
    echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n\n";
    
    if ($result['success']) {
        echo "✅ SUCCESS! Payment link created.\n";
        echo "Link ID: " . $result['link_id'] . "\n";
        echo "Checkout URL: " . $result['checkout_url'] . "\n\n";
        
        // Test 3: Try to insert into database
        echo "=== Test 3: Database Insert Test ===\n";
        try {
            $conn = getDBConnection();
            echo "✅ Database connected\n";
            
            $test_appointment_id = 1; // Use existing appointment
            $test_patient_id = 1; // Use existing patient
            $test_amount = 100.00;
            $test_transaction_id = 'PM-LINK-TEST-' . time();
            
            $insert_stmt = $conn->prepare("
                INSERT INTO tbl_payments 
                (payment_type, appointment_id, patient_id, amount, payment_method, transaction_id, payment_status, payment_date)
                VALUES ('Downpayment', ?, ?, ?, 'E-Wallet', ?, 'Pending', NOW())
            ");
            
            if (!$insert_stmt) {
                echo "❌ PREPARE FAILED: " . $conn->error . "\n";
            } else {
                echo "✅ Statement prepared\n";
                
                $insert_stmt->bind_param("iids", $test_appointment_id, $test_patient_id, $test_amount, $test_transaction_id);
                
                if (!$insert_stmt->execute()) {
                    echo "❌ INSERT FAILED: " . $insert_stmt->error . "\n";
                } else {
                    $payment_id = $conn->insert_id;
                    echo "✅ INSERT SUCCESS! Payment ID: " . $payment_id . "\n";
                    
                    // Clean up test record
                    $conn->query("DELETE FROM tbl_payments WHERE payment_id = " . $payment_id);
                    echo "✅ Test record cleaned up\n";
                }
                
                $insert_stmt->close();
            }
            
            $conn->close();
            
        } catch (Exception $e) {
            echo "❌ Database Error: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "❌ FAILED to create payment link\n";
        echo "Error: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test Complete ===\n";
echo "</pre>";

echo "<hr>";
echo "<p><a href='book_appointment.php'>Back to Appointments</a></p>";
?>
