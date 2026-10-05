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
    $u_stmt = $conn->prepare("SELECT id, name, email, mobile, created_at FROM users WHERE id = ? AND role = 'patient'");
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
        'customer' => [
            'id' => $customer['id'],
            'name' => $customer['name'],
            'email' => $customer['email'],
            'mobile' => $customer['mobile'],
            'joined_date' => date('M d, Y', strtotime($customer['created_at']))
        ],
        'wallet' => [
            'available_balance' => floatval($wallet['available_balance']),
            'pending_balance' => floatval($wallet['pending_balance']),
            'status' => $wallet['status'],
            'updated_at' => date('M d, Y h:i A', strtotime($wallet['updated_at']))
        ],
        'stats' => [
            'approved_topups_cnt' => intval($app_top['cnt'] ?? 0),
            'approved_topups_amt' => floatval($app_top['amt'] ?? 0),
            'rejected_topups_cnt' => intval($rej_top['cnt'] ?? 0),
            'rejected_topups_amt' => floatval($rej_top['amt'] ?? 0),
            'spent_orders_amt' => floatval($spent_ord['amt'] ?? 0),
            'refunds_amt' => floatval($ref_ord['amt'] ?? 0),
            'admin_adj_amt' => floatval($adm_adj['amt'] ?? 0)
        ],
        'transactions' => $transactions,
        'topups' => $topups
    ]);
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

$pend_top_res = $conn->query("SELECT COUNT(*) as cnt, SUM(COALESCE(paid_amount, amount)) as amt FROM wallet_topups WHERE (LOWER(status) IN ('pending_approval', 'amount_mismatch', 'pending') OR status IS NULL OR status = '' OR LOWER(status) NOT IN ('approved', 'rejected', 'payment_failed'))");
$pend_top_row = $pend_top_res->fetch_assoc();
$pending_topups_count = intval($pend_top_row['cnt']);
$pending_topups_amt = floatval($pend_top_row['amt'] ?? 0);

$tot_top_res = $conn->query("SELECT COUNT(*) as cnt, SUM(amount) as total FROM wallet_transactions WHERE transaction_type IN ('topup', 'topup_approved') AND status = 'completed'");
$tot_top_row = $tot_top_res->fetch_assoc();
$total_topups_count = intval($tot_top_row['cnt'] ?? 0);
$total_topups_amount = floatval($tot_top_row['total'] ?? 0);

$rej_top_res = $conn->query("SELECT COUNT(*) as cnt, SUM(amount) as total FROM wallet_topups WHERE LOWER(status) IN ('rejected', 'payment_failed')");
$rej_top_row = $rej_top_res->fetch_assoc();
$rejected_topups_count = intval($rej_top_row['cnt'] ?? 0);
$rejected_topups_amt = floatval($rej_top_row['total'] ?? 0);

$active_cust_res = $conn->query("SELECT COUNT(*) as cnt FROM wallets WHERE available_balance > 0");
$customers_with_balance_cnt = intval($active_cust_res->fetch_assoc()['cnt'] ?? 0);

$tot_spent_res = $conn->query("SELECT SUM(amount) as total FROM wallet_transactions WHERE transaction_type = 'payment' AND status = 'completed'");
$total_spent_amount = floatval($tot_spent_res->fetch_assoc()['total'] ?? 0);

// Search & Customer Wallets Directory
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = "WHERE u.role = 'patient'";

if ($search !== '') {
    $search_safe = $conn->real_escape_string($search);
    $where_clause .= " AND (u.name LIKE '%$search_safe%' OR u.mobile LIKE '%$search_safe%' OR u.id LIKE '%$search_safe%' OR u.email LIKE '%$search_safe%')";
}

