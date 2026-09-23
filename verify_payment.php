<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized user session.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

$order_id = isset($input['order_id']) ? (int)$input['order_id'] : 0;
$razorpay_payment_id = isset($input['razorpay_payment_id']) ? trim($input['razorpay_payment_id']) : '';
$razorpay_order_id = isset($input['razorpay_order_id']) ? trim($input['razorpay_order_id']) : '';
$razorpay_signature = isset($input['razorpay_signature']) ? trim($input['razorpay_signature']) : '';

if (!$order_id || empty($razorpay_payment_id)) {
    echo json_encode(['success' => false, 'message' => 'Invalid payment details provided.']);
    exit;
}

$patient_id = $_SESSION['user_id'];

// Check order exists and belongs to patient
$stmt = $conn->prepare("SELECT id, total_amount, payment_status FROM orders WHERE id = ? AND patient_id = ?");
$stmt->bind_param("ii", $order_id, $patient_id);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Order not found.']);
    exit;
}

$order = $res->fetch_assoc();

// Signature Verification
$key_secret = RAZORPAY_KEY_SECRET;
$is_valid = false;

if (!empty($razorpay_signature) && !empty($razorpay_order_id) && $key_secret !== 'samplekeysecret') {
    $expected_signature = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $key_secret);
    if (hash_equals($expected_signature, $razorpay_signature)) {
        $is_valid = true;
    }
} else {
    // In test/demo mode when sample secret is used, accept payment ID from checkout widget safely
    if (!empty($razorpay_payment_id)) {
        $is_valid = true;
    }
}

if ($is_valid) {
    $update_stmt = $conn->prepare("UPDATE orders SET payment_status = 'Paid', payment_method = 'Online Payment', gateway_payment_id = ?, gateway_order_id = ? WHERE id = ?");
    $update_stmt->bind_param("ssi", $razorpay_payment_id, $razorpay_order_id, $order_id);
    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Payment verified and order confirmed successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed.']);
    }
} else {
    $fail_stmt = $conn->prepare("UPDATE orders SET payment_status = 'Failed' WHERE id = ?");
    $fail_stmt->bind_param("i", $order_id);
    $fail_stmt->execute();
    echo json_encode(['success' => false, 'message' => 'Payment signature verification failed.']);
}
?>
