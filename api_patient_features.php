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

// 6. Family Member Management (Feature Group 3)
if ($action === 'add_family_member') {
    $name = trim($_POST['name'] ?? '');
    $rel = trim($_POST['relationship'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? 'Other');
    $blood = trim($_POST['blood_group'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '');
    $emergency = trim($_POST['emergency_contact'] ?? '');
    $notes = trim($_POST['medical_notes'] ?? '');

    if (empty($name) || empty($rel)) {
        echo json_encode(['success' => false, 'message' => 'Name and relationship are required']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO family_members (primary_user_id, name, relationship, dob, gender, blood_group, allergies, emergency_contact, medical_notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $dob_val = !empty($dob) ? $dob : null;
    $stmt->bind_param("issssssss", $user_id, $name, $rel, $dob_val, $gender, $blood, $allergies, $emergency, $notes);
    $ok = $stmt->execute();
    echo json_encode(['success' => (bool)$ok, 'message' => 'Family member profile added successfully']);
    exit;
}

if ($action === 'delete_family_member') {
    $fid = (int)($_POST['family_id'] ?? 0);
    $conn->query("DELETE FROM family_members WHERE id = $fid AND primary_user_id = $user_id");
    echo json_encode(['success' => true, 'message' => 'Family profile removed']);
    exit;
}

// 7. Emergency Medical Card Config & QR Generation (Feature Group 4)
if ($action === 'save_emergency_card') {
    $fields = $_POST['public_fields'] ?? [];
    if (!is_array($fields)) $fields = [];

    $fields_json = json_encode($fields);
    $qr_token = md5('EMG_' . $user_id . '_' . time() . '_' . rand(1000, 9999));

    // Check existing
    $chk = $conn->query("SELECT id, qr_token FROM emergency_cards WHERE user_id = $user_id");
    if ($chk && $chk->num_rows > 0) {
        $existing = $chk->fetch_assoc();
        $qr_token = $existing['qr_token']; // retain persistent token
        $stmt = $conn->prepare("UPDATE emergency_cards SET public_fields_json = ? WHERE user_id = ?");
        $stmt->bind_param("si", $fields_json, $user_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO emergency_cards (user_id, public_fields_json, qr_token) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $fields_json, $qr_token);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'qr_token' => $qr_token, 'message' => 'Emergency Medical Card configuration saved']);
    exit;
}

// 8. Health Trends Metric Logger (Feature Group 5)
if ($action === 'add_health_trend') {
    $metric = trim($_POST['metric_type'] ?? '');
    $val = (float)($_POST['metric_value'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($metric) || $val <= 0) {
        echo json_encode(['success' => false, 'message' => 'Valid metric type and value required']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO health_trends (patient_id, metric_type, metric_value, unit, notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("isdss", $user_id, $metric, $val, $unit, $notes);
    $ok = $stmt->execute();
    echo json_encode(['success' => (bool)$ok, 'message' => 'Health metric recorded']);
    exit;
}

// 9. Upload & Categorize Medical Document (Feature Group 9)
if ($action === 'upload_medical_document') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? 'Other');

    if (empty($title) || !isset($_FILES['document_file'])) {
        echo json_encode(['success' => false, 'message' => 'Document title and file are required']);
        exit;
    }

    $file = $_FILES['document_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File upload error code: ' . $file['error']]);
        exit;
    }

    $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file format. Allowed: JPG, PNG, PDF, DOC']);
        exit;
    }

    $upload_dir = __DIR__ . '/uploads/medical_docs/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $file_name = 'DOC_' . $user_id . '_' . time() . '_' . rand(100, 999) . '.' . $ext;
    $target_file = $upload_dir . $file_name;
    $rel_path = 'uploads/medical_docs/' . $file_name;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $stmt = $conn->prepare("INSERT INTO medical_documents (patient_id, title, category, file_path) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $title, $category, $rel_path);
        $ok = $stmt->execute();
        echo json_encode(['success' => (bool)$ok, 'message' => 'Medical document uploaded and categorized successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save document file on server']);
    }
    exit;
}

if ($action === 'delete_medical_document') {
    $doc_id = (int)($_POST['doc_id'] ?? 0);
    $chk = $conn->query("SELECT file_path FROM medical_documents WHERE id = $doc_id AND patient_id = $user_id");
    if ($chk && $row = $chk->fetch_assoc()) {
        if (!empty($row['file_path']) && file_exists(__DIR__ . '/' . $row['file_path'])) {
            @unlink(__DIR__ . '/' . $row['file_path']);
        }
        $conn->query("DELETE FROM medical_documents WHERE id = $doc_id AND patient_id = $user_id");
        echo json_encode(['success' => true, 'message' => 'Document deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Document not found or access denied']);
    }
    exit;
}

// 10. Secure Selective Record Sharing (Feature Group 10)
if ($action === 'create_secure_share') {
    $items = $_POST['items'] ?? [];
    $exp_minutes = (int)($_POST['expiration_minutes'] ?? 60);

    if (empty($items) || !is_array($items)) {
        echo json_encode(['success' => false, 'message' => 'Please select at least one record to share']);
        exit;
    }

    $valid_exp = [15, 30, 60, 1440];
    if (!in_array($exp_minutes, $valid_exp)) $exp_minutes = 60;

    $expires_at = date('Y-m-d H:i:s', time() + ($exp_minutes * 60));
    $share_token = bin2hex(random_bytes(16));
    $items_json = json_encode($items);

    $stmt = $conn->prepare("INSERT INTO secure_shares (patient_id, share_token, items_json, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $user_id, $share_token, $items_json, $expires_at);
    $ok = $stmt->execute();

    if ($ok) {
        $share_url = 'view_shared.php?token=' . $share_token;
        echo json_encode(['success' => true, 'share_token' => $share_token, 'share_url' => $share_url, 'expires_at' => $expires_at, 'message' => 'Secure share link generated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create secure share token']);
    }
    exit;
}

if ($action === 'revoke_secure_share') {
    $share_id = (int)($_POST['share_id'] ?? 0);
    $conn->query("UPDATE secure_shares SET is_revoked = 1 WHERE id = $share_id AND patient_id = $user_id");
    echo json_encode(['success' => true, 'message' => 'Share access revoked immediately']);
    exit;
}

// 11. AI Doctor Preparation Assistant Questionnaire Save (Feature Group 30)
if ($action === 'save_doctor_prep') {
    $appt_id = (int)($_POST['appointment_id'] ?? 0);
    $concern = trim($_POST['main_concern'] ?? '');
    $duration = trim($_POST['duration'] ?? '');
    $severity = trim($_POST['severity'] ?? '');
    $meds = trim($_POST['current_medicines'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if (empty($concern)) {
        echo json_encode(['success' => false, 'message' => 'Main concern description is required']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO doctor_prep_summaries (patient_id, appointment_id, main_concern, duration, severity, current_medicines, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisssss", $user_id, $appt_id, $concern, $duration, $severity, $meds, $notes);
    $ok = $stmt->execute();

    echo json_encode(['success' => (bool)$ok, 'prep_id' => $conn->insert_id, 'message' => 'Doctor Preparation Summary generated for your upcoming visit']);
    exit;
}

// 12. Record Recently Viewed (Feature Group 35)
if ($action === 'record_recently_viewed') {
    $type = trim($_POST['item_type'] ?? '');
    $item_id = (int)($_POST['item_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');

    if (!empty($type) && $item_id > 0) {
        $stmt = $conn->prepare("INSERT INTO recently_viewed (user_id, item_type, item_id, title, url) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE title=VALUES(title), url=VALUES(url), viewed_at=CURRENT_TIMESTAMP");
        $stmt->bind_param("isiss", $user_id, $type, $item_id, $title, $url);
        $stmt->execute();
    }
    echo json_encode(['success' => true]);
    exit;
}

// 13. Save Notification Preferences (Feature Group 27)
if ($action === 'save_notification_preferences') {
    $ord = isset($_POST['order_notif']) ? 1 : 0;
    $app = isset($_POST['appointment_notif']) ? 1 : 0;
    $ref = isset($_POST['referral_notif']) ? 1 : 0;
    $pay = isset($_POST['payment_notif']) ? 1 : 0;
    $sys = isset($_POST['system_notif']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO notification_preferences (user_id, order_notif, appointment_notif, referral_notif, payment_notif, system_notif) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE order_notif=?, appointment_notif=?, referral_notif=?, payment_notif=?, system_notif=?");
    $stmt->bind_param("iiiiiiiiii", $user_id, $ord, $app, $ref, $pay, $sys, $ord, $app, $ref, $pay, $sys);
    $ok = $stmt->execute();
    echo json_encode(['success' => (bool)$ok, 'message' => 'Notification preferences saved']);
    exit;
}

// 14. Join Appointment Waitlist (Feature Group 9)
if ($action === 'join_waitlist') {
    $doctor_id = (int)($_POST['doctor_id'] ?? 0);
    $pref_date = trim($_POST['preferred_date'] ?? date('Y-m-d'));
    $notes = trim($_POST['notes'] ?? '');

    if ($doctor_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid doctor selection']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO appointment_waitlist (patient_id, doctor_id, preferred_date, notes) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $doctor_id, $pref_date, $notes);
    $ok = $stmt->execute();
    echo json_encode(['success' => (bool)$ok, 'message' => 'Successfully joined doctor appointment waitlist']);
    exit;
}

// 15. Reschedule Appointment (Feature Group 8)
if ($action === 'reschedule_appointment') {
    $appt_id = (int)($_POST['appointment_id'] ?? 0);
    $new_date = trim($_POST['new_date'] ?? '');
    $new_time = trim($_POST['new_time'] ?? '');

    if ($appt_id <= 0 || empty($new_date) || empty($new_time)) {
        echo json_encode(['success' => false, 'message' => 'Appointment ID, date, and time are required']);
        exit;
    }

    $chk = $conn->query("SELECT id FROM appointments WHERE id = $appt_id AND patient_id = $user_id");
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or access denied']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE appointments SET appointment_date = ?, appointment_time = ?, status = 'pending' WHERE id = ? AND patient_id = ?");
    $stmt->bind_param("ssii", $new_date, $new_time, $appt_id, $user_id);
    $ok = $stmt->execute();
    echo json_encode(['success' => (bool)$ok, 'message' => 'Appointment rescheduled successfully']);
    exit;
}

// 16. Report Order Issue (Feature Group 18)
if ($action === 'report_order_issue') {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $issue_type = trim($_POST['issue_type'] ?? 'General Issue');
    $desc = trim($_POST['description'] ?? '');

    if ($order_id <= 0 || empty($desc)) {
        echo json_encode(['success' => false, 'message' => 'Order ID and issue description required']);
        exit;
    }

    $stmt = $conn->prepare("INSERT INTO order_issues (patient_id, order_id, issue_type, description) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $user_id, $order_id, $issue_type, $desc);
    $ok = $stmt->execute();
    echo json_encode(['success' => (bool)$ok, 'message' => 'Order issue reported to Support and Admin']);
    exit;
}

// 17. Digital Queue Tracker Data (Feature Group 10)
if ($action === 'get_queue_tracker') {
    $appt_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;
    $queue_data = getPatientQueueData($conn, $user_id, $appt_id);
    echo json_encode(['success' => true, 'data' => $queue_data]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
