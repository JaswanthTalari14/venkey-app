<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success = '';

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = $conn->real_escape_string($_POST['status']);
    
    if ($conn->query("UPDATE orders SET status='$new_status' WHERE id=$order_id")) {
        $success = "Order #ORD-" . str_pad($order_id, 4, '0', STR_PAD_LEFT) . " status updated to $new_status!";
        require_once 'includes/referral_functions.php';
        sync_pending_referrals();
    }
}

$orders = $conn->query("
    SELECT o.id, o.total_amount, o.status, o.payment_method, o.payment_status, o.address, o.created_at, p.name as patient_name 
    FROM orders o
    JOIN users p ON o.patient_id = p.id
    ORDER BY o.created_at DESC
");
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
            <li><a href="admin_orders.php" class="active"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_referrals.php"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_referral_settings.php"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Medicine Delivery Orders</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Manage and track all medicine delivery orders placed by patients.</p>

        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem; padding: 1rem; background: rgba(46, 213, 115, 0.1); border-radius: 8px;"><?php echo $success; ?></p><?php endif; ?>

        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Order ID</th>
                        <th style="padding: 1rem;">Patient</th>
                        <th style="padding: 1rem;">Total Amount</th>
                        <th style="padding: 1rem;">Payment Method</th>
                        <th style="padding: 1rem;">Payment Status</th>
                        <th style="padding: 1rem;">Date</th>
                        <th style="padding: 1rem;">Status</th>
                        <th style="padding: 1rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($orders && $orders->num_rows > 0): ?>
                        <?php while($o = $orders->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#ORD-<?php echo str_pad($o['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td style="padding: 1rem; font-weight: bold;"><?php echo htmlspecialchars($o['patient_name']); ?></td>
                                <td style="padding: 1rem; color: var(--secondary-color);">₹<?php echo $o['total_amount']; ?></td>
                                <td style="padding: 1rem; font-size: 0.9rem; color: var(--text-secondary);"><?php echo htmlspecialchars($o['payment_method'] ?? 'COD'); ?></td>
                                <td style="padding: 1rem;">
                                    <?php
                                        $pay_status = $o['payment_status'] ?? 'Cash on Delivery';
                                        $pay_color = '#f5a623';
                                        if ($pay_status === 'Paid') $pay_color = '#2ed573';
                                        if ($pay_status === 'Failed') $pay_color = '#ff4757';
                                        if ($pay_status === 'Cash on Delivery') $pay_color = '#3498db';
                                    ?>
                                    <span style="color: <?php echo $pay_color; ?>; font-weight: bold; font-size: 0.82rem;">
                                        <?php echo htmlspecialchars($pay_status); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                                <td style="padding: 1rem;">
                                    <?php
                                        $status_color = 'var(--text-primary)';
                                        if ($o['status'] == 'pending') $status_color = 'var(--accent)';
                                        if ($o['status'] == 'shipped') $status_color = '#3498db';
                                        if ($o['status'] == 'delivered') $status_color = '#2ed573';
                                        if ($o['status'] == 'cancelled') $status_color = '#ff4757';
                                    ?>
                                    <span style="color: <?php echo $status_color; ?>; font-weight: bold; text-transform: capitalize; background: rgba(255,255,255,0.05); padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.8rem;">
                                        <?php echo $o['status']; ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem;">
                                    <form method="POST" action="" style="display: flex; gap: 0.5rem; align-items: center;">
                                        <input type="hidden" name="order_id" value="<?php echo $o['id']; ?>">
                                        <select name="status" class="form-control" style="padding: 0.3rem; font-size: 0.8rem; width: 110px;">
                                            <option value="pending" <?php if($o['status']=='pending') echo 'selected'; ?>>Pending</option>
                                            <option value="shipped" <?php if($o['status']=='shipped') echo 'selected'; ?>>Shipped</option>
                                            <option value="delivered" <?php if($o['status']=='delivered') echo 'selected'; ?>>Delivered</option>
                                            <option value="cancelled" <?php if($o['status']=='cancelled') echo 'selected'; ?>>Cancelled</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 1rem; text-align: center;">No orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
