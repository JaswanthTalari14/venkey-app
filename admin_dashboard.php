<?php
require_once 'config.php';
require_once 'includes/security_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['user_id'];

// Feature 39: Admin Database Backup Tool (Export SQL Schema & Data)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_db'])) {
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="healthcare_db_backup_' . date('Y-m-d_H-i-s') . '.sql"');
    
    echo "-- Healthcare PWA Database Backup Export\n";
    echo "-- Exported on: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Platform: " . $_SERVER['HTTP_HOST'] . "\n\n";

    $tables = [];
    $res = $conn->query("SHOW TABLES");
    if ($res) {
        while ($row = $res->fetch_row()) {
            $tables[] = $row[0];
        }
    }

    foreach ($tables as $table) {
        $show_create = $conn->query("SHOW CREATE TABLE `$table`")->fetch_row();
        echo "DROP TABLE IF EXISTS `$table`;\n";
        echo $show_create[1] . ";\n\n";

        $rows = $conn->query("SELECT * FROM `$table`");
        if ($rows) {
            while ($r = $rows->fetch_assoc()) {
                $vals = array_map(function($v) use ($conn) {
                    if ($v === null) return 'NULL';
                    return "'" . $conn->real_escape_string($v) . "'";
                }, array_values($r));
                echo "INSERT INTO `$table` VALUES (" . implode(", ", $vals) . ");\n";
            }
        }
        echo "\n\n";
    }
    
    log_admin_activity($conn, $admin_id, 'Database Backup Exported', 'system', null, 'Downloaded full database SQL dump');
    exit;
}

// Feature 21: Advanced Platform Analytics
$total_patients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='patient'")->fetch_assoc()['count'];
$total_doctors = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='doctor'")->fetch_assoc()['count'];
$total_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];

$rev_orders = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE payment_status = 'completed' OR payment_status = 'Paid'")->fetch_assoc()['total'] ?? 0;
$rev_appointments = $conn->query("SELECT COUNT(*) * 300 as total FROM appointments WHERE status = 'confirmed' OR status = 'completed'")->fetch_assoc()['total'] ?? 0;
$total_revenue = $rev_orders + $rev_appointments;

$pending_refunds = $conn->query("SELECT COUNT(*) as count FROM refund_requests WHERE status='Requested'")->fetch_assoc()['count'] ?? 0;
$pending_wallet_topups = $conn->query("SELECT COUNT(*) as count FROM wallet_topups WHERE (LOWER(status) IN ('pending', 'pending_approval', 'amount_mismatch') OR status IS NULL OR status = '' OR LOWER(status) NOT IN ('approved', 'rejected', 'payment_failed'))")->fetch_assoc()['count'] ?? 0;

// Feature 17: Admin Global Search Query
$search_query = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_results = [];
if ($search_query !== '') {
    $sq = $conn->real_escape_string($search_query);
    
    // Search Users
    $u_res = $conn->query("SELECT id, name, email, role, phone FROM users WHERE name LIKE '%$sq%' OR email LIKE '%$sq%' OR phone LIKE '%$sq%' LIMIT 5");
    while($u = $u_res->fetch_assoc()) {
        $search_results[] = [
            'type' => 'User ('.ucfirst($u['role']).')',
            'title' => $u['name'],
            'subtitle' => $u['email'] . ' | Phone: ' . $u['phone'],
            'link' => 'admin_users.php?search=' . urlencode($u['name'])
        ];
    }

    // Search Orders
    $o_res = $conn->query("SELECT id, total_amount, status, payment_status FROM orders WHERE id LIKE '%$sq%' OR gateway_order_id LIKE '%$sq%' LIMIT 5");
    while($o = $o_res->fetch_assoc()) {
        $search_results[] = [
            'type' => 'Order',
            'title' => '#ORD-' . str_pad($o['id'], 4, '0', STR_PAD_LEFT),
            'subtitle' => 'Amount: ₹' . number_format($o['total_amount'], 2) . ' | Status: ' . $o['status'],
            'link' => 'admin_orders_management.php?search=' . urlencode($o['id'])
        ];
    }

    // Search Medicines
    $m_res = $conn->query("SELECT id, name, price, stock FROM medicines WHERE name LIKE '%$sq%' LIMIT 5");
    while($m = $m_res->fetch_assoc()) {
        $search_results[] = [
            'type' => 'Medicine',
            'title' => $m['name'],
            'subtitle' => 'Price: ₹' . number_format($m['price'], 2) . ' | Stock: ' . $m['stock'],
            'link' => 'admin_medicines.php?q=' . urlencode($m['name'])
        ];
    }
}

