<?php
require_once 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Doctor authorization required']);
    exit;
}

$doctor_id = (int)$_SESSION['user_id'];
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : '';

// 1. Update Appointment Status (Feature Group 15)
if ($action === 'update_appointment_status') {
    $appt_id = (int)($_POST['appointment_id'] ?? 0);
    $status = trim($_POST['status'] ?? '');

    $allowed_statuses = ['pending', 'confirmed', 'waiting', 'in_consultation', 'completed', 'cancelled', 'no_show'];
    if ($appt_id <= 0 || !in_array($status, $allowed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid appointment ID or status']);
        exit;
    }

    // Verify ownership
    $chk = $conn->query("SELECT id, patient_id FROM appointments WHERE id = $appt_id AND doctor_id = $doctor_id");
    if (!$chk || $chk->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or access denied']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ? AND doctor_id = ?");
    $stmt->bind_param("sii", $status, $appt_id, $doctor_id);
    $ok = $stmt->execute();

    // Trigger Notification for Patient
    $appt_row = $chk->fetch_assoc();
    $pid = (int)$appt_row['patient_id'];
    require_once 'includes/notification_functions.php';
    if (function_exists('send_user_notification')) {
        send_user_notification($pid, 'appointment', 'Appointment Update', "Your appointment #$appt_id status updated to: " . ucfirst($status), 'appointment', $appt_id);
    }

    echo json_encode(['success' => (bool)$ok, 'message' => 'Appointment status updated to ' . ucfirst($status)]);
    exit;
}

// 2. Save Doctor Consultation Workspace & Generate Prescription (Feature Groups 11 & 13)
if ($action === 'save_consultation') {
    $appt_id = (int)($_POST['appointment_id'] ?? 0);
    $patient_id = (int)($_POST['patient_id'] ?? 0);
    $chief_complaint = trim($_POST['chief_complaint'] ?? '');
    $clinical_notes = trim($_POST['clinical_notes'] ?? '');
    $diagnosis = trim($_POST['diagnosis'] ?? '');
    $followup_date = trim($_POST['followup_date'] ?? '');
    $medicines = $_POST['medicines'] ?? []; // Array of [{name, dosage, frequency, duration, instructions}]

    if ($patient_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
        exit;
    }

    // Begin DB Transaction
    $conn->begin_transaction();

    try {
        // Update Appointment Notes & Status
        if ($appt_id > 0) {
            $fdate_val = !empty($followup_date) ? $followup_date : null;
            $stmt = $conn->prepare("UPDATE appointments SET status = 'completed', chief_complaint = ?, clinical_notes = ?, diagnosis = ?, followup_date = ? WHERE id = ? AND doctor_id = ?");
            $stmt->bind_param("ssssii", $chief_complaint, $clinical_notes, $diagnosis, $fdate_val, $appt_id, $doctor_id);
            $stmt->execute();
        }

        // Save Consultation Note
        $notes_text = "Chief Complaint: $chief_complaint\nDiagnosis: $diagnosis\nNotes: $clinical_notes";
        $c_stmt = $conn->prepare("INSERT INTO consultation_notes (doctor_id, patient_id, appointment_id, notes) VALUES (?, ?, ?, ?)");
        $c_stmt->bind_param("iiis", $doctor_id, $patient_id, $appt_id, $notes_text);
        $c_stmt->execute();

        // Create Digital Prescription Record
        $rx_stmt = $conn->prepare("INSERT INTO prescriptions (patient_id, doctor_id, appointment_id, consultation_date, notes) VALUES (?, ?, ?, CURDATE(), ?)");
        $rx_stmt->bind_param("iiis", $patient_id, $doctor_id, $appt_id, $notes_text);
        $rx_stmt->execute();
        $prescription_id = $conn->insert_id;
        register_document_verification($conn, 'prescription', $prescription_id, $patient_id, $doctor_id, 'doctor');

        // Insert Prescription Medicine Items
        if (is_array($medicines) && !empty($medicines)) {
            $item_stmt = $conn->prepare("INSERT INTO prescription_items (prescription_id, medicine_name, dosage, frequency, duration, instructions) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($medicines as $med) {
                $mname = trim($med['name'] ?? '');
                $mdosage = trim($med['dosage'] ?? '1 tablet');
                $mfreq = trim($med['frequency'] ?? 'Twice daily');
                $mdur = trim($med['duration'] ?? '5 days');
                $minst = trim($med['instructions'] ?? 'After meals');
                if (!empty($mname)) {
                    $item_stmt->bind_param("isssss", $prescription_id, $mname, $mdosage, $mfreq, $mdur, $minst);
                    $item_stmt->execute();
                }
            }
        }

        $conn->commit();

        // Send Notification
        require_once 'includes/notification_functions.php';
        if (function_exists('send_user_notification')) {
            send_user_notification($patient_id, 'prescription', 'New Prescription Issued', "Dr. " . $_SESSION['name'] . " issued a digital prescription (Rx #$prescription_id).", 'prescription', $prescription_id);
        }

        echo json_encode(['success' => true, 'prescription_id' => $prescription_id, 'message' => 'Consultation saved and Digital Prescription issued successfully']);
    } catch (Throwable $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// 3. Save Doctor Schedule & Availability (Feature Group 14)
if ($action === 'save_availability') {
    $schedule = $_POST['schedule'] ?? []; // Array of [{day, start, end, is_available}]
    if (is_array($schedule)) {
        $conn->query("DELETE FROM doctor_availability WHERE doctor_id = $doctor_id");
        $stmt = $conn->prepare("INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available) VALUES (?, ?, ?, ?, ?)");
        foreach ($schedule as $s) {
            $day = trim($s['day'] ?? '');
            $start = trim($s['start'] ?? '09:00:00');
            $end = trim($s['end'] ?? '17:00:00');
            $avail = !empty($s['is_available']) ? 1 : 0;
            if (!empty($day)) {
                $stmt->bind_param("isssi", $doctor_id, $day, $start, $end, $avail);
                $stmt->execute();
            }
        }
    }
    echo json_encode(['success' => true, 'message' => 'Doctor availability schedule updated']);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
