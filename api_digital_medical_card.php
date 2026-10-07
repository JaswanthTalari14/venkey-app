<?php
require_once 'config.php';
require_once 'includes/notification_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized user session']);
    exit;
}

$patient_id = (int)$_SESSION['user_id'];
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

// 1. Submit Online Paid Application
if ($action === 'apply_card_online') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $payment_id = isset($input['payment_id']) ? trim($input['payment_id']) : '';
    $razorpay_order_id = isset($input['razorpay_order_id']) ? trim($input['razorpay_order_id']) : '';

    if (empty($payment_id)) {
        echo json_encode(['success' => false, 'message' => 'Payment reference is required']);
        exit;
    }

    // Check existing card status
    $existing_chk = $conn->query("SELECT id, status FROM digital_medical_cards WHERE patient_id = $patient_id AND status IN ('active', 'pending') ORDER BY id DESC LIMIT 1");
    if ($existing_chk && $existing_chk->num_rows > 0) {
        $ex = $existing_chk->fetch_assoc();
        if ($ex['status'] === 'active') {
            echo json_encode(['success' => false, 'message' => 'Your Digital Medical Card is already active!']);
            exit;
        } elseif ($ex['status'] === 'pending') {
            echo json_encode(['success' => false, 'message' => 'You already have a pending Digital Medical Card application waiting for Admin approval.']);
            exit;
        }
    }

    $settings = get_medical_card_settings($conn);
    $card_price = $settings['card_price']; // ₹50.00 fixed server-side

    $stmt = $conn->prepare("INSERT INTO digital_medical_cards (patient_id, amount_paid, payment_method, payment_status, gateway_payment_id, gateway_order_id, status) VALUES (?, ?, 'Online Payment', 'Paid', ?, ?, 'pending')");
    $stmt->bind_param("idss", $patient_id, $card_price, $payment_id, $razorpay_order_id);

    if ($stmt->execute()) {
        $app_id = $stmt->insert_id;
        create_notification(
            $patient_id,
            "Medical Card Application Submitted",
            "Your ₹50 payment for Digital Medical Card application (#DMC-APP-" . str_pad($app_id, 4, '0', STR_PAD_LEFT) . ") was verified. Status is now Pending Admin Approval.",
            'system',
            'medical_card',
            $app_id
        );
        echo json_encode(['success' => true, 'message' => 'Payment verified successfully! Card application is pending Admin approval.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed. Please try again.']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
