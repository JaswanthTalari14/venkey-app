<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'patient';
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

// 1. Toggle Favorite Doctor (Feature Group 7)
if ($action === 'toggle_favorite') {
    $doctor_id = isset($_POST['doctor_id']) ? (int)$_POST['doctor_id'] : 0;
    if ($doctor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid doctor ID']);
        exit;
    }

    $chk = $conn->query("SELECT id FROM favorite_doctors WHERE patient_id = $user_id AND doctor_id = $doctor_id");
    if ($chk && $chk->num_rows > 0) {
        $conn->query("DELETE FROM favorite_doctors WHERE patient_id = $user_id AND doctor_id = $doctor_id");
        echo json_encode(['success' => true, 'is_favorite' => false, 'message' => 'Removed from favorites']);
    } else {
        $stmt = $conn->prepare("INSERT INTO favorite_doctors (patient_id, doctor_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $user_id, $doctor_id);
        $stmt->execute();
        echo json_encode(['success' => true, 'is_favorite' => true, 'message' => 'Added to favorites']);
    }
    exit;
}

// 2. Toggle Doctor Online/Offline Availability (Feature Group 9)
if ($action === 'toggle_doctor_availability') {
    if ($role !== 'doctor') {
        echo json_encode(['success' => false, 'message' => 'Doctor role required']);
        exit;
    }
    $status = isset($_POST['is_online']) ? (int)$_POST['is_online'] : 1;
    $stmt = $conn->prepare("UPDATE users SET is_online_available = ? WHERE id = ? AND role = 'doctor'");
    $stmt->bind_param("ii", $status, $user_id);
    $stmt->execute();
    echo json_encode(['success' => true, 'is_online' => $status, 'message' => 'Availability status updated']);
    exit;
}

// 3. Save / Update Refill Reminder (Feature Group 2)
if ($action === 'save_refill_reminder') {
    $med_name = trim($_POST['medicine_name'] ?? '');
    $dosage = trim($_POST['dosage'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $time = trim($_POST['reminder_time'] ?? '08:00:00');
    $start_date = trim($_POST['start_date'] ?? date('Y-m-d'));

    if (empty($med_name) || empty($dosage)) {
        echo json_encode(['success' => false, 'message' => 'Medicine name and dosage required']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO refill_reminders (patient_id, medicine_name, dosage, frequency, reminder_time, start_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $user_id, $med_name, $dosage, $frequency, $time, $start_date);
    $ok = $stmt->execute();
    echo json_encode(['success' => (bool)$ok, 'message' => 'Medicine refill reminder created successfully']);
    exit;
}

// 4. Delete Refill Reminder (Feature Group 2)
if ($action === 'delete_refill_reminder') {
    $reminder_id = (int)($_POST['reminder_id'] ?? 0);
    $conn->query("DELETE FROM refill_reminders WHERE id = $reminder_id AND patient_id = $user_id");
    echo json_encode(['success' => true, 'message' => 'Reminder deleted']);
    exit;
}

// 5. Submit Refund Request (Feature Group 19 & 33)
if ($action === 'submit_refund_request') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');

    if ($order_id <= 0 || empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Order ID and reason are required']);
        exit;
    }

    // Verify order ownership
    $chk = $conn->query("SELECT id, total_amount, payment_method, gateway_payment_id FROM orders WHERE id = $order_id AND patient_id = $user_id");
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Order not found or access denied']);
        exit;
    }
    $ord = $chk->fetch_assoc();
    $tx_id = $ord['gateway_payment_id'] ?? ('ORD-' . $order_id);
    $refund_amt = ($amount > 0) ? $amount : $ord['total_amount'];

    $stmt = $conn->prepare("INSERT INTO refund_requests (patient_id, order_id, transaction_id, amount, reason, status) VALUES (?, ?, ?, ?, ?, 'Requested')");
    $stmt->bind_param("iisds", $user_id, $order_id, $tx_id, $refund_amt, $reason);
    $ok = $stmt->execute();

    echo json_encode(['success' => (bool)$ok, 'message' => 'Refund request submitted for Admin review']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
