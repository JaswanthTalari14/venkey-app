<?php
require_once 'config.php';
require_once 'includes/security_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Ensure database indexes exist for performance
@$conn->query("CREATE INDEX idx_aal_created ON admin_activity_logs (created_at)");
@$conn->query("CREATE INDEX idx_aal_action ON admin_activity_logs (action)");

// Filter Parameters
$date_preset = isset($_GET['preset']) ? trim($_GET['preset']) : 'all';
$from_date = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$to_date = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
$admin_filter = isset($_GET['admin_id']) ? (int)$_GET['admin_id'] : 0;
$action_filter = isset($_GET['action_type']) ? trim($_GET['action_type']) : 'all';
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';

$where_clauses = ["1=1"];

// Handle Preset Date Filters
if ($date_preset === 'today') {
    $where_clauses[] = "DATE(l.created_at) = CURDATE()";
} elseif ($date_preset === 'yesterday') {
    $where_clauses[] = "DATE(l.created_at) = SUBDATE(CURDATE(), INTERVAL 1 DAY)";
} elseif ($date_preset === 'last_7_days') {
    $where_clauses[] = "l.created_at >= NOW() - INTERVAL 7 DAY";
} elseif ($date_preset === 'last_30_days') {
    $where_clauses[] = "l.created_at >= NOW() - INTERVAL 30 DAY";
} elseif ($date_preset === 'custom' && !empty($from_date) && !empty($to_date)) {
    $f_date = $conn->real_escape_string($from_date) . " 00:00:00";
    $t_date = $conn->real_escape_string($to_date) . " 23:59:59";
    $where_clauses[] = "(l.created_at BETWEEN '$f_date' AND '$t_date')";
}

if ($admin_filter > 0) {
    $where_clauses[] = "l.admin_id = $admin_filter";
}

if ($action_filter !== 'all' && !empty($action_filter)) {
    $act_esc = $conn->real_escape_string($action_filter);
    $where_clauses[] = "(l.entity_type = '$act_esc' OR l.action LIKE '%$act_esc%')";
}

if (!empty($search_q)) {
    $sq_esc = $conn->real_escape_string($search_q);
    $where_clauses[] = "(l.action LIKE '%$sq_esc%' OR l.details LIKE '%$sq_esc%' OR u.name LIKE '%$sq_esc%' OR CAST(l.entity_id AS CHAR) LIKE '%$sq_esc%')";
}

$where_sql = implode(" AND ", $where_clauses);

