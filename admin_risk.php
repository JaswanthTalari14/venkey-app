<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dismiss_flag'])) {
    $flag_id = (int)($_POST['flag_id'] ?? 0);
    $conn->query("UPDATE system_risk_flags SET status = 'dismissed' WHERE id = $flag_id");
    $msg = "Risk flag #$flag_id reviewed and dismissed.";
}

// Auto-Detect Risks (Dynamic calculation from actual DB data)
// 1. Detect duplicate gateway payment IDs in orders
$dup_q = $conn->query("SELECT gateway_payment_id, COUNT(*) as cnt FROM orders WHERE gateway_payment_id IS NOT NULL AND gateway_payment_id != '' GROUP BY gateway_payment_id HAVING cnt > 1");
if ($dup_q) {
    while($row = $dup_q->fetch_assoc()) {
        $gw = $conn->real_escape_string($row['gateway_payment_id']);
        $chk = $conn->query("SELECT id FROM system_risk_flags WHERE entity_type = 'order_payment' AND flag_reason LIKE '%$gw%'");
        if ($chk && $chk->num_rows === 0) {
            $reason = "Critical: Duplicate Gateway Payment Reference detected ($gw)";
            $conn->query("INSERT INTO system_risk_flags (entity_type, severity, flag_reason, status) VALUES ('order_payment', 'critical', '$reason', 'pending')");
        }
    }
}

// 2. Detect repeated refund requests (>2 per patient)
$ref_q = $conn->query("SELECT patient_id, COUNT(*) as cnt FROM refund_requests GROUP BY patient_id HAVING cnt >= 2");
if ($ref_q) {
    while($row = $ref_q->fetch_assoc()) {
        $pid = (int)$row['patient_id'];
        $chk = $conn->query("SELECT id FROM system_risk_flags WHERE user_id = $pid AND entity_type = 'refund_request'");
        if ($chk && $chk->num_rows === 0) {
            $reason = "Medium: Multiple refund requests submitted by Patient ID #$pid";
            $conn->query("INSERT INTO system_risk_flags (user_id, entity_type, severity, flag_reason, status) VALUES ($pid, 'refund_request', 'medium', '$reason', 'pending')");
        }
    }
}

$flags_q = $conn->query("SELECT * FROM system_risk_flags WHERE status = 'pending' ORDER BY created_at DESC");

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
            <li><a href="admin_risk.php" class="active"><i class="fas fa-shield-alt"></i> Risk Center</a></li>
            <li><a href="admin_audit.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Orders</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallets</a></li>
            <li><a href="admin_reconciliation.php"><i class="fas fa-calculator"></i> Reconciliation</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-shield-alt" style="color: #ff4757;"></i> Security & Financial Risk Center</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Intelligent anomaly detection for duplicate payment references, unusual wallet activity, and repeated refund patterns.</p>

        <?php if ($msg): ?>
            <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="margin-top: 0; color: var(--text-primary);"><i class="fas fa-flag" style="color: #ff4757;"></i> Pending Risk Flags</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 0.8rem;">Flag ID</th>
                            <th style="padding: 0.8rem;">Severity</th>
                            <th style="padding: 0.8rem;">Risk Category</th>
                            <th style="padding: 0.8rem;">Flag Reason / Observation</th>
                            <th style="padding: 0.8rem;">Detected At</th>
                            <th style="padding: 0.8rem;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($flags_q && $flags_q->num_rows > 0): ?>
                            <?php while($f = $flags_q->fetch_assoc()): 
                                $sev_color = '#ff4757';
                                if ($f['severity'] === 'high') $sev_color = 'var(--accent)';
                                elseif ($f['severity'] === 'medium') $sev_color = '#ffab00';
                            ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.8rem; font-weight: bold;">#FLAG-<?php echo $f['id']; ?></td>
                                    <td style="padding: 0.8rem;">
                                        <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.75rem; font-weight: bold; background: rgba(255, 71, 87, 0.15); color: <?php echo $sev_color; ?>; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($f['severity']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.8rem; font-weight: bold; color: var(--secondary-color);"><?php echo htmlspecialchars(str_replace('_', ' ', $f['entity_type'])); ?></td>
                                    <td style="padding: 0.8rem; color: var(--text-primary); font-size: 0.9rem;"><?php echo htmlspecialchars($f['flag_reason']); ?></td>
                                    <td style="padding: 0.8rem; color: var(--text-secondary); font-size: 0.85rem;"><?php echo date('M d, h:i A', strtotime($f['created_at'])); ?></td>
                                    <td style="padding: 0.8rem;">
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="dismiss_flag" value="1">
                                            <input type="hidden" name="flag_id" value="<?php echo $f['id']; ?>">
                                            <button type="submit" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">Review & Dismiss</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" style="padding: 1.5rem; text-align: center; color: #2ed573; font-weight: bold;"><i class="fas fa-check-circle"></i> System Clean! No pending security or financial risk flags.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
