<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$msg = '';

// Handle Admin Resolution / Manual Match Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resolve_mismatch'])) {
    $order_id = (int)($_POST['order_id'] ?? 0);
    $topup_id = (int)($_POST['topup_id'] ?? 0);
    $note = trim($_POST['admin_note'] ?? 'Manually reconciled by Admin');

    if ($order_id > 0) {
        $conn->query("UPDATE orders SET payment_status = 'Completed / Matched' WHERE id = $order_id");
        $msg = "Order #ORD-$order_id payment manually reconciled!";
    } elseif ($topup_id > 0) {
        $conn->query("UPDATE wallet_topups SET status = 'completed' WHERE id = $topup_id");
        $msg = "Wallet Top-up #$topup_id manually reconciled!";
    }

    // Log in admin activity log
    $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, details) VALUES (?, 'payment_reconciliation', ?)");
    $stmt->bind_param("is", $admin_id, $note);
    $stmt->execute();
}

// Reconcile stats
$matched_orders_q = $conn->query("SELECT COUNT(*) as cnt FROM orders WHERE payment_status LIKE '%Paid%' OR payment_status LIKE '%Completed%'");
$matched_orders = $matched_orders_q ? $matched_orders_q->fetch_assoc()['cnt'] : 0;

$matched_wallets_q = $conn->query("SELECT COUNT(*) as cnt FROM wallet_topups WHERE status IN ('approved', 'completed')");
$matched_wallets = $matched_wallets_q ? $matched_wallets_q->fetch_assoc()['cnt'] : 0;

$unmatched_orders_q = $conn->query("SELECT o.*, u.name as patient_name FROM orders o JOIN users u ON o.patient_id = u.id WHERE (o.gateway_payment_id IS NOT NULL AND o.gateway_payment_id != '') AND (o.payment_status LIKE '%Pending%' OR o.payment_status LIKE '%Failed%') ORDER BY o.created_at DESC");

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
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Orders</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallets</a></li>
            <li><a href="admin_reconciliation.php" class="active"><i class="fas fa-calculator"></i> Payment Reconciliation</a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_audit.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="admin_risk.php"><i class="fas fa-shield-alt"></i> Risk Center</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-calculator" style="color: var(--primary-color);"></i> Admin Payment Reconciliation</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Match gateway payments against orders and wallet credits to resolve financial mismatches.</p>

        <?php if ($msg): ?>
            <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="features-grid" style="margin-bottom: 2rem;">
            <div class="feature-card glass-panel" style="padding: 1.25rem; text-align: center;">
                <h4 style="color: #2ed573; margin-bottom: 0.5rem;"><i class="fas fa-box-check"></i> Matched Medicine Orders</h4>
                <p style="font-size: 2rem; font-weight: bold; margin: 0;"><?php echo $matched_orders; ?></p>
            </div>
            <div class="feature-card glass-panel" style="padding: 1.25rem; text-align: center;">
                <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;"><i class="fas fa-wallet"></i> Matched Wallet Top-ups</h4>
                <p style="font-size: 2rem; font-weight: bold; margin: 0;"><?php echo $matched_wallets; ?></p>
            </div>
            <div class="feature-card glass-panel" style="padding: 1.25rem; text-align: center;">
                <h4 style="color: #ff4757; margin-bottom: 0.5rem;"><i class="fas fa-exclamation-triangle"></i> Unmatched / Review Mismatches</h4>
                <p style="font-size: 2rem; font-weight: bold; margin: 0;"><?php echo $unmatched_orders_q ? $unmatched_orders_q->num_rows : 0; ?></p>
            </div>
        </div>

        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="margin-top: 0; color: var(--text-primary);"><i class="fas fa-search-dollar"></i> Flagged Gateway Payment Mismatches</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 0.8rem;">Order / Ref ID</th>
                            <th style="padding: 0.8rem;">Patient</th>
                            <th style="padding: 0.8rem;">Amount</th>
                            <th style="padding: 0.8rem;">Gateway Payment ID</th>
                            <th style="padding: 0.8rem;">Payment Status</th>
                            <th style="padding: 0.8rem;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($unmatched_orders_q && $unmatched_orders_q->num_rows > 0): ?>
                            <?php while($u = $unmatched_orders_q->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.8rem; font-weight: bold;">#ORD-<?php echo $u['id']; ?></td>
                                    <td style="padding: 0.8rem;"><?php echo htmlspecialchars($u['patient_name']); ?></td>
                                    <td style="padding: 0.8rem; color: var(--secondary-color); font-weight: bold;">₹<?php echo $u['total_amount']; ?></td>
                                    <td style="padding: 0.8rem; color: var(--primary-color); font-family: monospace;"><?php echo htmlspecialchars($u['gateway_payment_id']); ?></td>
                                    <td style="padding: 0.8rem;"><span style="color: #ff4757; font-weight: bold;"><?php echo htmlspecialchars($u['payment_status']); ?></span></td>
                                    <td style="padding: 0.8rem;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="resolve_mismatch" value="1">
                                            <input type="hidden" name="order_id" value="<?php echo $u['id']; ?>">
                                            <button type="submit" class="btn btn-primary" style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">Match & Verify</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="padding: 1.5rem; text-align: center; color: var(--text-secondary);">No financial payment mismatches detected. All gateway payments matched.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