// Server-side Pagination
$per_page = 25;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$count_q = $conn->query("
    SELECT COUNT(*) as total 
    FROM admin_activity_logs l
    LEFT JOIN users u ON l.admin_id = u.id
    WHERE $where_sql
");
$total_records = ($count_q) ? (int)$count_q->fetch_assoc()['total'] : 0;
$total_pages = max(1, ceil($total_records / $per_page));

// Query Persistent Audit Logs
$logs_q = $conn->query("
    SELECT l.*, u.name as admin_name, u.email as admin_email
    FROM admin_activity_logs l
    LEFT JOIN users u ON l.admin_id = u.id
    WHERE $where_sql
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT $per_page OFFSET $offset
");

// Fetch Admins list for filter dropdown
$admins_q = $conn->query("SELECT id, name FROM users WHERE role = 'admin' ORDER BY name ASC");

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
            <li><a href="admin_approval_center.php"><i class="fas fa-check-double"></i> Approval Center</a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_audit.php" class="active"><i class="fas fa-history"></i> Audit Timeline</a></li>
            <li><a href="admin_digital_cards.php"><i class="fas fa-id-card"></i> Medical Cards</a></li>
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallets</a></li>
            <li><a href="admin_refunds.php"><i class="fas fa-undo"></i> Refunds</a></li>
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Orders</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <h2><i class="fas fa-history" style="color: var(--primary-color);"></i> Admin Audit Timeline & Security Logs</h2>
                <p style="color: var(--text-secondary); margin-top: 0.3rem;">Immutable, persistent server-side audit trail recording all administrative operations.</p>
            </div>

            <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
                <div style="background: rgba(80, 227, 194, 0.15); border: 1px solid #50e3c2; color: #50e3c2; padding: 0.4rem 1rem; border-radius: 20px; font-weight: bold; font-size: 0.85rem;">
                    <i class="fas fa-shield-alt"></i> Recorded Events: <?php echo number_format($total_records); ?>
                </div>
                <a href="verify_document.php" target="_blank" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.4rem 0.9rem;">
                    <i class="fas fa-search"></i> Verification Portal
                </a>
            </div>
        </div>

        <?php
        // Fetch Document Verification System Summary Stats
        $tot_ver_docs = (int)($conn->query("SELECT COUNT(*) as cnt FROM document_verifications")->fetch_assoc()['cnt'] ?? 0);
        $tot_revoked_docs = (int)($conn->query("SELECT COUNT(*) as cnt FROM document_verifications WHERE status = 'REVOKED'")->fetch_assoc()['cnt'] ?? 0);
        $tot_suspicious_reports = (int)($conn->query("SELECT COUNT(*) as cnt FROM document_verification_reports")->fetch_assoc()['cnt'] ?? 0);
        ?>

        <!-- Document Authenticity System Counter Summary -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <div class="glass-panel" style="padding: 1.25rem; border-left: 4px solid #10b981;">
                <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; font-weight: bold;">Verified Medical Documents</div>
                <div style="font-size: 1.8rem; font-weight: bold; color: #10b981; margin: 0.3rem 0;"><?php echo number_format($tot_ver_docs); ?></div>
                <div style="font-size: 0.78rem; color: var(--text-secondary);"><i class="fas fa-fingerprint me-1"></i> SHA-256 Registered</div>
            </div>
            <div class="glass-panel" style="padding: 1.25rem; border-left: 4px solid #ef4444;">
                <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; font-weight: bold;">Revoked Documents</div>
                <div style="font-size: 1.8rem; font-weight: bold; color: #ef4444; margin: 0.3rem 0;"><?php echo number_format($tot_revoked_docs); ?></div>
                <div style="font-size: 0.78rem; color: var(--text-secondary);"><i class="fas fa-ban me-1"></i> Invalidated by Issuer</div>
            </div>
            <div class="glass-panel" style="padding: 1.25rem; border-left: 4px solid #f59e0b;">
                <div style="font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; font-weight: bold;">Suspicious Reports</div>
                <div style="font-size: 1.8rem; font-weight: bold; color: #f59e0b; margin: 0.3rem 0;"><?php echo number_format($tot_suspicious_reports); ?></div>
                <div style="font-size: 0.78rem; color: var(--text-secondary);"><i class="fas fa-flag me-1"></i> Flagged for Audit</div>
            </div>
        </div>

        <!-- Filter & Search Panel -->
        <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2rem;">
            <form method="GET" action="admin_audit.php" style="display: flex; flex-direction: column; gap: 1rem;">
                <!-- Date Presets Row -->
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                    <span style="font-size: 0.82rem; color: var(--text-secondary); font-weight: bold; margin-right: 0.5rem;">DATE FILTER:</span>
                    <a href="admin_audit.php?preset=all" class="btn <?php echo $date_preset === 'all' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.3rem 0.8rem;">All Time</a>
                    <a href="admin_audit.php?preset=today" class="btn <?php echo $date_preset === 'today' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.3rem 0.8rem;">Today</a>
                    <a href="admin_audit.php?preset=yesterday" class="btn <?php echo $date_preset === 'yesterday' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.3rem 0.8rem;">Yesterday</a>
                    <a href="admin_audit.php?preset=last_7_days" class="btn <?php echo $date_preset === 'last_7_days' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.3rem 0.8rem;">Last 7 Days</a>
                    <a href="admin_audit.php?preset=last_30_days" class="btn <?php echo $date_preset === 'last_30_days' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.8rem; padding: 0.3rem 0.8rem;">Last 30 Days</a>
                </div>

                <!-- Custom Filters Row -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                    <div>
                        <label style="display: block; font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 0.3rem;">Filter by Admin</label>
                        <select name="admin_id" class="form-control" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                            <option value="0">All Admins</option>
                            <?php if ($admins_q): ?>
                                <?php while ($adm = $admins_q->fetch_assoc()): ?>
                                    <option value="<?php echo $adm['id']; ?>" <?php echo $admin_filter == $adm['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($adm['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 0.3rem;">Filter by Action Type</label>
                        <select name="action_type" class="form-control" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                            <option value="all" <?php echo $action_filter === 'all' ? 'selected' : ''; ?>>All Action Types</option>
                            <option value="digital_medical_cards" <?php echo $action_filter === 'digital_medical_cards' ? 'selected' : ''; ?>>Medical Card Actions</option>
                            <option value="wallet" <?php echo $action_filter === 'wallet' ? 'selected' : ''; ?>>Wallet Top-Up Actions</option>
                            <option value="users" <?php echo $action_filter === 'users' ? 'selected' : ''; ?>>Verification & User Actions</option>
                            <option value="refund_requests" <?php echo $action_filter === 'refund_requests' ? 'selected' : ''; ?>>Refund Actions</option>
                            <option value="orders" <?php echo $action_filter === 'orders' ? 'selected' : ''; ?>>Order Actions</option>
                        </select>
                    </div>

                    <div>
                        <label style="display: block; font-size: 0.78rem; color: var(--text-secondary); margin-bottom: 0.3rem;">Search Audit Details</label>
                        <input type="text" name="q" class="form-control" placeholder="Search action, details, ref ID..." value="<?php echo htmlspecialchars($search_q); ?>" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                    </div>

                    <div style="display: flex; align-items: flex-end; gap: 0.5rem;">
                        <input type="hidden" name="preset" value="custom">
                        <button type="submit" class="btn btn-primary" style="padding: 0.45rem 1.2rem; font-size: 0.85rem; flex: 1;"><i class="fas fa-filter"></i> Apply Filters</button>
                        <a href="admin_audit.php" class="btn btn-outline" style="padding: 0.45rem 0.8rem; font-size: 0.85rem;" title="Clear Filters"><i class="fas fa-times"></i> Reset</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Audit Log Visual Timeline -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <?php if ($logs_q && $logs_q->num_rows > 0): ?>
                <div class="audit-timeline" style="position: relative; padding-left: 1.5rem; border-left: 2px solid var(--glass-border);">
                    <?php 
                    $current_date_group = '';
                    while ($log = $logs_q->fetch_assoc()): 
                        $log_timestamp = strtotime($log['created_at']);
                        $log_date_str = date('Y-m-d', $log_timestamp);
                        $today_str = date('Y-m-d');
                        $yesterday_str = date('Y-m-d', strtotime('-1 day'));

                        $display_date_group = date('F d, Y (l)', $log_timestamp);
                        if ($log_date_str === $today_str) {
                            $display_date_group = "Today — " . date('F d, Y', $log_timestamp);
                        } elseif ($log_date_str === $yesterday_str) {
                            $display_date_group = "Yesterday — " . date('F d, Y', $log_timestamp);
                        }

                        // Date Group Header
                        if ($display_date_group !== $current_date_group) {
                            $current_date_group = $display_date_group;
                            ?>
                            <div style="position: relative; margin: 1.5rem 0 1rem -2.15rem; display: flex; align-items: center; gap: 0.6rem;">
                                <div style="width: 14px; height: 14px; border-radius: 50%; background: var(--primary-color); border: 3px solid #121826;"></div>
                                <h4 style="margin: 0; color: #50e3c2; font-size: 0.92rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <?php echo htmlspecialchars($display_date_group); ?>
                                </h4>
                            </div>
                            <?php
                        }

                        // Determine Icon and Color based on Action / Entity
                        $act_lower = strtolower($log['action']);
                        $badge_bg = 'rgba(80, 227, 194, 0.15)';
                        $badge_color = '#50e3c2';
                        $icon_class = 'fa-check-circle';

                        if (strpos($act_lower, 'approved') !== false || strpos($act_lower, 'verified') !== false) {
                            $badge_bg = 'rgba(46, 213, 115, 0.15)';
                            $badge_color = '#2ed573';
                            $icon_class = 'fa-check-circle';
                        } elseif (strpos($act_lower, 'rejected') !== false || strpos($act_lower, 'cancelled') !== false) {
                            $badge_bg = 'rgba(255, 71, 87, 0.15)';
                            $badge_color = '#ff4757';
                            $icon_class = 'fa-times-circle';
                        } elseif (strpos($act_lower, 'refund') !== false || strpos($act_lower, 'wallet') !== false) {
                            $badge_bg = 'rgba(112, 161, 255, 0.15)';
                            $badge_color = '#70a1ff';
                            $icon_class = 'fa-wallet';
                        } elseif (strpos($act_lower, 'backup') !== false || strpos($act_lower, 'system') !== false) {
                            $badge_bg = 'rgba(165, 94, 234, 0.15)';
                            $badge_color = '#a55eea';
                            $icon_class = 'fa-cog';
                        }
                    ?>

                    <div style="position: relative; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px; padding: 1rem 1.2rem; margin-bottom: 1rem; margin-left: 0.5rem; transition: background 0.2s;">
                        <!-- Timeline Node Dot -->
                        <div style="position: absolute; left: -2.05rem; top: 1.2rem; width: 10px; height: 10px; border-radius: 50%; background: <?php echo $badge_color; ?>; box-shadow: 0 0 8px <?php echo $badge_color; ?>;"></div>

                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.4rem;">
                            <div>
                                <span style="font-weight: 800; color: var(--text-primary); font-size: 0.95rem;">
                                    <i class="fas <?php echo $icon_class; ?>" style="color: <?php echo $badge_color; ?>; margin-right: 0.3rem;"></i>
                                    <?php echo htmlspecialchars($log['admin_name'] ?: 'Admin User'); ?>
                                </span>
                                <span style="font-size: 0.85rem; color: var(--text-secondary); margin-left: 0.4rem;">
                                    performed 
                                    <strong style="color: <?php echo $badge_color; ?>; text-transform: uppercase; font-size: 0.82rem; font-weight: 700;">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </strong>
                                </span>
                            </div>

                            <div style="font-size: 0.8rem; color: var(--text-secondary);">
                                <i class="fas fa-clock" style="opacity: 0.7;"></i> <?php echo date('h:i:s A', $log_timestamp); ?>
                            </div>
                        </div>

                        <!-- Details / Summary Description -->
                        <div style="font-size: 0.88rem; color: var(--text-primary); line-height: 1.45; margin-bottom: 0.4rem;">
                            <?php echo htmlspecialchars($log['details'] ?: 'No additional details provided.'); ?>
                        </div>

                        <!-- Footer Meta (Entity info + IP) -->
                        <div style="display: flex; gap: 1rem; font-size: 0.78rem; color: var(--text-secondary); opacity: 0.85; flex-wrap: wrap;">
                            <?php if (!empty($log['entity_type'])): ?>
                                <span><strong>Target Entity:</strong> <?php echo htmlspecialchars($log['entity_type']); ?> <?php echo $log['entity_id'] ? ('#' . $log['entity_id']) : ''; ?></span>
                            <?php endif; ?>

                            <?php if (!empty($log['ip_address'])): ?>
                                <span><i class="fas fa-laptop" style="font-size: 0.75rem;"></i> IP: <?php echo htmlspecialchars($log['ip_address']); ?></span>
                            <?php endif; ?>

                            <span>Audit Log ID: #LOG-<?php echo $log['id']; ?></span>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--glass-border); flex-wrap: wrap; gap: 1rem;">
                        <span style="font-size: 0.85rem; color: var(--text-secondary);">
                            Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_records; ?> total records)
                        </span>

                        <div style="display: flex; gap: 0.4rem;">
                            <?php if ($page > 1): ?>
                                <a href="admin_audit.php?page=<?php echo $page - 1; ?>&preset=<?php echo urlencode($date_preset); ?>&admin_id=<?php echo $admin_filter; ?>&action_type=<?php echo urlencode($action_filter); ?>&q=<?php echo urlencode($search_q); ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.3rem 0.7rem;"><i class="fas fa-chevron-left"></i> Previous</a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="admin_audit.php?page=<?php echo $page + 1; ?>&preset=<?php echo urlencode($date_preset); ?>&admin_id=<?php echo $admin_filter; ?>&action_type=<?php echo urlencode($action_filter); ?>&q=<?php echo urlencode($search_q); ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.3rem 0.7rem;">Next <i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="text-align: center; padding: 3rem 1rem; color: var(--text-secondary);">
                    <i class="fas fa-clipboard-list" style="font-size: 3rem; opacity: 0.4; margin-bottom: 1rem; display: block;"></i>
                    <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">No Audit Records Found</h3>
                    <p style="margin: 0;">No administrative activity logs match the selected filter criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
