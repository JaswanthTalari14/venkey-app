<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// If already verified
if (!empty($_SESSION['security_gate_verified'])) {
    $redirect = $_SESSION['security_gate_redirect'] ?? 'index.php';
    unset($_SESSION['security_gate_redirect']);
    echo json_encode(['success' => true, 'message' => 'Already verified', 'redirect' => $redirect]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$input_key = isset($_POST['security_key']) ? trim($_POST['security_key']) : '';

if (empty($input_key)) {
    echo json_encode(['success' => false, 'message' => 'Security key is required.']);
    exit;
}

// Track failed attempts for brute-force mitigation
$failed_attempts = isset($_SESSION['gate_failed_attempts']) ? (int)$_SESSION['gate_failed_attempts'] : 0;
if ($failed_attempts >= 3) {
    usleep(500000); // 500ms delay on repeated failures
}

$expected_key = defined('SECURITY_GATE_KEY') ? SECURITY_GATE_KEY : 'ask mandotary:539539';

// Exact string comparison
if (hash_equals($expected_key, $input_key)) {
    $_SESSION['security_gate_verified'] = true;
    $_SESSION['gate_failed_attempts']   = 0;

    $redirect = $_SESSION['security_gate_redirect'] ?? 'index.php';
    if (empty($redirect) || strpos($redirect, 'security_gate.php') !== false) {
        $redirect = 'index.php';
    }
    unset($_SESSION['security_gate_redirect']);

    echo json_encode([
        'success'  => true,
        'message'  => 'Security Verified',
        'redirect' => $redirect
    ]);
    exit;
} else {
    $_SESSION['gate_failed_attempts'] = $failed_attempts + 1;
    echo json_encode([
        'success' => false,
        'message' => 'Incorrect security key. Please try again.'
    ]);
    exit;
}
