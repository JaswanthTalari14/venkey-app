<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_case'])) {
    $case_id = (int)($_POST['case_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'resolved');
    $notes = trim($_POST['notes'] ?? '');

    $stmt = $conn->prepare("UPDATE support_cases SET status = ?, notes = ?, assigned_admin_id = ? WHERE id = ?");
    $stmt->bind_param("ssii", $status, $notes, $admin_id, $case_id);
    $stmt->execute();
    $msg = "Case #CASE-$case_id status updated to " . ucfirst($status);
}

// Fetch reported order issues and cases
$issues_q = $conn->query("
    SELECT i.*, p.name as patient_name, p.phone
    FROM order_issues i
    JOIN users p ON i.patient_id = p.id
    ORDER BY i.created_at DESC
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
            <li><a href="admin_cases.php" class="active"><i class="fas fa-briefcase"></i> Case Management</a></li>
            <li><a href="admin_system_health.php"><i class="fas fa-heartbeat"></i> System Health</a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_reconciliation.php"><i class="fas fa-calculator"></i> Reconciliation</a></li>
            <li><a href="admin_audit.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="admin_risk.php"><i class="fas fa-shield-alt"></i> Risk Center</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-briefcase" style="color: var(--primary-color);"></i> Admin Support & Case Management</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Investigate patient order issues, reported delivery disputes, and support cases.</p>

        <?php if ($msg): ?>
            <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="margin-top: 0; color: var(--text-primary);"><i class="fas fa-clipboard-check"></i> Reported Order Cases & Disputes</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 0.8rem;">Case ID</th>
                            <th style="padding: 0.8rem;">Order ID</th>
                            <th style="padding: 0.8rem;">Patient</th>
                            <th style="padding: 0.8rem;">Issue Category</th>
                            <th style="padding: 0.8rem;">Description</th>
                            <th style="padding: 0.8rem;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($issues_q && $issues_q->num_rows > 0): ?>
                            <?php while($is = $issues_q->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.8rem; font-weight: bold;">#CASE-<?php echo $is['id']; ?></td>
                                    <td style="padding: 0.8rem; font-weight: bold; color: var(--primary-color);">#ORD-<?php echo $is['order_id']; ?></td>
                                    <td style="padding: 0.8rem;">
                                        <strong><?php echo htmlspecialchars($is['patient_name']); ?></strong><br>
                                        <a href="tel:<?php echo htmlspecialchars($is['phone']); ?>" style="font-size: 0.8rem; color: var(--secondary-color);"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($is['phone']); ?></a>
                                    </td>
                                    <td style="padding: 0.8rem; font-weight: bold; color: var(--accent);"><?php echo htmlspecialchars($is['issue_type']); ?></td>
                                    <td style="padding: 0.8rem; font-size: 0.85rem; color: var(--text-primary);"><?php echo htmlspecialchars($is['description']); ?></td>
                                    <td style="padding: 0.8rem;">
                                        <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: bold; background: rgba(255, 171, 0, 0.15); color: var(--accent);">
                                            <?php echo htmlspecialchars($is['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="padding: 1.5rem; text-align: center; color: var(--text-secondary);">No active order cases or disputes reported.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
