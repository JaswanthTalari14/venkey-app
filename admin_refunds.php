<?php
require_once 'config.php';
require_once 'includes/security_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle Refund Request Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_refund'])) {
    $refund_id = (int)$_POST['refund_id'];
    $new_status = trim($conn->real_escape_string($_POST['status']));
    $admin_note = trim($conn->real_escape_string($_POST['admin_note']));

    $stmt = $conn->prepare("UPDATE refund_requests SET status = ?, admin_note = ? WHERE id = ?");
    $stmt->bind_param("ssi", $new_status, $admin_note, $refund_id);
    if ($stmt->execute()) {
        // If refunded, update corresponding order status if applicable
        $ref_info = $conn->query("SELECT order_id, patient_id, amount FROM refund_requests WHERE id = $refund_id")->fetch_assoc();
        if ($ref_info && $ref_info['order_id'] && $new_status === 'Refunded') {
            $conn->query("UPDATE orders SET payment_status = 'Refunded' WHERE id = " . (int)$ref_info['order_id']);
        }

        // Log Admin Activity (Feature 22)
        log_admin_activity($conn, $admin_id, 'Refund Processed', 'refund_requests', $refund_id, "Set status to '$new_status' for Refund #$refund_id");

        $success_msg = "Refund request #$refund_id updated to '$new_status'.";
    } else {
        $error_msg = "Failed to update refund request.";
    }
}

// Filter Status
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : '';
$where_clause = "";
if ($status_filter) {
    $safe_status = $conn->real_escape_string($status_filter);
    $where_clause = "WHERE r.status = '$safe_status'";
}

// Fetch Refund Requests with Patient Details
$query = "
    SELECT r.*, u.name as patient_name, u.email as patient_email, u.phone as patient_phone
    FROM refund_requests r
    JOIN users u ON r.patient_id = u.id
    $where_clause
    ORDER BY r.created_at DESC
";
$refunds = $conn->query($query);

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
            <li><a href="admin_refunds.php" class="active"><i class="fas fa-undo"></i> Refunds & Disputes</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_referrals.php"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2>🛡️ Refund & Dispute Management</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Review patient refund requests, resolve transaction disputes, and manage approvals.</p>

        <?php if ($success_msg): ?>
            <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid #ff4757; color: #ff4757; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <!-- Filter Buttons -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <a href="admin_refunds.php" class="btn <?php echo !$status_filter ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">All Requests</a>
            <a href="admin_refunds.php?status=Requested" class="btn <?php echo $status_filter === 'Requested' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">Requested</a>
            <a href="admin_refunds.php?status=Approved" class="btn <?php echo $status_filter === 'Approved' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">Approved</a>
            <a href="admin_refunds.php?status=Refunded" class="btn <?php echo $status_filter === 'Refunded' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">Refunded</a>
            <a href="admin_refunds.php?status=Rejected" class="btn <?php echo $status_filter === 'Rejected' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">Rejected</a>
        </div>

        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">ID</th>
                        <th style="padding: 1rem;">Patient</th>
                        <th style="padding: 1rem;">Order / Ref ID</th>
                        <th style="padding: 1rem;">Amount</th>
                        <th style="padding: 1rem;">Reason</th>
                        <th style="padding: 1rem;">Status</th>
                        <th style="padding: 1rem;">Submitted</th>
                        <th style="padding: 1rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($refunds && $refunds->num_rows > 0): ?>
                        <?php while($ref = $refunds->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#REFUND-<?php echo str_pad($ref['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td style="padding: 1rem;">
                                    <strong><?php echo htmlspecialchars($ref['patient_name']); ?></strong><br>
                                    <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($ref['patient_phone'] ?: $ref['patient_email']); ?></small>
                                </td>
                                <td style="padding: 1rem; color: var(--secondary-color);">
                                    <?php echo $ref['order_id'] ? '#ORD-'.str_pad($ref['order_id'], 4, '0', STR_PAD_LEFT) : htmlspecialchars($ref['transaction_id'] ?: 'N/A'); ?>
                                </td>
                                <td style="padding: 1rem; font-weight: bold; color: #2ed573;">
                                    ₹<?php echo number_format($ref['amount'], 2); ?>
                                </td>
                                <td style="padding: 1rem; color: var(--text-secondary); font-size: 0.9rem; max-width: 220px;">
                                    <?php echo htmlspecialchars($ref['reason']); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php
                                        $badge_bg = 'rgba(255,255,255,0.1)';
                                        $badge_color = 'var(--text-primary)';
                                        if ($ref['status'] === 'Requested') { $badge_bg = 'rgba(255, 171, 0, 0.15)'; $badge_color = 'var(--accent)'; }
                                        elseif ($ref['status'] === 'Approved') { $badge_bg = 'rgba(52, 152, 219, 0.15)'; $badge_color = '#3498db'; }
                                        elseif ($ref['status'] === 'Refunded') { $badge_bg = 'rgba(46, 213, 115, 0.15)'; $badge_color = '#2ed573'; }
                                        elseif ($ref['status'] === 'Rejected') { $badge_bg = 'rgba(255, 71, 87, 0.15)'; $badge_color = '#ff4757'; }
                                    ?>
                                    <span style="padding: 0.3rem 0.7rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>;">
                                        <?php echo htmlspecialchars($ref['status']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; color: var(--text-secondary); font-size: 0.85rem;">
                                    <?php echo date('M d, Y H:i', strtotime($ref['created_at'])); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <form method="POST" style="display: flex; flex-direction: column; gap: 0.5rem; min-width: 150px;">
                                        <input type="hidden" name="action_refund" value="1">
                                        <input type="hidden" name="refund_id" value="<?php echo $ref['id']; ?>">
                                        
                                        <select name="status" class="form-control" style="padding: 0.3rem; font-size: 0.8rem;">
                                            <option value="Requested" <?php if($ref['status']==='Requested') echo 'selected'; ?>>Requested</option>
                                            <option value="Approved" <?php if($ref['status']==='Approved') echo 'selected'; ?>>Approved</option>
                                            <option value="Refunded" <?php if($ref['status']==='Refunded') echo 'selected'; ?>>Refunded</option>
                                            <option value="Rejected" <?php if($ref['status']==='Rejected') echo 'selected'; ?>>Rejected</option>
                                        </select>
                                        <input type="text" name="admin_note" class="form-control" placeholder="Admin note..." value="<?php echo htmlspecialchars($ref['admin_note'] ?? ''); ?>" style="padding: 0.3rem; font-size: 0.8rem;">
                                        <button type="submit" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.5rem;">Update Status</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="8" style="padding: 1.5rem; text-align: center; color: var(--text-secondary);">No refund requests found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
