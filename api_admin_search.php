<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

// Mandatory Backend Authorization Check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access. Admin privileges required.'
    ]);
    exit;
}

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($q) || strlen($q) < 1) {
    echo json_encode([
        'success' => true,
        'query' => '',
        'total_results' => 0,
        'results' => [
            'patients' => [],
            'doctors' => [],
            'rmps' => [],
            'orders' => [],
            'payments' => [],
            'medical_cards' => [],
            'appointments' => []
        ]
    ]);
    exit;
}

$search_param = "%$q%";
$total_count = 0;

$results = [
    'patients' => [],
    'doctors' => [],
    'rmps' => [],
    'orders' => [],
    'payments' => [],
    'medical_cards' => [],
    'appointments' => []
];

// 1. Search Patients (role = 'patient')
$p_stmt = $conn->prepare("
    SELECT id, health_id, name, email, phone, role, created_at 
    FROM users 
    WHERE role = 'patient' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR health_id LIKE ? OR CAST(id AS CHAR) = ?) 
    LIMIT 8
");
$p_stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $q);
$p_stmt->execute();
$p_res = $p_stmt->get_result();
while ($row = $p_res->fetch_assoc()) {
    $results['patients'][] = [
        'id' => $row['id'],
        'health_id' => $row['health_id'] ?: ('MAK-' . str_pad($row['id'], 6, '0', STR_PAD_LEFT)),
        'name' => $row['name'],
        'email' => $row['email'],
        'phone' => $row['phone'],
        'link' => 'admin_users.php?search=' . urlencode($row['name'])
    ];
    $total_count++;
}

// 2. Search Doctors (role = 'doctor')
$d_stmt = $conn->prepare("
    SELECT id, name, email, phone, specialization, is_verified 
    FROM users 
    WHERE role = 'doctor' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR specialization LIKE ? OR CAST(id AS CHAR) = ?) 
    LIMIT 8
");
$d_stmt->bind_param("sssss", $search_param, $search_param, $search_param, $search_param, $q);
$d_stmt->execute();
$d_res = $d_stmt->get_result();
while ($row = $d_res->fetch_assoc()) {
    $results['doctors'][] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'specialization' => $row['specialization'] ?: 'General Physician',
        'contact' => $row['phone'] . ' | ' . $row['email'],
        'is_verified' => (bool)$row['is_verified'],
        'link' => 'admin_verify.php?page=1'
    ];
    $total_count++;
}

// 3. Search RMPs (role = 'rmp')
$r_stmt = $conn->prepare("
    SELECT id, name, email, phone, is_verified 
    FROM users 
    WHERE role = 'rmp' AND (name LIKE ? OR email LIKE ? OR phone LIKE ? OR CAST(id AS CHAR) = ?) 
    LIMIT 8
");
$r_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $q);
$r_stmt->execute();
$r_res = $r_stmt->get_result();
while ($row = $r_res->fetch_assoc()) {
    $results['rmps'][] = [
        'id' => $row['id'],
        'name' => $row['name'],
        'contact' => $row['phone'] . ' | ' . $row['email'],
        'is_verified' => (bool)$row['is_verified'],
        'link' => 'admin_verify.php?page=1'
    ];
    $total_count++;
}

