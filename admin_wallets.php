<?php
require_once 'config.php';
require_once 'includes/wallet_functions.php';

// Server-side strict authorization check: Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = (int)$_SESSION['user_id'];

// AJAX Endpoint: Fetch Customer Wallet Details for Inspection Modal
if (isset($_GET['action']) && $_GET['action'] === 'get_customer_details') {
    header('Content-Type: application/json');
    $cust_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

    if ($cust_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid customer ID.']);
        exit;
    }

    // Customer Info
    $u_stmt = $conn->prepare("SELECT id, name, email, COALESCE(phone, mobile, '') as mobile, created_at FROM users WHERE id = ? AND role = 'patient'");
    $u_stmt->bind_param("i", $cust_id);
    $u_stmt->execute();
    $u_res = $u_stmt->get_result();

    if (!$u_res || $u_res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Customer profile not found.']);
        exit;
    }

    $customer = $u_res->fetch_assoc();

    // Wallet Info
    $wallet = get_or_create_wallet($cust_id);

    // Stats for Customer
    $app_top = $conn->query("SELECT COUNT(*) as cnt, SUM(amount) as amt FROM wallet_topups WHERE customer_id = $cust_id AND status = 'approved'")->fetch_assoc();
    $rej_top = $conn->query("SELECT COUNT(*) as cnt, SUM(amount) as amt FROM wallet_topups WHERE customer_id = $cust_id AND status = 'rejected'")->fetch_assoc();
    $spent_ord = $conn->query("SELECT SUM(amount) as amt FROM wallet_transactions WHERE customer_id = $cust_id AND transaction_type = 'payment' AND status = 'completed'")->fetch_assoc();
    $ref_ord = $conn->query("SELECT SUM(amount) as amt FROM wallet_transactions WHERE customer_id = $cust_id AND transaction_type = 'refund' AND status = 'completed'")->fetch_assoc();
    $adm_adj = $conn->query("SELECT SUM(amount) as amt FROM wallet_transactions WHERE customer_id = $cust_id AND transaction_type IN ('admin_credit', 'admin_debit') AND status = 'completed'")->fetch_assoc();

    // Transactions History for Customer
    $tx_res = $conn->query("SELECT * FROM wallet_transactions WHERE customer_id = $cust_id ORDER BY created_at DESC LIMIT 50");
    $transactions = [];
    if ($tx_res) {
        while ($t = $tx_res->fetch_assoc()) {
            $transactions[] = [
                'transaction_id' => $t['transaction_id'],
                'transaction_type' => str_replace('_', ' ', $t['transaction_type']),
                'direction' => $t['direction'],
                'amount' => floatval($t['amount']),
                'previous_balance' => floatval($t['previous_balance']),
                'new_balance' => floatval($t['new_balance']),
                'status' => $t['status'],
                'order_id' => $t['order_id'],
                'reason' => $t['reason'],
                'created_at' => date('M d, Y h:i A', strtotime($t['created_at']))
            ];
        }
    }

    // Topups History for Customer
    $top_res = $conn->query("SELECT * FROM wallet_topups WHERE customer_id = $cust_id ORDER BY created_at DESC LIMIT 20");
    $topups = [];
    if ($top_res) {
        while ($tp = $top_res->fetch_assoc()) {
            $topups[] = [
                'topup_id' => $tp['topup_id'],
                'amount' => floatval($tp['amount']),
                'paid_amount' => floatval($tp['paid_amount'] ?? 0),
                'payment_method' => $tp['payment_method'],
                'gateway_reference' => $tp['gateway_reference'],
                'payment_id' => $tp['payment_id'],
                'status' => $tp['status'],
                'rejection_reason' => $tp['rejection_reason'],
                'created_at' => date('M d, Y h:i A', strtotime($tp['created_at']))
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'customer' => $customer,
        'wallet' => [
            'available_balance' => floatval($wallet['available_balance']),
            'pending_balance' => floatval($wallet['pending_balance'])
        ],
        'stats' => [
            'approved_topups_cnt' => intval($app_top['cnt'] ?? 0),
            'approved_topups_amt' => floatval($app_top['amt'] ?? 0),
            'rejected_topups_cnt' => intval($rej_top['cnt'] ?? 0),
            'rejected_topups_amt' => floatval($rej_top['amt'] ?? 0),
            'spent_orders_amt' => floatval($spent_ord['amt'] ?? 0),
            'refunds_amt' => floatval($ref_ord['amt'] ?? 0),
        ],
        'transactions' => $transactions,
        'topups' => $topups
    ]);
    exit;
}

// AJAX Endpoint: Fetch Specific Top-Up Details & Audit Trail
if (isset($_GET['action']) && $_GET['action'] === 'get_topup_details') {
    header('Content-Type: application/json');
    $topup_id = isset($_GET['topup_id']) ? trim($_GET['topup_id']) : '';

    if (empty($topup_id)) {
        echo json_encode(['success' => false, 'message' => 'Top-up ID required.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT t.*, u.name as customer_name, COALESCE(u.phone, u.mobile, 'N/A') as customer_mobile, u.email as customer_email,
               COALESCE(w.available_balance, 0.00) as available_balance
        FROM wallet_topups t
        LEFT JOIN users u ON t.customer_id = u.id
        LEFT JOIN wallets w ON t.customer_id = w.customer_id
        WHERE t.topup_id = ?
    ");
    $stmt->bind_param("s", $topup_id);
    $stmt->execute();
    $topup_res = $stmt->get_result();

    if (!$topup_res || $topup_res->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Top-up request not found.']);
        exit;
    }

    $topup = $topup_res->fetch_assoc();

    // Duplicate UTR / Payment ID check
    $dup_check = false;
    $dup_records = [];
    $gw_ref = $topup['gateway_reference'];
    $pay_id = $topup['payment_id'];
    if (!empty($gw_ref) || !empty($pay_id)) {
        $chk = $conn->prepare("SELECT topup_id, amount, status, created_at FROM wallet_topups WHERE topup_id != ? AND ((gateway_reference = ? AND gateway_reference != '') OR (payment_id = ? AND payment_id != ''))");
        $chk->bind_param("sss", $topup_id, $gw_ref, $pay_id);
        $chk->execute();
        $c_res = $chk->get_result();
        if ($c_res && $c_res->num_rows > 0) {
            $dup_check = true;
            while ($dr = $c_res->fetch_assoc()) {
                $dup_records[] = $dr;
            }
        }
    }

    // Amount Mismatch check
    $req_amt = floatval($topup['amount']);
    $paid_amt = floatval($topup['paid_amount'] ?? 0);
    $is_mismatch = ($paid_amt > 0 && abs($req_amt - $paid_amt) > 0.01);

    // Audit logs
    $aud_stmt = $conn->prepare("
        SELECT l.*, COALESCE(u.name, CONCAT('Admin #', l.admin_id)) as admin_name 
        FROM wallet_audit_logs l 
        LEFT JOIN users u ON l.admin_id = u.id 
        WHERE l.topup_id = ? 
        ORDER BY l.created_at ASC
    ");
    $aud_stmt->bind_param("s", $topup_id);
    $aud_stmt->execute();
    $aud_res = $aud_stmt->get_result();
    $audit_logs = [];
    if ($aud_res) {
        while ($al = $aud_res->fetch_assoc()) {
            $audit_logs[] = [
                'action' => ucfirst($al['action']),
                'prev_status' => $al['prev_status'],
                'new_status' => $al['new_status'],
                'admin_name' => $al['admin_name'],
                'reason' => $al['reason'],
                'timestamp' => date('M d, Y h:i A', strtotime($al['created_at']))
            ];
        }
    }

    // Timeline events
    $timeline = [
        [
            'title' => 'Request Created',
            'desc' => "Customer requested ₹" . number_format($req_amt, 2) . " via " . ($topup['payment_method'] ?: 'online'),
            'timestamp' => date('M d, Y h:i A', strtotime($topup['created_at'])),
            'completed' => true
        ]
    ];

    $st = strtolower($topup['status']);
    if ($st === 'pending' || $st === 'pending_approval' || $st === 'amount_mismatch') {
        $timeline[] = [
            'title' => 'Pending Admin Verification',
            'desc' => 'Awaiting payment verification by Admin',
            'timestamp' => date('M d, Y h:i A', strtotime($topup['updated_at'])),
            'completed' => false
        ];
    } else if ($st === 'approved') {
        $timeline[] = [
            'title' => 'Approved & Credited',
            'desc' => "₹" . number_format($paid_amt > 0 ? $paid_amt : $req_amt, 2) . " added to available balance",
            'timestamp' => date('M d, Y h:i A', strtotime($topup['approved_at'] ?? $topup['updated_at'])),
            'completed' => true
        ];
    } else if ($st === 'rejected' || $st === 'payment_failed') {
        $timeline[] = [
            'title' => 'Request Rejected',
            'desc' => "Reason: " . ($topup['rejection_reason'] ?: 'Verification Failed'),
            'timestamp' => date('M d, Y h:i A', strtotime($topup['approved_at'] ?? $topup['updated_at'])),
            'completed' => true
        ];
    } else if ($st === 'reversed') {
        $timeline[] = [
            'title' => 'Credit Reversed',
            'desc' => "Reason: " . ($topup['rejection_reason'] ?: 'Admin Reversal'),
            'timestamp' => date('M d, Y h:i A', strtotime($topup['updated_at'])),
            'completed' => true
        ];
    }

    echo json_encode([
        'success' => true,
        'topup' => [
            'topup_id' => $topup['topup_id'],
            'customer_id' => $topup['customer_id'],
            'customer_name' => $topup['customer_name'],
            'customer_mobile' => $topup['customer_mobile'],
            'customer_email' => $topup['customer_email'],
            'available_balance' => floatval($topup['available_balance']),
            'amount' => $req_amt,
            'paid_amount' => $paid_amt,
            'payment_method' => $topup['payment_method'],
            'gateway_reference' => $topup['gateway_reference'],
            'payment_id' => $topup['payment_id'],
            'status' => $topup['status'],
            'rejection_reason' => $topup['rejection_reason'],
            'created_at' => date('M d, Y h:i A', strtotime($topup['created_at'])),
            'updated_at' => date('M d, Y h:i A', strtotime($topup['updated_at'])),
            'approved_at' => $topup['approved_at'] ? date('M d, Y h:i A', strtotime($topup['approved_at'])) : null
        ],
        'duplicate_check' => [
            'has_duplicate' => $dup_check,
            'records' => $dup_records
        ],
        'amount_mismatch' => [
            'has_mismatch' => $is_mismatch,
            'requested' => $req_amt,
            'paid' => $paid_amt
        ],
        'audit_logs' => $audit_logs,
        'timeline' => $timeline
    ]);
    exit;
}

// AJAX Endpoint: Unread/New Pending Topups Count
if (isset($_GET['action']) && $_GET['action'] === 'get_unread_topups_count') {
    header('Content-Type: application/json');
    $res = $conn->query("SELECT COUNT(*) as cnt FROM wallet_topups WHERE (LOWER(status) IN ('pending_approval', 'amount_mismatch', 'pending') OR status IS NULL OR status = '' OR LOWER(status) NOT IN ('approved', 'rejected', 'payment_failed')) AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $cnt = intval($res ? $res->fetch_assoc()['cnt'] : 0);
    echo json_encode(['success' => true, 'pending_count' => $cnt]);
    exit;
}

// AJAX Endpoint: Financial Reconciliation Report
if (isset($_GET['action']) && $_GET['action'] === 'get_reconciliation_report') {
    header('Content-Type: application/json');
    $tot_req = $conn->query("SELECT COUNT(*) as cnt, SUM(amount) as amt FROM wallet_topups")->fetch_assoc();
    $tot_app = $conn->query("SELECT COUNT(*) as cnt, SUM(COALESCE(paid_amount, amount)) as amt FROM wallet_topups WHERE LOWER(status) = 'approved'")->fetch_assoc();
    $tot_tx = $conn->query("SELECT COUNT(*) as cnt, SUM(amount) as amt FROM wallet_transactions WHERE transaction_type = 'topup_approved' AND status = 'completed'")->fetch_assoc();
    $tot_rej = $conn->query("SELECT COUNT(*) as cnt, SUM(amount) as amt FROM wallet_topups WHERE LOWER(status) IN ('rejected', 'payment_failed')")->fetch_assoc();

    $app_amt = floatval($tot_app['amt'] ?? 0);
    $tx_amt = floatval($tot_tx['amt'] ?? 0);
    $discrepancy = abs($app_amt - $tx_amt) > 0.01;

    echo json_encode([
        'success' => true,
        'total_requests' => intval($tot_req['cnt'] ?? 0),
        'total_requested_amount' => floatval($tot_req['amt'] ?? 0),
        'approved_requests' => intval($tot_app['cnt'] ?? 0),
        'approved_amount' => $app_amt,
        'ledger_credited_amount' => $tx_amt,
        'rejected_requests' => intval($tot_rej['cnt'] ?? 0),
        'rejected_amount' => floatval($tot_rej['amt'] ?? 0),
        'has_discrepancy' => $discrepancy
    ]);
    exit;
}

// CSV Export Action
if (isset($_GET['export']) && $_GET['export'] === 'topups_csv') {
    $t_status = isset($_GET['topup_status']) ? strtolower(trim($_GET['topup_status'])) : 'all';
    $t_search = isset($_GET['topup_search']) ? trim($_GET['topup_search']) : '';
    $date_range = isset($_GET['date_range']) ? trim($_GET['date_range']) : 'all';

    $where = [];
    if ($t_status === 'pending') {
        $where[] = "(LOWER(t.status) IN ('pending_approval', 'amount_mismatch', 'pending') OR t.status IS NULL OR t.status = '' OR LOWER(t.status) NOT IN ('approved', 'rejected', 'payment_failed'))";
    } else if ($t_status === 'approved') {
        $where[] = "LOWER(t.status) = 'approved'";
    } else if ($t_status === 'rejected') {
        $where[] = "LOWER(t.status) IN ('rejected', 'payment_failed')";
    }

    if (!empty($t_search)) {
        $ts = $conn->real_escape_string($t_search);
        $where[] = "(t.topup_id LIKE '%$ts%' OR u.name LIKE '%$ts%' OR u.phone LIKE '%$ts%' OR u.mobile LIKE '%$ts%' OR u.email LIKE '%$ts%' OR t.gateway_reference LIKE '%$ts%' OR t.payment_id LIKE '%$ts%')";
    }

    if ($date_range === 'today') {
        $where[] = "DATE(t.created_at) = CURDATE()";
    } else if ($date_range === 'this_week') {
        $where[] = "YEARWEEK(t.created_at, 1) = YEARWEEK(CURDATE(), 1)";
    } else if ($date_range === 'this_month') {
        $where[] = "YEAR(t.created_at) = YEAR(CURDATE()) AND MONTH(t.created_at) = MONTH(CURDATE())";
    } else if ($date_range === 'custom' && !empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $sd = $conn->real_escape_string($_GET['start_date']);
        $ed = $conn->real_escape_string($_GET['end_date']);
        $where[] = "DATE(t.created_at) BETWEEN '$sd' AND '$ed'";
    }

    $where_sql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $query = "
        SELECT t.topup_id, COALESCE(u.name, CONCAT('Customer #', t.customer_id)) as customer_name,
               COALESCE(u.phone, u.mobile, 'N/A') as phone, t.amount, t.paid_amount,
               t.payment_method, t.gateway_reference, t.payment_id, t.status,
               t.rejection_reason, t.created_at, t.approved_at
        FROM wallet_topups t
        LEFT JOIN users u ON t.customer_id = u.id
        $where_sql
        ORDER BY t.created_at DESC
    ";

    $res = $conn->query($query);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="wallet_topups_export_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Topup ID', 'Customer Name', 'Phone', 'Requested Amount (INR)', 'Paid Amount (INR)', 'Payment Method', 'Gateway Order ID', 'Payment ID', 'Status', 'Rejection Reason', 'Created At', 'Approved At']);

    if ($res) {
        while ($row = $res->fetch_assoc()) {
            fputcsv($output, [
                $row['topup_id'],
                $row['customer_name'],
                $row['phone'],
                number_format($row['amount'], 2, '.', ''),
                number_format($row['paid_amount'] ?? 0, 2, '.', ''),
                $row['payment_method'],
                $row['gateway_reference'],
                $row['payment_id'],
                strtoupper($row['status']),
                $row['rejection_reason'],
                $row['created_at'],
                $row['approved_at']
            ]);
        }
    }
    fclose($output);
    exit;
}

$message = '';
$msg_type = 'info';

// Handle Administrative POST Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'adjust_wallet') {
        $customer_id = intval($_POST['customer_id']);
        $adj_type = $_POST['type']; // 'credit' or 'debit'
        $amount = floatval($_POST['amount']);
        $reason = trim($_POST['reason']);

        if ($customer_id <= 0 || $amount <= 0 || empty($reason)) {
            $message = "Please specify a valid customer, amount greater than zero, and a mandatory reason.";
            $msg_type = 'danger';
        } else {
            $res = admin_adjust_wallet($admin_id, $customer_id, $adj_type, $amount, $reason);
            if ($res['success']) {
                $message = "Customer wallet balance updated successfully. Transaction ID: " . $res['tx_id'];
                $msg_type = 'success';
            } else {
                $message = "Wallet adjustment failed: " . $res['message'];
                $msg_type = 'danger';
            }
        }
    } else if ($action === 'toggle_freeze') {
        $customer_id = intval($_POST['customer_id']);
        $new_status = $_POST['status']; // 'active' or 'frozen'
        if (toggle_wallet_freeze($admin_id, $customer_id, $new_status)) {
            $message = "Customer wallet status set to " . strtoupper($new_status) . ".";
            $msg_type = 'success';
        } else {
            $message = "Failed to update wallet status.";
            $msg_type = 'danger';
        }
    } else if ($action === 'approve_topup') {
        $topup_id = trim($_POST['topup_id']);
        $res = verify_and_complete_topup($topup_id, $admin_id);
        if ($res['success']) {
            $message = "Top-up request " . htmlspecialchars($topup_id) . " approved and credited successfully.";
            $msg_type = 'success';
        } else {
            $message = $res['message'];
            $msg_type = 'danger';
        }
    } else if ($action === 'reject_topup') {
        $topup_id = trim($_POST['topup_id']);
        $reason = trim($_POST['reason']);
        if (empty($reason)) {
            $reason = 'Payment verification failed';
        }
        $res = reject_topup_with_reason($topup_id, $admin_id, $reason);
        if ($res['success']) {
            $message = "Top-up request " . htmlspecialchars($topup_id) . " rejected successfully.";
            $msg_type = 'info';
        } else {
            $message = $res['message'];
            $msg_type = 'danger';
        }
    } else if ($action === 'reverse_topup') {
        $topup_id = trim($_POST['topup_id']);
        $reason = trim($_POST['reason']);
        if (empty($reason)) {
            $reason = 'Admin Credit Reversal';
        }
        $res = reverse_approved_topup($topup_id, $admin_id, $reason);
        if ($res['success']) {
            $message = "Top-up request " . htmlspecialchars($topup_id) . " credit reversed successfully.";
            $msg_type = 'info';
        } else {
            $message = $res['message'];
            $msg_type = 'danger';
        }
    } else if ($action === 'update_settings') {
        $min_topup = floatval($_POST['min_topup']);
        $max_topup = floatval($_POST['max_topup']);
        if ($min_topup < 1 || $max_topup < $min_topup) {
            $message = "Invalid minimum or maximum top-up limit configuration.";
            $msg_type = 'danger';
        } else if (update_wallet_settings($min_topup, $max_topup)) {
            $message = "Customer wallet top-up limits updated successfully.";
            $msg_type = 'success';
        } else {
            $message = "Failed to update top-up limits.";
            $msg_type = 'danger';
        }
    }
}

$settings = get_wallet_settings();

// Platform Management Statistics
$tot_bal_res = $conn->query("SELECT SUM(available_balance) as total FROM wallets");
$platform_wallet_balance = floatval($tot_bal_res->fetch_assoc()['total'] ?? 0);

$pend_top_res = $conn->query("SELECT COUNT(*) as cnt FROM wallet_topups WHERE (LOWER(status) IN ('pending_approval', 'amount_mismatch', 'pending') OR status IS NULL OR status = '' OR LOWER(status) NOT IN ('approved', 'rejected', 'payment_failed'))");
$pending_topups_count = intval($pend_top_res ? $pend_top_res->fetch_assoc()['cnt'] : 0);

$app_top_res = $conn->query("SELECT COUNT(*) as cnt FROM wallet_topups WHERE LOWER(status) = 'approved'");
$approved_topups_count = intval($app_top_res ? $app_top_res->fetch_assoc()['cnt'] : 0);

$rej_top_res = $conn->query("SELECT COUNT(*) as cnt FROM wallet_topups WHERE LOWER(status) IN ('rejected', 'payment_failed')");
$rejected_topups_count = intval($rej_top_res ? $rej_top_res->fetch_assoc()['cnt'] : 0);

// Search & Customer Wallets Directory
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = "WHERE u.role = 'patient'";

if ($search !== '') {
    $search_safe = $conn->real_escape_string($search);
    $where_clause .= " AND (u.name LIKE '%$search_safe%' OR u.phone LIKE '%$search_safe%' OR u.mobile LIKE '%$search_safe%' OR u.id LIKE '%$search_safe%' OR u.email LIKE '%$search_safe%')";
}

$customer_wallets = $conn->query("
    SELECT u.id as customer_id, u.name, COALESCE(u.phone, u.mobile, 'N/A') as mobile, u.email, u.profile_image,
           COALESCE(w.available_balance, 0.00) as available_balance,
           COALESCE(w.pending_balance, 0.00) as pending_balance,
           COALESCE(w.status, 'active') as wallet_status,
           w.updated_at as last_tx_date
    FROM users u
    LEFT JOIN wallets w ON u.id = w.customer_id
    $where_clause
    ORDER BY w.available_balance DESC
");

// Top-ups Verification Queue with Search, Status, and Date Filtering
$topup_status = isset($_GET['topup_status']) ? strtolower(trim($_GET['topup_status'])) : 'pending';
$topup_search = isset($_GET['topup_search']) ? trim($_GET['topup_search']) : '';
$date_range = isset($_GET['date_range']) ? trim($_GET['date_range']) : 'all';
$start_date = isset($_GET['start_date']) ? trim($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? trim($_GET['end_date']) : '';

$t_where = [];

if ($topup_status === 'pending') {
    $t_where[] = "(LOWER(t.status) IN ('pending_approval', 'amount_mismatch', 'pending') OR t.status IS NULL OR t.status = '' OR LOWER(t.status) NOT IN ('approved', 'rejected', 'payment_failed'))";
} else if ($topup_status === 'approved') {
    $t_where[] = "LOWER(t.status) = 'approved'";
} else if ($topup_status === 'rejected') {
    $t_where[] = "LOWER(t.status) IN ('rejected', 'payment_failed')";
}

if (!empty($topup_search)) {
    $ts_safe = $conn->real_escape_string($topup_search);
    $t_where[] = "(t.topup_id LIKE '%$ts_safe%' OR u.name LIKE '%$ts_safe%' OR u.phone LIKE '%$ts_safe%' OR u.mobile LIKE '%$ts_safe%' OR u.email LIKE '%$ts_safe%' OR t.gateway_reference LIKE '%$ts_safe%' OR t.payment_id LIKE '%$ts_safe%')";
}

if ($date_range === 'today') {
    $t_where[] = "DATE(t.created_at) = CURDATE()";
} else if ($date_range === 'this_week') {
    $t_where[] = "YEARWEEK(t.created_at, 1) = YEARWEEK(CURDATE(), 1)";
} else if ($date_range === 'this_month') {
    $t_where[] = "YEAR(t.created_at) = YEAR(CURDATE()) AND MONTH(t.created_at) = MONTH(CURDATE())";
} else if ($date_range === 'custom' && !empty($start_date) && !empty($end_date)) {
    $sd_safe = $conn->real_escape_string($start_date);
    $ed_safe = $conn->real_escape_string($end_date);
    $t_where[] = "DATE(t.created_at) BETWEEN '$sd_safe' AND '$ed_safe'";
}

$topup_where_sql = !empty($t_where) ? "WHERE " . implode(" AND ", $t_where) : "";

$pending_topups_list = $conn->query("
    SELECT t.*, 
           COALESCE(u.name, CONCAT('Customer #', t.customer_id)) as customer_name, 
           COALESCE(u.phone, u.mobile, 'N/A') as customer_mobile, 
           COALESCE(u.email, 'N/A') as customer_email,
           COALESCE(w.available_balance, 0.00) as current_wallet_balance
    FROM wallet_topups t
    LEFT JOIN users u ON t.customer_id = u.id
    LEFT JOIN wallets w ON t.customer_id = w.customer_id
    $topup_where_sql
    ORDER BY t.created_at DESC
");

// Filtered Audit Log
$ledger_filter = isset($_GET['ledger_filter']) ? $_GET['ledger_filter'] : 'all';
$ledger_query = "
    SELECT wt.*, u.name as customer_name, COALESCE(u.phone, u.mobile, 'N/A') as customer_mobile
    FROM wallet_transactions wt
    JOIN users u ON wt.customer_id = u.id
";

if ($ledger_filter === 'credits') {
    $ledger_query .= " WHERE wt.direction = 'credit'";
} else if ($ledger_filter === 'debits') {
    $ledger_query .= " WHERE wt.direction = 'debit'";
} else if ($ledger_filter === 'topups') {
    $ledger_query .= " WHERE wt.transaction_type IN ('topup', 'topup_approved')";
} else if ($ledger_filter === 'payments') {
    $ledger_query .= " WHERE wt.transaction_type = 'payment'";
} else if ($ledger_filter === 'refunds') {
    $ledger_query .= " WHERE wt.transaction_type = 'refund'";
} else if ($ledger_filter === 'admin') {
    $ledger_query .= " WHERE wt.transaction_type IN ('admin_credit', 'admin_debit')";
}

$ledger_query .= " ORDER BY wt.created_at DESC LIMIT 100";
$audit_log = $conn->query($ledger_query);

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Admin Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Admin Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Admin Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-chart-pie"></i> Overview</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Order Management</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_referrals.php"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_referral_settings.php"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="admin_wallets.php" class="active"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_digital_cards.php"><i class="fas fa-id-card"></i> Medical Card Approvals</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>

    <main class="dashboard-content" style="max-width: 100%; overflow-x: hidden;">
        <!-- Header & Admin Banner -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem; background: rgba(255, 255, 255, 0.03); padding: 1.2rem 1.5rem; border-radius: 16px; border: 1px solid var(--glass-border);">
            <div>
                <h2 style="margin: 0; font-size: 1.6rem; display: flex; align-items: center; gap: 0.6rem;">
                    <i class="fas fa-wallet" style="color: var(--primary-color);"></i> Customer Wallet Management
                </h2>
                <p style="color: var(--text-secondary); margin: 0.4rem 0 0 0; font-size: 0.9rem;">
                    Administrative control panel for verifying customer wallet top-ups, reviewing customer ledgers, issuing manual balance adjustments, and configuring policy limits.
                </p>
            </div>
            <button onclick="document.getElementById('settings-modal').style.display='flex'" class="btn btn-outline" style="white-space: nowrap;">
                <i class="fas fa-sliders-h"></i> Top-Up Limits Configuration
            </button>
        </div>

        <?php if (isset($message) && !empty($message)): ?>
            <div style="background: <?php echo $msg_type === 'success' ? 'rgba(46, 213, 115, 0.15)' : ($msg_type === 'info' ? 'rgba(74, 144, 226, 0.15)' : 'rgba(255, 71, 87, 0.15)'); ?>; border: 1px solid <?php echo $msg_type === 'success' ? '#2ed573' : ($msg_type === 'info' ? '#4a90e2' : '#ff4757'); ?>; color: <?php echo $msg_type === 'success' ? '#2ed573' : ($msg_type === 'info' ? '#4a90e2' : '#ff4757'); ?>; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem;">
                <i class="fas <?php echo $msg_type === 'success' ? 'fa-check-circle' : ($msg_type === 'info' ? 'fa-info-circle' : 'fa-exclamation-triangle'); ?>"></i>
                <div><?php echo htmlspecialchars($message); ?></div>
            </div>
        <?php endif; ?>

        <!-- SECTION 1: Management Statistics & System Metrics -->
        <div style="margin-bottom: 2rem;">
            <h3 style="margin-bottom: 1rem; font-size: 1.15rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-chart-line" style="color: var(--primary-color);"></i> Management Statistics & System Metrics
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                <div class="glass-panel" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #f39c12;">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; font-weight: 500;">Pending Requests</p>
                    <p style="font-size: 1.9rem; font-weight: 800; color: #f39c12; margin: 0.4rem 0;"><?php echo $pending_topups_count; ?></p>
                    <small style="color: var(--text-secondary); font-size: 0.75rem;">Awaiting verification</small>
                </div>

                <div class="glass-panel" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #2ed573;">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; font-weight: 500;">Approved Requests</p>
                    <p style="font-size: 1.9rem; font-weight: 800; color: #2ed573; margin: 0.4rem 0;"><?php echo $approved_topups_count; ?></p>
                    <small style="color: var(--text-secondary); font-size: 0.75rem;">Verification approved</small>
                </div>

                <div class="glass-panel" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #ff4757;">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; font-weight: 500;">Rejected Requests</p>
                    <p style="font-size: 1.9rem; font-weight: 800; color: #ff4757; margin: 0.4rem 0;"><?php echo $rejected_topups_count; ?></p>
                    <small style="color: var(--text-secondary); font-size: 0.75rem;">Verification declined</small>
                </div>
            </div>
        </div>

        <!-- SECTION 2: Customer Wallet Top-Up Verification Requests -->
        <div style="margin-bottom: 2.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; gap: 0.8rem;">
                <div>
                    <h3 style="margin: 0; color: #f39c12; font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
                        <i class="fas fa-clock"></i> Customer Wallet Top-Up Verification Requests
                        <span id="unread-topup-badge" style="background: #f39c12; color: #121212; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold;"><?php echo $pending_topups_count; ?> Pending</span>
                    </h3>
                </div>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                    <button type="button" onclick="openReconciliationModal()" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; color: #4a90e2; border-color: #4a90e2;">
                        <i class="fas fa-balance-scale"></i> Reconciliation Report
                    </button>

                    <a href="admin_wallets.php?export=topups_csv&topup_status=<?php echo urlencode($topup_status); ?>&topup_search=<?php echo urlencode($topup_search); ?>&date_range=<?php echo urlencode($date_range); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; color: #2ed573; border-color: #2ed573;">
                        <i class="fas fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </div>

            <!-- Filter Controls Bar -->
            <form method="GET" action="admin_wallets.php" style="background: rgba(255, 255, 255, 0.02); padding: 1rem; border-radius: 14px; border: 1px solid var(--glass-border); margin-bottom: 1rem; display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center;">
                <input type="hidden" name="topup_status" value="<?php echo htmlspecialchars($topup_status); ?>">

                <!-- Status Filter Tabs -->
                <div style="display: flex; gap: 0.3rem; flex-wrap: wrap;">
                    <a href="admin_wallets.php?topup_status=pending&topup_search=<?php echo urlencode($topup_search); ?>&date_range=<?php echo urlencode($date_range); ?>" class="btn <?php echo $topup_status === 'pending' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                        Pending (<?php echo $pending_topups_count; ?>)
                    </a>
                    <a href="admin_wallets.php?topup_status=approved&topup_search=<?php echo urlencode($topup_search); ?>&date_range=<?php echo urlencode($date_range); ?>" class="btn <?php echo $topup_status === 'approved' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                        Approved
                    </a>
                    <a href="admin_wallets.php?topup_status=rejected&topup_search=<?php echo urlencode($topup_search); ?>&date_range=<?php echo urlencode($date_range); ?>" class="btn <?php echo $topup_status === 'rejected' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                        Rejected
                    </a>
                    <a href="admin_wallets.php?topup_status=all&topup_search=<?php echo urlencode($topup_search); ?>&date_range=<?php echo urlencode($date_range); ?>" class="btn <?php echo $topup_status === 'all' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                        All Requests
                    </a>
                </div>

                <!-- Search Input -->
                <div style="flex: 1; min-width: 220px;">
                    <input type="text" name="topup_search" value="<?php echo htmlspecialchars($topup_search); ?>" placeholder="Search ID, Customer, Phone, Gateway Ref / UTR..." class="glass-panel" style="width: 100%; padding: 0.45rem 0.85rem; border-radius: 8px; font-size: 0.85rem; color: var(--text-primary); border: 1px solid var(--glass-border);">
                </div>

                <!-- Date Range Selector -->
                <div>
                    <select name="date_range" onchange="document.getElementById('custom-date-box').style.display = this.value === 'custom' ? 'flex' : 'none';" class="glass-panel" style="padding: 0.45rem 0.85rem; border-radius: 8px; font-size: 0.85rem; color: var(--text-primary); border: 1px solid var(--glass-border);">
                        <option value="all" <?php echo $date_range === 'all' ? 'selected' : ''; ?>>All Dates</option>
                        <option value="today" <?php echo $date_range === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="this_week" <?php echo $date_range === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="this_month" <?php echo $date_range === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="custom" <?php echo $date_range === 'custom' ? 'selected' : ''; ?>>Custom Range...</option>
                    </select>
                </div>

                <div id="custom-date-box" style="display: <?php echo $date_range === 'custom' ? 'flex' : 'none'; ?>; gap: 0.4rem; align-items: center;">
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="glass-panel" style="padding: 0.4rem; border-radius: 8px; font-size: 0.8rem; color: var(--text-primary); border: 1px solid var(--glass-border);">
                    <span style="color: var(--text-secondary); font-size: 0.8rem;">to</span>
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="glass-panel" style="padding: 0.4rem; border-radius: 8px; font-size: 0.8rem; color: var(--text-primary); border: 1px solid var(--glass-border);">
                </div>

                <button type="submit" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.45rem 0.85rem;">
                    <i class="fas fa-filter"></i> Apply Filters
                </button>
                <?php if (!empty($topup_search) || $date_range !== 'all'): ?>
                    <a href="admin_wallets.php?topup_status=<?php echo urlencode($topup_status); ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.45rem 0.65rem; color: var(--text-secondary);">
                        <i class="fas fa-times"></i> Clear
                    </a>
                <?php endif; ?>
            </form>

            <div class="glass-panel" style="overflow-x: auto; padding: 1rem; border-radius: 16px; border-left: 4px solid #f39c12;">
                <table style="width: 100%; min-width: 950px; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border); color: var(--text-secondary); font-size: 0.85rem;">
                            <th style="padding: 0.8rem 1rem;">Top-Up ID</th>
                            <th style="padding: 0.8rem 1rem;">Customer Details</th>
                            <th style="padding: 0.8rem 1rem;">Requested / Paid</th>
                            <th style="padding: 0.8rem 1rem;">Gateway Ref & Payment ID</th>
                            <th style="padding: 0.8rem 1rem;">Current Balance</th>
                            <th style="padding: 0.8rem 1rem;">Verification Status</th>
                            <th style="padding: 0.8rem 1rem;">Request Date & Time</th>
                            <th style="padding: 0.8rem 1rem; text-align: right;">Administrative Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($pending_topups_list && $pending_topups_list->num_rows > 0): ?>
                            <?php while ($top = $pending_topups_list->fetch_assoc()): 
                                $t_status = strtolower($top['status']);
                            ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem;">
                                    <td style="padding: 1rem; font-family: monospace; font-weight: bold; color: var(--primary-color); white-space: nowrap;">
                                        <?php echo htmlspecialchars($top['topup_id']); ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($top['customer_name']); ?></strong> 
                                        <span style="color: var(--text-secondary); font-size: 0.8rem;">(#<?php echo $top['customer_id']; ?>)</span><br>
                                        <small style="color: var(--text-secondary);"><i class="fas fa-phone-alt" style="font-size: 0.75rem;"></i> <?php echo htmlspecialchars($top['customer_mobile']); ?></small>
                                    </td>
                                    <td style="padding: 1rem; white-space: nowrap;">
                                        <div style="font-weight: bold; color: #2ed573;">Req: ₹<?php echo number_format($top['amount'], 2); ?></div>
                                        <?php if ($top['paid_amount'] > 0): ?>
                                            <small style="color: <?php echo abs($top['amount'] - $top['paid_amount']) > 0.01 ? '#9b59b6' : 'var(--text-secondary)'; ?>; font-weight: bold;">
                                                Paid: ₹<?php echo number_format($top['paid_amount'], 2); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.85rem;">
                                        <div style="font-weight: bold; color: #4a90e2;"><i class="fas fa-credit-card"></i> <?php echo htmlspecialchars($top['payment_method'] ?: 'Razorpay'); ?></div>
                                        <small style="color: var(--text-secondary); font-family: monospace; display: block;">
                                            Order ID: <?php echo htmlspecialchars($top['gateway_reference'] ?: '-'); ?>
                                        </small>
                                        <small style="color: var(--text-secondary); font-family: monospace; display: block;">
                                            Pay ID: <?php echo htmlspecialchars($top['payment_id'] ?: '-'); ?>
                                        </small>
                                    </td>
                                    <td style="padding: 1rem; font-weight: bold; color: #3498db; white-space: nowrap;">
                                        ₹<?php echo number_format($top['current_wallet_balance'], 2); ?>
                                    </td>
                                    <td style="padding: 1rem; white-space: nowrap;">
                                        <?php if ($t_status === 'approved'): ?>
                                            <span style="background: rgba(46, 213, 115, 0.15); color: #2ed573; padding: 0.35rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; border: 1px solid rgba(46, 213, 115, 0.3);">
                                                <i class="fas fa-check-circle"></i> Approved
                                            </span>
                                        <?php elseif ($t_status === 'rejected' || $t_status === 'payment_failed'): ?>
                                            <span style="background: rgba(255, 71, 87, 0.15); color: #ff4757; padding: 0.35rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; border: 1px solid rgba(255, 71, 87, 0.3);">
                                                <i class="fas fa-times-circle"></i> Rejected
                                            </span>
                                            <?php if (!empty($top['rejection_reason'])): ?>
                                                <small style="color: #ff4757; display: block; margin-top: 0.25rem; font-size: 0.75rem;">
                                                    <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($top['rejection_reason']); ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php elseif ($t_status === 'reversed'): ?>
                                            <span style="background: rgba(149, 165, 166, 0.15); color: #95a5a6; padding: 0.35rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; border: 1px solid rgba(149, 165, 166, 0.3);">
                                                <i class="fas fa-undo"></i> Credit Reversed
                                            </span>
                                        <?php elseif ($t_status === 'amount_mismatch'): ?>
                                            <span style="background: rgba(155, 89, 182, 0.15); color: #9b59b6; padding: 0.35rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; border: 1px solid rgba(155, 89, 182, 0.3);">
                                                <i class="fas fa-exclamation-triangle"></i> Mismatch Review
                                            </span>
                                        <?php elseif ($t_status === 'pending_approval'): ?>
                                            <span style="background: rgba(230, 126, 34, 0.15); color: #e67e22; padding: 0.35rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; border: 1px solid rgba(230, 126, 34, 0.3);">
                                                <i class="fas fa-clock"></i> Pending Verification
                                            </span>
                                        <?php else: ?>
                                            <span style="background: rgba(241, 196, 15, 0.15); color: #f1c40f; padding: 0.35rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; border: 1px solid rgba(241, 196, 15, 0.3);">
                                                <i class="fas fa-hourglass-half"></i> Payment Pending
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-secondary); white-space: nowrap;">
                                        <?php echo date('M d, Y h:i A', strtotime($top['created_at'])); ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: right; white-space: nowrap;">
                                        <!-- Inspect Request Modal Button -->
                                        <button type="button" onclick="openTopupDetailsModal('<?php echo htmlspecialchars(addslashes($top['topup_id'])); ?>')" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.45rem 0.65rem; margin-right: 0.3rem;" title="Inspect request details, timeline, and audit log">
                                            <i class="fas fa-eye"></i> Details
                                        </button>

                                        <?php if ($t_status === 'approved'): ?>
                                            <button type="button" onclick="promptReverseTopup('<?php echo htmlspecialchars(addslashes($top['topup_id'])); ?>', '<?php echo htmlspecialchars(addslashes($top['customer_name'])); ?>', <?php echo $top['amount']; ?>)" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.45rem 0.75rem; color: #e67e22; border-color: #e67e22;" title="Safely reverse credited balance from customer wallet">
                                                <i class="fas fa-undo"></i> Reverse Credit
                                            </button>
                                        <?php elseif ($t_status === 'rejected' || $t_status === 'payment_failed'): ?>
                                            <form method="POST" action="admin_wallets.php" onsubmit="return handleFormSubmit(this, 'Re-approving and crediting customer wallet...');" style="display: inline-block;">
                                                <input type="hidden" name="action" value="approve_topup">
                                                <input type="hidden" name="topup_id" value="<?php echo htmlspecialchars($top['topup_id']); ?>">
                                                <button type="submit" onclick="return confirm('Confirm Admin Re-Approval: Change status to Approved and credit ₹<?php echo number_format($top['paid_amount'] > 0 ? $top['paid_amount'] : $top['amount'], 2); ?> to customer wallet?');" class="btn btn-primary approve-btn" style="font-size: 0.8rem; padding: 0.45rem 0.85rem; background: #2ed573; border-color: #2ed573;">
                                                    <i class="fas fa-check-circle"></i> Re-Approve & Credit
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" action="admin_wallets.php" onsubmit="return handleFormSubmit(this, 'Approving and crediting customer wallet...');" style="display: inline-block;">
                                                <input type="hidden" name="action" value="approve_topup">
                                                <input type="hidden" name="topup_id" value="<?php echo htmlspecialchars($top['topup_id']); ?>">
                                                <button type="submit" onclick="return confirm('Confirm Admin Approval: Add ₹<?php echo number_format($top['paid_amount'] > 0 ? $top['paid_amount'] : $top['amount'], 2); ?> to customer wallet?');" class="btn btn-primary approve-btn" style="font-size: 0.8rem; padding: 0.45rem 0.85rem;">
                                                    <i class="fas fa-check-circle"></i> Approve & Credit Wallet
                                                </button>
                                            </form>

                                            <button type="button" onclick="openRejectModal('<?php echo htmlspecialchars(addslashes($top['topup_id'])); ?>', '<?php echo htmlspecialchars(addslashes($top['customer_name'])); ?>', <?php echo $top['amount']; ?>)" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.45rem 0.85rem; color: #ff4757; border-color: #ff4757; margin-left: 0.3rem;">
                                                <i class="fas fa-times-circle"></i> Reject
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="padding: 2.5rem; text-align: center; color: var(--text-secondary);">
                                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 0.5rem; opacity: 0.5; display: block;"></i>
                                    No customer wallet top-up requests found for status "<strong><?php echo htmlspecialchars(ucfirst($topup_status)); ?></strong>".
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 3: Customer Wallets Directory -->
        <div style="margin-bottom: 2.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-users" style="color: var(--primary-color);"></i> Customer Wallets Directory
                </h3>
                <form method="GET" action="admin_wallets.php" style="display: flex; gap: 0.5rem; max-width: 420px; width: 100%;">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search Customer Name, Phone, ID..." class="glass-panel" style="padding: 0.6rem 1rem; border-radius: 10px; width: 100%; color: var(--text-primary); border: 1px solid var(--glass-border);">
                    <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1.1rem; white-space: nowrap;"><i class="fas fa-search"></i> Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="admin_wallets.php" class="btn btn-outline" style="padding: 0.6rem 0.8rem; color: var(--text-secondary);"><i class="fas fa-times"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="glass-panel" style="overflow-x: auto; padding: 1rem; border-radius: 16px;">
                <table style="width: 100%; min-width: 850px; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border); color: var(--text-secondary); font-size: 0.85rem;">
                            <th style="padding: 0.8rem 1rem;">Customer ID</th>
                            <th style="padding: 0.8rem 1rem;">Customer Name</th>
                            <th style="padding: 0.8rem 1rem;">Mobile / Email</th>
                            <th style="padding: 0.8rem 1rem;">Available Balance</th>
                            <th style="padding: 0.8rem 1rem;">Pending Balance</th>
                            <th style="padding: 0.8rem 1rem;">Status</th>
                            <th style="padding: 0.8rem 1rem; text-align: right;">Management Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($customer_wallets && $customer_wallets->num_rows > 0): ?>
                            <?php while ($cw = $customer_wallets->fetch_assoc()): 
                                $cw_img_url = get_profile_image_url($cw);
                            ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem;">
                                    <td style="padding: 1rem; font-weight: bold; font-family: monospace;">#<?php echo $cw['customer_id']; ?></td>
                                    <td style="padding: 1rem; font-weight: bold; color: var(--primary-color);">
                                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                                            <?php if (!empty($cw_img_url)): ?>
                                                <img src="<?php echo $cw_img_url; ?>" alt="" style="width: 30px; height: 30px; border-radius: 50%; object-fit: cover; border: 1px solid var(--primary-color);">
                                            <?php else: ?>
                                                <div style="width: 30px; height: 30px; border-radius: 50%; background: rgba(255,255,255,0.1); display: flex; align-items: center; justify-content: center; font-size: 0.8rem; color: var(--text-secondary);">
                                                    <i class="fas fa-user"></i>
                                                </div>
                                            <?php endif; ?>
                                            <span><?php echo htmlspecialchars($cw['name']); ?></span>
                                        </div>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.85rem;">
                                        <i class="fas fa-phone-alt" style="font-size: 0.75rem; color: var(--text-secondary);"></i> <?php echo htmlspecialchars($cw['mobile']); ?><br>
                                        <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($cw['email']); ?></small>
                                    </td>
                                    <td style="padding: 1rem; font-weight: bold; color: #2ed573; font-size: 1.1rem; white-space: nowrap;">
                                        ₹<?php echo number_format($cw['available_balance'], 2); ?>
                                    </td>
                                    <td style="padding: 1rem; color: #f39c12; font-weight: bold; white-space: nowrap;">
                                        ₹<?php echo number_format($cw['pending_balance'], 2); ?>
                                    </td>
                                    <td style="padding: 1rem;">
                                        <span style="background: <?php echo $cw['wallet_status'] === 'active' ? 'rgba(46, 213, 115, 0.15)' : 'rgba(255, 71, 87, 0.15)'; ?>; color: <?php echo $cw['wallet_status'] === 'active' ? '#2ed573' : '#ff4757'; ?>; border: 1px solid <?php echo $cw['wallet_status'] === 'active' ? 'rgba(46, 213, 115, 0.3)' : 'rgba(255, 71, 87, 0.3)'; ?>; padding: 0.3rem 0.75rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; text-transform: capitalize;">
                                            <?php echo htmlspecialchars($cw['wallet_status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; text-align: right; white-space: nowrap;">
                                        <button onclick="openCustomerDetailsModal(<?php echo $cw['customer_id']; ?>)" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; margin-right: 0.3rem;" title="Inspect customer ledger and transaction history">
                                            <i class="fas fa-eye"></i> Details
                                        </button>
                                        <button onclick="openAdjustModal(<?php echo $cw['customer_id']; ?>, '<?php echo htmlspecialchars(addslashes($cw['name'])); ?>')" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; margin-right: 0.3rem;">
                                            <i class="fas fa-edit"></i> Adjust (+/-)
                                        </button>
                                        <form method="POST" action="admin_wallets.php" style="display: inline-block;">
                                            <input type="hidden" name="action" value="toggle_freeze">
                                            <input type="hidden" name="customer_id" value="<?php echo $cw['customer_id']; ?>">
                                            <input type="hidden" name="status" value="<?php echo $cw['wallet_status'] === 'active' ? 'frozen' : 'active'; ?>">
                                            <button type="submit" onclick="return confirm('Change customer wallet status to <?php echo strtoupper($cw['wallet_status'] === 'active' ? 'FROZEN' : 'ACTIVE'); ?>?');" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.35rem 0.65rem; color: <?php echo $cw['wallet_status'] === 'active' ? '#ff4757' : '#2ed573'; ?>; border-color: <?php echo $cw['wallet_status'] === 'active' ? '#ff4757' : '#2ed573'; ?>;">
                                                <?php echo $cw['wallet_status'] === 'active' ? 'Freeze' : 'Unfreeze'; ?>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="7" style="padding: 2.5rem; text-align: center; color: var(--text-secondary);">No customer wallet accounts found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- SECTION 4: System Wallet Ledger & Audit Logs -->
        <div style="margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                <h3 style="margin: 0; font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-history" style="color: var(--primary-color);"></i> System Wallet Ledger & Audit Logs
                </h3>

                <!-- Filter Tabs -->
                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    <a href="admin_wallets.php?ledger_filter=all<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="btn <?php echo $ledger_filter==='all'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">All Logs</a>
                    <a href="admin_wallets.php?ledger_filter=credits<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="btn <?php echo $ledger_filter==='credits'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">Credits (+)</a>
                    <a href="admin_wallets.php?ledger_filter=debits<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="btn <?php echo $ledger_filter==='debits'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">Debits (-)</a>
                    <a href="admin_wallets.php?ledger_filter=topups<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="btn <?php echo $ledger_filter==='topups'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">Top-Ups</a>
                    <a href="admin_wallets.php?ledger_filter=payments<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="btn <?php echo $ledger_filter==='payments'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">Payments</a>
                    <a href="admin_wallets.php?ledger_filter=refunds<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="btn <?php echo $ledger_filter==='refunds'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">Refunds</a>
                    <a href="admin_wallets.php?ledger_filter=admin<?php echo !empty($search) ? '&search='.urlencode($search) : ''; ?>" class="btn <?php echo $ledger_filter==='admin'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">Admin Adjustments</a>
                </div>
            </div>

            <div class="glass-panel" style="overflow-x: auto; padding: 1rem; border-radius: 16px;">
                <table style="width: 100%; min-width: 900px; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border); color: var(--text-secondary); font-size: 0.85rem;">
                            <th style="padding: 0.8rem 1rem;">Tx ID</th>
                            <th style="padding: 0.8rem 1rem;">Customer Details</th>
                            <th style="padding: 0.8rem 1rem;">Transaction Type</th>
                            <th style="padding: 0.8rem 1rem;">Direction</th>
                            <th style="padding: 0.8rem 1rem;">Amount</th>
                            <th style="padding: 0.8rem 1rem;">Balance After</th>
                            <th style="padding: 0.8rem 1rem;">Reason / Order Ref</th>
                            <th style="padding: 0.8rem 1rem;">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($audit_log && $audit_log->num_rows > 0): ?>
                            <?php while ($al = $audit_log->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.88rem;">
                                    <td style="padding: 1rem; font-family: monospace; font-size: 0.85rem; font-weight: bold; color: var(--primary-color); white-space: nowrap;"><?php echo htmlspecialchars($al['transaction_id']); ?></td>
                                    <td style="padding: 1rem;">
                                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($al['customer_name']); ?></strong><br>
                                        <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($al['customer_mobile']); ?></small>
                                    </td>
                                    <td style="padding: 1rem; text-transform: capitalize; font-size: 0.85rem; white-space: nowrap;">
                                        <span style="background: rgba(255,255,255,0.05); padding: 0.25rem 0.6rem; border-radius: 8px; border: 1px solid var(--glass-border);">
                                            <?php echo str_replace('_', ' ', $al['transaction_type']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; font-weight: bold; color: <?php echo $al['direction'] === 'credit' ? '#2ed573' : '#ff4757'; ?>; white-space: nowrap;">
                                        <?php if ($al['direction'] === 'credit'): ?>
                                            <i class="fas fa-arrow-down" style="font-size: 0.75rem;"></i> Credit
                                        <?php else: ?>
                                            <i class="fas fa-arrow-up" style="font-size: 0.75rem;"></i> Debit
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; font-weight: bold; color: <?php echo $al['direction'] === 'credit' ? '#2ed573' : '#ff4757'; ?>; white-space: nowrap;">
                                        <?php echo $al['direction'] === 'credit' ? '+' : '-'; ?>₹<?php echo number_format($al['amount'], 2); ?>
                                    </td>
                                    <td style="padding: 1rem; font-weight: 500; white-space: nowrap;">₹<?php echo number_format($al['new_balance'], 2); ?></td>
                                    <td style="padding: 1rem; font-size: 0.85rem; max-width: 250px; word-wrap: break-word;">
                                        <?php echo htmlspecialchars($al['reason'] ?? '-'); ?>
                                        <?php if ($al['order_id']): ?><br><small style="color: var(--primary-color); font-weight: bold;">Order #<?php echo $al['order_id']; ?></small><?php endif; ?>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-secondary); white-space: nowrap;"><?php echo date('M d, Y h:i A', strtotime($al['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="padding: 2.5rem; text-align: center; color: var(--text-secondary);">No ledger records found for selected filter.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Modal 1: Customer Wallet Inspection & Details Modal -->
<div id="customer-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); z-index: 9999; justify-content: center; align-items: center; padding: 1rem; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="background: var(--bg-card); border: 1px solid var(--glass-border); width: 100%; max-width: 850px; max-height: 90vh; overflow-y: auto; padding: 2rem; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.3rem; display: flex; align-items: center; gap: 0.6rem;">
                <i class="fas fa-user-circle" style="color: var(--primary-color);"></i> Customer Wallet Detailed Ledger & Breakdown
            </h3>
            <button onclick="document.getElementById('customer-details-modal').style.display='none'" style="background: none; border: none; color: var(--text-primary); font-size: 1.6rem; cursor: pointer;">&times;</button>
        </div>

        <div id="customer-modal-loading" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
            <i class="fas fa-spinner fa-spin fa-2x" style="color: var(--primary-color); margin-bottom: 1rem;"></i>
            <p>Fetching customer wallet data...</p>
        </div>

        <div id="customer-modal-body" style="display: none;">
            <!-- Customer Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                    <small style="color: var(--text-secondary); text-transform: uppercase;">Customer Profile</small>
                    <h4 id="cust_modal_name" style="margin: 0.3rem 0 0.1rem 0; color: var(--primary-color);"></h4>
                    <p style="margin: 0; font-size: 0.85rem; color: var(--text-secondary);" id="cust_modal_contact"></p>
                </div>

                <div style="background: rgba(46, 213, 115, 0.08); padding: 1rem; border-radius: 12px; border: 1px solid rgba(46, 213, 115, 0.3);">
                    <small style="color: #2ed573; text-transform: uppercase; font-weight: bold;">Current Available Balance</small>
                    <div id="cust_modal_balance" style="font-size: 1.8rem; font-weight: bold; color: #2ed573; margin-top: 0.2rem;">₹0.00</div>
                </div>

                <div style="background: rgba(243, 156, 18, 0.08); padding: 1rem; border-radius: 12px; border: 1px solid rgba(243, 156, 18, 0.3);">
                    <small style="color: #f39c12; text-transform: uppercase; font-weight: bold;">Pending Top-Ups</small>
                    <div id="cust_modal_pending" style="font-size: 1.8rem; font-weight: bold; color: #f39c12; margin-top: 0.2rem;">₹0.00</div>
                </div>
            </div>

            <!-- Customer Breakdown Metrics -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; text-align: center;">
                <div style="background: rgba(255,255,255,0.02); padding: 0.75rem; border-radius: 10px; border: 1px solid var(--glass-border);">
                    <small style="color: var(--text-secondary);">Approved Top-Ups</small>
                    <div id="cust_modal_app_topups" style="font-weight: bold; color: #2ed573; margin-top: 0.2rem;">0 (₹0)</div>
                </div>
                <div style="background: rgba(255,255,255,0.02); padding: 0.75rem; border-radius: 10px; border: 1px solid var(--glass-border);">
                    <small style="color: var(--text-secondary);">Rejected Top-Ups</small>
                    <div id="cust_modal_rej_topups" style="font-weight: bold; color: #ff4757; margin-top: 0.2rem;">0 (₹0)</div>
                </div>
                <div style="background: rgba(255,255,255,0.02); padding: 0.75rem; border-radius: 10px; border: 1px solid var(--glass-border);">
                    <small style="color: var(--text-secondary);">Medicine Orders Spent</small>
                    <div id="cust_modal_spent" style="font-weight: bold; color: #3498db; margin-top: 0.2rem;">₹0</div>
                </div>
                <div style="background: rgba(255,255,255,0.02); padding: 0.75rem; border-radius: 10px; border: 1px solid var(--glass-border);">
                    <small style="color: var(--text-secondary);">Refunds Received</small>
                    <div id="cust_modal_refunds" style="font-weight: bold; color: #9b59b6; margin-top: 0.2rem;">₹0</div>
                </div>
            </div>

            <!-- Customer Transaction History -->
            <h4 style="margin: 1rem 0 0.5rem 0; font-size: 1rem; color: var(--text-primary);"><i class="fas fa-list-alt"></i> Recent Wallet Ledger Entries</h4>
            <div style="overflow-x: auto; max-height: 250px; overflow-y: auto; margin-bottom: 1.5rem; border: 1px solid var(--glass-border); border-radius: 10px;">
                <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.85rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border); background: rgba(255,255,255,0.03);">
                            <th style="padding: 0.6rem;">Tx ID</th>
                            <th style="padding: 0.6rem;">Type</th>
                            <th style="padding: 0.6rem;">Direction</th>
                            <th style="padding: 0.6rem;">Amount</th>
                            <th style="padding: 0.6rem;">New Balance</th>
                            <th style="padding: 0.6rem;">Reason / Order</th>
                            <th style="padding: 0.6rem;">Date</th>
                        </tr>
                    </thead>
                    <tbody id="cust_modal_tx_body">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal 2: Manual Customer Wallet Adjustment -->
<div id="adjust-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); z-index: 9999; justify-content: center; align-items: center; padding: 1rem; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="background: var(--bg-card); border: 1px solid var(--glass-border); width: 100%; max-width: 460px; padding: 2rem; border-radius: 18px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.2rem;"><i class="fas fa-edit" style="color: var(--primary-color);"></i> Manual Customer Wallet Adjustment</h3>
            <button onclick="document.getElementById('adjust-modal').style.display='none'" style="background: none; border: none; color: var(--text-primary); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="admin_wallets.php" onsubmit="return handleFormSubmit(this, 'Submitting balance adjustment...');">
            <input type="hidden" name="action" value="adjust_wallet">
            <input type="hidden" id="modal_customer_id" name="customer_id" value="">

            <p style="margin-bottom: 1rem; font-weight: bold; background: rgba(255,255,255,0.03); padding: 0.8rem; border-radius: 8px; border: 1px solid var(--glass-border);">
                Customer: <span id="modal_customer_name" style="color: var(--primary-color);"></span>
            </p>

            <label style="display: block; margin-bottom: 0.4rem; font-weight: bold; font-size: 0.9rem;">Adjustment Direction</label>
            <select name="type" class="glass-panel" style="width: 100%; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; color: var(--text-primary); border: 1px solid var(--glass-border);">
                <option value="credit">Credit (+ Add Money to Customer Wallet)</option>
                <option value="debit">Debit (- Deduct Money from Customer Wallet)</option>
            </select>

            <label style="display: block; margin-bottom: 0.4rem; font-weight: bold; font-size: 0.9rem;">Adjustment Amount (₹)</label>
            <input type="number" name="amount" min="0.01" step="0.01" placeholder="Enter amount (e.g. 250.00)" class="glass-panel" style="width: 100%; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; color: var(--text-primary); border: 1px solid var(--glass-border);" required>

            <label style="display: block; margin-bottom: 0.4rem; font-weight: bold; font-size: 0.9rem;">Mandatory Reason / Audit Note</label>
            <textarea name="reason" placeholder="Explain why this customer wallet balance is being adjusted..." class="glass-panel" style="width: 100%; padding: 0.75rem; border-radius: 8px; margin-bottom: 1.5rem; color: var(--text-primary); border: 1px solid var(--glass-border);" required></textarea>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-size: 1rem; font-weight: bold;">
                <i class="fas fa-check-circle"></i> Submit Administrative Adjustment
            </button>
        </form>
    </div>
</div>

<!-- Modal 3: Reject Top-Up Request Modal -->
<div id="reject-topup-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); z-index: 9999; justify-content: center; align-items: center; padding: 1rem; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="background: var(--bg-card); border: 1px solid var(--glass-border); width: 100%; max-width: 460px; padding: 2rem; border-radius: 18px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.2rem; color: #ff4757;"><i class="fas fa-times-circle"></i> Reject Top-Up Request</h3>
            <button onclick="document.getElementById('reject-topup-modal').style.display='none'" style="background: none; border: none; color: var(--text-primary); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="admin_wallets.php" onsubmit="return handleFormSubmit(this, 'Rejecting top-up request...');">
            <input type="hidden" name="action" value="reject_topup">
            <input type="hidden" id="reject_topup_id" name="topup_id" value="">

            <p style="margin-bottom: 0.4rem; font-weight: bold; font-size: 0.9rem;">Top-Up Request: <span id="reject_topup_display_id" style="color: var(--primary-color); font-family: monospace;"></span></p>
            <p style="margin-bottom: 1rem; color: var(--text-secondary); font-size: 0.9rem;">Customer: <strong id="reject_customer_name"></strong> (₹<span id="reject_amount"></span>)</p>

            <label style="display: block; margin-bottom: 0.4rem; font-weight: bold; font-size: 0.9rem;">Select Rejection Reason</label>
            <select id="reason_preset" onchange="if(this.value !== 'Other') document.getElementById('reject_reason_text').value = this.value;" class="glass-panel" style="width: 100%; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; color: var(--text-primary); border: 1px solid var(--glass-border);">
                <option value="Payment not received in merchant account">Payment not received in merchant account</option>
                <option value="Payment amount mismatch">Payment amount mismatch</option>
                <option value="Invalid transaction reference">Invalid transaction reference</option>
                <option value="Duplicate payment submission">Duplicate payment submission</option>
                <option value="Payment failed / declined at gateway">Payment failed / declined at gateway</option>
                <option value="Other">Other (Specify custom reason below)</option>
            </select>

            <label style="display: block; margin-bottom: 0.4rem; font-weight: bold; font-size: 0.9rem;">Detailed Rejection Note</label>
            <textarea id="reject_reason_text" name="reason" placeholder="Explain why this top-up request is being rejected..." class="glass-panel" style="width: 100%; padding: 0.75rem; border-radius: 8px; margin-bottom: 1.5rem; color: var(--text-primary); border: 1px solid var(--glass-border);" required>Payment not received in merchant account</textarea>

            <button type="submit" class="btn btn-outline" style="width: 100%; padding: 0.8rem; font-size: 1rem; font-weight: bold; color: #ff4757; border-color: #ff4757;">
                <i class="fas fa-ban"></i> Confirm Request Rejection
            </button>
        </form>
    </div>
</div>

<!-- Modal 4: Customer Top-Up Limits Settings Modal -->
<div id="settings-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); z-index: 9999; justify-content: center; align-items: center; padding: 1rem; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="background: var(--bg-card); border: 1px solid var(--glass-border); width: 100%; max-width: 460px; padding: 2rem; border-radius: 18px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="margin: 0; font-size: 1.2rem;"><i class="fas fa-sliders-h" style="color: var(--primary-color);"></i> Customer Wallet Top-Up Policy</h3>
            <button onclick="document.getElementById('settings-modal').style.display='none'" style="background: none; border: none; color: var(--text-primary); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="admin_wallets.php" onsubmit="return handleFormSubmit(this, 'Saving settings...');">
            <input type="hidden" name="action" value="update_settings">

            <p style="color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 1.2rem;">
                Configure minimum and maximum single top-up deposit amounts allowed for customer wallets on the platform.
            </p>

            <label style="display: block; margin-bottom: 0.4rem; font-weight: bold; font-size: 0.9rem;">Minimum Customer Top-Up Limit (₹)</label>
            <input type="number" name="min_topup" value="<?php echo $settings['min_topup']; ?>" min="1" step="1" class="glass-panel" style="width: 100%; padding: 0.75rem; border-radius: 8px; margin-bottom: 1rem; color: var(--text-primary); border: 1px solid var(--glass-border);" required>

            <label style="display: block; margin-bottom: 0.4rem; font-weight: bold; font-size: 0.9rem;">Maximum Customer Top-Up Limit (₹)</label>
            <input type="number" name="max_topup" value="<?php echo $settings['max_topup']; ?>" min="100" step="100" class="glass-panel" style="width: 100%; padding: 0.75rem; border-radius: 8px; margin-bottom: 1.5rem; color: var(--text-primary); border: 1px solid var(--glass-border);" required>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-size: 1rem; font-weight: bold;">
                <i class="fas fa-save"></i> Save Top-Up Limits Configuration
            </button>
        </form>
    </div>
</div>

<!-- Hidden Form for Credit Reversal -->
<form id="reverse-topup-form" method="POST" action="admin_wallets.php" style="display: none;">
    <input type="hidden" name="action" value="reverse_topup">
    <input type="hidden" id="reverse_topup_id" name="topup_id" value="">
    <input type="hidden" id="reverse_topup_reason" name="reason" value="">
</form>

<!-- Non-Intrusive Auto-Refresh Toast -->
<div id="auto-refresh-toast" style="display: none; position: fixed; top: 20px; right: 20px; z-index: 10000; background: #f39c12; color: #121212; padding: 0.9rem 1.4rem; border-radius: 14px; font-weight: bold; box-shadow: 0 10px 30px rgba(0,0,0,0.5); align-items: center; gap: 0.8rem;">
    <i class="fas fa-bell fa-bounce" style="font-size: 1.2rem;"></i>
    <div>
        <div id="toast-title" style="font-size: 0.95rem;">New Top-Up Request Arrived!</div>
        <small style="opacity: 0.9;"><a href="admin_wallets.php" style="color: #121212; text-decoration: underline; font-weight: bold;">Click to Reload Page</a></small>
    </div>
    <button onclick="document.getElementById('auto-refresh-toast').style.display='none'" style="background: none; border: none; color: #121212; font-size: 1.2rem; cursor: pointer; margin-left: 0.5rem;">&times;</button>
</div>

<!-- Modal 5: Top-Up Request Forensic Details Modal -->
<div id="topup-details-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); z-index: 9999; justify-content: center; align-items: center; padding: 1rem; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="background: var(--bg-card); border: 1px solid var(--glass-border); width: 100%; max-width: 750px; max-height: 90vh; overflow-y: auto; padding: 2rem; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.25rem; display: flex; align-items: center; gap: 0.6rem;">
                <i class="fas fa-file-invoice-dollar" style="color: var(--primary-color);"></i> Top-Up Request Forensic Details
            </h3>
            <button onclick="document.getElementById('topup-details-modal').style.display='none'" style="background: none; border: none; color: var(--text-primary); font-size: 1.6rem; cursor: pointer;">&times;</button>
        </div>

        <div id="topup-modal-loading" style="text-align: center; padding: 3rem; color: var(--text-secondary);">
            <i class="fas fa-spinner fa-spin fa-2x" style="color: var(--primary-color); margin-bottom: 1rem;"></i>
            <p>Fetching top-up details and audit trail...</p>
        </div>

        <div id="topup-modal-body" style="display: none;">
            <!-- Mismatch & Duplicate Warnings Container -->
            <div id="topup-warning-container"></div>

            <!-- Topup Info Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                    <small style="color: var(--text-secondary); text-transform: uppercase;">Top-Up ID</small>
                    <h4 id="td_modal_topup_id" style="margin: 0.2rem 0 0 0; color: var(--primary-color); font-family: monospace;"></h4>
                    <div id="td_modal_status_badge" style="margin-top: 0.4rem;"></div>
                </div>

                <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                    <small style="color: var(--text-secondary); text-transform: uppercase;">Customer Profile</small>
                    <h4 id="td_modal_customer" style="margin: 0.2rem 0 0 0; color: var(--text-primary);"></h4>
                    <p id="td_modal_contact" style="margin: 0.2rem 0 0 0; font-size: 0.85rem; color: var(--text-secondary);"></p>
                </div>

                <div style="background: rgba(46, 213, 115, 0.08); padding: 1rem; border-radius: 12px; border: 1px solid rgba(46, 213, 115, 0.3);">
                    <small style="color: #2ed573; text-transform: uppercase; font-weight: bold;">Requested Amount</small>
                    <div id="td_modal_amount" style="font-size: 1.6rem; font-weight: bold; color: #2ed573; margin-top: 0.2rem;">₹0.00</div>
                    <small id="td_modal_paid" style="color: var(--text-secondary); display: block; font-weight: 500;"></small>
                </div>
            </div>

            <!-- Payment Reference Breakdown -->
            <div style="background: rgba(255,255,255,0.02); padding: 1rem; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 1.5rem;">
                <h4 style="margin: 0 0 0.8rem 0; font-size: 0.95rem; color: var(--text-primary);"><i class="fas fa-credit-card"></i> Payment Reference Details</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; font-size: 0.88rem;">
                    <div><strong>Payment Method:</strong> <span id="td_modal_method" style="color: #4a90e2;"></span></div>
                    <div><strong>Gateway Order ID:</strong> <span id="td_modal_order_id" style="font-family: monospace;"></span></div>
                    <div><strong>Payment ID / UTR:</strong> <span id="td_modal_pay_id" style="font-family: monospace;"></span></div>
                    <div><strong>Submitted At:</strong> <span id="td_modal_created_at" style="color: var(--text-secondary);"></span></div>
                </div>
            </div>

            <!-- Event Timeline -->
            <h4 style="margin: 1.2rem 0 0.6rem 0; font-size: 0.95rem; color: var(--text-primary);"><i class="fas fa-stream"></i> Request Event Timeline</h4>
            <div id="td_modal_timeline" style="margin-bottom: 1.5rem; background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 12px; border: 1px solid var(--glass-border);"></div>

            <!-- Admin Action Audit Logs -->
            <h4 style="margin: 1.2rem 0 0.6rem 0; font-size: 0.95rem; color: var(--text-primary);"><i class="fas fa-user-shield"></i> Admin Audit Log History</h4>
            <div style="overflow-x: auto; max-height: 200px; overflow-y: auto; border: 1px solid var(--glass-border); border-radius: 10px;">
                <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.85rem;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border); background: rgba(255,255,255,0.03);">
                            <th style="padding: 0.6rem;">Action</th>
                            <th style="padding: 0.6rem;">Prev Status</th>
                            <th style="padding: 0.6rem;">New Status</th>
                            <th style="padding: 0.6rem;">Performed By</th>
                            <th style="padding: 0.6rem;">Notes</th>
                            <th style="padding: 0.6rem;">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody id="td_modal_audit_body">
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal 6: Financial Reconciliation Report Modal -->
<div id="reconciliation-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); z-index: 9999; justify-content: center; align-items: center; padding: 1rem; backdrop-filter: blur(4px);">
    <div class="glass-panel" style="background: var(--bg-card); border: 1px solid var(--glass-border); width: 100%; max-width: 600px; padding: 2rem; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem;">
            <h3 style="margin: 0; font-size: 1.25rem; display: flex; align-items: center; gap: 0.6rem; color: #4a90e2;">
                <i class="fas fa-balance-scale"></i> Financial Reconciliation Report
            </h3>
            <button onclick="document.getElementById('reconciliation-modal').style.display='none'" style="background: none; border: none; color: var(--text-primary); font-size: 1.6rem; cursor: pointer;">&times;</button>
        </div>

        <div id="recon-modal-loading" style="text-align: center; padding: 2rem; color: var(--text-secondary);">
            <i class="fas fa-spinner fa-spin fa-2x" style="color: var(--primary-color); margin-bottom: 1rem;"></i>
            <p>Auditing platform ledger and computing totals...</p>
        </div>

        <div id="recon-modal-body" style="display: none;">
            <div id="recon-discrepancy-alert" style="margin-bottom: 1.2rem;"></div>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: rgba(255,255,255,0.03); padding: 1rem; border-radius: 12px; border: 1px solid var(--glass-border);">
                    <small style="color: var(--text-secondary);">Total Top-Up Requests</small>
                    <div id="recon_tot_cnt" style="font-size: 1.5rem; font-weight: bold; color: var(--text-primary);">0</div>
                    <small id="recon_tot_amt" style="color: var(--text-secondary); font-weight: 500;">₹0.00 Requested</small>
                </div>

                <div style="background: rgba(46, 213, 115, 0.08); padding: 1rem; border-radius: 12px; border: 1px solid rgba(46, 213, 115, 0.3);">
                    <small style="color: #2ed573; font-weight: bold;">Total Approved & Credited</small>
                    <div id="recon_app_cnt" style="font-size: 1.5rem; font-weight: bold; color: #2ed573;">0</div>
                    <small id="recon_app_amt" style="color: #2ed573; font-weight: bold;">₹0.00 Total Approved</small>
                </div>

                <div style="background: rgba(52, 152, 219, 0.08); padding: 1rem; border-radius: 12px; border: 1px solid rgba(52, 152, 219, 0.3);">
                    <small style="color: #3498db; font-weight: bold;">Ledger Credited Sum</small>
                    <div id="recon_tx_amt" style="font-size: 1.5rem; font-weight: bold; color: #3498db;">₹0.00</div>
                    <small style="color: var(--text-secondary);">From transactions log</small>
                </div>

                <div style="background: rgba(255, 71, 87, 0.08); padding: 1rem; border-radius: 12px; border: 1px solid rgba(255, 71, 87, 0.3);">
                    <small style="color: #ff4757; font-weight: bold;">Total Rejected Requests</small>
                    <div id="recon_rej_cnt" style="font-size: 1.5rem; font-weight: bold; color: #ff4757;">0</div>
                    <small id="recon_rej_amt" style="color: #ff4757;">₹0.00 Rejected</small>
                </div>
            </div>

            <button type="button" onclick="document.getElementById('reconciliation-modal').style.display='none'" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: bold;">
                <i class="fas fa-check"></i> Close Report
            </button>
        </div>
    </div>
</div>

<script>
// Prevent double form submission on fast double-clicks
function handleFormSubmit(form, loadingText) {
    const btn = form.querySelector('button[type="submit"]');
    if (btn) {
        if (btn.disabled) return false;
        btn.disabled = true;
        btn.dataset.originalText = btn.innerHTML;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> ${loadingText}`;
    }
    return true;
}

function promptReverseTopup(topupId, customerName, amount) {
    const reason = prompt(`SAFE CREDIT REVERSAL WARNING:\n\nYou are about to reverse the ₹${Number(amount).toFixed(2)} wallet credit for ${customerName} (Topup #${topupId}).\n\nPlease enter the mandatory reason for this reversal:`, "Admin reversal: incorrect verification");
    if (reason !== null && reason.trim() !== "") {
        document.getElementById('reverse_topup_id').value = topupId;
        document.getElementById('reverse_topup_reason').value = reason.trim();
        document.getElementById('reverse-topup-form').submit();
    }
}

function openAdjustModal(cid, name) {
    document.getElementById('modal_customer_id').value = cid;
    document.getElementById('modal_customer_name').innerText = name;
    document.getElementById('adjust-modal').style.display = 'flex';
}

function openRejectModal(topupId, customerName, amount) {
    document.getElementById('reject_topup_id').value = topupId;
    document.getElementById('reject_topup_display_id').innerText = topupId;
    document.getElementById('reject_customer_name').innerText = customerName;
    document.getElementById('reject_amount').innerText = Number(amount).toFixed(2);
    document.getElementById('reject-topup-modal').style.display = 'flex';
}

function openCustomerDetailsModal(customerId) {
    const modal = document.getElementById('customer-details-modal');
    const loading = document.getElementById('customer-modal-loading');
    const body = document.getElementById('customer-modal-body');

    modal.style.display = 'flex';
    loading.style.display = 'block';
    body.style.display = 'none';

    fetch(`admin_wallets.php?action=get_customer_details&customer_id=${customerId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Error fetching customer data');
                modal.style.display = 'none';
                return;
            }

            document.getElementById('cust_modal_name').innerText = `${data.customer.name} (#${data.customer.id})`;
            document.getElementById('cust_modal_contact').innerText = `Phone: ${data.customer.mobile} | Email: ${data.customer.email}`;
            document.getElementById('cust_modal_balance').innerText = `₹${data.wallet.available_balance.toFixed(2)}`;
            document.getElementById('cust_modal_pending').innerText = `₹${data.wallet.pending_balance.toFixed(2)}`;

            document.getElementById('cust_modal_app_topups').innerText = `${data.stats.approved_topups_cnt} (₹${data.stats.approved_topups_amt.toFixed(2)})`;
            document.getElementById('cust_modal_rej_topups').innerText = `${data.stats.rejected_topups_cnt} (₹${data.stats.rejected_topups_amt.toFixed(2)})`;
            document.getElementById('cust_modal_spent').innerText = `₹${data.stats.spent_orders_amt.toFixed(2)}`;
            document.getElementById('cust_modal_refunds').innerText = `₹${data.stats.refunds_amt.toFixed(2)}`;

            const txBody = document.getElementById('cust_modal_tx_body');
            txBody.innerHTML = '';

            if (data.transactions && data.transactions.length > 0) {
                data.transactions.forEach(tx => {
                    const row = document.createElement('tr');
                    row.style.borderBottom = '1px solid rgba(255,255,255,0.05)';
                    const isCredit = tx.direction === 'credit';
                    const color = isCredit ? '#2ed573' : '#ff4757';
                    const sign = isCredit ? '+' : '-';

                    row.innerHTML = `
                        <td style="padding: 0.6rem; font-family: monospace; font-weight: bold; color: var(--primary-color);">${tx.transaction_id}</td>
                        <td style="padding: 0.6rem; text-transform: capitalize;">${tx.transaction_type}</td>
                        <td style="padding: 0.6rem; font-weight: bold; color: ${color};">${tx.direction}</td>
                        <td style="padding: 0.6rem; font-weight: bold; color: ${color};">${sign}₹${tx.amount.toFixed(2)}</td>
                        <td style="padding: 0.6rem;">₹${tx.new_balance.toFixed(2)}</td>
                        <td style="padding: 0.6rem; font-size: 0.8rem; color: var(--text-secondary);">${tx.reason || '-'}${tx.order_id ? ` (Order #${tx.order_id})` : ''}</td>
                        <td style="padding: 0.6rem; font-size: 0.8rem; color: var(--text-secondary);">${tx.created_at}</td>
                    `;
                    txBody.appendChild(row);
                });
            } else {
                txBody.innerHTML = '<tr><td colspan="7" style="padding: 1.5rem; text-align: center; color: var(--text-secondary);">No ledger transactions recorded for this customer.</td></tr>';
            }

            loading.style.display = 'none';
            body.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            alert('Failed to load customer details.');
            modal.style.display = 'none';
        });
}

function openTopupDetailsModal(topupId) {
    const modal = document.getElementById('topup-details-modal');
    const loading = document.getElementById('topup-modal-loading');
    const body = document.getElementById('topup-modal-body');

    modal.style.display = 'flex';
    loading.style.display = 'block';
    body.style.display = 'none';

    fetch(`admin_wallets.php?action=get_topup_details&topup_id=${encodeURIComponent(topupId)}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert(data.message || 'Error loading top-up details.');
                modal.style.display = 'none';
                return;
            }

            const tp = data.topup;

            document.getElementById('td_modal_topup_id').innerText = tp.topup_id;
            document.getElementById('td_modal_customer').innerText = `${tp.customer_name} (#${tp.customer_id})`;
            document.getElementById('td_modal_contact').innerText = `Phone: ${tp.customer_mobile} | Email: ${tp.customer_email}`;
            document.getElementById('td_modal_amount').innerText = `₹${tp.amount.toFixed(2)}`;
            document.getElementById('td_modal_paid').innerText = tp.paid_amount > 0 ? `Paid: ₹${tp.paid_amount.toFixed(2)}` : 'Paid: Pending Gateway';

            document.getElementById('td_modal_method').innerText = tp.payment_method || 'Online Razorpay';
            document.getElementById('td_modal_order_id').innerText = tp.gateway_reference || '-';
            document.getElementById('td_modal_pay_id').innerText = tp.payment_id || '-';
            document.getElementById('td_modal_created_at').innerText = tp.created_at;

            // Status Badge
            const stBadge = document.getElementById('td_modal_status_badge');
            const st = tp.status.toLowerCase();
            if (st === 'approved') {
                stBadge.innerHTML = '<span style="background: rgba(46, 213, 115, 0.15); color: #2ed573; padding: 0.3rem 0.75rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; border: 1px solid rgba(46, 213, 115, 0.3);"><i class="fas fa-check-circle"></i> Approved</span>';
            } else if (st === 'rejected' || st === 'payment_failed') {
                stBadge.innerHTML = '<span style="background: rgba(255, 71, 87, 0.15); color: #ff4757; padding: 0.3rem 0.75rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; border: 1px solid rgba(255, 71, 87, 0.3);"><i class="fas fa-times-circle"></i> Rejected</span>';
            } else if (st === 'reversed') {
                stBadge.innerHTML = '<span style="background: rgba(149, 165, 166, 0.15); color: #95a5a6; padding: 0.3rem 0.75rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; border: 1px solid rgba(149, 165, 166, 0.3);"><i class="fas fa-undo"></i> Credit Reversed</span>';
            } else {
                stBadge.innerHTML = '<span style="background: rgba(243, 156, 18, 0.15); color: #f39c12; padding: 0.3rem 0.75rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; border: 1px solid rgba(243, 156, 18, 0.3);"><i class="fas fa-clock"></i> Pending Verification</span>';
            }

            // Warnings section
            const warnBox = document.getElementById('topup-warning-container');
            warnBox.innerHTML = '';

            if (data.amount_mismatch && data.amount_mismatch.has_mismatch) {
                warnBox.innerHTML += `
                    <div style="background: rgba(155, 89, 182, 0.15); border: 1px solid #9b59b6; color: #9b59b6; padding: 0.9rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.88rem;">
                        <strong><i class="fas fa-exclamation-triangle"></i> AMOUNT MISMATCH ALERT:</strong> Customer requested ₹${data.amount_mismatch.requested.toFixed(2)}, but gateway paid amount is ₹${data.amount_mismatch.paid.toFixed(2)}. Please verify transaction details carefully.
                    </div>
                `;
            }

            if (data.duplicate_check && data.duplicate_check.has_duplicate) {
                const dupCount = data.duplicate_check.records.length;
                let dupList = data.duplicate_check.records.map(r => `#${r.topup_id} (₹${Number(r.amount).toFixed(2)} - ${r.status})`).join(', ');
                warnBox.innerHTML += `
                    <div style="background: rgba(231, 76, 60, 0.15); border: 1px solid #e74c3c; color: #e74c3c; padding: 0.9rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.88rem;">
                        <strong><i class="fas fa-copy"></i> DUPLICATE UTR / PAYMENT REFERENCE DETECTED:</strong> Found ${dupCount} other request(s) matching this Gateway Reference / Payment ID: ${dupList}.
                    </div>
                `;
            }

            // Timeline rendering
            const tlContainer = document.getElementById('td_modal_timeline');
            tlContainer.innerHTML = '';
            if (data.timeline && data.timeline.length > 0) {
                data.timeline.forEach((step, idx) => {
                    const iconColor = step.completed ? '#2ed573' : '#f39c12';
                    const icon = step.completed ? 'fa-check-circle' : 'fa-clock';
                    tlContainer.innerHTML += `
                        <div style="display: flex; gap: 0.8rem; align-items: flex-start; margin-bottom: ${idx === data.timeline.length - 1 ? '0' : '0.8rem'};">
                            <i class="fas ${icon}" style="color: ${iconColor}; font-size: 1.1rem; margin-top: 0.2rem;"></i>
                            <div>
                                <strong style="color: var(--text-primary); font-size: 0.9rem;">${step.title}</strong>
                                <p style="margin: 0.1rem 0 0 0; color: var(--text-secondary); font-size: 0.82rem;">${step.desc}</p>
                                <small style="color: var(--text-secondary); font-size: 0.75rem;">${step.timestamp}</small>
                            </div>
                        </div>
                    `;
                });
            }

            // Audit Trail Body
            const auditBody = document.getElementById('td_modal_audit_body');
            auditBody.innerHTML = '';
            if (data.audit_logs && data.audit_logs.length > 0) {
                data.audit_logs.forEach(log => {
                    auditBody.innerHTML += `
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 0.5rem; font-weight: bold; color: var(--primary-color);">${log.action}</td>
                            <td style="padding: 0.5rem;">${log.prev_status || '-'}</td>
                            <td style="padding: 0.5rem; font-weight: bold;">${log.new_status}</td>
                            <td style="padding: 0.5rem;">${log.admin_name}</td>
                            <td style="padding: 0.5rem; color: var(--text-secondary); font-size: 0.8rem;">${log.reason || '-'}</td>
                            <td style="padding: 0.5rem; color: var(--text-secondary); font-size: 0.8rem;">${log.timestamp}</td>
                        </tr>
                    `;
                });
            } else {
                auditBody.innerHTML = '<tr><td colspan="6" style="padding: 1rem; text-align: center; color: var(--text-secondary);">No administrative audit actions recorded yet.</td></tr>';
            }

            loading.style.display = 'none';
            body.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            alert('Failed to load top-up details.');
            modal.style.display = 'none';
        });
}

function openReconciliationModal() {
    const modal = document.getElementById('reconciliation-modal');
    const loading = document.getElementById('recon-modal-loading');
    const body = document.getElementById('recon-modal-body');

    modal.style.display = 'flex';
    loading.style.display = 'block';
    body.style.display = 'none';

    fetch('admin_wallets.php?action=get_reconciliation_report')
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('Error computing financial reconciliation report.');
                modal.style.display = 'none';
                return;
            }

            document.getElementById('recon_tot_cnt').innerText = data.total_requests;
            document.getElementById('recon_tot_amt').innerText = `₹${data.total_requested_amount.toFixed(2)} Requested`;
            document.getElementById('recon_app_cnt').innerText = data.approved_requests;
            document.getElementById('recon_app_amt').innerText = `₹${data.approved_amount.toFixed(2)} Total Approved`;
            document.getElementById('recon_tx_amt').innerText = `₹${data.ledger_credited_amount.toFixed(2)}`;
            document.getElementById('recon_rej_cnt').innerText = data.rejected_requests;
            document.getElementById('recon_rej_amt').innerText = `₹${data.rejected_amount.toFixed(2)} Rejected`;

            const alertBox = document.getElementById('recon-discrepancy-alert');
            if (data.has_discrepancy) {
                alertBox.innerHTML = `
                    <div style="background: rgba(231, 76, 60, 0.15); border: 1px solid #e74c3c; color: #e74c3c; padding: 0.9rem; border-radius: 12px; font-size: 0.88rem;">
                        <strong><i class="fas fa-exclamation-circle"></i> LEDGER DISCREPANCY DETECTED:</strong> The sum of approved top-ups (₹${data.approved_amount.toFixed(2)}) does not match transaction ledger credits (₹${data.ledger_credited_amount.toFixed(2)}). Please perform an audit.
                    </div>
                `;
            } else {
                alertBox.innerHTML = `
                    <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 0.9rem; border-radius: 12px; font-size: 0.88rem;">
                        <strong><i class="fas fa-check-circle"></i> LEDGER BALANCED:</strong> All approved top-up amounts match transaction ledger credit entries perfectly.
                    </div>
                `;
            }

            loading.style.display = 'none';
            body.style.display = 'block';
        })
        .catch(err => {
            console.error(err);
            alert('Failed to compute reconciliation report.');
            modal.style.display = 'none';
        });
}

// 20-Second Non-Intrusive Auto-Refresh Polling Script
let initialPendingCount = <?php echo $pending_topups_count; ?>;
setInterval(function() {
    fetch('admin_wallets.php?action=get_unread_topups_count')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const newCnt = data.pending_count;
                const badge = document.getElementById('unread-topup-badge');
                if (badge) {
                    badge.innerText = `${newCnt} Pending`;
                }

                if (newCnt > initialPendingCount) {
                    const toast = document.getElementById('auto-refresh-toast');
                    const toastTitle = document.getElementById('toast-title');
                    if (toast && toastTitle) {
                        toastTitle.innerText = `${newCnt - initialPendingCount} New Wallet Top-Up Request(s) Submitted!`;
                        toast.style.display = 'flex';
                    }
                }
            }
        })
        .catch(err => console.error('Auto-refresh poll error:', err));
}, 20000);
</script>

<?php include 'includes/footer.php'; ?>
