<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$status_filter = isset($_GET['status']) ? trim($_GET['status']) : 'all';
$search_q = isset($_GET['q']) ? trim($_GET['q']) : '';

$where_clauses = ["customer_id = $user_id"];

if ($status_filter !== 'all' && !empty($status_filter)) {
    $st_esc = $conn->real_escape_string($status_filter);
    $where_clauses[] = "status = '$st_esc'";
}

if (!empty($search_q)) {
    $q_esc = $conn->real_escape_string($search_q);
    $where_clauses[] = "(ticket_number LIKE '%$q_esc%' OR subject LIKE '%$q_esc%' OR category LIKE '%$q_esc%')";
}

$where_sql = implode(" AND ", $where_clauses);

// Fetch Tickets
$tickets = $conn->query("
    SELECT * FROM support_tickets 
    WHERE $where_sql 
    ORDER BY updated_at DESC, id DESC
");

// Counts for filter badges
$cnt_all = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE customer_id = $user_id")->fetch_assoc()['cnt'];
$cnt_open = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE customer_id = $user_id AND status = 'open'")->fetch_assoc()['cnt'];
$cnt_in_prog = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE customer_id = $user_id AND status = 'in_progress'")->fetch_assoc()['cnt'];
$cnt_waiting = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE customer_id = $user_id AND status = 'waiting_customer'")->fetch_assoc()['cnt'];
$cnt_resolved = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE customer_id = $user_id AND status = 'resolved'")->fetch_assoc()['cnt'];
$cnt_closed = (int)$conn->query("SELECT COUNT(*) as cnt FROM support_tickets WHERE customer_id = $user_id AND status = 'closed'")->fetch_assoc()['cnt'];

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-columns"></i> Dashboard</a></li>
            <li><a href="customer_support.php"><i class="fas fa-headset"></i> Customer Support</a></li>
            <li><a href="my_tickets.php" class="active"><i class="fas fa-ticket-alt"></i> My Tickets</a></li>
            <li><a href="create_ticket.php"><i class="fas fa-plus-circle"></i> Create Ticket</a></li>
            <li><a href="your_orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a></li>
            <li><a href="my_wallet.php"><i class="fas fa-wallet"></i> My Wallet</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0; color: var(--text-primary);"><i class="fas fa-ticket-alt" style="color: var(--primary-color);"></i> My Support Tickets</h2>
                <p style="color: var(--text-secondary); margin-top: 0.3rem;">Track your support requests, replies, and status resolutions.</p>
            </div>
            <a href="create_ticket.php" class="btn btn-primary" style="font-size: 0.9rem; font-weight: bold;"><i class="fas fa-plus"></i> Create New Ticket</a>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="glass-panel" style="padding: 1.2rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                <a href="my_tickets.php?status=all" class="btn <?php echo $status_filter === 'all' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.35rem 0.8rem;">
                    All (<?php echo $cnt_all; ?>)
                </a>
                <a href="my_tickets.php?status=open" class="btn <?php echo $status_filter === 'open' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.35rem 0.8rem;">
                    🟡 Open (<?php echo $cnt_open; ?>)
                </a>
                <a href="my_tickets.php?status=in_progress" class="btn <?php echo $status_filter === 'in_progress' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.35rem 0.8rem;">
                    🔵 In Progress (<?php echo $cnt_in_prog; ?>)
                </a>
                <a href="my_tickets.php?status=waiting_customer" class="btn <?php echo $status_filter === 'waiting_customer' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.35rem 0.8rem;">
                    🟠 Waiting (<?php echo $cnt_waiting; ?>)
                </a>
                <a href="my_tickets.php?status=resolved" class="btn <?php echo $status_filter === 'resolved' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.35rem 0.8rem;">
                    🟢 Resolved (<?php echo $cnt_resolved; ?>)
                </a>
                <a href="my_tickets.php?status=closed" class="btn <?php echo $status_filter === 'closed' ? 'btn-primary' : 'btn-outline'; ?>" style="font-size: 0.82rem; padding: 0.35rem 0.8rem;">
                    ⚫ Closed (<?php echo $cnt_closed; ?>)
                </a>
            </div>

            <form method="GET" action="my_tickets.php" style="display: flex; gap: 0.5rem; flex: 1; max-width: 260px;">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <input type="text" name="q" class="form-control" placeholder="Search ticket # or subject..." value="<?php echo htmlspecialchars($search_q); ?>" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">
                <button type="submit" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-search"></i></button>
            </form>
        </div>

        <!-- Tickets List Table -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <?php if ($tickets && $tickets->num_rows > 0): ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid var(--glass-border); color: var(--text-secondary);">
                                <th style="padding: 0.8rem;">Ticket Ref</th>
                                <th style="padding: 0.8rem;">Category</th>
                                <th style="padding: 0.8rem;">Subject</th>
                                <th style="padding: 0.8rem;">Priority</th>
                                <th style="padding: 0.8rem;">Status</th>
                                <th style="padding: 0.8rem;">Last Updated</th>
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
                                        $st_label = 'Waiting Reply';
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
                                    <td style="padding: 0.8rem; font-weight: 600;">
                                        <?php echo htmlspecialchars($t['category']); ?>
                                    </td>
                                    <td style="padding: 0.8rem; color: var(--text-primary);">
                                        <strong><?php echo htmlspecialchars($t['subject']); ?></strong>
                                    </td>
                                    <td style="padding: 0.8rem; font-size: 0.8rem; text-transform: uppercase; font-weight: bold; color: <?php echo $prio_color; ?>;">
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
                                        <a href="ticket_view.php?id=<?php echo $t['id']; ?>" class="btn btn-outline" style="font-size: 0.78rem; padding: 0.3rem 0.7rem;">
                                            <i class="fas fa-comments"></i> Chat / View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem 1rem; color: var(--text-secondary);">
                    <i class="fas fa-ticket-alt" style="font-size: 3rem; opacity: 0.4; margin-bottom: 1rem; display: block;"></i>
                    <h3 style="color: var(--text-primary); margin-bottom: 0.5rem;">No Tickets Found</h3>
                    <p style="margin: 0 0 1.5rem 0;">No support tickets match the selected status or search filter.</p>
                    <a href="create_ticket.php" class="btn btn-primary"><i class="fas fa-plus"></i> Create Support Ticket</a>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
