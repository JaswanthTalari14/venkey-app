<?php
require_once 'config.php';
require_once 'includes/notification_functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized admin session']);
    exit;
}

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

// 1. Approve Medical Card Application
if ($action === 'approve_card') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $app_id = isset($input['app_id']) ? (int)$input['app_id'] : 0;

    if ($app_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Application ID']);
        exit;
    }

    $chk = $conn->query("SELECT * FROM digital_medical_cards WHERE id = $app_id");
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Application record not found']);
        exit;
    }

    $app = $chk->fetch_assoc();
    $patient_id = (int)$app['patient_id'];

    // Idempotent Check: If already active, return clean success without duplicating
    if ($app['status'] === 'active') {
        echo json_encode([
            'success' => true,
            'card_number' => $app['card_number'],
            'message' => 'Card is already active. Card Number: ' . $app['card_number']
        ]);
        exit;
    }

    $settings = get_medical_card_settings($conn);
    $validity_months = $settings['validity_months']; // 5 Months

    $card_num = !empty($app['card_number']) ? $app['card_number'] : generate_unique_card_number($conn);

    // Calculate valid_from and valid_until (5 calendar months from NOW)
    $stmt = $conn->prepare("
        UPDATE digital_medical_cards 
        SET status = 'active', 
            card_number = ?, 
            approved_at = NOW(), 
            valid_from = NOW(), 
            valid_until = DATE_ADD(NOW(), INTERVAL ? MONTH) 
        WHERE id = ?
    ");
    $stmt->bind_param("sii", $card_num, $validity_months, $app_id);

    if ($stmt->execute()) {
        create_notification(
            $patient_id,
            "Digital Medical Card Approved! 🎉",
            "Your Digital Medical Card ($card_num) has been approved and activated! You can now enjoy $validity_months months of exclusive discounts on doctor consults and medicine orders.",
            'system',
            'medical_card',
            $app_id
        );

        echo json_encode([
            'success' => true,
            'app_id' => $app_id,
            'card_number' => $card_num,
            'message' => "Digital Medical Card ($card_num) approved and activated successfully!"
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }
    exit;
}

// 2. Reject Medical Card Application
if ($action === 'reject_card') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    $app_id = isset($input['app_id']) ? (int)$input['app_id'] : 0;
    $reason = isset($input['reason']) ? trim($input['reason']) : 'Information incomplete or payment verification pending';

    if ($app_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid Application ID']);
        exit;
    }

    $chk = $conn->query("SELECT * FROM digital_medical_cards WHERE id = $app_id");
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Application record not found']);
        exit;
    }

    $app = $chk->fetch_assoc();
    $patient_id = (int)$app['patient_id'];

    $stmt = $conn->prepare("UPDATE digital_medical_cards SET status = 'rejected', rejected_at = NOW(), admin_notes = ? WHERE id = ?");
    $stmt->bind_param("si", $reason, $app_id);

    if ($stmt->execute()) {
        create_notification(
            $patient_id,
            "Medical Card Application Update",
            "Your Digital Medical Card application was reviewed and rejected. Reason: " . $reason,
            'system',
            'medical_card',
            $app_id
        );

        echo json_encode([
            'success' => true,
            'app_id' => $app_id,
            'message' => 'Digital Medical Card application rejected.'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
