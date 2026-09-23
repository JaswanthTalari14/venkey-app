<?php
require_once 'config.php';
require_once 'includes/wallet_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$message = '';
$msg_type = 'info';

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'adjust_wallet') {
        $customer_id = intval($_POST['customer_id']);
        $adj_type = $_POST['type']; // 'credit' or 'debit'
        $amount = floatval($_POST['amount']);
        $reason = trim($_POST['reason']);

        if ($amount <= 0 || empty($reason)) {
            $message = "Please specify a valid amount and a reason.";
            $msg_type = 'danger';
        } else {
            $res = admin_adjust_wallet($admin_id, $customer_id, $adj_type, $amount, $reason);
            if ($res['success']) {
                $message = "Wallet balance updated successfully. Transaction ID: " . $res['tx_id'];
                $msg_type = 'success';
            } else {
                $message = "Failed: " . $res['message'];
                $msg_type = 'danger';
            }
        }
    } else if ($action === 'toggle_freeze') {
        $customer_id = intval($_POST['customer_id']);
        $new_status = $_POST['status']; // 'active' or 'frozen'
        if (toggle_wallet_freeze($admin_id, $customer_id, $new_status)) {
            $message = "Wallet status set to " . strtoupper($new_status) . ".";
            $msg_type = 'success';
        } else {
            $message = "Failed to update wallet status.";
            $msg_type = 'danger';
        }
    } else if ($action === 'approve_topup') {
        $topup_id = $_POST['topup_id'];
        $res = verify_and_complete_topup($topup_id, 'ADMIN_APPROVED_BY_' . $admin_id);
        if ($res['success']) {
            $message = "Top-up " . $topup_id . " approved and credited successfully.";
            $msg_type = 'success';
        } else {
            $message = $res['message'];
            $msg_type = 'danger';
        }
    } else if ($action === 'reject_topup') {
        $topup_id = $_POST['topup_id'];
        $reason = trim($_POST['reason']);
        if (reject_topup($topup_id, $reason)) {
            $message = "Top-up " . $topup_id . " rejected.";
            $msg_type = 'info';
        } else {
            $message = "Failed to reject top-up.";
            $msg_type = 'danger';
        }
    } else if ($action === 'update_settings') {
        $min_topup = floatval($_POST['min_topup']);
        $max_topup = floatval($_POST['max_topup']);
        if (update_wallet_settings($min_topup, $max_topup)) {
            $message = "Wallet settings updated successfully.";
            $msg_type = 'success';
        } else {
            $message = "Failed to update settings.";
            $msg_type = 'danger';
        }
    }
}

$settings = get_wallet_settings();

// Platform Metrics
$tot_bal_res = $conn->query("SELECT SUM(available_balance) as total FROM wallets");
$platform_wallet_balance = floatval($tot_bal_res->fetch_assoc()['total'] ?? 0);

$pend_top_res = $conn->query("SELECT COUNT(*) as cnt, SUM(COALESCE(paid_amount, amount)) as amt FROM wallet_topups WHERE status IN ('pending_approval', 'amount_mismatch', 'pending')");
$pend_top_row = $pend_top_res->fetch_assoc();
$pending_topups_count = intval($pend_top_row['cnt']);
$pending_topups_amt = floatval($pend_top_row['amt'] ?? 0);

$tot_top_res = $conn->query("SELECT SUM(amount) as total FROM wallet_transactions WHERE transaction_type IN ('topup', 'topup_approved') AND status = 'completed'");
$total_topups_amount = floatval($tot_top_res->fetch_assoc()['total'] ?? 0);

$tot_spent_res = $conn->query("SELECT SUM(amount) as total FROM wallet_transactions WHERE transaction_type = 'payment' AND status = 'completed'");
$total_spent_amount = floatval($tot_spent_res->fetch_assoc()['total'] ?? 0);

