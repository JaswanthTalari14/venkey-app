<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $target = trim($_POST['target_role'] ?? 'all');
    $status = trim($_POST['status'] ?? 'published');

    if ($title && $message) {
        $stmt = $conn->prepare("INSERT INTO announcements (title, message, target_role, status, created_by) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssi", $title, $message, $target, $status, $admin_id);
        if ($stmt->execute()) {
            $msg = "System announcement published successfully!";

            // If published, trigger notifications for targeted users
            if ($status === 'published') {
                require_once 'includes/notification_functions.php';
                $role_sql = ($target === 'all') ? "" : "WHERE role = '$target'";
                $u_res = $conn->query("SELECT id FROM users $role_sql");
                if ($u_res) {
                    while($u = $u_res->fetch_assoc()) {
                        send_user_notification($u['id'], 'system', $title, $message, 'announcement');
                    }
                }
            }
        }
    }
}

$announcements_q = $conn->query("SELECT * FROM announcements ORDER BY created_at DESC");

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
            <li><a href="admin_announcements.php" class="active"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li><a href="admin_risk.php"><i class="fas fa-shield-alt"></i> Risk Center</a></li>
            <li><a href="admin_audit.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Orders</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallets</a></li>
            <li><a href="admin_reconciliation.php"><i class="fas fa-calculator"></i> Reconciliation</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0;"><i class="fas fa-bullhorn" style="color: var(--primary-color);"></i> System Announcement Center</h2>
                <p style="color: var(--text-secondary); margin: 0.3rem 0 0;">Publish platform announcements targeted at Patients, Doctors, or RMPs.</p>
            </div>
            <button onclick="document.getElementById('newAnnModal').style.display='flex'" class="btn btn-primary" style="font-size: 0.85rem;">
                <i class="fas fa-plus"></i> New Announcement
            </button>
        </div>

        <?php if ($msg): ?>
            <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="glass-panel" style="padding: 1.5rem;">
            <div style="overflow-x: auto;">
                <table style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 0.8rem;">Title</th>
                            <th style="padding: 0.8rem;">Target Audience</th>
                            <th style="padding: 0.8rem;">Status</th>
                            <th style="padding: 0.8rem;">Date Published</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($announcements_q && $announcements_q->num_rows > 0): ?>
                            <?php while($a = $announcements_q->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.8rem; font-weight: bold; color: var(--text-primary);"><?php echo htmlspecialchars($a['title']); ?></td>
                                    <td style="padding: 0.8rem; text-transform: uppercase; font-size: 0.8rem; font-weight: bold; color: var(--secondary-color);"><?php echo htmlspecialchars($a['target_role']); ?></td>
                                    <td style="padding: 0.8rem;">
                                        <span style="padding: 0.2rem 0.5rem; border-radius: 10px; font-size: 0.75rem; font-weight: bold; background: rgba(46, 213, 115, 0.15); color: #2ed573; text-transform: uppercase;">
                                            <?php echo htmlspecialchars($a['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.8rem; color: var(--text-secondary); font-size: 0.85rem;"><?php echo date('M d, Y h:i A', strtotime($a['created_at'])); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="padding: 1.5rem; text-align: center; color: var(--text-secondary);">No announcements created yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- New Announcement Modal -->
<div id="newAnnModal" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: var(--darker-bg, #121826); border: 1px solid var(--glass-border); border-radius: 16px; padding: 1.5rem; max-width: 500px; width: 100%; color: var(--text-primary);">
        <h3 style="margin-top: 0;">Publish System Announcement</h3>
        <form method="POST">
            <input type="hidden" name="create_announcement" value="1">
            <div class="form-group">
                <label>Announcement Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. Platform Scheduled Maintenance Notice">
            </div>
            <div class="form-group">
                <label>Target Audience *</label>
                <select name="target_role" class="form-control" required>
                    <option value="all">All Users (Patients, Doctors, RMPs)</option>
                    <option value="patient">Patients Only</option>
                    <option value="doctor">Doctors Only</option>
                    <option value="rmp">RMPs Only</option>
                </select>
            </div>
            <div class="form-group">
                <label>Message Content *</label>
                <textarea name="message" class="form-control" rows="4" required placeholder="Enter announcement body text..."></textarea>
            </div>
            <div style="display: flex; gap: 0.8rem; margin-top: 1.5rem;">
                <button type="button" onclick="document.getElementById('newAnnModal').style.display='none'" class="btn btn-outline" style="flex: 1;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1;">Publish Now</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
