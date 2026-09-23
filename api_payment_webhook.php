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
    
    // Update order status if order exists
    $stmt = $conn->prepare("UPDATE orders SET payment_status = 'Paid', gateway_payment_id = ? WHERE gateway_order_id = ?");
    $stmt->bind_param("ss", $gateway_payment_id, $gateway_order_id);
    $stmt->execute();
}

http_response_code(200);
echo json_encode(['status' => 'success']);
?>
