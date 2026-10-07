<?php
require_once 'config.php';
require_once 'includes/security_helper.php';
require_once 'includes/notification_functions.php';
require_once 'includes/wallet_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$admin_name = $_SESSION['name'] ?? 'Admin';

$success_msg = '';
$error_msg = '';

// Handle POST Approval / Rejection Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    $action_type = trim($_POST['action_type']);
    $item_id = isset($_POST['item_id']) ? trim($_POST['item_id']) : '';

    if ($action_type === 'approve_medical_card' || $action_type === 'reject_medical_card') {
        $card_id = (int)$item_id;
        $chk = $conn->query("SELECT c.*, u.name as patient_name FROM digital_medical_cards c JOIN users u ON c.patient_id = u.id WHERE c.id = $card_id AND c.status = 'pending'");
        if ($chk && $chk->num_rows > 0) {
            $card = $chk->fetch_assoc();
            $patient_id = (int)$card['patient_id'];

            if ($action_type === 'approve_medical_card') {
                $settings = get_medical_card_settings($conn);
                $validity_months = $settings['validity_months'];
                $card_num = !empty($card['card_number']) ? $card['card_number'] : generate_unique_card_number($conn);

                $stmt = $conn->prepare("UPDATE digital_medical_cards SET status = 'active', card_number = ?, approved_at = NOW(), valid_from = NOW(), valid_until = DATE_ADD(NOW(), INTERVAL ? MONTH) WHERE id = ? AND status = 'pending'");
                $stmt->bind_param("sii", $card_num, $validity_months, $card_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    log_admin_activity($conn, $admin_id, 'Medical Card Approved', 'digital_medical_cards', $card_id, "Approved Digital Medical Card ($card_num) for patient {$card['patient_name']}");
                    create_notification($patient_id, "Digital Medical Card Approved! 🎉", "Your Digital Medical Card ($card_num) has been approved and activated for $validity_months months!", 'system', 'medical_card', (string)$card_id);
                    $success_msg = "Digital Medical Card ($card_num) approved successfully!";
                } else {
                    $error_msg = "Failed to approve Medical Card or it was already processed.";
                }
            } else {
                $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Information verification failed';
                $stmt = $conn->prepare("UPDATE digital_medical_cards SET status = 'rejected', rejected_at = NOW(), admin_notes = ? WHERE id = ? AND status = 'pending'");
                $stmt->bind_param("si", $reason, $card_id);
                if ($stmt->execute() && $stmt->affected_rows > 0) {
                    log_admin_activity($conn, $admin_id, 'Medical Card Rejected', 'digital_medical_cards', $card_id, "Rejected Medical Card for patient {$card['patient_name']}. Reason: $reason");
                    create_notification($patient_id, "Medical Card Update", "Your Digital Medical Card application was rejected. Reason: $reason", 'system', 'medical_card', (string)$card_id);
                    $success_msg = "Medical Card application rejected.";
                } else {
                    $error_msg = "Failed to reject Medical Card or it was already processed.";
                }
            }
        } else {
            $error_msg = "Medical Card request not found or already processed.";
        }
    } elseif ($action_type === 'approve_verification' || $action_type === 'reject_verification') {
        $target_user_id = (int)$item_id;
        $u_res = $conn->query("SELECT name, role, is_verified FROM users WHERE id = $target_user_id AND role IN ('doctor', 'rmp')");
        if ($u_res && $u_res->num_rows > 0) {
            $u_row = $u_res->fetch_assoc();
            $new_status = ($action_type === 'approve_verification') ? 1 : 0;
            $role_title = strtoupper($u_row['role']);

            $stmt = $conn->prepare("UPDATE users SET is_verified = ? WHERE id = ?");
            $stmt->bind_param("ii", $new_status, $target_user_id);
            if ($stmt->execute()) {
                $conn->query("INSERT INTO verification_history (user_id, admin_id, previous_status, new_status, reason) VALUES ($target_user_id, $admin_id, {$u_row['is_verified']}, $new_status, 'Admin action via Approval Center')");
                $action_txt = $new_status ? "Verified" : "Verification Rejected";
                log_admin_activity($conn, $admin_id, "$role_title Verification " . ($new_status ? 'Approved' : 'Rejected'), 'users', $target_user_id, "$action_txt account for {$u_row['name']}");
                
                $notif_msg = $new_status ? "Congratulations! Your {$role_title} account has been verified by Admin." : "Your {$role_title} verification request was rejected. Please update your profile documents.";
                create_notification($target_user_id, "Account Verification Update", $notif_msg, 'info', 'verification', (string)$target_user_id, $u_row['role']);
                $success_msg = "{$role_title} verification status updated to " . ($new_status ? "Verified" : "Unverified") . ".";
            } else {
                $error_msg = "Failed to update verification status.";
            }
        } else {
            $error_msg = "User record not found.";
        }
    } elseif ($action_type === 'approve_wallet' || $action_type === 'reject_wallet') {
        $topup_id = trim($item_id);
        if ($action_type === 'approve_wallet') {
            $res = verify_and_complete_topup($topup_id, $admin_id, 'ADMIN_APPROVAL_CENTER');
            if ($res['success']) {
                log_admin_activity($conn, $admin_id, 'Wallet Top-Up Approved', 'wallet_topups', null, "Approved wallet top-up request #$topup_id");
                $success_msg = $res['message'];
            } else {
                $error_msg = $res['message'];
            }
        } else {
            $reason = isset($_POST['reason']) ? trim($_POST['reason']) : 'Payment reference verification failed';
            $res = reject_topup_with_reason($topup_id, $admin_id, $reason);
            if ($res['success']) {
                log_admin_activity($conn, $admin_id, 'Wallet Top-Up Rejected', 'wallet_topups', null, "Rejected top-up #$topup_id. Reason: $reason");
                $success_msg = $res['message'];
            } else {
                $error_msg = $res['message'];
            }
        }
    } elseif ($action_type === 'approve_refund' || $action_type === 'reject_refund') {
        $refund_id = (int)$item_id;
        $ref_res = $conn->query("SELECT r.*, u.name as patient_name FROM refund_requests r JOIN users u ON r.patient_id = u.id WHERE r.id = $refund_id");
        if ($ref_res && $ref_res->num_rows > 0) {
            $ref = $ref_res->fetch_assoc();
            $patient_id = (int)$ref['patient_id'];
            $new_status = ($action_type === 'approve_refund') ? 'Refunded' : 'Rejected';
            $admin_note = isset($_POST['reason']) ? trim($_POST['reason']) : 'Processed by Admin via Approval Center';

            $stmt = $conn->prepare("UPDATE refund_requests SET status = ?, admin_note = ? WHERE id = ?");
            $stmt->bind_param("ssi", $new_status, $admin_note, $refund_id);
            if ($stmt->execute()) {
                if ($ref['order_id'] && $new_status === 'Refunded') {
                    $conn->query("UPDATE orders SET payment_status = 'Refunded' WHERE id = " . (int)$ref['order_id']);
                }

                log_admin_activity($conn, $admin_id, "Refund $new_status", 'refund_requests', $refund_id, "Refund #$refund_id for patient {$ref['patient_name']} set to $new_status (Amount: ₹{$ref['amount']})");
                create_notification($patient_id, "Refund Request Update", "Your refund request of ₹{$ref['amount']} has been $new_status.", 'payment', 'refund', (string)$refund_id);
                $success_msg = "Refund request #$refund_id updated to '$new_status'.";
            } else {
                $error_msg = "Failed to update refund request.";
            }
        } else {
            $error_msg = "Refund request not found.";
        }
    }
}