// 4. Search Orders
$o_stmt = $conn->prepare("
    SELECT o.id, o.patient_id, o.total_amount, o.status, o.payment_status, o.payment_method, o.gateway_order_id, o.gateway_payment_id, o.created_at, u.name as patient_name 
    FROM orders o 
    JOIN users u ON o.patient_id = u.id 
    WHERE CAST(o.id AS CHAR) LIKE ? OR o.gateway_order_id LIKE ? OR o.gateway_payment_id LIKE ? OR u.name LIKE ? 
    ORDER BY o.id DESC LIMIT 8
");
$o_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
$o_stmt->execute();
$o_res = $o_stmt->get_result();
while ($row = $o_res->fetch_assoc()) {
    $results['orders'][] = [
        'id' => $row['id'],
        'order_ref' => '#ORD-' . str_pad($row['id'], 4, '0', STR_PAD_LEFT),
        'patient_name' => $row['patient_name'],
        'amount' => (float)$row['total_amount'],
        'status' => ucfirst($row['status']),
        'payment_status' => ucfirst($row['payment_status'] ?: 'Pending'),
        'gateway_payment_id' => $row['gateway_payment_id'] ?: 'N/A',
        'receipt_url' => 'digital_receipt.php?type=order&id=' . $row['id'],
        'link' => 'admin_orders_management.php?search=' . urlencode($row['id'])
    ];
    $total_count++;
}

// 5. Search Payments / Wallet Transactions
$w_stmt = $conn->prepare("
    SELECT wt.id, wt.transaction_id, wt.transaction_type, wt.direction, wt.amount, wt.status, wt.payment_id, wt.created_at, u.name as customer_name 
    FROM wallet_transactions wt 
    JOIN users u ON wt.customer_id = u.id 
    WHERE wt.transaction_id LIKE ? OR wt.payment_id LIKE ? OR u.name LIKE ? OR CAST(wt.amount AS CHAR) LIKE ? 
    ORDER BY wt.id DESC LIMIT 8
");
$w_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
$w_stmt->execute();
$w_res = $w_stmt->get_result();
while ($row = $w_res->fetch_assoc()) {
    $results['payments'][] = [
        'id' => $row['id'],
        'transaction_id' => $row['transaction_id'],
        'customer_name' => $row['customer_name'],
        'amount' => (float)$row['amount'],
        'direction' => $row['direction'],
        'type' => str_replace('_', ' ', $row['transaction_type']),
        'status' => ucfirst($row['status']),
        'receipt_url' => 'digital_receipt.php?type=wallet&id=' . urlencode($row['transaction_id']),
        'link' => 'admin_wallets.php'
    ];
    $total_count++;
}

// 6. Search Digital Medical Cards
$c_stmt = $conn->prepare("
    SELECT c.id, c.card_number, c.amount_paid, c.status, c.valid_until, c.gateway_payment_id, u.name as patient_name 
    FROM digital_medical_cards c 
    JOIN users u ON c.patient_id = u.id 
    WHERE c.card_number LIKE ? OR u.name LIKE ? OR c.gateway_payment_id LIKE ? OR CAST(c.id AS CHAR) = ? 
    ORDER BY c.id DESC LIMIT 8
");
$c_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $q);
$c_stmt->execute();
$c_res = $c_stmt->get_result();
while ($row = $c_res->fetch_assoc()) {
    $results['medical_cards'][] = [
        'id' => $row['id'],
        'card_number' => $row['card_number'] ?: ('DMC-' . $row['id']),
        'patient_name' => $row['patient_name'],
        'status' => ucfirst($row['status']),
        'amount' => (float)$row['amount_paid'],
        'validity' => $row['valid_until'] ? date('M Y', strtotime($row['valid_until'])) : 'Pending',
        'receipt_url' => 'digital_receipt.php?type=medical_card&id=' . urlencode($row['card_number'] ?: $row['id']),
        'link' => 'admin_digital_cards.php?search=' . urlencode($row['card_number'] ?: $row['patient_name'])
    ];
    $total_count++;
}

// 7. Search Appointments
$a_stmt = $conn->prepare("
    SELECT a.id, a.token_no, a.appointment_date, a.appointment_time, a.status, p.name as patient_name, d.name as doctor_name 
    FROM appointments a 
    JOIN users p ON a.patient_id = p.id 
    JOIN users d ON a.doctor_id = d.id 
    WHERE p.name LIKE ? OR d.name LIKE ? OR a.token_no LIKE ? OR CAST(a.id AS CHAR) = ? 
    ORDER BY a.id DESC LIMIT 8
");
$a_stmt->bind_param("ssss", $search_param, $search_param, $search_param, $q);
$a_stmt->execute();
$a_res = $a_stmt->get_result();
while ($row = $a_res->fetch_assoc()) {
    $results['appointments'][] = [
        'id' => $row['id'],
        'token_no' => $row['token_no'] ?: ('Appt #' . $row['id']),
        'patient_name' => $row['patient_name'],
        'doctor_name' => 'Dr. ' . $row['doctor_name'],
        'date' => date('M d, Y', strtotime($row['appointment_date'])) . ' ' . date('h:i A', strtotime($row['appointment_time'])),
        'status' => ucfirst($row['status']),
        'link' => 'admin_bookings.php'
    ];
    $total_count++;
}

echo json_encode([
    'success' => true,
    'query' => $q,
    'total_results' => $total_count,
    'results' => $results
]);
?>
