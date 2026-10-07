<?php
require_once 'config.php';
require_once 'includes/security_helper.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$admin_id = (int)$_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : 'all';
$priority_filter = isset($_GET['priority']) ? trim($_GET['priority']) : 'all';
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';

$where_clauses = ["1=1"];

if ($status_filter !== 'all' && !empty($status_filter)) {
    $st_esc = $conn->real_escape_string($status_filter);
    $where_clauses[] = "t.status = '$st_esc'";
}

if ($category_filter !== 'all' && !empty($category_filter)) {
    $cat_esc = $conn->real_escape_string($category_filter);
    $where_clauses[] = "t.category = '$cat_esc'";
}

if ($priority_filter !== 'all' && !empty($priority_filter)) {
    $prio_esc = $conn->real_escape_string($priority_filter);
    $where_clauses[] = "t.priority = '$prio_esc'";
}

if (!empty($search_q)) {
    $q_esc = $conn->real_escape_string($search_q);
    $where_clauses[] = "(t.ticket_number LIKE '%$q_esc%' OR t.subject LIKE '%$q_esc%' OR u.name LIKE '%$q_esc%' OR u.phone LIKE '%$q_esc%')";
}

$where_sql = implode(" AND ", $where_clauses);

// Pagination
$per_page = 20;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$total_records = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets t JOIN users u ON t.customer_id = u.id WHERE $where_sql")->fetch_assoc()['cnt'];
$total_pages = max(1, ceil($total_records / $per_page));

