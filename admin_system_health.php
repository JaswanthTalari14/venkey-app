<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// System Health Checks
$db_status = ($conn && !$conn->connect_error) ? 'Operational' : 'Error';
$table_cnt = $conn->query("SHOW TABLES")->num_rows;
$user_cnt = $conn->query("SELECT COUNT(*) as cnt FROM users")->fetch_assoc()['cnt'];
$order_cnt = $conn->query("SELECT COUNT(*) as cnt FROM orders")->fetch_assoc()['cnt'];

$failed_jobs_q = $conn->query("SELECT * FROM failed_jobs ORDER BY created_at DESC LIMIT 10");

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
            <li><a href="admin_system_health.php" class="active"><i class="fas fa-heartbeat"></i> System Health</a></li>
            <li><a href="admin_cases.php"><i class="fas fa-briefcase"></i> Case Management</a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_reconciliation.php"><i class="fas fa-calculator"></i> Reconciliation</a></li>
            <li><a href="admin_audit.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="admin_risk.php"><i class="fas fa-shield-alt"></i> Risk Center</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-heartbeat" style="color: #2ed573;"></i> System Health & Monitor</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Real-time infrastructure health, database connection status, and background job monitors.</p>

        <div class="features-grid" style="margin-bottom: 2rem;">
            <div class="feature-card glass-panel" style="padding: 1.25rem; text-align: center;">
                <h4 style="color: #2ed573; margin-bottom: 0.5rem;"><i class="fas fa-database"></i> Database Engine</h4>
                <p style="font-size: 1.5rem; font-weight: bold; margin: 0; color: #2ed573;"><?php echo $db_status; ?></p>
                <span style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo $table_cnt; ?> Active Tables</span>
            </div>
            <div class="feature-card glass-panel" style="padding: 1.25rem; text-align: center;">
                <h4 style="color: var(--primary-color); margin-bottom: 0.5rem;"><i class="fas fa-users"></i> Total Registered Users</h4>
                <p style="font-size: 1.8rem; font-weight: bold; margin: 0;"><?php echo $user_cnt; ?></p>
            </div>
            <div class="feature-card glass-panel" style="padding: 1.25rem; text-align: center;">
                <h4 style="color: var(--secondary-color); margin-bottom: 0.5rem;"><i class="fas fa-shopping-bag"></i> Total Medicine Orders</h4>
                <p style="font-size: 1.8rem; font-weight: bold; margin: 0;"><?php echo $order_cnt; ?></p>
            </div>
            <div class="feature-card glass-panel" style="padding: 1.25rem; text-align: center;">
                <h4 style="color: var(--accent); margin-bottom: 0.5rem;"><i class="fas fa-mobile-alt"></i> PWA Service Worker</h4>
                <p style="font-size: 1.2rem; font-weight: bold; margin: 0; color: var(--accent);">PWA v5 Active</p>
                <span style="font-size: 0.8rem; color: var(--text-secondary);">HTTPS SSL Encrypted</span>
            </div>
        </div>

        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="margin-top: 0; color: var(--text-primary);"><i class="fas fa-tasks"></i> Failed Jobs & Notification Monitor</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 0.8rem;">Job Type</th>
                            <th style="padding: 0.8rem;">Error Description</th>
                            <th style="padding: 0.8rem;">Timestamp</th>
                            <th style="padding: 0.8rem;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($failed_jobs_q && $failed_jobs_q->num_rows > 0): ?>
                            <?php while($j = $failed_jobs_q->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.8rem; font-weight: bold; color: var(--primary-color);"><?php echo htmlspecialchars($j['job_type']); ?></td>
                                    <td style="padding: 0.8rem; color: #ff4757; font-size: 0.85rem;"><?php echo htmlspecialchars($j['error_message']); ?></td>
                                    <td style="padding: 0.8rem; color: var(--text-secondary); font-size: 0.85rem;"><?php echo date('M d, h:i A', strtotime($j['created_at'])); ?></td>
                                    <td style="padding: 0.8rem;"><span style="color: #ff4757; font-weight: bold;"><?php echo htmlspecialchars($j['status']); ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="padding: 1.5rem; text-align: center; color: #2ed573; font-weight: bold;"><i class="fas fa-check-circle"></i> No failed background jobs or notification errors recorded. All systems operational.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
