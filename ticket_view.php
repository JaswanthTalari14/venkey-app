<?php
require_once 'config.php';
require_once 'includes/notification_functions.php';
require_once 'includes/security_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'patient';
$user_name = $_SESSION['name'] ?? 'User';

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg_success = '';
$msg_error = '';

if ($ticket_id <= 0) {
    die("Invalid Ticket ID.");
}

// Fetch Ticket with Security Ownership Check
$stmt = $conn->prepare("
    SELECT t.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
    FROM support_tickets t
    JOIN users u ON t.customer_id = u.id
    WHERE t.id = ? AND (t.customer_id = ? OR ? = 'admin')
");
$stmt->bind_param("iis", $ticket_id, $user_id, $user_role);
$stmt->execute();
$ticket_res = $stmt->get_result();

if (!$ticket_res || $ticket_res->num_rows === 0) {
    die("Support ticket not found or access denied.");
}

$ticket = $ticket_res->fetch_assoc();
$is_admin = ($user_role === 'admin');

// Handle Customer / Admin Ticket Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Close Ticket Action (Customer or Admin)
    if (isset($_POST['close_ticket'])) {
        $stmt_close = $conn->prepare("UPDATE support_tickets SET status = 'closed', closed_at = NOW() WHERE id = ?");
        $stmt_close->bind_param("i", $ticket_id);
        if ($stmt_close->execute()) {
            $ticket['status'] = 'closed';
            log_admin_activity($conn, $user_id, "Ticket Closed", "support_tickets", $ticket_id, "Closed ticket #{$ticket['ticket_number']}");
            create_notification((int)$ticket['customer_id'], "Support Ticket Closed", "Your support ticket #{$ticket['ticket_number']} has been closed.", 'system', 'ticket', (string)$ticket_id);
            $msg_success = "Support ticket has been closed.";
        }
    }

    // 2. Admin Status / Priority Update
    if ($is_admin && isset($_POST['update_status_priority'])) {
        $new_st = trim($_POST['status'] ?? $ticket['status']);
        $new_prio = trim($_POST['priority'] ?? $ticket['priority']);
        $prev_st = $ticket['status'];

        $resolved_sql = ($new_st === 'resolved' && $prev_st !== 'resolved') ? ", resolved_at = NOW()" : "";
        $closed_sql = ($new_st === 'closed' && $prev_st !== 'closed') ? ", closed_at = NOW()" : "";

        $stmt_upd = $conn->prepare("UPDATE support_tickets SET status = ?, priority = ? $resolved_sql $closed_sql WHERE id = ?");
        $stmt_upd->bind_param("ssi", $new_st, $new_prio, $ticket_id);
        if ($stmt_upd->execute()) {
            $ticket['status'] = $new_st;
            $ticket['priority'] = $new_prio;
            
            log_admin_activity($conn, $user_id, "Ticket Status Updated", "support_tickets", $ticket_id, "Admin updated ticket #{$ticket['ticket_number']} status to '$new_st' (Priority: $new_prio)");

            $st_label = ucfirst(str_replace('_', ' ', $new_st));
            create_notification((int)$ticket['customer_id'], "Support Ticket Status: $st_label 🔄", "Your support ticket #{$ticket['ticket_number']} status is now $st_label.", 'system', 'ticket', (string)$ticket_id);

            $msg_success = "Ticket status updated to '$st_label'.";
        }
    }

    // 3. Post Reply Message
    if (isset($_POST['post_reply'])) {
        $reply_msg = trim($_POST['reply_message'] ?? '');
        $is_internal = ($is_admin && isset($_POST['is_internal_note'])) ? 1 : 0;

        if (empty($reply_msg)) {
            $msg_error = "Please enter a message reply.";
        } else {
            // File Attachment
            $attachment_path = null;
            if (isset($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['reply_attachment'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

                if (in_array($ext, $allowed) && $file['size'] <= 5 * 1024 * 1024) {
                    $upload_dir = 'uploads/support/';
                    if (!file_exists($upload_dir)) @mkdir($upload_dir, 0777, true);
                    $filename = 'SUP_REPLY_' . time() . '_' . rand(1000, 9999) . '.' . $ext;
                    $target_path = $upload_dir . $filename;
                    if (move_uploaded_file($file['tmp_name'], $target_path)) {
                        $attachment_path = $target_path;
                    }
                }
            }

            $sender_role = $is_admin ? 'admin' : 'patient';
            $stmt_rep = $conn->prepare("
                INSERT INTO support_ticket_replies 
                (ticket_id, sender_id, sender_role, message, is_internal_note, attachment_path)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_rep->bind_param("iissis", $ticket_id, $user_id, $sender_role, $reply_msg, $is_internal, $attachment_path);

            if ($stmt_rep->execute()) {
                // Update Ticket status and timestamp
                if ($is_admin && !$is_internal) {
                    $new_st = 'waiting_customer';
                    $conn->query("UPDATE support_tickets SET status = '$new_st', updated_at = NOW() WHERE id = $ticket_id");
                    $ticket['status'] = $new_st;

                    create_notification((int)$ticket['customer_id'], "Support Team Replied 💬", "Support team replied to your ticket #{$ticket['ticket_number']}.", 'system', 'ticket', (string)$ticket_id);
                } elseif (!$is_admin) {
                    $new_st = ($ticket['status'] === 'waiting_customer') ? 'in_progress' : $ticket['status'];
                    $conn->query("UPDATE support_tickets SET status = '$new_st', updated_at = NOW() WHERE id = $ticket_id");
                    $ticket['status'] = $new_st;

                    // Notify Admins
                    $admins_q = $conn->query("SELECT id FROM users WHERE role = 'admin'");
                    if ($admins_q) {
                        while ($adm = $admins_q->fetch_assoc()) {
                            create_notification((int)$adm['id'], "Customer Replied #{$ticket['ticket_number']}", "Customer $user_name replied on ticket #{$ticket['ticket_number']}.", 'system', 'ticket', (string)$ticket_id, 'admin');
                        }
                    }
                }

                $msg_success = $is_internal ? "Internal Admin Note added." : "Reply sent successfully.";
            } else {
                $msg_error = "Failed to send message reply.";
            }
        }
    }
}

// Fetch Conversation Replies
$replies_where = $is_admin ? "1=1" : "is_internal_note = 0";
$replies_stmt = $conn->prepare("
    SELECT r.*, u.name as sender_name 
    FROM support_ticket_replies r
    JOIN users u ON r.sender_id = u.id
    WHERE r.ticket_id = ? AND $replies_where
    ORDER BY r.created_at ASC
");
$replies_stmt->bind_param("i", $ticket_id);
$replies_stmt->execute();
$replies_res = $replies_stmt->get_result();

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;"><?php echo $is_admin ? 'Admin Menu' : 'Patient Menu'; ?></h3>
        <ul class="sidebar-menu">
            <?php if ($is_admin): ?>
                <li><a href="admin_dashboard.php"><i class="fas fa-chart-pie"></i> Overview</a></li>
                <li><a href="admin_approval_center.php"><i class="fas fa-check-double"></i> Approval Center</a></li>
                <li><a href="admin_support.php" class="active"><i class="fas fa-headset"></i> Support Tickets</a></li>
                <li><a href="admin_search.php"><i class="fas fa-search"></i> Global Search</a></li>
                <li><a href="admin_audit.php"><i class="fas fa-history"></i> Audit Timeline</a></li>
            <?php else: ?>
                <li><a href="patient_dashboard.php"><i class="fas fa-columns"></i> Dashboard</a></li>
                <li><a href="customer_support.php"><i class="fas fa-headset"></i> Customer Support</a></li>
                <li><a href="my_tickets.php" class="active"><i class="fas fa-ticket-alt"></i> My Tickets</a></li>
                <li><a href="create_ticket.php"><i class="fas fa-plus-circle"></i> Create Ticket</a></li>
            <?php endif; ?>
        </ul>
    </aside>

    <main class="dashboard-content">
        <!-- Ticket Header Card -->
        <div class="glass-panel" style="padding: 1.8rem; margin-bottom: 1.5rem; border-top: 5px solid var(--primary-color);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem; margin-bottom: 1rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                        <h2 style="margin: 0; color: var(--text-primary); font-size: 1.4rem; font-family: monospace;">
                            <?php echo htmlspecialchars($ticket['ticket_number']); ?>
                        </h2>
                        <span style="background: rgba(80, 227, 194, 0.15); color: #50e3c2; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.78rem; font-weight: bold;">
                            <?php echo htmlspecialchars($ticket['category']); ?>
                        </span>
                        <?php
                            $st = strtolower($ticket['status']);
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
                                $st_label = 'Waiting for Customer';
                            } elseif ($st === 'resolved') {
                                $badge_bg = 'rgba(46, 213, 115, 0.2)';
                                $badge_color = '#2ed573';
                                $st_label = 'Resolved';
                            } elseif ($st === 'closed') {
                                $badge_bg = 'rgba(255,255,255,0.1)';
                                $badge_color = 'var(--text-secondary)';
                                $st_label = 'Closed';
                            }
                        ?>
                        <span style="display: inline-block; padding: 0.25rem 0.7rem; border-radius: 12px; font-size: 0.8rem; font-weight: bold; background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; border: 1px solid <?php echo $badge_color; ?>;">
                            <?php echo $st_label; ?>
                        </span>
                    </div>
                    <h3 style="margin: 0.5rem 0 0 0; color: var(--text-primary); font-size: 1.15rem;">
                        <?php echo htmlspecialchars($ticket['subject']); ?>
                    </h3>
                </div>

                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="<?php echo $is_admin ? 'admin_support.php' : 'my_tickets.php'; ?>" class="btn btn-outline" style="font-size: 0.82rem;"><i class="fas fa-arrow-left"></i> Back</a>

                    <?php if ($st !== 'closed'): ?>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Close this ticket? You can reopen or post a new message anytime.');">
                            <button type="submit" name="close_ticket" value="1" class="btn btn-outline" style="font-size: 0.82rem; color: #ff4757; border-color: #ff4757;">
                                <i class="fas fa-lock"></i> Close Ticket
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Meta & Customer Info -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; font-size: 0.88rem; color: var(--text-secondary);">
                <div><strong>Customer Name:</strong> <span style="color: var(--text-primary);"><?php echo htmlspecialchars($ticket['customer_name']); ?></span></div>
                <div><strong>Contact:</strong> <?php echo htmlspecialchars($ticket['customer_phone'] . ' | ' . $ticket['customer_email']); ?></div>
                <div><strong>Created On:</strong> <?php echo date('M d, Y h:i A', strtotime($ticket['created_at'])); ?></div>
                
                <?php if (!empty($ticket['related_entity_type']) && !empty($ticket['related_entity_id'])): ?>
                    <div>
                        <strong>Related Record:</strong> 
                        <span style="color: #50e3c2; font-weight: bold; text-transform: uppercase;">
                            <?php echo htmlspecialchars($ticket['related_entity_type']); ?> #<?php echo htmlspecialchars($ticket['related_entity_id']); ?>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Admin Controls Box -->
            <?php if ($is_admin): ?>
                <div style="margin-top: 1.2rem; padding-top: 1rem; border-top: 1px dashed var(--glass-border); background: rgba(80, 227, 194, 0.05); padding: 1rem; border-radius: 8px;">
                    <form method="POST" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                        <span style="font-weight: bold; font-size: 0.85rem; color: var(--primary-color);">ADMIN TICKET STATUS:</span>
                        <select name="status" class="form-control" style="max-width: 180px; font-size: 0.85rem; padding: 0.35rem 0.6rem;">
                            <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>🟡 Open</option>
                            <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>🔵 In Progress</option>
                            <option value="waiting_customer" <?php echo $ticket['status'] === 'waiting_customer' ? 'selected' : ''; ?>>🟠 Waiting Customer</option>
                            <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>🟢 Resolved</option>
                            <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>⚫ Closed</option>
                        </select>

                        <select name="priority" class="form-control" style="max-width: 140px; font-size: 0.85rem; padding: 0.35rem 0.6rem;">
                            <option value="low" <?php echo $ticket['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                            <option value="normal" <?php echo $ticket['priority'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                            <option value="high" <?php echo $ticket['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="urgent" <?php echo $ticket['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>

                        <button type="submit" name="update_status_priority" value="1" class="btn btn-primary" style="font-size: 0.82rem; padding: 0.35rem 0.9rem;">
                            <i class="fas fa-save"></i> Save Status
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($msg_success): ?>
            <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 0.9rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg_success); ?>
            </div>
        <?php endif; ?>

        <?php if ($msg_error): ?>
            <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid #ff4757; color: #ff4757; padding: 0.9rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($msg_error); ?>
            </div>
        <?php endif; ?>

        <!-- Conversation Chat Stream -->
        <div class="glass-panel" style="padding: 1.8rem; margin-bottom: 1.5rem;">
            <h4 style="margin-top: 0; margin-bottom: 1.5rem; color: var(--text-primary); border-bottom: 1px solid var(--glass-border); padding-bottom: 0.5rem;"><i class="fas fa-comments" style="color: var(--primary-color);"></i> Ticket Conversation</h4>

            <div id="chatStream" style="display: flex; flex-direction: column; gap: 1.2rem; max-height: 500px; overflow-y: auto; padding-right: 0.5rem; margin-bottom: 1.5rem;">
                <?php if ($replies_res && $replies_res->num_rows > 0): ?>
                    <?php while ($r = $replies_res->fetch_assoc()): ?>
                        <?php
                            $is_my_msg = ($r['sender_id'] == $user_id);
                            $is_support_msg = ($r['sender_role'] === 'admin');
                            $is_internal_note = (bool)$r['is_internal_note'];

                            $box_align = $is_support_msg ? 'flex-start' : 'flex-end';
                            $bg_style = $is_support_msg ? 'background: rgba(74, 144, 226, 0.12); border: 1px solid rgba(74, 144, 226, 0.3);' : 'background: rgba(80, 227, 194, 0.12); border: 1px solid rgba(80, 227, 194, 0.3);';
                            $sender_label = $is_support_msg ? '🎧 Support Team (' . htmlspecialchars($r['sender_name']) . ')' : '👤 ' . htmlspecialchars($r['sender_name']);

                            if ($is_internal_note) {
                                $bg_style = 'background: rgba(255, 171, 0, 0.12); border: 1px dashed #ffab00;';
                                $sender_label = '🔒 Internal Admin Note (' . htmlspecialchars($r['sender_name']) . ')';
                            }
                        ?>

                        <div style="align-self: <?php echo $box_align; ?>; max-width: 80%; border-radius: 12px; padding: 1rem 1.2rem; <?php echo $bg_style; ?>">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem; gap: 1rem;">
                                <strong style="font-size: 0.85rem; color: <?php echo $is_internal_note ? '#ffab00' : ($is_support_msg ? '#70a1ff' : '#50e3c2'); ?>;">
                                    <?php echo $sender_label; ?>
                                </strong>
                                <span style="font-size: 0.75rem; color: var(--text-secondary);">
                                    <?php echo date('M d, Y h:i A', strtotime($r['created_at'])); ?>
                                </span>
                            </div>

                            <div style="font-size: 0.92rem; color: var(--text-primary); line-height: 1.5; white-space: pre-line;">
                                <?php echo htmlspecialchars($r['message']); ?>
                            </div>

                            <?php if (!empty($r['attachment_path']) && file_exists($r['attachment_path'])): ?>
                                <div style="margin-top: 0.8rem; padding-top: 0.6rem; border-top: 1px solid rgba(255,255,255,0.1);">
                                    <a href="<?php echo htmlspecialchars($r['attachment_path']); ?>" target="_blank" class="btn btn-outline" style="font-size: 0.78rem; padding: 0.25rem 0.6rem; color: #50e3c2; border-color: #50e3c2;">
                                        <i class="fas fa-paperclip"></i> View Attachment
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="text-align: center; color: var(--text-secondary);">No messages recorded yet.</p>
                <?php endif; ?>
            </div>

            <!-- Reply Composer -->
            <?php if ($st !== 'closed'): ?>
                <form method="POST" enctype="multipart/form-data" style="border-top: 1px solid var(--glass-border); padding-top: 1.2rem;">
                    <?php if ($is_admin): ?>
                        <div style="margin-bottom: 0.6rem;">
                            <label style="font-size: 0.85rem; color: #ffab00; font-weight: bold; cursor: pointer;">
                                <input type="checkbox" name="is_internal_note" value="1"> 🔒 Add as Internal Admin Note (Hidden from Customer)
                            </label>
                        </div>
                    <?php endif; ?>

                    <div style="margin-bottom: 1rem;">
                        <textarea name="reply_message" class="form-control" rows="3" required placeholder="Write your reply message here..." style="font-size: 0.95rem; padding: 0.8rem; border-radius: 10px;"></textarea>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <input type="file" name="reply_attachment" class="form-control" accept=".jpg,.jpeg,.png,.webp,.pdf" style="font-size: 0.8rem; max-width: 250px;">
                        </div>

                        <button type="submit" name="post_reply" value="1" class="btn btn-primary" style="padding: 0.6rem 1.8rem; font-weight: bold;">
                            <i class="fas fa-paper-plane"></i> Send Reply
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div style="background: rgba(255,255,255,0.05); padding: 1rem; text-align: center; border-radius: 8px; color: var(--text-secondary); font-size: 0.9rem;">
                    <i class="fas fa-lock" style="margin-right: 0.4rem;"></i> This ticket is closed. Reopen or create a new support ticket if you require further assistance.
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const stream = document.getElementById('chatStream');
    if (stream) {
        stream.scrollTop = stream.scrollHeight;
    }
});
</script>

<?php include 'includes/footer.php'; ?>
