<?php
require_once 'config.php';

header('Content-Type: application/json');

$post_data = file_get_contents('php://input');
$signature = isset($_SERVER['HTTP_X_RAZORPAY_SIGNATURE']) ? $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] : '';

$webhook_secret = RAZORPAY_WEBHOOK_SECRET;

if (!empty($signature) && $webhook_secret !== 'samplewebhooksecret') {
    $expected_signature = hash_hmac('sha256', $post_data, $webhook_secret);
    if (!hash_equals($expected_signature, $signature)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid webhook signature']);
        exit;
    }
}

$event = json_decode($post_data, true);

if (isset($event['event']) && $event['event'] === 'order.paid') {
    $payload = $event['payload']['payment']['entity'];
    $gateway_payment_id = $payload['id'];
    $gateway_order_id = $payload['order_id'];
    
    // Find matching order
    $find_stmt = $conn->prepare("SELECT id, patient_id, total_amount, payment_status FROM orders WHERE gateway_order_id = ?");
    $find_stmt->bind_param("s", $gateway_order_id);
    $find_stmt->execute();
    $ord_res = $find_stmt->get_result();
    
    if ($ord_res && $ord_res->num_rows > 0) {
        $ord = $ord_res->fetch_assoc();
        if ($ord['payment_status'] !== 'Paid') {
            $stmt = $conn->prepare("UPDATE orders SET payment_status = 'Paid', gateway_payment_id = ? WHERE id = ?");
            $stmt->bind_param("si", $gateway_payment_id, $ord['id']);
            $stmt->execute();
            
            require_once 'includes/referral_functions.php';
            process_referral_order_qualification($ord['id'], $ord['patient_id'], $ord['total_amount'], true);
        }
    }
}

http_response_code(200);
echo json_encode(['status' => 'success']);
?>
