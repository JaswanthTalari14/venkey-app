<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$logs_q = $conn->query("
    SELECT l.*, u.name as admin_name
    FROM admin_activity_logs l
    JOIN users u ON l.admin_id = u.id
    ORDER BY l.created_at DESC
    LIMIT 50
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
            <li><a href="admin_audit.php" class="active"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="admin_risk.php"><i class="fas fa-shield-alt"></i> Risk Center</a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Orders</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallets</a></li>
            <li><a href="admin_reconciliation.php"><i class="fas fa-calculator"></i> Reconciliation</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-history" style="color: var(--primary-color);"></i> System Audit Log</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Append-oriented immutable security & audit logs of sensitive administrative actions.</p>

        <div class="glass-panel" style="padding: 1.5rem;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 0.8rem;">Timestamp</th>
                            <th style="padding: 0.8rem;">Admin</th>
                            <th style="padding: 0.8rem;">Action</th>
                            <th style="padding: 0.8rem;">Details / Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs_q && $logs_q->num_rows > 0): ?>
                            <?php while($l = $logs_q->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.8rem; font-size: 0.85rem; color: var(--text-secondary);"><?php echo date('M d, Y h:i A', strtotime($l['created_at'])); ?></td>
                                    <td style="padding: 0.8rem; font-weight: bold; color: var(--primary-color);"><?php echo htmlspecialchars($l['admin_name']); ?></td>
                                    <td style="padding: 0.8rem; font-weight: bold; text-transform: uppercase; font-size: 0.8rem; color: var(--secondary-color);"><?php echo htmlspecialchars($l['action']); ?></td>
                                    <td style="padding: 0.8rem; font-size: 0.85rem; color: var(--text-primary);"><?php echo htmlspecialchars($l['details'] ?: '-'); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="padding: 1.5rem; text-align: center; color: var(--text-secondary);">No audit logs recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
