<?php
/**
 * PayMongo Payment Service
 * Handles payment processing via PayMongo API
 */

require_once __DIR__ . '/../config/paymongo_config.php';

class PaymentService {
    private $secretKey;
    private $publicKey;
    private $apiUrl;
    
    public function __construct() {
        if (!isPayMongoConfigured()) {
            throw new Exception('PayMongo is not configured. Please set up API keys.');
        }
        
        $this->secretKey = PAYMONGO_SECRET_KEY;
        $this->publicKey = PAYMONGO_PUBLIC_KEY;
        $this->apiUrl = PAYMONGO_API_URL;
    }
    
    /**
     * Create a payment intent
     * Returns payment intent data with client key for frontend
     */
    public function createPaymentIntent($amount, $description, $metadata = []) {
        $url = $this->apiUrl . '/payment_intents';
        
        $data = [
            'data' => [
                'attributes' => [
                    'amount' => (int)($amount * 100), // Convert to cents
                    'currency' => PAYMONGO_CURRENCY,
                    'description' => $description,
                    'metadata' => $metadata
                ]
            ]
        ];
        
        $response = $this->makeRequest('POST', $url, $data);
        
        if ($response && isset($response['data'])) {
            return [
                'success' => true,
                'client_key' => $response['data']['attributes']['client_key'],
                'payment_intent_id' => $response['data']['id'],
                'amount' => $response['data']['attributes']['amount'] / 100,
                'status' => $response['data']['attributes']['status']
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['errors'][0]['detail'] ?? 'Unknown error'
        ];
    }
    
    /**
     * Attach payment method to payment intent
     */
    public function attachPaymentMethod($paymentIntentId, $paymentMethodId) {
        $url = $this->apiUrl . '/payment_intents/' . $paymentIntentId . '/attach';
        
        $data = [
            'data' => [
                'attributes' => [
                    'payment_method' => $paymentMethodId,
                    'return_url' => 'https://ivydentalbckup.gt.tc/payments/paymongo/return.php'
                ]
            ]
        ];
        
        $response = $this->makeRequest('POST', $url, $data);
        
        if ($response && isset($response['data'])) {
            return [
                'success' => true,
                'status' => $response['data']['attributes']['status'],
                'next_action' => $response['data']['attributes']['next_action'] ?? null
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['errors'][0]['detail'] ?? 'Unknown error'
        ];
    }
    
    /**
     * Retrieve payment intent status
     */
    public function getPaymentIntent($paymentIntentId) {
        $url = $this->apiUrl . '/payment_intents/' . $paymentIntentId;
        
        $response = $this->makeRequest('GET', $url);
        
        if ($response && isset($response['data'])) {
            return [
                'success' => true,
                'status' => $response['data']['attributes']['status'],
                'amount' => $response['data']['attributes']['amount'] / 100,
                'payment_method' => $response['data']['attributes']['payment_method'] ?? null
            ];
        }
        
        return [
            'success' => false,
            'error' => $response['errors'][0]['detail'] ?? 'Unknown error'
        ];
    }
    
    /**
     * Get payment link status from PayMongo API
     * This verifies if the payment was actually completed
     */
    public function getPaymentLinkStatus($linkId) {
        // Handle different link_id formats
        // PayMongo returns link IDs as 'link_xxxxx' but we might store them without prefix
        $cleanLinkId = $linkId;
        
        // If it doesn't start with 'link_', add it
        if (strpos($cleanLinkId, 'link_') !== 0) {
            $cleanLinkId = 'link_' . $cleanLinkId;
        }
        
        $url = $this->apiUrl . '/links/' . $cleanLinkId;
        
        error_log('PayMongo: Checking link status. Original ID: ' . $linkId . ', Clean ID: ' . $cleanLinkId);
        
        $response = $this->makeRequest('GET', $url);
        
        if ($response && isset($response['data'])) {
            $status = $response['data']['attributes']['status'] ?? 'unknown';
            $archived = $response['data']['attributes']['archived'] ?? false;
            $remarks = $response['data']['attributes']['remarks'] ?? null;
            
            // Check if link has payments - even if status is 'unpaid', payments might exist and be paid
            $hasPayments = false;
            $paymentPaid = false;
            
            // Try to get payments for this link
            try {
                $paymentsUrl = $this->apiUrl . '/payments?link_id=' . $cleanLinkId;
                $paymentsResponse = $this->makeRequest('GET', $paymentsUrl);
                
                if ($paymentsResponse && isset($paymentsResponse['data']) && is_array($paymentsResponse['data'])) {
                    $hasPayments = count($paymentsResponse['data']) > 0;
                    
                    // Check if any payment is paid
                    foreach ($paymentsResponse['data'] as $payment) {
                        $paymentStatus = $payment['attributes']['status'] ?? '';
                        if ($paymentStatus === 'paid' || $paymentStatus === 'succeeded') {
                            $paymentPaid = true;
                            break;
                        }
                    }
                    
                    error_log('PayMongo: Found ' . count($paymentsResponse['data']) . ' payment(s) for link. Has paid payment: ' . ($paymentPaid ? 'yes' : 'no'));
                }
            } catch (Exception $e) {
                error_log('PayMongo: Error checking payments: ' . $e->getMessage());
            }
            
            // Link is paid if:
            // 1. Status is 'paid'
            // 2. Link is archived (meaning payment was completed)
            // 3. There's a paid payment associated with the link
            $isPaid = ($status === 'paid' || $archived === true || $paymentPaid === true);
            
            error_log('PayMongo: Link status check. Link ID: ' . $cleanLinkId . ', Status: ' . $status . ', Archived: ' . ($archived ? 'true' : 'false') . ', Has Payments: ' . ($hasPayments ? 'true' : 'false') . ', Payment Paid: ' . ($paymentPaid ? 'true' : 'false') . ', Is Paid: ' . ($isPaid ? 'true' : 'false'));
            
            return [
                'success' => true,
                'status' => $status,
                'archived' => $archived,
                'is_paid' => $isPaid,
                'has_payments' => $hasPayments,
                'payment_paid' => $paymentPaid,
                'amount' => ($response['data']['attributes']['amount'] ?? 0) / 100,
                'checkout_url' => $response['data']['attributes']['checkout_url'] ?? null,
                'remarks' => $remarks
            ];
        }
        
        // Try without 'link_' prefix if first attempt failed
        if (strpos($linkId, 'link_') === 0) {
            $altLinkId = str_replace('link_', '', $linkId);
            $altUrl = $this->apiUrl . '/links/' . $altLinkId;
            error_log('PayMongo: Retrying with alternative format. Alt ID: ' . $altLinkId);
            $response = $this->makeRequest('GET', $altUrl);
            
            if ($response && isset($response['data'])) {
                $status = $response['data']['attributes']['status'] ?? 'unknown';
                $archived = $response['data']['attributes']['archived'] ?? false;
                $remarks = $response['data']['attributes']['remarks'] ?? null;
                
                // Check payments for alternative format too
                $hasPayments = false;
                $paymentPaid = false;
                
                try {
                    $paymentsUrl = $this->apiUrl . '/payments?link_id=' . $altLinkId;
                    $paymentsResponse = $this->makeRequest('GET', $paymentsUrl);
                    
                    if ($paymentsResponse && isset($paymentsResponse['data']) && is_array($paymentsResponse['data'])) {
                        $hasPayments = count($paymentsResponse['data']) > 0;
                        
                        foreach ($paymentsResponse['data'] as $payment) {
                            $paymentStatus = $payment['attributes']['status'] ?? '';
                            if ($paymentStatus === 'paid' || $paymentStatus === 'succeeded') {
                                $paymentPaid = true;
                                break;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Ignore payment check errors for alternative format
                }
                
                $isPaid = ($status === 'paid' || $archived === true || $paymentPaid === true);
                
                error_log('PayMongo: Link status check (alt). Link ID: ' . $altLinkId . ', Status: ' . $status . ', Is Paid: ' . ($isPaid ? 'true' : 'false'));
                
                return [
                    'success' => true,
                    'status' => $status,
                    'archived' => $archived,
                    'is_paid' => $isPaid,
                    'has_payments' => $hasPayments,
                    'payment_paid' => $paymentPaid,
                    'amount' => ($response['data']['attributes']['amount'] ?? 0) / 100,
                    'checkout_url' => $response['data']['attributes']['checkout_url'] ?? null,
                    'remarks' => $remarks
                ];
            }
        }
        
        error_log('PayMongo: Failed to get link status. Link ID: ' . $linkId . ', Response: ' . json_encode($response));
        
        return [
            'success' => false,
            'is_paid' => false,
            'error' => $response['errors'][0]['detail'] ?? 'Unknown error'
        ];
    }
    
    /**
     * Create a payment link (simpler redirect flow)
     */
    public function createPaymentLink($amount, $description, $metadata = []) {
        $url = $this->apiUrl . '/links';
        
        $returnUrl = 'https://ivydentalbckup.gt.tc/payments/paymongo/return.php?success=1';
        
        $data = [
            'data' => [
                'attributes' => [
                    'amount' => (int)($amount * 100), // Convert to cents
                    'currency' => PAYMONGO_CURRENCY,
                    'description' => $description,
                    'remarks' => json_encode($metadata),
                    'reference_number' => 'APT-' . ($metadata['appointment_id'] ?? time())
                ]
            ]
        ];
        
        $response = $this->makeRequest('POST', $url, $data);
        
        if ($response && isset($response['data'])) {
            // PayMongo returns link ID in format: link_xxxxx
            $link_id = $response['data']['id'];
            
            error_log('PayMongo: Created payment link. Link ID: ' . $link_id . ', Checkout URL: ' . ($response['data']['attributes']['checkout_url'] ?? 'N/A'));
            
            return [
                'success' => true,
                'checkout_url' => $response['data']['attributes']['checkout_url'],
                'link_id' => $link_id,
                'amount' => $response['data']['attributes']['amount'] / 100,
                'status' => $response['data']['attributes']['status']
            ];
        }
        
        error_log('PayMongo: Failed to create payment link. Response: ' . json_encode($response));
        
        return [
            'success' => false,
            'error' => $response['errors'][0]['detail'] ?? 'Unknown error'
        ];
    }
    
    /**
     * Make API request to PayMongo
     */
    private function makeRequest($method, $url, $data = null) {
        $ch = curl_init($url);
        
        $headers = [
            'Authorization: Basic ' . base64_encode($this->secretKey . ':'),
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        
        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        }
        
        error_log('PayMongo API Error: HTTP ' . $httpCode . ' - ' . $response);
        return json_decode($response, true);
    }
    
    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature($payload, $signature) {
        $computedSignature = hash_hmac('sha256', $payload, PAYMONGO_WEBHOOK_SECRET);
        return hash_equals($computedSignature, $signature);
    }
}

