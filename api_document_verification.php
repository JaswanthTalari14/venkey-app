<?php
require_once 'config.php';
header('Content-Type: application/json');

$action = $_REQUEST['action'] ?? '';
$user = get_logged_in_user();

switch ($action) {
    case 'verify_lookup':
        $vid = trim($_REQUEST['vid'] ?? $_REQUEST['verification_id'] ?? $_REQUEST['qr'] ?? '');
        if (empty($vid)) {
            echo json_encode(['success' => false, 'message' => 'Verification ID or QR code is required.']);
            exit;
        }

        $record = get_document_verification_by_id($conn, $vid);
        if (!$record) {
            echo json_encode([
                'success' => true,
                'found' => false,
                'status' => 'NOT_FOUND',
                'message' => 'No medical document found matching this Verification ID or QR code in MedicalIAk records.'
            ]);
            exit;
        }

        // Mask patient name for privacy compliance (e.g. John Doe -> J*** D***)
        $patient_name = $record['patient_name'] ?? 'Registered Patient';
        $parts = explode(' ', $patient_name);
        $masked_parts = array_map(function($p) {
            if (mb_strlen($p) <= 1) return $p;
            return mb_substr($p, 0, 1) . str_repeat('*', max(1, mb_strlen($p) - 1));
        }, $parts);
        $masked_name = implode(' ', $masked_parts);

        // Calculate hash preview
        $hash_preview = !empty($record['doc_hash']) ? substr($record['doc_hash'], 0, 16) . '...' . substr($record['doc_hash'], -16) : 'N/A';

        // Format response safely without exposing clinical notes or raw PII
        echo json_encode([
            'success' => true,
            'found' => true,
            'data' => [
                'verification_id' => $record['verification_id'],
                'doc_type' => ucwords(str_replace('_', ' ', $record['doc_type'])),
                'doc_type_raw' => $record['doc_type'],
                'status' => $record['status'],
                'issued_at' => $record['issued_at'] ? date('M d, Y', strtotime($record['issued_at'])) : 'N/A',
                'expires_at' => $record['expires_at'] ? date('M d, Y', strtotime($record['expires_at'])) : 'No Expiration',
                'masked_patient_name' => $masked_name,
                'patient_health_id' => $record['patient_health_id'] ?? null,
                'issuer_name' => $record['issuer_name'] ? 'Dr. ' . str_replace('Dr. ', '', $record['issuer_name']) : 'Authorized Medical Practitioner',
                'issuer_role' => ucwords($record['issuer_role'] ?? 'Doctor'),
                'issuer_specialization' => $record['issuer_specialization'] ?? 'Healthcare Professional',
                'issuer_is_verified' => (bool)($record['issuer_is_verified'] ?? true),
                'doc_hash' => $record['doc_hash'],
                'hash_preview' => $hash_preview,
                'revocation_reason' => $record['status'] === 'REVOKED' ? $record['revocation_reason'] : null,
                'revoked_at' => $record['status'] === 'REVOKED' ? $record['revoked_at'] : null
            ]
        ]);
        break;

    case 'verify_file_integrity':
        $vid = trim($_REQUEST['verification_id'] ?? '');
        if (!isset($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'Please upload a valid document file (PDF or image).']);
            exit;
        }

        $tmp_path = $_FILES['doc_file']['tmp_name'];
        $uploaded_hash = hash_file('sha256', $tmp_path);

        $record = null;
        if (!empty($vid)) {
            $record = get_document_verification_by_id($conn, $vid);
        }

        if (!$record) {
            // Search by hash in DB
            $stmt = $conn->prepare("
                SELECT v.*, 
                       p.name as patient_name, p.health_id as patient_health_id,
                       i.name as issuer_name, i.role as issuer_role, i.specialization as issuer_specialization
                FROM document_verifications v
                LEFT JOIN users p ON v.patient_id = p.id
                LEFT JOIN users i ON v.issuer_id = i.id
                WHERE v.doc_hash = ?
            ");
            $stmt->bind_param("s", $uploaded_hash);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res && $res->num_rows > 0) {
                $record = $res->fetch_assoc();
            }
        }

        if ($record) {
            $is_authentic = (strtolower($record['doc_hash']) === strtolower($uploaded_hash));
            
            $patient_name = $record['patient_name'] ?? 'Registered Patient';
            $parts = explode(' ', $patient_name);
            $masked_parts = array_map(function($p) {
                if (mb_strlen($p) <= 1) return $p;
                return mb_substr($p, 0, 1) . str_repeat('*', max(1, mb_strlen($p) - 1));
            }, $parts);
            $masked_name = implode(' ', $masked_parts);

            echo json_encode([
                'success' => true,
                'match_found' => true,
                'is_authentic' => $is_authentic && ($record['status'] === 'VERIFIED'),
                'status' => $record['status'],
                'uploaded_hash' => $uploaded_hash,
                'registered_hash' => $record['doc_hash'],
                'verification_id' => $record['verification_id'],
                'doc_type' => ucwords(str_replace('_', ' ', $record['doc_type'])),
                'masked_patient_name' => $masked_name,
                'issuer_name' => $record['issuer_name'] ?? 'Authorized Medical Specialist',
                'issued_at' => $record['issued_at'] ? date('M d, Y', strtotime($record['issued_at'])) : 'N/A'
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'match_found' => false,
                'is_authentic' => false,
                'uploaded_hash' => $uploaded_hash,
                'message' => 'The uploaded file SHA-256 digital signature does not match any official document registered in MedicalIAk system. This document may be modified, unverified, or issued outside MedicalIAk.'
            ]);
        }
        break;

    case 'revoke':
        if (!$user || !in_array($user['role'], ['doctor', 'rmp', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized. Only doctors, RMPs, or admins can revoke medical documents.']);
            exit;
        }

        $vid = trim($_POST['verification_id'] ?? '');
        $reason = trim($_POST['reason'] ?? '');

        if (empty($vid) || empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Verification ID and a mandatory revocation reason are required.']);
            exit;
        }

        $record = get_document_verification_by_id($conn, $vid);
        if (!$record) {
            echo json_encode(['success' => false, 'message' => 'Document record not found.']);
            exit;
        }

        // Doctor/RMP check ownership unless admin
        if ($user['role'] !== 'admin' && $record['issuer_id'] != $user['id']) {
            echo json_encode(['success' => false, 'message' => 'You can only revoke medical documents issued by yourself.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE document_verifications SET status = 'REVOKED', revocation_reason = ?, revoked_by = ?, revoked_at = NOW() WHERE verification_id = ?");
        $stmt->bind_param("sis", $reason, $user['id'], $vid);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Document has been successfully revoked. Verification status updated to REVOKED.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update revocation status.']);
        }
        break;

    case 'report_suspicious':
        $vid = trim($_POST['verification_id'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        $details = trim($_POST['details'] ?? '');
        $reporter_email = trim($_POST['reporter_email'] ?? ($user['email'] ?? ''));

        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Please provide a reason for reporting this document.']);
            exit;
        }

        $reporter_id = $user['id'] ?? null;
        $stmt = $conn->prepare("INSERT INTO document_verification_reports (verification_id, reporter_id, reporter_email, reason, details) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sisss", $vid, $reporter_id, $reporter_email, $reason, $details);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Thank you. Your report has been submitted to MedicalIAk Security & Compliance team for immediate investigation.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to submit report. Please try again.']);
        }
        break;

    case 'get_patient_verifications':
        if (!$user) {
            echo json_encode(['success' => false, 'message' => 'Authentication required.']);
            exit;
        }

        $patient_id = intval($_GET['patient_id'] ?? $user['id']);
        if ($user['role'] === 'patient' && $patient_id !== $user['id']) {
            $patient_id = $user['id'];
        }

        $stmt = $conn->prepare("
            SELECT v.*, i.name as issuer_name, i.role as issuer_role
            FROM document_verifications v
            LEFT JOIN users i ON v.issuer_id = i.id
            WHERE v.patient_id = ?
            ORDER BY v.issued_at DESC
        ");
        $stmt->bind_param("i", $patient_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $list = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row['doc_type_formatted'] = ucwords(str_replace('_', ' ', $row['doc_type']));
                $row['issuer_display'] = $row['issuer_name'] ? 'Dr. ' . str_replace('Dr. ', '', $row['issuer_name']) : 'MedicalIAk Health Center';
                $row['hash_preview'] = !empty($row['doc_hash']) ? substr($row['doc_hash'], 0, 10) . '...' . substr($row['doc_hash'], -8) : 'N/A';
                $list[] = $row;
            }
        }

        echo json_encode(['success' => true, 'data' => $list]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
        break;
}