// Fetch latest registered users
$latest_users = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");

// Feature 22: Fetch Admin Activity Logs
$activity_logs = $conn->query("
    SELECT l.*, u.name as admin_name 
    FROM admin_activity_logs l 
    LEFT JOIN users u ON l.admin_id = u.id 
    ORDER BY l.created_at DESC LIMIT 10
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
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-chart-pie"></i> Overview</a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_reconciliation.php"><i class="fas fa-calculator"></i> Reconciliation</a></li>
            <li><a href="admin_cases.php"><i class="fas fa-briefcase"></i> Case Management</a></li>
            <li><a href="admin_system_health.php"><i class="fas fa-heartbeat"></i> System Health</a></li>
            <li><a href="admin_audit.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a></li>
            <li><a href="admin_risk.php"><i class="fas fa-shield-alt"></i> Risk Center</a></li>
            <li><a href="admin_announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Order Management</a></li>
            <li><a href="admin_refunds.php"><i class="fas fa-undo"></i> Refunds & Disputes</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_referrals.php"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_referral_settings.php"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <h2>Admin Management Dashboard</h2>
                <p style="color: var(--text-secondary); margin: 0;">Monitor platform activities, oversee doctors, and control services.</p>
            </div>
            
            <!-- Feature 39: Database Backup Button -->
            <form method="POST" style="margin: 0;">
                <button type="submit" name="export_db" value="1" class="btn btn-outline" style="font-size: 0.85rem; border-color: var(--secondary-color); color: var(--secondary-color);">
                    <i class="fas fa-database"></i> Export SQL Backup
                </button>
            </form>
        </div>

        <!-- Feature 17: Admin Global Search Bar -->
        <div class="glass-panel" style="padding: 1.25rem; margin-bottom: 2rem;">
            <form method="GET" action="admin_dashboard.php" style="display: flex; gap: 0.5rem; align-items: center;">
                <div style="position: relative; flex: 1;">
                    <i class="fas fa-search" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); color: var(--text-secondary);"></i>
                    <input type="text" name="q" class="form-control" placeholder="Global Search across Patients, Doctors, Orders, Medicines..." value="<?php echo htmlspecialchars($search_query); ?>" style="padding-left: 2.75rem;">
                </div>
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.25rem;">Search</button>
                <?php if ($search_query): ?>
                    <a href="admin_dashboard.php" class="btn btn-outline" style="padding: 0.75rem 1rem;">Clear</a>
                <?php endif; ?>
            </form>

            <?php if ($search_query !== ''): ?>
                <div style="margin-top: 1.25rem; border-top: 1px solid var(--glass-border); padding-top: 1rem;">
                    <h4 style="margin-bottom: 0.75rem; color: var(--secondary-color);">Search Results for "<?php echo htmlspecialchars($search_query); ?>" (<?php echo count($search_results); ?> matches)</h4>
                    <?php if (count($search_results) > 0): ?>
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <?php foreach ($search_results as $sr): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; background: rgba(255,255,255,0.03); border-radius: 8px; border: 1px solid rgba(255,255,255,0.05);">
                                    <div>
                                        <span style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; background: rgba(255,255,255,0.1); padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: bold; margin-right: 0.5rem;">
                                            <?php echo htmlspecialchars($sr['type']); ?>
                                        </span>
                                        <strong><?php echo htmlspecialchars($sr['title']); ?></strong>
                                        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-top: 0.2rem;"><?php echo htmlspecialchars($sr['subtitle']); ?></div>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($sr['link']); ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.3rem 0.8rem;">View</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-secondary); margin: 0;">No matching records found in Patients, Doctors, Orders, or Medicines.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Feature 21: Analytics Cards -->
        <div class="features-grid" style="margin-top: 1rem;">
            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: #2ed573;"><i class="fas fa-coins"></i> Total Platform Revenue</h4>
                <p style="font-size: 2.2rem; font-weight: bold; margin: 0.5rem 0;">₹<?php echo number_format($total_revenue, 2); ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: var(--primary-color);"><i class="fas fa-users"></i> Total Patients</h4>
                <p style="font-size: 2.2rem; font-weight: bold; margin: 0.5rem 0;"><?php echo $total_patients; ?></p>
            </div>
            
            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: var(--secondary-color);"><i class="fas fa-user-md"></i> Total Doctors</h4>
                <p style="font-size: 2.2rem; font-weight: bold; margin: 0.5rem 0;"><?php echo $total_doctors; ?></p>
            </div>
            
            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: var(--accent);"><i class="fas fa-calendar-alt"></i> Total Appointments</h4>
                <p style="font-size: 2.2rem; font-weight: bold; margin: 0.5rem 0;"><?php echo $total_appointments; ?></p>
            </div>
            
            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: #fff;"><i class="fas fa-truck"></i> Total Orders</h4>
                <p style="font-size: 2.2rem; font-weight: bold; margin: 0.5rem 0;"><?php echo $total_orders; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: #ff4757;"><i class="fas fa-undo"></i> Pending Refunds</h4>
                <p style="font-size: 2.2rem; font-weight: bold; margin: 0.5rem 0;"><?php echo $pending_refunds; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: #f39c12;"><a href="admin_wallets.php?topup_status=pending" style="color: inherit; text-decoration: none;"><i class="fas fa-wallet"></i> Pending Wallet Requests</a></h4>
                <p style="font-size: 2.2rem; font-weight: bold; margin: 0.5rem 0; color: #f39c12;"><?php echo $pending_wallet_topups; ?></p>
                <small><a href="admin_wallets.php?topup_status=pending" style="color: #f39c12; text-decoration: underline; font-size: 0.8rem;">Review Requests &rarr;</a></small>
            </div>
        </div>

        <h3 style="margin-top: 3rem; margin-bottom: 1rem;">Recently Registered Users</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem; margin-bottom: 2.5rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">ID</th>
                        <th style="padding: 1rem;">Name</th>
                        <th style="padding: 1rem;">Email</th>
                        <th style="padding: 1rem;">Role</th>
                        <th style="padding: 1rem;">Registration Date</th>
                        <th style="padding: 1rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($latest_users && $latest_users->num_rows > 0): ?>
                        <?php while($u = $latest_users->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#<?php echo $u['id']; ?></td>
                                <td style="padding: 1rem; font-weight: bold;"><?php echo htmlspecialchars($u['name']); ?></td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td style="padding: 1rem;">
                                    <span style="background: rgba(255,255,255,0.1); padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; text-transform: capitalize;">
                                        <?php echo htmlspecialchars($u['role']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td style="padding: 1rem;"><a href="admin_users.php?search=<?php echo urlencode($u['name']); ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 1rem;">Manage</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 1rem; text-align: center;">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Feature 22: Admin Activity Log Viewer -->
        <h3><i class="fas fa-list-alt" style="color: var(--secondary-color);"></i> System Activity Logs</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem; margin-top: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 0.8rem;">Timestamp</th>
                        <th style="padding: 0.8rem;">Admin</th>
                        <th style="padding: 0.8rem;">Action</th>
                        <th style="padding: 0.8rem;">Entity</th>
                        <th style="padding: 0.8rem;">Details</th>
                        <th style="padding: 0.8rem;">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($activity_logs && $activity_logs->num_rows > 0): ?>
                        <?php while($log = $activity_logs->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 0.8rem; color: var(--text-secondary); font-size: 0.85rem;"><?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?></td>
                                <td style="padding: 0.8rem; font-weight: bold; color: var(--primary-color);"><?php echo htmlspecialchars($log['admin_name'] ?? 'Admin #'.$log['admin_id']); ?></td>
                                <td style="padding: 0.8rem; font-weight: 600;"><?php echo htmlspecialchars($log['action']); ?></td>
                                <td style="padding: 0.8rem; color: var(--secondary-color);"><?php echo htmlspecialchars($log['entity_type'] ? $log['entity_type'].' #'.$log['entity_id'] : 'System'); ?></td>
                                <td style="padding: 0.8rem; color: var(--text-secondary); font-size: 0.85rem; max-width: 250px;"><?php echo htmlspecialchars($log['details']); ?></td>
                                <td style="padding: 0.8rem; color: var(--text-secondary); font-size: 0.85rem; font-family: monospace;"><?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 1rem; text-align: center; color: var(--text-secondary);">No activity logs recorded yet. Operations will be logged automatically.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>