$customer_wallets = $conn->query("
    SELECT u.id as customer_id, u.name, u.mobile, u.email, u.profile_image,
           COALESCE(w.available_balance, 0.00) as available_balance,
           COALESCE(w.pending_balance, 0.00) as pending_balance,
           COALESCE(w.status, 'active') as wallet_status,
           w.updated_at as last_tx_date
    FROM users u
    LEFT JOIN wallets w ON u.id = w.customer_id
    $where_clause
    ORDER BY w.available_balance DESC
");

// Top-ups Verification Queue with Status Filtering
$topup_status = isset($_GET['topup_status']) ? strtolower(trim($_GET['topup_status'])) : 'pending';
$topup_where = "";
if ($topup_status === 'pending') {
    $topup_where = "WHERE (LOWER(t.status) IN ('pending_approval', 'amount_mismatch', 'pending') OR t.status IS NULL OR t.status = '' OR LOWER(t.status) NOT IN ('approved', 'rejected', 'payment_failed'))";
} else if ($topup_status === 'approved') {
    $topup_where = "WHERE LOWER(t.status) = 'approved'";
} else if ($topup_status === 'rejected') {
    $topup_where = "WHERE LOWER(t.status) IN ('rejected', 'payment_failed')";
} else if ($topup_status === 'all') {
    $topup_where = "";
} else {
    $topup_status = 'pending';
    $topup_where = "WHERE (LOWER(t.status) IN ('pending_approval', 'amount_mismatch', 'pending') OR t.status IS NULL OR t.status = '' OR LOWER(t.status) NOT IN ('approved', 'rejected', 'payment_failed'))";
}

$pending_topups_list = $conn->query("
    SELECT t.*, 
           COALESCE(u.name, CONCAT('Customer #', t.customer_id)) as customer_name, 
           COALESCE(u.mobile, 'N/A') as customer_mobile, 
           COALESCE(u.email, 'N/A') as customer_email,
           COALESCE(w.available_balance, 0.00) as current_wallet_balance
    FROM wallet_topups t
    LEFT JOIN users u ON t.customer_id = u.id
    LEFT JOIN wallets w ON t.customer_id = w.customer_id
    $topup_where
    ORDER BY t.created_at DESC
");

// Filtered Audit Log
$ledger_filter = isset($_GET['ledger_filter']) ? $_GET['ledger_filter'] : 'all';
$ledger_query = "
    SELECT wt.*, u.name as customer_name, u.mobile as customer_mobile
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

        <!-- SECTION 1: Management Statistics -->
        <div style="margin-bottom: 2rem;">
            <h3 style="margin-bottom: 1rem; font-size: 1.15rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-chart-line" style="color: var(--primary-color);"></i> Management Statistics & System Metrics
            </h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                <div class="glass-panel" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid var(--primary-color);">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; font-weight: 500;">Total Customer Balances</p>
                    <p style="font-size: 1.9rem; font-weight: 800; color: var(--primary-color); margin: 0.4rem 0;">₹<?php echo number_format($platform_wallet_balance, 2); ?></p>
                    <small style="color: var(--text-secondary); font-size: 0.75rem;">Active system financial liabilities</small>
                </div>

                <div class="glass-panel" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #f39c12;">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; font-weight: 500;">Pending Verification Top-Ups</p>
                    <p style="font-size: 1.9rem; font-weight: 800; color: #f39c12; margin: 0.4rem 0;"><?php echo $pending_topups_count; ?></p>
                    <small style="color: var(--text-secondary); font-size: 0.75rem;">₹<?php echo number_format($pending_topups_amt, 2); ?> awaiting admin review</small>
                </div>

                <div class="glass-panel" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #2ed573;">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; font-weight: 500;">Total Top-Ups Credited</p>
                    <p style="font-size: 1.9rem; font-weight: 800; color: #2ed573; margin: 0.4rem 0;">₹<?php echo number_format($total_topups_amount, 2); ?></p>
                    <small style="color: var(--text-secondary); font-size: 0.75rem;"><?php echo $total_topups_count; ?> approved customer deposits</small>
                </div>

                <div class="glass-panel" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #ff4757;">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; font-weight: 500;">Total Top-Ups Rejected</p>
                    <p style="font-size: 1.9rem; font-weight: 800; color: #ff4757; margin: 0.4rem 0;"><?php echo $rejected_topups_count; ?></p>
                    <small style="color: var(--text-secondary); font-size: 0.75rem;">₹<?php echo number_format($rejected_topups_amt, 2); ?> declined requests</small>
                </div>

                <div class="glass-panel" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #9b59b6;">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; font-weight: 500;">Customers with Wallet Funds</p>
                    <p style="font-size: 1.9rem; font-weight: 800; color: #9b59b6; margin: 0.4rem 0;"><?php echo $customers_with_balance_cnt; ?></p>
                    <small style="color: var(--text-secondary); font-size: 0.75rem;">Accounts with positive balance</small>
                </div>

                <div class="glass-panel" style="padding: 1.25rem; border-radius: 14px; border-left: 4px solid #3498db;">
                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0; font-weight: 500;">Total Orders Paid via Wallet</p>
                    <p style="font-size: 1.9rem; font-weight: 800; color: #3498db; margin: 0.4rem 0;">₹<?php echo number_format($total_spent_amount, 2); ?></p>
                    <small style="color: var(--text-secondary); font-size: 0.75rem;">Medicine checkout utilization</small>
                </div>
            </div>
        </div>

        <!-- SECTION 2: Customer Wallet Top-Up Verification Requests -->
        <div style="margin-bottom: 2.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; margin-bottom: 1rem; gap: 0.5rem;">
                <h3 style="margin: 0; color: #f39c12; font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-clock"></i> Customer Wallet Top-Up Verification Requests
                    <span style="background: #f39c12; color: #121212; padding: 0.2rem 0.6rem; border-radius: 20px; font-size: 0.8rem; font-weight: bold;"><?php echo $pending_topups_count; ?> Pending</span>
                </h3>
                <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                    <a href="admin_wallets.php?topup_status=pending" class="btn <?php echo $topup_status === 'pending' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                        Pending (<?php echo $pending_topups_count; ?>)
                    </a>
                    <a href="admin_wallets.php?topup_status=approved" class="btn <?php echo $topup_status === 'approved' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                        Approved
                    </a>
                    <a href="admin_wallets.php?topup_status=rejected" class="btn <?php echo $topup_status === 'rejected' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                        Rejected
                    </a>
                    <a href="admin_wallets.php?topup_status=all" class="btn <?php echo $topup_status === 'all' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;">
                        All Requests
                    </a>
                </div>
            </div>

            <div class="glass-panel" style="overflow-x: auto; padding: 1rem; border-radius: 16px; border-left: 4px solid #f39c12;">
                <table style="width: 100%; min-width: 900px; text-align: left; border-collapse: collapse;">
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
                                        <?php if ($t_status === 'approved'): ?>
                                            <span style="color: #2ed573; font-size: 0.85rem; font-weight: 600;"><i class="fas fa-check-circle"></i> Credited</span>
                                        <?php elseif ($t_status === 'rejected' || $t_status === 'payment_failed'): ?>
                                            <span style="color: #ff4757; font-size: 0.85rem; font-weight: 600;"><i class="fas fa-ban"></i> Rejected</span>
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
</script>

<?php include 'includes/footer.php'; ?>