// Fetch Tickets
$tickets = $conn->query("
    SELECT t.*, u.name as customer_name, u.phone as customer_phone, u.email as customer_email 
    FROM support_tickets t 
    JOIN users u ON t.customer_id = u.id 
    WHERE $where_sql 
    ORDER BY CASE WHEN t.status = 'open' THEN 1 WHEN t.status = 'in_progress' THEN 2 ELSE 3 END, t.updated_at DESC 
    LIMIT $per_page OFFSET $offset
");

// Counts from database
$cnt_open = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE status = 'open'")->fetch_assoc()['cnt'];
$cnt_in_prog = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE status = 'in_progress'")->fetch_assoc()['cnt'];
$cnt_waiting = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE status = 'waiting_customer'")->fetch_assoc()['cnt'];
$cnt_resolved = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE status = 'resolved'")->fetch_assoc()['cnt'];
$cnt_closed = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE status = 'closed'")->fetch_assoc()['cnt'];
$total_all = $cnt_open + $cnt_in_prog + $cnt_waiting + $cnt_resolved + $cnt_closed;

// WhatsApp Status
$wa_settings = get_whatsapp_support_settings($conn);
$is_wa_online = is_whatsapp_support_available($conn);

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
            <li><a href="admin_support.php" class="active"><i class="fas fa-headset"></i> Support Tickets</a></li>
            <li><a href="admin_whatsapp_settings.php"><i class="fab fa-whatsapp"></i> WhatsApp Settings</a></li>
            <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
            <li><a href="admin_audit.php"><i class="fas fa-history"></i> Audit Timeline</a></li>
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
                <h2 style="margin: 0; color: var(--text-primary);"><i class="fas fa-headset" style="color: var(--primary-color);"></i> Customer Support Management</h2>
                <p style="color: var(--text-secondary); margin-top: 0.3rem;">Manage customer support tickets, replies, priorities, and WhatsApp availability.</p>
            </div>

            <div style="display: flex; gap: 0.8rem; flex-wrap: wrap;">
                <a href="admin_whatsapp_settings.php" class="btn btn-outline" style="font-size: 0.85rem; border-color: #25D366; color: #25D366;">
                    <i class="fab fa-whatsapp"></i> Configure WhatsApp (<?php echo $is_wa_online ? '🟢 Online' : '🔴 Offline'; ?>)
                </a>
            </div>
        </div>

        <!-- Dashboard Counts Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
            <a href="admin_support.php?status=open" style="text-decoration: none;">
                <div class="glass-panel" style="padding: 1.2rem; border-left: 4px solid #ff4757; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Open Tickets</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #ff4757; margin-top: 0.4rem;">
                        🔴 <?php echo $cnt_open; ?>
                    </div>
                </div>
            </a>

            <a href="admin_support.php?status=in_progress" style="text-decoration: none;">
                <div class="glass-panel" style="padding: 1.2rem; border-left: 4px solid #70a1ff; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">In Progress</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #70a1ff; margin-top: 0.4rem;">
                        🟠 <?php echo $cnt_in_prog; ?>
                    </div>
                </div>
            </a>

            <a href="admin_support.php?status=waiting_customer" style="text-decoration: none;">
                <div class="glass-panel" style="padding: 1.2rem; border-left: 4px solid #f5a623; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Waiting Customer</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #f5a623; margin-top: 0.4rem;">
                        🟡 <?php echo $cnt_waiting; ?>
                    </div>
                </div>
            </a>

            <a href="admin_support.php?status=resolved" style="text-decoration: none;">
                <div class="glass-panel" style="padding: 1.2rem; border-left: 4px solid #2ed573; transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Resolved</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #2ed573; margin-top: 0.4rem;">
                        🟢 <?php echo $cnt_resolved; ?>
                    </div>
                </div>
            </a>

            <a href="admin_support.php?status=closed" style="text-decoration: none;">
                <div class="glass-panel" style="padding: 1.2rem; border-left: 4px solid var(--text-secondary); transition: transform 0.2s;" onmouseover="this.style.transform='translateY(-3px)'" onmouseout="this.style.transform='translateY(0)'">
                    <div style="font-size: 0.82rem; color: var(--text-secondary); text-transform: uppercase; font-weight: 700;">Closed</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: var(--text-secondary); margin-top: 0.4rem;">
                        ⚫ <?php echo $cnt_closed; ?>
                    </div>
                </div>
            </a>
        </div>

        <!-- Toolbar Filters -->
        <div class="glass-panel" style="padding: 1.2rem; margin-bottom: 1.5rem;">
            <form method="GET" action="admin_support.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: center;">
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; flex: 1;">
                    <select name="status" class="form-control" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; max-width: 160px;">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="open" <?php echo $status_filter === 'open' ? 'selected' : ''; ?>>Open</option>
                        <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="waiting_customer" <?php echo $status_filter === 'waiting_customer' ? 'selected' : ''; ?>>Waiting Customer</option>
                        <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                        <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                    </select>

                    <select name="category" class="form-control" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; max-width: 160px;">
                        <option value="all" <?php echo $category_filter === 'all' ? 'selected' : ''; ?>>All Categories</option>
                        <option value="Payment">Payment</option>
                        <option value="Order">Order</option>
                        <option value="Wallet">Wallet</option>
                        <option value="Medical Card">Medical Card</option>
                        <option value="Appointment">Appointment</option>
                        <option value="Prescription">Prescription</option>
                        <option value="Account/Login">Account/Login</option>
                        <option value="Referral">Referral</option>
                        <option value="Technical Issue">Technical Issue</option>
                        <option value="Other">Other</option>
                    </select>

                    <select name="priority" class="form-control" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; max-width: 140px;">
                        <option value="all" <?php echo $priority_filter === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                        <option value="low">Low</option>
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>

                    <input type="text" name="q" class="form-control" placeholder="Search ticket #, customer, subject..." value="<?php echo htmlspecialchars($search_q); ?>" style="font-size: 0.85rem; padding: 0.4rem 0.8rem; flex: 1; min-width: 200px;">
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.4rem 1rem;"><i class="fas fa-filter"></i> Filter</button>
                    <a href="admin_support.php" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;"><i class="fas fa-times"></i> Reset</a>
                </div>
            </form>
        </div>

        <!-- Ticket Listing Table -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <?php if ($tickets && $tickets->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--glass-border); color: var(--text-secondary);">
                                <th style="padding: 0.8rem;">Ticket Ref</th>
                                <th style="padding: 0.8rem;">Customer</th>
                                <th style="padding: 0.8rem;">Category</th>
                                <th style="padding: 0.8rem;">Subject</th>
                                <th style="padding: 0.8rem;">Priority</th>
                                <th style="padding: 0.8rem;">Status</th>
                                <th style="padding: 0.8rem;">Updated</th>
                                <th style="padding: 0.8rem;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($t = $tickets->fetch_assoc()): ?>
                                <?php
                                    $st = strtolower($t['status']);
                                    $badge_bg = 'rgba(255, 171, 0, 0.2)';
                                    $badge_color = '#ffab00';
                                    $st_label = 'Open';
                                    if ($st === 'in_progress') {
                                        $badge_bg = 'rgba(112, 161, 255, 0.2)';
                                        $badge_color = '#70a1ff';
                                        $st_label = 'In Progress';
                                    } elseif ($st === 'waiting_customer') {
                                        $badge_bg = 'rgba(245, 166, 35, 0.2)';
                                        $badge_color = '#f5a623';
                                        $st_label = 'Waiting Customer';
                                    } elseif ($st === 'resolved') {
                                        $badge_bg = 'rgba(46, 213, 115, 0.2)';
                                        $badge_color = '#2ed573';
                                        $st_label = 'Resolved';
                                    } elseif ($st === 'closed') {
                                        $badge_bg = 'rgba(255,255,255,0.1)';
                                        $badge_color = 'var(--text-secondary)';
                                        $st_label = 'Closed';
                                    }

                                    $prio = strtolower($t['priority'] ?? 'normal');
                                    $prio_color = 'var(--text-secondary)';
                                    if ($prio === 'high') $prio_color = '#f5a623';
                                    elseif ($prio === 'urgent') $prio_color = '#ff4757';
                                ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.8rem; font-weight: bold; color: var(--primary-color); font-family: monospace;">
                                        <?php echo htmlspecialchars($t['ticket_number']); ?>
                                    </td>
                                    <td style="padding: 0.8rem;">
                                        <strong><?php echo htmlspecialchars($t['customer_name']); ?></strong><br>
                                        <span style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($t['customer_phone']); ?></span>
                                    </td>
                                    <td style="padding: 0.8rem; font-weight: 600;">
                                        <?php echo htmlspecialchars($t['category']); ?>
                                    </td>
                                    <td style="padding: 0.8rem; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($t['subject']); ?>
                                    </td>
                                    <td style="padding: 0.8rem; font-size: 0.78rem; text-transform: uppercase; font-weight: bold; color: <?php echo $prio_color; ?>;">
                                        <?php echo $prio; ?>
                                    </td>
                                    <td style="padding: 0.8rem;">
                                        <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.78rem; font-weight: bold; background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; border: 1px solid <?php echo $badge_color; ?>;">
                                            <?php echo $st_label; ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.8rem; color: var(--text-secondary); font-size: 0.82rem;">
                                        <?php echo date('M d, Y h:i A', strtotime($t['updated_at'])); ?>
                                    </td>
                                    <td style="padding: 0.8rem;">
                                        <a href="ticket_view.php?id=<?php echo $t['id']; ?>" class="btn btn-primary" style="font-size: 0.78rem; padding: 0.3rem 0.7rem;">
                                            <i class="fas fa-reply"></i> Open & Reply
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($total_pages > 1): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--glass-border); flex-wrap: wrap; gap: 1rem;">
                        <span style="font-size: 0.85rem; color: var(--text-secondary);">
                            Showing page <?php echo $page; ?> of <?php echo $total_pages; ?> (<?php echo $total_records; ?> total tickets)
                        </span>

                        <div style="display: flex; gap: 0.4rem;">
                            <?php if ($page > 1): ?>
                                <a href="admin_support.php?page=<?php echo $page - 1; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&q=<?php echo urlencode($search_q); ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.3rem 0.7rem;"><i class="fas fa-chevron-left"></i> Previous</a>
                            <?php endif; ?>

                            <?php if ($page < $total_pages): ?>
                                <a href="admin_support.php?page=<?php echo $page + 1; ?>&status=<?php echo urlencode($status_filter); ?>&category=<?php echo urlencode($category_filter); ?>&q=<?php echo urlencode($search_q); ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.3rem 0.7rem;">Next <i class="fas fa-chevron-right"></i></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div style="text-align: center; padding: 3rem 1rem; color: var(--text-secondary);">
                    <i class="fas fa-headset" style="font-size: 3rem; opacity: 0.4; margin-bottom: 1rem; display: block;"></i>
                    <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">No Support Tickets Found</h3>
                    <p style="margin: 0;">No support tickets match the selected filter criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