// Fetch Real-time Live Pending Counts directly from DB
$cnt_medical_cards = (int)$conn->query("SELECT COUNT(*) as cnt FROM digital_medical_cards WHERE status = 'pending'")->fetch_assoc()['cnt'];
$cnt_doctors = (int)$conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'doctor' AND is_verified = 0")->fetch_assoc()['cnt'];
$cnt_rmps = (int)$conn->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'rmp' AND is_verified = 0")->fetch_assoc()['cnt'];
$cnt_wallets = (int)$conn->query("SELECT COUNT(*) as cnt FROM wallet_topups WHERE status IN ('pending', 'pending_approval', 'amount_mismatch')")->fetch_assoc()['cnt'];
$cnt_refunds = (int)$conn->query("SELECT COUNT(*) as cnt FROM refund_requests WHERE status = 'Requested'")->fetch_assoc()['cnt'];
$total_pending = $cnt_medical_cards + $cnt_doctors + $cnt_rmps + $cnt_wallets + $cnt_refunds;

// Category Filter
$category = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

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
            <li><a href="admin_approval_center.php" class="active" style="display: flex; justify-content: space-between; align-items: center;">
                <span><i class="fas fa-check-double"></i> Approval Center</span>
                <?php if ($total_pending > 0): ?>
                    <span style="background: #ff4757; color: white; border-radius: 12px; padding: 0.15rem 0.5rem; font-size: 0.75rem; font-weight: bold;"><?php echo $total_pending; ?></span>
                <?php endif; ?>
            </a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_audit.php"><i class="fas fa-history"></i> Audit Timeline</a></li>
            <li><a href="admin_digital_cards.php"><i class="fas fa-id-card"></i> Medical Cards</a></li>
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallets</a></li>
            <li><a href="admin_refunds.php"><i class="fas fa-undo"></i> Refunds</a></li>
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Orders</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <!-- Title & Subtitle -->
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <h2 style="margin: 0; color: var(--text-primary);"><i class="fas fa-tasks" style="color: var(--primary-color);"></i> Admin Approval Center</h2>
                <p style="color: var(--text-secondary); margin-top: 0.3rem;">Centralized control hub for all pending administrative actions across the platform.</p>
            </div>
            <a href="admin_approval_center.php" class="btn btn-outline" style="font-size: 0.85rem;"><i class="fas fa-sync"></i> Refresh Data</a>
        </div>

        <?php if ($success_msg): ?>
            <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_msg): ?>
            <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid #ff4757; color: #ff4757; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <!-- ADMIN ACTION REQUIRED DASHBOARD (Summary Header Cards) -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <a href="admin_approval_center.php?category=wallets" style="text-decoration: none;">
                <div class="glass-panel" style="padding: 1.2rem; border-left: 4px solid #ff4757; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Wallet Requests</span>
                        <i class="fas fa-wallet" style="color: #ff4757; font-size: 1.2rem;"></i>
                    </div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #ff4757; margin-top: 0.4rem;">
                        🔴 <?php echo $cnt_wallets; ?>
                    </div>
                </div>
            </a>

            <a href="admin_approval_center.php?category=medical_cards" style="text-decoration: none;">
                <div class="glass-panel" style="padding: 1.2rem; border-left: 4px solid #f5a623; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Medical Cards</span>
                        <i class="fas fa-id-card" style="color: #f5a623; font-size: 1.2rem;"></i>
                    </div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #f5a623; margin-top: 0.4rem;">
                        🟡 <?php echo $cnt_medical_cards; ?>
                    </div>
                </div>
            </a>

            <a href="admin_approval_center.php?category=doctors" style="text-decoration: none;">
                <div class="glass-panel" style="padding: 1.2rem; border-left: 4px solid #ff7f50; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Doctor Verify</span>
                        <i class="fas fa-user-md" style="color: #ff7f50; font-size: 1.2rem;"></i>
                    </div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #ff7f50; margin-top: 0.4rem;">
                        🟠 <?php echo $cnt_doctors; ?>
                    </div>
                </div>
            </a>

            <a href="admin_approval_center.php?category=refunds" style="text-decoration: none;">
                <div class="glass-panel" style="padding: 1.2rem; border-left: 4px solid #70a1ff; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Refund Requests</span>
                        <i class="fas fa-undo" style="color: #70a1ff; font-size: 1.2rem;"></i>
                    </div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #70a1ff; margin-top: 0.4rem;">
                        🔵 <?php echo $cnt_refunds; ?>
                    </div>
                </div>
            </a>

            <a href="admin_approval_center.php?category=rmps" style="text-decoration: none;">
                <div class="glass-panel" style="padding: 1.2rem; border-left: 4px solid #a55eea; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">RMP Verify</span>
                        <i class="fas fa-user-nurse" style="color: #a55eea; font-size: 1.2rem;"></i>
                    </div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #a55eea; margin-top: 0.4rem;">
                        🟣 <?php echo $cnt_rmps; ?>
                    </div>
                </div>
            </a>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="glass-panel" style="padding: 1.2rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                <a href="admin_approval_center.php?category=all" class="btn <?php echo $category === 'all' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.4rem 0.9rem;">
                    All Pending (<?php echo $total_pending; ?>)
                </a>
                <a href="admin_approval_center.php?category=wallets" class="btn <?php echo $category === 'wallets' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.4rem 0.9rem;">
                    🟢 Wallet (<?php echo $cnt_wallets; ?>)
                </a>
                <a href="admin_approval_center.php?category=medical_cards" class="btn <?php echo $category === 'medical_cards' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.4rem 0.9rem;">
                    🟡 Cards (<?php echo $cnt_medical_cards; ?>)
                </a>
                <a href="admin_approval_center.php?category=doctors" class="btn <?php echo $category === 'doctors' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.4rem 0.9rem;">
                    🔵 Doctors (<?php echo $cnt_doctors; ?>)
                </a>
                <a href="admin_approval_center.php?category=rmps" class="btn <?php echo $category === 'rmps' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.4rem 0.9rem;">
                    🟣 RMPs (<?php echo $cnt_rmps; ?>)
                </a>
                <a href="admin_approval_center.php?category=refunds" class="btn <?php echo $category === 'refunds' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.4rem 0.9rem;">
                    🔴 Refunds (<?php echo $cnt_refunds; ?>)
                </a>
            </div>

            <form method="GET" style="display: flex; gap: 0.5rem; flex: 1; max-width: 300px;">
                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                <input type="text" name="search" class="form-control" placeholder="Search pending items..." value="<?php echo htmlspecialchars($search); ?>" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                <button type="submit" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <!-- Approval Items Listing -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <?php
            $search_esc = $conn->real_escape_string($search);
            $has_items = false;

            // 1. Pending Wallet Top-ups
            if (in_array($category, ['all', 'wallets'])) {
                $w_where = "wt.status IN ('pending', 'pending_approval', 'amount_mismatch')";
                if ($search_esc !== '') {
                    $w_where .= " AND (u.name LIKE '%$search_esc%' OR wt.topup_id LIKE '%$search_esc%' OR wt.payment_id LIKE '%$search_esc%')";
                }
                $wallets_q = $conn->query("SELECT wt.*, u.name as customer_name, u.email, u.phone FROM wallet_topups wt JOIN users u ON wt.customer_id = u.id WHERE $w_where ORDER BY wt.id DESC");

                if ($wallets_q && $wallets_q->num_rows > 0) {
                    $has_items = true;
                    echo '<h3 style="color: #2ed573; margin-top: 0; font-size: 1.1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;"><i class="fas fa-wallet"></i> Pending Wallet Top-Up Requests</h3>';
                    while ($w = $wallets_q->fetch_assoc()) {
                        $amt = number_format($w['paid_amount'] > 0 ? $w['paid_amount'] : $w['amount'], 2);
                        ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding: 1rem 0; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <div style="font-weight: bold; color: var(--text-primary); font-size: 1rem;">
                                    <?php echo htmlspecialchars($w['customer_name']); ?> 
                                    <span style="font-size: 0.75rem; background: rgba(46, 213, 115, 0.2); color: #2ed573; border: 1px solid #2ed573; padding: 0.1rem 0.5rem; border-radius: 10px;">Wallet Top-Up</span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                    Request ID: <strong style="color: var(--primary-color);"><?php echo htmlspecialchars($w['topup_id']); ?></strong> | 
                                    Ref/Payment: <strong><?php echo htmlspecialchars($w['payment_id'] ?: ($w['gateway_reference'] ?: 'N/A')); ?></strong> | 
                                    Date: <?php echo date('M d, Y h:i A', strtotime($w['created_at'])); ?>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                <div style="font-size: 1.3rem; font-weight: 800; color: #2ed573;">
                                    +₹<?php echo $amt; ?>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Approve wallet top-up of ₹<?php echo $amt; ?> for <?php echo htmlspecialchars(addslashes($w['customer_name'])); ?>?');">
                                        <input type="hidden" name="action_type" value="approve_wallet">
                                        <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($w['topup_id']); ?>">
                                        <button type="submit" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: #2ed573; border-color: #2ed573; color: #000;"><i class="fas fa-check"></i> Approve</button>
                                    </form>

                                    <button type="button" onclick="promptReject('reject_wallet', '<?php echo htmlspecialchars(addslashes($w['topup_id'])); ?>', '<?php echo htmlspecialchars(addslashes($w['customer_name'])); ?>')" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; color: #ff4757; border-color: #ff4757;"><i class="fas fa-times"></i> Reject</button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
            }

            // 2. Pending Medical Cards
            if (in_array($category, ['all', 'medical_cards'])) {
                $c_where = "c.status = 'pending'";
                if ($search_esc !== '') {
                    $c_where .= " AND (u.name LIKE '%$search_esc%' OR c.card_number LIKE '%$search_esc%' OR c.gateway_payment_id LIKE '%$search_esc%')";
                }
                $cards_q = $conn->query("SELECT c.*, u.name as patient_name, u.phone, u.email FROM digital_medical_cards c JOIN users u ON c.patient_id = u.id WHERE $c_where ORDER BY c.id DESC");

                if ($cards_q && $cards_q->num_rows > 0) {
                    $has_items = true;
                    echo '<h3 style="color: #f5a623; margin-top: 1.5rem; font-size: 1.1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;"><i class="fas fa-id-card"></i> Pending Digital Medical Cards</h3>';
                    while ($c = $cards_q->fetch_assoc()) {
                        ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding: 1rem 0; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <div style="font-weight: bold; color: var(--text-primary); font-size: 1rem;">
                                    <?php echo htmlspecialchars($c['patient_name']); ?> 
                                    <span style="font-size: 0.75rem; background: rgba(245, 166, 35, 0.2); color: #f5a623; border: 1px solid #f5a623; padding: 0.1rem 0.5rem; border-radius: 10px;">Medical Card Application</span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                    App ID: <strong>#DMC-<?php echo $c['id']; ?></strong> | 
                                    Phone: <strong><?php echo htmlspecialchars($c['phone']); ?></strong> | 
                                    Payment ID: <strong><?php echo htmlspecialchars($c['gateway_payment_id'] ?: 'Paid'); ?></strong> | 
                                    Applied: <?php echo date('M d, Y h:i A', strtotime($c['created_at'])); ?>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                <div style="font-size: 1.1rem; font-weight: 800; color: #f5a623;">
                                    ₹<?php echo number_format($c['amount_paid'], 2); ?>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Approve 5-Month Medical Card for <?php echo htmlspecialchars(addslashes($c['patient_name'])); ?>?');">
                                        <input type="hidden" name="action_type" value="approve_medical_card">
                                        <input type="hidden" name="item_id" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: #f5a623; border-color: #f5a623; color: #000;"><i class="fas fa-check"></i> Approve Card</button>
                                    </form>

                                    <button type="button" onclick="promptReject('reject_medical_card', '<?php echo $c['id']; ?>', '<?php echo htmlspecialchars(addslashes($c['patient_name'])); ?>')" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; color: #ff4757; border-color: #ff4757;"><i class="fas fa-times"></i> Reject</button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
            }

            // 3. Pending Doctor Verifications
            if (in_array($category, ['all', 'doctors'])) {
                $doc_where = "role = 'doctor' AND is_verified = 0";
                if ($search_esc !== '') {
                    $doc_where .= " AND (name LIKE '%$search_esc%' OR email LIKE '%$search_esc%' OR phone LIKE '%$search_esc%' OR specialization LIKE '%$search_esc%')";
                }
                $docs_q = $conn->query("SELECT * FROM users WHERE $doc_where ORDER BY id DESC");

                if ($docs_q && $docs_q->num_rows > 0) {
                    $has_items = true;
                    echo '<h3 style="color: #ff7f50; margin-top: 1.5rem; font-size: 1.1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;"><i class="fas fa-user-md"></i> Pending Doctor Verifications</h3>';
                    while ($d = $docs_q->fetch_assoc()) {
                        $doc_src = '';
                        foreach (['verification_document', 'license_document', 'document_path'] as $df) {
                            if (!empty($d[$df]) && file_exists($d[$df])) {
                                $doc_src = $d[$df];
                                break;
                            }
                        }
                        ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding: 1rem 0; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <div style="font-weight: bold; color: var(--text-primary); font-size: 1rem;">
                                    Dr. <?php echo htmlspecialchars($d['name']); ?> 
                                    <span style="font-size: 0.75rem; background: rgba(255, 127, 80, 0.2); color: #ff7f50; border: 1px solid #ff7f50; padding: 0.1rem 0.5rem; border-radius: 10px;">Doctor Registration</span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                    Specialization: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($d['specialization'] ?: 'General'); ?></strong> | 
                                    Phone: <strong><?php echo htmlspecialchars($d['phone']); ?></strong> | 
                                    Registered: <?php echo date('M d, Y', strtotime($d['created_at'])); ?>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <?php if ($doc_src): ?>
                                    <a href="<?php echo htmlspecialchars($doc_src); ?>" target="_blank" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;"><i class="fas fa-file-medical"></i> View Doc</a>
                                <?php endif; ?>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('Approve verification for Dr. <?php echo htmlspecialchars(addslashes($d['name'])); ?>?');">
                                    <input type="hidden" name="action_type" value="approve_verification">
                                    <input type="hidden" name="item_id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: #ff7f50; border-color: #ff7f50; color: #fff;"><i class="fas fa-check"></i> Verify Doctor</button>
                                </form>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('Reject verification for Dr. <?php echo htmlspecialchars(addslashes($d['name'])); ?>?');">
                                    <input type="hidden" name="action_type" value="reject_verification">
                                    <input type="hidden" name="item_id" value="<?php echo $d['id']; ?>">
                                    <button type="submit" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; color: #ff4757; border-color: #ff4757;"><i class="fas fa-times"></i> Reject</button>
                                </form>
                            </div>
                        </div>
                        <?php
                    }
                }
            }

            // 4. Pending RMP Verifications
            if (in_array($category, ['all', 'rmps'])) {
                $rmp_where = "role = 'rmp' AND is_verified = 0";
                if ($search_esc !== '') {
                    $rmp_where .= " AND (name LIKE '%$search_esc%' OR email LIKE '%$search_esc%' OR phone LIKE '%$search_esc%')";
                }
                $rmps_q = $conn->query("SELECT * FROM users WHERE $rmp_where ORDER BY id DESC");

                if ($rmps_q && $rmps_q->num_rows > 0) {
                    $has_items = true;
                    echo '<h3 style="color: #a55eea; margin-top: 1.5rem; font-size: 1.1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;"><i class="fas fa-user-nurse"></i> Pending RMP Verifications</h3>';
                    while ($r = $rmps_q->fetch_assoc()) {
                        $rmp_doc = '';
                        foreach (['verification_document', 'license_document', 'document_path'] as $df) {
                            if (!empty($r[$df]) && file_exists($r[$df])) {
                                $rmp_doc = $r[$df];
                                break;
                            }
                        }
                        ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding: 1rem 0; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <div style="font-weight: bold; color: var(--text-primary); font-size: 1rem;">
                                    <?php echo htmlspecialchars($r['name']); ?> 
                                    <span style="font-size: 0.75rem; background: rgba(165, 94, 234, 0.2); color: #a55eea; border: 1px solid #a55eea; padding: 0.1rem 0.5rem; border-radius: 10px;">RMP Verification</span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                    RMP ID: <strong>#RMP-<?php echo $r['id']; ?></strong> | 
                                    Phone: <strong><?php echo htmlspecialchars($r['phone']); ?></strong> | 
                                    Submitted: <?php echo date('M d, Y', strtotime($r['created_at'])); ?>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                <?php if ($rmp_doc): ?>
                                    <a href="<?php echo htmlspecialchars($rmp_doc); ?>" target="_blank" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;"><i class="fas fa-file-medical"></i> View License</a>
                                <?php endif; ?>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('Approve verification for RMP <?php echo htmlspecialchars(addslashes($r['name'])); ?>?');">
                                    <input type="hidden" name="action_type" value="approve_verification">
                                    <input type="hidden" name="item_id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: #a55eea; border-color: #a55eea; color: #fff;"><i class="fas fa-check"></i> Verify RMP</button>
                                </form>

                                <form method="POST" style="display: inline;" onsubmit="return confirm('Reject verification for RMP <?php echo htmlspecialchars(addslashes($r['name'])); ?>?');">
                                    <input type="hidden" name="action_type" value="reject_verification">
                                    <input type="hidden" name="item_id" value="<?php echo $r['id']; ?>">
                                    <button type="submit" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; color: #ff4757; border-color: #ff4757;"><i class="fas fa-times"></i> Reject</button>
                                </form>
                            </div>
                        </div>
                        <?php
                    }
                }
            }

            // 5. Pending Refund Requests
            if (in_array($category, ['all', 'refunds'])) {
                $ref_where = "r.status = 'Requested'";
                if ($search_esc !== '') {
                    $ref_where .= " AND (u.name LIKE '%$search_esc%' OR r.transaction_id LIKE '%$search_esc%' OR r.reason LIKE '%$search_esc%')";
                }
                $ref_q = $conn->query("SELECT r.*, u.name as patient_name, u.phone FROM refund_requests r JOIN users u ON r.patient_id = u.id WHERE $ref_where ORDER BY r.id DESC");

                if ($ref_q && $ref_q->num_rows > 0) {
                    $has_items = true;
                    echo '<h3 style="color: #70a1ff; margin-top: 1.5rem; font-size: 1.1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;"><i class="fas fa-undo"></i> Pending Refund Requests</h3>';
                    while ($rf = $ref_q->fetch_assoc()) {
                        ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.05); padding: 1rem 0; flex-wrap: wrap; gap: 1rem;">
                            <div>
                                <div style="font-weight: bold; color: var(--text-primary); font-size: 1rem;">
                                    <?php echo htmlspecialchars($rf['patient_name']); ?> 
                                    <span style="font-size: 0.75rem; background: rgba(112, 161, 255, 0.2); color: #70a1ff; border: 1px solid #70a1ff; padding: 0.1rem 0.5rem; border-radius: 10px;">Refund Request</span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;">
                                    Order / Ref ID: <strong><?php echo $rf['order_id'] ? '#ORD-' . $rf['order_id'] : ($rf['transaction_id'] ?: 'N/A'); ?></strong> | 
                                    Reason: <span style="color: var(--text-primary);"><?php echo htmlspecialchars($rf['reason']); ?></span> | 
                                    Requested: <?php echo date('M d, Y', strtotime($rf['created_at'])); ?>
                                </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                                <div style="font-size: 1.2rem; font-weight: 800; color: #70a1ff;">
                                    ₹<?php echo number_format($rf['amount'], 2); ?>
                                </div>
                                <div style="display: flex; gap: 0.5rem;">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Approve refund of ₹<?php echo $rf['amount']; ?> for <?php echo htmlspecialchars(addslashes($rf['patient_name'])); ?>?');">
                                        <input type="hidden" name="action_type" value="approve_refund">
                                        <input type="hidden" name="item_id" value="<?php echo $rf['id']; ?>">
                                        <button type="submit" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; background: #70a1ff; border-color: #70a1ff; color: #000;"><i class="fas fa-check"></i> Process Refund</button>
                                    </form>

                                    <button type="button" onclick="promptReject('reject_refund', '<?php echo $rf['id']; ?>', '<?php echo htmlspecialchars(addslashes($rf['patient_name'])); ?>')" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; color: #ff4757; border-color: #ff4757;"><i class="fas fa-times"></i> Reject</button>
                                </div>
                            </div>
                        </div>
                        <?php
                    }
                }
            }

            if (!$has_items) {
                echo '<div style="text-align: center; padding: 3rem 1rem; color: var(--text-secondary);">';
                echo '<i class="fas fa-check-circle" style="font-size: 3rem; color: #2ed573; margin-bottom: 1rem; display: block;"></i>';
                echo '<h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">All Caught Up!</h3>';
                echo '<p style="margin: 0;">There are currently no pending items requiring action in this category.</p>';
                echo '</div>';
            }
            ?>
        </div>
    </main>
</div>

<!-- Rejection Modal / Prompt Handler -->
<div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 99999; align-items: center; justify-content: center;">
    <div class="glass-panel" style="width: 90%; max-width: 480px; padding: 2rem; border-top: 4px solid #ff4757; background: #121826;">
        <h3 style="margin-top: 0; color: #ff4757;"><i class="fas fa-times-circle"></i> Reject Request</h3>
        <p style="color: var(--text-secondary); font-size: 0.9rem;" id="modalTargetName"></p>
        
        <form method="POST" id="rejectForm">
            <input type="hidden" name="action_type" id="modalActionType">
            <input type="hidden" name="item_id" id="modalItemId">
            
            <div style="margin-bottom: 1.2rem;">
                <label style="display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.4rem;">Reason for Rejection:</label>
                <textarea name="reason" class="form-control" rows="3" required placeholder="Enter clear rejection reason..." style="width: 100%; font-size: 0.9rem;"></textarea>
            </div>

            <div style="display: flex; gap: 0.8rem; justify-content: flex-end;">
                <button type="button" onclick="closeRejectModal()" class="btn btn-outline" style="font-size: 0.85rem;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="font-size: 0.85rem; background: #ff4757; border-color: #ff4757;">Confirm Rejection</button>
            </div>
        </form>
    </div>
</div>

<script>
function promptReject(actionType, itemId, targetName) {
    document.getElementById('modalActionType').value = actionType;
    document.getElementById('modalItemId').value = itemId;
    document.getElementById('modalTargetName').textContent = 'Providing rejection reason for: ' + targetName;
    document.getElementById('rejectModal').style.display = 'flex';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}
</script>

<?php include 'includes/footer.php'; ?>