$tot_ref_res = $conn->query("SELECT SUM(amount) as total FROM wallet_transactions WHERE transaction_type = 'refund' AND status = 'completed'");
$total_refunds_amount = floatval($tot_ref_res->fetch_assoc()['total'] ?? 0);

// Search & Filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where_clause = "WHERE u.role = 'patient'";

if ($search !== '') {
    $search_safe = $conn->real_escape_string($search);
    $where_clause .= " AND (u.name LIKE '%$search_safe%' OR u.mobile LIKE '%$search_safe%' OR u.id LIKE '%$search_safe%')";
}

$customer_wallets = $conn->query("
    SELECT u.id as customer_id, u.name, u.mobile, u.email,
           COALESCE(w.available_balance, 0.00) as available_balance,
           COALESCE(w.pending_balance, 0.00) as pending_balance,
           COALESCE(w.status, 'active') as wallet_status,
           w.updated_at as last_tx_date
    FROM users u
    LEFT JOIN wallets w ON u.id = w.customer_id
    $where_clause
    ORDER BY w.available_balance DESC
");

// Pending Top-ups List (Awaiting Mandatory Admin Approval)
$pending_topups_list = $conn->query("
    SELECT t.*, u.name as customer_name, u.mobile as customer_mobile, u.email as customer_email,
           COALESCE(w.available_balance, 0.00) as current_wallet_balance
    FROM wallet_topups t
    JOIN users u ON t.customer_id = u.id
    LEFT JOIN wallets w ON u.id = w.customer_id
    WHERE t.status IN ('pending_approval', 'amount_mismatch', 'pending')
    ORDER BY t.created_at DESC
");

// Audit Log
$audit_log = $conn->query("
    SELECT wt.*, u.name as customer_name, u.mobile as customer_mobile
    FROM wallet_transactions wt
    JOIN users u ON wt.customer_id = u.id
    ORDER BY wt.created_at DESC
    LIMIT 100
");

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
            <li><a href="admin_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_referrals.php"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_referral_settings.php"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="admin_wallets.php" class="active"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <h2><i class="fas fa-wallet" style="color: var(--primary-color);"></i> Customer Wallet Management</h2>
                <p style="color: var(--text-secondary);">Oversee balances, process top-ups, issue manual adjustments, and review ledger logs.</p>
            </div>
            <button onclick="document.getElementById('settings-modal').style.display='flex'" class="btn btn-outline">
                <i class="fas fa-cog"></i> Top-Up Limits
            </button>
        </div>

        <?php if (isset($message) && !empty($message)): ?>
            <div style="background: <?php echo $msg_type === 'success' ? 'rgba(46, 213, 115, 0.15)' : 'rgba(255, 71, 87, 0.15)'; ?>; border: 1px solid <?php echo $msg_type === 'success' ? '#2ed573' : '#ff4757'; ?>; color: <?php echo $msg_type === 'success' ? '#2ed573' : '#ff4757'; ?>; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- KPI Cards -->
        <div class="features-grid" style="margin-bottom: 2rem;">
            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center; border-left: 4px solid var(--primary-color);">
                <p style="font-size: 0.9rem; color: var(--text-secondary);">Total Customer Balances</p>
                <p style="font-size: 2.2rem; font-weight: bold; color: var(--primary-color); margin: 0.5rem 0;">₹<?php echo number_format($platform_wallet_balance, 2); ?></p>
                <small style="color: var(--text-secondary);">Active liabilities</small>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center; border-left: 4px solid #f39c12;">
                <p style="font-size: 0.9rem; color: var(--text-secondary);">Pending Top-Ups</p>
                <p style="font-size: 2.2rem; font-weight: bold; color: #f39c12; margin: 0.5rem 0;"><?php echo $pending_topups_count; ?></p>
                <small style="color: var(--text-secondary);">₹<?php echo number_format($pending_topups_amt, 2); ?> awaiting verification</small>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center; border-left: 4px solid #2ed573;">
                <p style="font-size: 0.9rem; color: var(--text-secondary);">Total Top-Ups Credited</p>
                <p style="font-size: 2.2rem; font-weight: bold; color: #2ed573; margin: 0.5rem 0;">₹<?php echo number_format($total_topups_amount, 2); ?></p>
                <small style="color: var(--text-secondary);">Lifetime online deposits</small>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center; border-left: 4px solid #3498db;">
                <p style="font-size: 0.9rem; color: var(--text-secondary);">Total Spent on Orders</p>
                <p style="font-size: 2.2rem; font-weight: bold; color: #3498db; margin: 0.5rem 0;">₹<?php echo number_format($total_spent_amount, 2); ?></p>
                <small style="color: var(--text-secondary);">Wallet medicine checkout</small>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center; border-left: 4px solid #9b59b6;">
                <p style="font-size: 0.9rem; color: var(--text-secondary);">Total Refunds Issued</p>
                <p style="font-size: 2.2rem; font-weight: bold; color: #9b59b6; margin: 0.5rem 0;">₹<?php echo number_format($total_refunds_amount, 2); ?></p>
                <small style="color: var(--text-secondary);">Cancelled order refunds</small>
            </div>
        </div>

        <!-- Pending Top-ups Verification Queue (Requires Mandatory Admin Verification) -->
        <?php if ($pending_topups_list && $pending_topups_list->num_rows > 0): ?>
            <h3 style="margin-bottom: 1rem; color: #f39c12;"><i class="fas fa-exclamation-circle"></i> Pending Wallet Top-Up Verification Requests</h3>
            <div class="glass-panel" style="overflow-x: auto; padding: 1rem; margin-bottom: 2rem; border-left: 4px solid #f39c12;">
                <table style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 1rem;">Top-Up ID</th>
                            <th style="padding: 1rem;">Customer Details</th>
                            <th style="padding: 1rem;">Requested / Paid</th>
                            <th style="padding: 1rem;">Gateway Ref & Tx ID</th>
                            <th style="padding: 1rem;">Current Balance</th>
                            <th style="padding: 1rem;">Verification Status</th>
                            <th style="padding: 1rem;">Date & Time</th>
                            <th style="padding: 1rem; text-align: right;">Mandatory Admin Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($top = $pending_topups_list->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem; font-family: monospace; font-weight: bold; color: var(--primary-color);">
                                    <?php echo htmlspecialchars($top['topup_id']); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <strong><?php echo htmlspecialchars($top['customer_name']); ?></strong> (ID: #<?php echo $top['customer_id']; ?>)<br>
                                    <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($top['customer_mobile']); ?></small>
                                </td>
                                <td style="padding: 1rem;">
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
                                        Payment ID: <?php echo htmlspecialchars($top['payment_id'] ?: '-'); ?>
                                    </small>
                                </td>
                                <td style="padding: 1rem; font-weight: bold; color: #3498db;">
                                    ₹<?php echo number_format($top['current_wallet_balance'], 2); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php if ($top['status'] === 'amount_mismatch'): ?>
                                        <span style="background: rgba(155, 89, 182, 0.15); color: #9b59b6; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">
                                            <i class="fas fa-exclamation-triangle"></i> Amount Mismatch
                                        </span>
                                    <?php elseif ($top['status'] === 'pending_approval'): ?>
                                        <span style="background: rgba(230, 126, 34, 0.15); color: #e67e22; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">
                                            <i class="fas fa-clock"></i> Payment Received (Pending Verification)
                                        </span>
                                    <?php else: ?>
                                        <span style="background: rgba(241, 196, 15, 0.15); color: #f1c40f; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">
                                            Payment Pending
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    <?php echo date('M d, Y h:i A', strtotime($top['created_at'])); ?>
                                </td>
                                <td style="padding: 1rem; text-align: right; white-space: nowrap;">
                                    <form method="POST" action="admin_wallets.php" style="display: inline-block;">
                                        <input type="hidden" name="action" value="approve_topup">
                                        <input type="hidden" name="topup_id" value="<?php echo htmlspecialchars($top['topup_id']); ?>">
                                        <button type="submit" onclick="return confirm('Confirm Admin Verification: Add ₹<?php echo number_format($top['paid_amount'] > 0 ? $top['paid_amount'] : $top['amount'], 2); ?> to customer wallet?');" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                                            <i class="fas fa-check-circle"></i> Approve & Add to Wallet
                                        </button>
                                    </form>

                                    <button type="button" onclick="openRejectModal('<?php echo htmlspecialchars(addslashes($top['topup_id'])); ?>', '<?php echo htmlspecialchars(addslashes($top['customer_name'])); ?>', <?php echo $top['amount']; ?>)" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; color: #ff4757; border-color: #ff4757; margin-left: 0.3rem;">
                                        <i class="fas fa-times-circle"></i> Reject
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Search Bar & Customer Wallets -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
            <h3><i class="fas fa-users"></i> Customer Wallets</h3>
            <form method="GET" action="admin_wallets.php" style="display: flex; gap: 0.5rem; max-width: 400px; width: 100%;">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search Name, Phone, ID..." class="glass-panel" style="padding: 0.6rem; border-radius: 8px; width: 100%; color: var(--text-primary);">
                <button type="submit" class="btn btn-primary" style="padding: 0.6rem 1rem;"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <div class="glass-panel" style="overflow-x: auto; padding: 1rem; margin-bottom: 3rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">ID</th>
                        <th style="padding: 1rem;">Customer Name</th>
                        <th style="padding: 1rem;">Mobile / Email</th>
                        <th style="padding: 1rem;">Available Balance</th>
                        <th style="padding: 1rem;">Pending Balance</th>
                        <th style="padding: 1rem;">Status</th>
                        <th style="padding: 1rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($customer_wallets && $customer_wallets->num_rows > 0): ?>
                        <?php while ($cw = $customer_wallets->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem; font-weight: bold;">#<?php echo $cw['customer_id']; ?></td>
                                <td style="padding: 1rem; font-weight: bold; color: var(--primary-color);">
                                    <?php echo htmlspecialchars($cw['name']); ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($cw['mobile']); ?><br>
                                    <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($cw['email']); ?></small>
                                </td>
                                <td style="padding: 1rem; font-weight: bold; color: #2ed573; font-size: 1.1rem;">
                                    ₹<?php echo number_format($cw['available_balance'], 2); ?>
                                </td>
                                <td style="padding: 1rem; color: #f39c12;">
                                    ₹<?php echo number_format($cw['pending_balance'], 2); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <span style="background: <?php echo $cw['wallet_status'] === 'active' ? 'rgba(46, 213, 115, 0.15)' : 'rgba(255, 71, 87, 0.15)'; ?>; color: <?php echo $cw['wallet_status'] === 'active' ? '#2ed573' : '#ff4757'; ?>; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; text-transform: capitalize;">
                                        <?php echo htmlspecialchars($cw['wallet_status']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem;">
                                    <button onclick="openAdjustModal(<?php echo $cw['customer_id']; ?>, '<?php echo htmlspecialchars(addslashes($cw['name'])); ?>')" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                                        <i class="fas fa-edit"></i> Adjust (+/-)
                                    </button>
                                    <form method="POST" action="admin_wallets.php" style="display: inline-block; margin-left: 0.3rem;">
                                        <input type="hidden" name="action" value="toggle_freeze">
                                        <input type="hidden" name="customer_id" value="<?php echo $cw['customer_id']; ?>">
                                        <input type="hidden" name="status" value="<?php echo $cw['wallet_status'] === 'active' ? 'frozen' : 'active'; ?>">
                                        <button type="submit" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; color: <?php echo $cw['wallet_status'] === 'active' ? '#ff4757' : '#2ed573'; ?>; border-color: <?php echo $cw['wallet_status'] === 'active' ? '#ff4757' : '#2ed573'; ?>;">
                                            <?php echo $cw['wallet_status'] === 'active' ? 'Freeze' : 'Unfreeze'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="padding: 2rem; text-align: center; color: var(--text-secondary);">No customers found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Audit Log -->
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-history"></i> System Ledger Audit Logs</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Tx ID</th>
                        <th style="padding: 1rem;">Customer</th>
                        <th style="padding: 1rem;">Type</th>
                        <th style="padding: 1rem;">Direction</th>
                        <th style="padding: 1rem;">Amount</th>
                        <th style="padding: 1rem;">New Balance</th>
                        <th style="padding: 1rem;">Reason / Order ID</th>
                        <th style="padding: 1rem;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($audit_log && $audit_log->num_rows > 0): ?>
                        <?php while ($al = $audit_log->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem; font-family: monospace; font-size: 0.85rem; font-weight: bold; color: var(--primary-color);"><?php echo htmlspecialchars($al['transaction_id']); ?></td>
                                <td style="padding: 1rem;">
                                    <strong><?php echo htmlspecialchars($al['customer_name']); ?></strong><br>
                                    <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($al['customer_mobile']); ?></small>
                                </td>
                                <td style="padding: 1rem; text-transform: capitalize; font-size: 0.85rem;"><?php echo str_replace('_', ' ', $al['transaction_type']); ?></td>
                                <td style="padding: 1rem; font-weight: bold; color: <?php echo $al['direction'] === 'credit' ? '#2ed573' : '#ff4757'; ?>;">
                                    <?php echo ucfirst($al['direction']); ?>
                                </td>
                                <td style="padding: 1rem; font-weight: bold;">₹<?php echo number_format($al['amount'], 2); ?></td>
                                <td style="padding: 1rem;">₹<?php echo number_format($al['new_balance'], 2); ?></td>
                                <td style="padding: 1rem; font-size: 0.85rem;">
                                    <?php echo htmlspecialchars($al['reason'] ?? '-'); ?>
                                    <?php if ($al['order_id']): ?><br><small style="color: var(--primary-color);">Order #<?php echo $al['order_id']; ?></small><?php endif; ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-secondary);"><?php echo date('M d, h:i A', strtotime($al['created_at'])); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="padding: 2rem; text-align: center; color: var(--text-secondary);">No ledger entries found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Manual Adjust Modal -->
<div id="adjust-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center; padding: 1rem;">
    <div class="glass-panel" style="background: var(--bg-card); border: 1px solid var(--glass-border); width: 100%; max-width: 450px; padding: 2rem; border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3><i class="fas fa-edit" style="color: var(--primary-color);"></i> Adjust Customer Wallet</h3>
            <button onclick="document.getElementById('adjust-modal').style.display='none'" style="background: none; border: none; color: var(--text-primary); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="admin_wallets.php">
            <input type="hidden" name="action" value="adjust_wallet">
            <input type="hidden" id="modal_customer_id" name="customer_id" value="">

            <p style="margin-bottom: 1rem; font-weight: bold;">Customer: <span id="modal_customer_name" style="color: var(--primary-color);"></span></p>

            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Adjustment Type</label>
            <select name="type" class="glass-panel" style="width: 100%; padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem; color: var(--text-primary);">
                <option value="credit">Credit (+ Add Money)</option>
                <option value="debit">Debit (- Deduct Money)</option>
            </select>

            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Amount (₹)</label>
            <input type="number" name="amount" min="1" step="0.01" placeholder="Enter amount" class="glass-panel" style="width: 100%; padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem; color: var(--text-primary);" required>

            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Mandatory Reason / Note</label>
            <textarea name="reason" placeholder="Explain why this wallet balance is being adjusted..." class="glass-panel" style="width: 100%; padding: 0.8rem; border-radius: 8px; margin-bottom: 1.5rem; color: var(--text-primary);" required></textarea>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-size: 1rem;">
                Submit Balance Adjustment
            </button>
        </form>
    </div>
</div>

<!-- Settings Modal -->
<div id="settings-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center; padding: 1rem;">
    <div class="glass-panel" style="background: var(--bg-card); border: 1px solid var(--glass-border); width: 100%; max-width: 450px; padding: 2rem; border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3><i class="fas fa-cog" style="color: var(--primary-color);"></i> Wallet Top-Up Limits</h3>
            <button onclick="document.getElementById('settings-modal').style.display='none'" style="background: none; border: none; color: var(--text-primary); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="admin_wallets.php">
            <input type="hidden" name="action" value="update_settings">

            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Minimum Top-Up Limit (₹)</label>
            <input type="number" name="min_topup" value="<?php echo $settings['min_topup']; ?>" min="1" step="1" class="glass-panel" style="width: 100%; padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem; color: var(--text-primary);" required>

            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Maximum Top-Up Limit (₹)</label>
            <input type="number" name="max_topup" value="<?php echo $settings['max_topup']; ?>" min="100" step="100" class="glass-panel" style="width: 100%; padding: 0.8rem; border-radius: 8px; margin-bottom: 1.5rem; color: var(--text-primary);" required>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-size: 1rem;">
                Save Settings
            </button>
        </form>
    </div>
</div>

<!-- Reject Top-Up Modal -->
<div id="reject-topup-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center; padding: 1rem;">
    <div class="glass-panel" style="background: var(--bg-card); border: 1px solid var(--glass-border); width: 100%; max-width: 450px; padding: 2rem; border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3 style="color: #ff4757;"><i class="fas fa-times-circle"></i> Reject Top-Up Request</h3>
            <button onclick="document.getElementById('reject-topup-modal').style.display='none'" style="background: none; border: none; color: var(--text-primary); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="admin_wallets.php">
            <input type="hidden" name="action" value="reject_topup">
            <input type="hidden" id="reject_topup_id" name="topup_id" value="">

            <p style="margin-bottom: 0.5rem; font-weight: bold;">Top-Up Request: <span id="reject_topup_display_id" style="color: var(--primary-color);"></span></p>
            <p style="margin-bottom: 1rem; color: var(--text-secondary);">Customer: <span id="reject_customer_name"></span> (₹<span id="reject_amount"></span>)</p>

            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Select Rejection Reason</label>
            <select id="reason_preset" onchange="if(this.value !== 'Other') document.getElementById('reject_reason_text').value = this.value;" class="glass-panel" style="width: 100%; padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem; color: var(--text-primary);">
                <option value="Payment not received in merchant account">Payment not received in merchant account</option>
                <option value="Payment amount mismatch">Payment amount mismatch</option>
                <option value="Invalid transaction reference">Invalid transaction reference</option>
                <option value="Duplicate payment submission">Duplicate payment submission</option>
                <option value="Payment failed / declined at gateway">Payment failed / declined at gateway</option>
                <option value="Other">Other (Specify below)</option>
            </select>

            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Detailed Reason / Note</label>
            <textarea id="reject_reason_text" name="reason" placeholder="Explain why this top-up payment is being rejected..." class="glass-panel" style="width: 100%; padding: 0.8rem; border-radius: 8px; margin-bottom: 1.5rem; color: var(--text-primary);" required>Payment not received in merchant account</textarea>

            <button type="submit" class="btn btn-outline" style="width: 100%; padding: 0.8rem; font-size: 1rem; color: #ff4757; border-color: #ff4757;">
                <i class="fas fa-ban"></i> Confirm Rejection
            </button>
        </form>
    </div>
</div>

<script>
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
</script>

<?php include 'includes/footer.php'; ?>
