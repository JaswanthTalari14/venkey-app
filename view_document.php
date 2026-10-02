<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die("Access Denied: Authentication required.");
}

$user_id = (int)$_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'patient';

$type = isset($_GET['type']) ? $_GET['type'] : '';
$file = isset($_GET['file']) ? trim($_GET['file']) : '';

if (empty($file)) {
    http_response_code(400);
    die("Invalid request parameters.");
}

// Sanitize filename to prevent directory traversal
$clean_file = basename($file);
$target_path = '';

if ($type === 'verification') {
    if ($role !== 'admin') {
        http_response_code(403);
        die("Access Denied: Admin authorization required.");
    }
    $target_path = __DIR__ . '/uploads/verification_docs/' . $clean_file;
} elseif ($type === 'prescription') {
    // Check if prescription belongs to this patient or doctor
    $check_stmt = $conn->prepare("SELECT id FROM prescriptions WHERE file_path LIKE ? AND (patient_id = ? OR doctor_id = ? OR ? = 'admin')");
    $search_pattern = "%" . $clean_file;
    $check_stmt->bind_param("siis", $search_pattern, $user_id, $user_id, $role);
    $check_stmt->execute();
    $res = $check_stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        http_response_code(403);
        die("Access Denied: You do not have permission to view this prescription.");
    }
    $target_path = __DIR__ . '/uploads/prescriptions/' . $clean_file;
} else {
    // Fallback profile images or safe uploads
    $target_path = __DIR__ . '/' . ltrim($file, '/\\');
}

if (!file_exists($target_path)) {
    http_response_code(440);
    die("File not found.");
}

$mime = mime_content_type($target_path) ?: 'application/octet-stream';
header("Content-Type: " . $mime);
header("Content-Length: " . filesize($target_path));
header("Content-Disposition: inline; filename=\"" . $clean_file . "\"");
readfile($target_path);
exit;
?>
