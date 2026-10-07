<?php
require_once 'config.php';
require_once 'includes/notification_functions.php';
require_once 'includes/security_helper.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'patient';
$user_name = $_SESSION['name'] ?? 'User';

$ticket_id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$msg_success = '';
$msg_error   = '';

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

$ticket   = $ticket_res->fetch_assoc();
$is_admin = ($user_role === 'admin');

// Handle Form Posts (Fallback if JS disabled or Status Updates)
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
        $new_st   = trim($_POST['status'] ?? $ticket['status']);
        $new_prio = trim($_POST['priority'] ?? $ticket['priority']);
        $prev_st  = $ticket['status'];

        $resolved_sql = ($new_st === 'resolved' && $prev_st !== 'resolved') ? ", resolved_at = NOW()" : "";
        $closed_sql   = ($new_st === 'closed' && $prev_st !== 'closed') ? ", closed_at = NOW()" : "";

        $stmt_upd = $conn->prepare("UPDATE support_tickets SET status = ?, priority = ? $resolved_sql $closed_sql WHERE id = ?");
        $stmt_upd->bind_param("ssi", $new_st, $new_prio, $ticket_id);
        if ($stmt_upd->execute()) {
            $ticket['status']   = $new_st;
            $ticket['priority'] = $new_prio;
            
            log_admin_activity($conn, $user_id, "Ticket Status Updated", "support_tickets", $ticket_id, "Admin updated ticket #{$ticket['ticket_number']} status to '$new_st' (Priority: $new_prio)");

            $st_label = ucfirst(str_replace('_', ' ', $new_st));
            create_notification((int)$ticket['customer_id'], "Support Ticket Status: $st_label", "Your support ticket #{$ticket['ticket_number']} status is now $st_label.", 'system', 'ticket', (string)$ticket_id);

            $msg_success = "Ticket status updated to '$st_label'.";
        }
    }

    // 3. Post Reply Message (Standard HTTP fallback)
    if (isset($_POST['post_reply'])) {
        $reply_msg   = trim($_POST['reply_message'] ?? '');
        $is_internal = ($is_admin && isset($_POST['is_internal_note'])) ? 1 : 0;

        if (empty($reply_msg)) {
            $msg_error = "Please enter a message reply.";
        } else {
            $attachment_path = null;
            if (isset($_FILES['reply_attachment']) && $_FILES['reply_attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['reply_attachment'];
                $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
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
                if ($is_admin && !$is_internal) {
                    $new_st = 'waiting_customer';
                    $conn->query("UPDATE support_tickets SET status = '$new_st', updated_at = NOW() WHERE id = $ticket_id");
                    $ticket['status'] = $new_st;

                    create_notification((int)$ticket['customer_id'], "Support Team Replied", "Support team replied to your ticket #{$ticket['ticket_number']}.", 'system', 'ticket', (string)$ticket_id);
                } elseif (!$is_admin) {
                    $new_st = ($ticket['status'] === 'waiting_customer') ? 'in_progress' : $ticket['status'];
                    $conn->query("UPDATE support_tickets SET status = '$new_st', updated_at = NOW() WHERE id = $ticket_id");
                    $ticket['status'] = $new_st;

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

// Fetch Initial Conversation Replies
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

$initial_replies = [];
$max_reply_id = 0;
if ($replies_res) {
    while ($row = $replies_res->fetch_assoc()) {
        $initial_replies[] = $row;
        if ((int)$row['id'] > $max_reply_id) {
            $max_reply_id = (int)$row['id'];
        }
    }
}

include 'includes/header.php';
?>

<style>
/* Custom Support Chat Workspace Styling (Emerald + Deep Navy) */
.support-chat-workspace {
    display: flex;
    flex-direction: column;
    height: calc(100dvh - 110px);
    height: calc(100vh - 110px);
    min-height: 640px;
    width: 100%;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(16px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
    margin-bottom: 0.5rem;
}

/* Chat Header */
.chat-top-header {
    background: rgba(15, 23, 42, 0.95);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding: 0.9rem 1.3rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.chat-top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.8rem;
}

.ticket-meta-title {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    flex-wrap: wrap;
}

.ticket-ref-badge {
    font-family: monospace;
    font-size: 1.25rem;
    font-weight: 800;
    color: #f8fafc;
    letter-spacing: 0.5px;
}

.ticket-status-pill {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.78rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}

.ticket-details-drawer {
    background: rgba(30, 41, 59, 0.5);
    border: 1px solid rgba(255, 255, 255, 0.05);
    border-radius: 12px;
    padding: 0.9rem 1.2rem;
    font-size: 0.86rem;
    color: #94a3b8;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.8rem;
    transition: all 0.3s ease;
}

.ticket-details-drawer.collapsed {
    display: none;
}

/* Scrollable Chat Stream Container */
.chat-messages-stream {
    flex: 1;
    overflow-y: auto;
    padding: 1.2rem 1.4rem;
    display: flex;
    flex-direction: column;
    gap: 0.8rem;
    scroll-behavior: smooth;
    background: radial-gradient(circle at top right, rgba(5, 150, 105, 0.05), transparent 40%),
                radial-gradient(circle at bottom left, rgba(15, 23, 42, 0.4), transparent 40%);
}

.chat-messages-stream::-webkit-scrollbar {
    width: 6px;
}
.chat-messages-stream::-webkit-scrollbar-track {
    background: rgba(0, 0, 0, 0.1);
}
.chat-messages-stream::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 3px;
}
.chat-messages-stream::-webkit-scrollbar-thumb:hover {
    background: var(--primary-color, #059669);
}

#messagesList {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    width: 100%;
}

/* Date Separators */
.chat-date-separator {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0.5rem 0;
    position: relative;
}

.chat-date-separator::before {
    content: '';
    position: absolute;
    left: 0; right: 0; top: 50%;
    height: 1px;
    background: rgba(255, 255, 255, 0.08);
}

.chat-date-separator span {
    position: relative;
    background: rgba(30, 41, 59, 0.9);
    color: #94a3b8;
    padding: 0.2rem 0.8rem;
    border-radius: 12px;
    font-size: 0.72rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid rgba(255, 255, 255, 0.06);
}

/* Message Bubble Architecture */
.chat-bubble-row {
    display: flex !important;
    flex-direction: column !important;
    justify-content: flex-start !important;
    align-items: flex-start !important;
    max-width: 78% !important;
    width: fit-content !important;
    height: auto !important;
    min-height: 0 !important;
    max-height: none !important;
    position: relative !important;
    animation: fadeInBubble 0.2s ease-out forwards;
    margin-bottom: 0.4rem !important;
}

@keyframes fadeInBubble {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

.chat-bubble-row.mine {
    align-self: flex-end !important;
    align-items: flex-end !important;
    margin-left: auto !important;
    margin-right: 0 !important;
}

.chat-bubble-row.other {
    align-self: flex-start !important;
    align-items: flex-start !important;
    margin-right: auto !important;
    margin-left: 0 !important;
}

.chat-bubble-row.internal {
    align-self: center !important;
    max-width: 90% !important;
    align-items: center !important;
    margin: 0 auto !important;
}

.chat-bubble-card {
    display: flex !important;
    flex-direction: column !important;
    justify-content: flex-start !important;
    align-items: flex-start !important;
    padding: 0.45rem 0.8rem !important;
    border-radius: 14px !important;
    font-size: 0.88rem !important;
    line-height: 1.35 !important;
    position: relative !important;
    word-break: break-word !important;
    white-space: pre-wrap !important;
    height: auto !important;
    min-height: 0 !important;
    max-height: none !important;
    width: fit-content !important;
    max-width: 100% !important;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.18) !important;
}

/* Mine (User's own messages) */
.chat-bubble-row.mine .chat-bubble-card {
    background: linear-gradient(135deg, #059669 0%, #047857 100%) !important;
    color: #ffffff !important;
    border-bottom-right-radius: 4px !important;
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
}

/* Other (Support Team / Customer depending on perspective) */
.chat-bubble-row.other .chat-bubble-card {
    background: rgba(30, 41, 59, 0.88) !important;
    color: #f1f5f9 !important;
    border-bottom-left-radius: 4px !important;
    border: 1px solid rgba(255, 255, 255, 0.08) !important;
}

/* Internal Note */
.chat-bubble-row.internal .chat-bubble-card {
    background: rgba(245, 158, 11, 0.12) !important;
    border: 1px dashed #f59e0b !important;
    color: #fbbf24 !important;
    border-radius: 14px !important;
    width: 100% !important;
}

.chat-sender-info {
    font-size: 0.7rem !important;
    font-weight: 700 !important;
    margin-top: 0 !important;
    margin-bottom: 0.15rem !important;
    padding: 0 !important;
    line-height: 1.2 !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.3rem !important;
}

.chat-msg-body {
    font-size: 0.88rem !important;
    line-height: 1.35 !important;
    margin: 0 !important;
    padding: 0 !important;
    color: inherit !important;
    word-break: break-word !important;
    white-space: pre-wrap !important;
}

.chat-bubble-row.mine .chat-sender-info {
    color: rgba(255, 255, 255, 0.9) !important;
}
.chat-bubble-row.other .chat-sender-info {
    color: #10b981 !important;
}

.chat-timestamp {
    font-size: 0.65rem !important;
    opacity: 0.78 !important;
    margin-top: 0.2rem !important;
    margin-bottom: 0 !important;
    padding: 0 !important;
    line-height: 1 !important;
    display: flex !important;
    align-items: center !important;
    gap: 0.25rem !important;
}

.chat-bubble-row.mine .chat-timestamp {
    justify-content: flex-end !important;
    color: rgba(255, 255, 255, 0.8) !important;
}

.chat-bubble-row.other .chat-timestamp {
    justify-content: flex-start !important;
    color: #94a3b8 !important;
}

/* Image / Attachment Previews */
.chat-attachment-box {
    margin-top: 0.6rem;
    padding-top: 0.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.12);
}

.chat-attachment-img {
    max-width: 220px;
    max-height: 180px;
    border-radius: 10px;
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
    border: 1px solid rgba(255, 255, 255, 0.15);
    margin-top: 0.3rem;
}

.chat-attachment-img:hover {
    transform: scale(1.03);
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.4);
}

/* Sticky Bottom Message Composer */
.chat-bottom-composer {
    background: rgba(15, 23, 42, 0.98);
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    padding: 0.7rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    position: relative;
    z-index: 10;
    width: 100%;
}

.composer-input-row {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    background: rgba(30, 41, 59, 0.85);
    border: 1px solid rgba(255, 255, 255, 0.12);
    border-radius: 20px;
    padding: 0.35rem 0.5rem 0.35rem 0.8rem;
    width: 100%;
}

.composer-input-row:focus-within {
    border-color: #059669;
    box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.2);
}

.chat-textarea {
    flex: 1 !important;
    min-width: 0 !important;
    width: 100% !important;
    background: transparent;
    border: none;
    outline: none;
    color: #f8fafc;
    font-size: 0.95rem;
    font-family: inherit;
    resize: none;
    max-height: 120px;
    min-height: 38px;
    padding: 0.45rem 0.3rem;
    line-height: 1.4;
}

.chat-textarea::placeholder {
    color: #64748b;
}

.attach-btn-icon {
    flex-shrink: 0;
    background: transparent;
    border: none;
    color: #94a3b8;
    font-size: 1.25rem;
    cursor: pointer;
    padding: 0.4rem;
    border-radius: 50%;
    transition: color 0.2s, background 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.attach-btn-icon:hover {
    color: #10b981;
    background: rgba(16, 185, 129, 0.1);
}

.send-msg-btn {
    flex-shrink: 0;
    background: linear-gradient(135deg, #059669 0%, #047857 100%);
    color: #ffffff;
    border: none;
    border-radius: 14px;
    padding: 0.55rem 1.1rem;
    font-weight: 700;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 14px rgba(5, 150, 105, 0.35);
    transition: transform 0.15s, opacity 0.15s;
    min-height: 38px;
}

.send-msg-btn:hover:not(:disabled) {
    transform: translateY(-1px);
    opacity: 0.95;
}

.send-msg-btn:disabled {
    background: #475569;
    box-shadow: none;
    cursor: not-allowed;
    opacity: 0.6;
}

/* Floating New Message Scroll Indicator */
.scroll-new-msg-pill {
    position: absolute;
    bottom: 80px;
    right: 20px;
    background: #059669;
    color: #ffffff;
    padding: 0.45rem 1rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 700;
    cursor: pointer;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
    display: flex;
    align-items: center;
    gap: 0.4rem;
    z-index: 20;
    transition: transform 0.2s, opacity 0.2s;
    animation: bouncePill 1s infinite alternate;
}

@keyframes bouncePill {
    from { transform: translateY(0); }
    to { transform: translateY(-4px); }
}

/* File Selected Chip */
.file-selected-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(5, 150, 105, 0.15);
    border: 1px solid rgba(5, 150, 105, 0.3);
    color: #34d399;
    font-size: 0.78rem;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    margin-bottom: 0.3rem;
}

.file-selected-chip .remove-file-btn {
    cursor: pointer;
    color: #ef4444;
    font-weight: bold;
    margin-left: 0.2rem;
}

/* Lightbox Modal */
.image-lightbox-modal {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(15, 23, 42, 0.92);
    backdrop-filter: blur(10px);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.25s ease;
}

.image-lightbox-modal.active {
    opacity: 1;
    pointer-events: auto;
}

.image-lightbox-modal img {
    max-width: 90vw;
    max-height: 85vh;
    border-radius: 12px;
    box-shadow: 0 20px 50px rgba(0,0,0,0.6);
}

.lightbox-close-btn {
    position: absolute;
    top: 20px;
    right: 25px;
    color: #ffffff;
    font-size: 2rem;
    cursor: pointer;
    background: rgba(255,255,255,0.1);
    width: 44px;
    height: 44px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Mobile Adjustments */
@media (max-width: 768px) {
    .support-chat-workspace {
        height: calc(100dvh - 80px);
        height: calc(100vh - 80px);
        min-height: 480px;
        border-radius: 12px;
        padding-bottom: env(safe-area-inset-bottom, 0px);
    }
    .chat-bubble-row {
        max-width: 88%;
    }
    .chat-top-header {
        padding: 0.8rem 1rem;
    }
    .chat-messages-stream {
        padding: 0.9rem;
    }
}
</style>

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
        <?php if ($msg_success): ?>
            <div style="background: rgba(16, 185, 129, 0.15); border: 1px solid #10b981; color: #10b981; padding: 0.8rem 1.2rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.9rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg_success); ?>
            </div>
        <?php endif; ?>

        <?php if ($msg_error): ?>
            <div style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #ef4444; padding: 0.8rem 1.2rem; border-radius: 12px; margin-bottom: 1rem; font-size: 0.9rem;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($msg_error); ?>
            </div>
        <?php endif; ?>

        <!-- Full Support Ticket Conversation Workspace -->
        <div class="support-chat-workspace">
            <!-- Header Bar -->
            <div class="chat-top-header">
                <div class="chat-top-bar">
                    <div class="ticket-meta-title">
                        <a href="<?php echo $is_admin ? 'admin_support.php' : 'my_tickets.php'; ?>" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.3rem 0.7rem; border-radius: 8px;">
                            <i class="fas fa-arrow-left"></i> Back
                        </a>

                        <span class="ticket-ref-badge"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>

                        <?php
                            $st = strtolower($ticket['status']);
                            $badge_bg = 'rgba(245, 158, 11, 0.18)';
                            $badge_color = '#f59e0b';
                            $st_label = 'Open';
                            if ($st === 'in_progress') {
                                $badge_bg = 'rgba(59, 130, 246, 0.18)';
                                $badge_color = '#60a5fa';
                                $st_label = 'In Progress';
                            } elseif ($st === 'waiting_customer') {
                                $badge_bg = 'rgba(245, 158, 11, 0.18)';
                                $badge_color = '#fbbf24';
                                $st_label = 'Waiting Customer';
                            } elseif ($st === 'resolved') {
                                $badge_bg = 'rgba(16, 185, 129, 0.18)';
                                $badge_color = '#34d399';
                                $st_label = 'Resolved';
                            } elseif ($st === 'closed') {
                                $badge_bg = 'rgba(148, 163, 184, 0.15)';
                                $badge_color = '#94a3b8';
                                $st_label = 'Closed';
                            }
                        ?>
                        <span id="ticketStatusPill" class="ticket-status-pill" style="background: <?php echo $badge_bg; ?>; color: <?php echo $badge_color; ?>; border: 1px solid <?php echo $badge_color; ?>;">
                            <i class="fas fa-circle" style="font-size: 0.5rem;"></i> <?php echo $st_label; ?>
                        </span>

                        <span style="background: rgba(16, 185, 129, 0.12); color: #34d399; padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.76rem; font-weight: 700;">
                            <?php echo htmlspecialchars($ticket['category']); ?>
                        </span>
                    </div>

                    <div style="display: flex; gap: 0.6rem; align-items: center;">
                        <button id="toggleDetailsBtn" type="button" class="btn btn-outline" style="font-size: 0.78rem; padding: 0.3rem 0.7rem;" onclick="toggleTicketDrawer()">
                            <i class="fas fa-info-circle"></i> Ticket Details <i class="fas fa-chevron-down" id="drawerChevron"></i>
                        </button>

                        <?php if ($st !== 'closed'): ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to close this ticket?');">
                                <button type="submit" name="close_ticket" value="1" class="btn btn-outline" style="font-size: 0.78rem; padding: 0.3rem 0.7rem; color: #f87171; border-color: #f87171;">
                                    <i class="fas fa-lock"></i> Close
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Collapsible Ticket Details Drawer -->
                <div id="ticketDetailsDrawer" class="ticket-details-drawer collapsed">
                    <div><strong>Subject:</strong> <span style="color: #f8fafc;"><?php echo htmlspecialchars($ticket['subject']); ?></span></div>
                    <div><strong>Customer:</strong> <span style="color: #f8fafc;"><?php echo htmlspecialchars($ticket['customer_name']); ?></span></div>
                    <div><strong>Contact:</strong> <?php echo htmlspecialchars($ticket['customer_phone']); ?></div>
                    <div><strong>Created:</strong> <?php echo date('M d, Y h:i A', strtotime($ticket['created_at'])); ?></div>
                    <?php if (!empty($ticket['related_entity_type']) && !empty($ticket['related_entity_id'])): ?>
                        <div><strong>Related Record:</strong> <span style="color: #34d399; font-weight: bold; text-transform: uppercase;"><?php echo htmlspecialchars($ticket['related_entity_type']); ?> #<?php echo htmlspecialchars($ticket['related_entity_id']); ?></span></div>
                    <?php endif; ?>

                    <?php if ($is_admin): ?>
                        <div style="display: none; grid-column: 1 / -1; padding-top: 0.6rem; border-top: 1px dashed rgba(255,255,255,0.08); margin-top: 0.4rem;">
                            <form method="POST" style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
                                <span style="font-weight: 700; font-size: 0.78rem; color: #10b981;">STATUS OVERRIDE:</span>
                                <select name="status" class="form-control" style="max-width: 150px; font-size: 0.78rem; padding: 0.25rem 0.5rem;">
                                    <option value="open" <?php echo $ticket['status'] === 'open' ? 'selected' : ''; ?>>🟡 Open</option>
                                    <option value="in_progress" <?php echo $ticket['status'] === 'in_progress' ? 'selected' : ''; ?>>🔵 In Progress</option>
                                    <option value="waiting_customer" <?php echo $ticket['status'] === 'waiting_customer' ? 'selected' : ''; ?>>🟠 Waiting Customer</option>
                                    <option value="resolved" <?php echo $ticket['status'] === 'resolved' ? 'selected' : ''; ?>>🟢 Resolved</option>
                                    <option value="closed" <?php echo $ticket['status'] === 'closed' ? 'selected' : ''; ?>>⚫ Closed</option>
                                </select>

                                <select name="priority" class="form-control" style="max-width: 120px; font-size: 0.78rem; padding: 0.25rem 0.5rem;">
                                    <option value="low" <?php echo $ticket['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="normal" <?php echo $ticket['priority'] === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                    <option value="high" <?php echo $ticket['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                                    <option value="urgent" <?php echo $ticket['priority'] === 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                                </select>

                                <button type="submit" name="update_status_priority" value="1" class="btn btn-primary" style="font-size: 0.76rem; padding: 0.25rem 0.7rem;">
                                    Save
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Scrollable Messages Stream -->
            <div id="chatMessageContainer" class="chat-messages-stream">
                <div style="text-align: center; font-size: 0.75rem; color: #64748b; margin-bottom: 0.5rem;">
                    <i class="fas fa-shield-alt" style="color: #059669;"></i> End-to-End Encrypted Support Channel &bull; Live Connected
                </div>

                <!-- Messages Rendered Dynamically via JS & Initial Payload -->
                <div id="messagesList"></div>
            </div>

            <!-- Floating New Message Alert -->
            <div id="scrollPill" class="scroll-new-msg-pill" style="display: none;" onclick="scrollToBottom(true)">
                <i class="fas fa-arrow-down"></i> New Message Below
            </div>

            <!-- Sticky Bottom Composer -->
            <?php if ($st !== 'closed'): ?>
                <div class="chat-bottom-composer">
                    <!-- Selected File Chip -->
                    <div id="fileSelectedChip" class="file-selected-chip" style="display: none;">
                        <i class="fas fa-paperclip"></i> <span id="fileNameText">filename.jpg</span>
                        <span class="remove-file-btn" onclick="clearSelectedFile()">&times;</span>
                    </div>

                    <?php if ($is_admin): ?>
                        <div style="font-size: 0.8rem; color: #fbbf24; display: flex; align-items: center; gap: 0.4rem; cursor: pointer;">
                            <label style="display: flex; align-items: center; gap: 0.4rem; cursor: pointer; user-select: none;">
                                <input type="checkbox" id="isInternalNote" value="1"> 🔒 Internal Admin Note (Hidden from Customer)
                            </label>
                        </div>
                    <?php endif; ?>

                    <form id="chatComposerForm" onsubmit="handleSendReply(event)">
                        <div class="composer-input-row">
                            <!-- Hidden File Input -->
                            <input type="file" id="replyAttachment" accept=".jpg,.jpeg,.png,.webp,.pdf" style="display: none;" onchange="handleFileSelected(this)">

                            <!-- File Attach Icon Button -->
                            <button type="button" class="attach-btn-icon" title="Attach file or screenshot" onclick="document.getElementById('replyAttachment').click()">
                                <i class="fas fa-paperclip"></i>
                            </button>

                            <!-- Multi-line Auto-expanding Textarea -->
                            <textarea id="replyMessage" class="chat-textarea" placeholder="Type your message..." rows="1" onkeydown="handleKeyDown(event)" oninput="autoGrowTextarea(this)" required></textarea>

                            <!-- Submit Button -->
                            <button type="submit" id="sendBtn" class="send-msg-btn">
                                <span>Send</span> <i class="fas fa-paper-plane"></i>
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div style="background: rgba(15, 23, 42, 0.95); padding: 1.2rem; text-align: center; border-top: 1px solid rgba(255, 255, 255, 0.08); color: #94a3b8; font-size: 0.9rem;">
                    <i class="fas fa-lock" style="color: #64748b; margin-right: 0.4rem;"></i> This support ticket is closed.
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Image Lightbox Modal -->
<div id="imageLightbox" class="image-lightbox-modal" onclick="closeLightbox()">
    <span class="lightbox-close-btn" onclick="closeLightbox()">&times;</span>
    <img id="lightboxImg" src="" alt="Enlarged Screenshot">
</div>

<script>
const TICKET_ID = <?php echo $ticket_id; ?>;
const CURRENT_USER_ID = <?php echo $user_id; ?>;
const IS_ADMIN = <?php echo $is_admin ? 'true' : 'false'; ?>;

let lastReplyId = 0;
let isUserScrolledUp = false;
let pollingInterval = null;
let initialPayload = <?php echo json_encode($initial_replies); ?>;

document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('chatMessageContainer');
    
    // Process Initial Messages
    if (initialPayload && initialPayload.length > 0) {
        renderMessagesBatch(initialPayload, true);
    } else {
        document.getElementById('messagesList').innerHTML = `
            <div style="text-align: center; padding: 3rem 1rem; color: #64748b;" id="emptyMsgState">
                <i class="fas fa-comments" style="font-size: 2.5rem; opacity: 0.4; margin-bottom: 0.8rem; display: block;"></i>
                No messages yet. Send a message below to start the conversation.
            </div>
        `;
    }

    scrollToBottom(false);

    // Scroll listener to detect if user scrolls up
    if (container) {
        container.addEventListener('scroll', function() {
            const distanceFromBottom = container.scrollHeight - container.scrollTop - container.clientHeight;
            if (distanceFromBottom > 120) {
                isUserScrolledUp = true;
            } else {
                isUserScrolledUp = false;
                hideScrollPill();
            }
        });
    }

    // Start Live Polling every 3 seconds
    pollingInterval = setInterval(fetchLiveMessages, 3000);
});

// Toggle Header Drawer
function toggleTicketDrawer() {
    const drawer = document.getElementById('ticketDetailsDrawer');
    const chevron = document.getElementById('drawerChevron');
    if (drawer) {
        drawer.classList.toggle('collapsed');
        if (chevron) {
            chevron.className = drawer.classList.contains('collapsed') ? 'fas fa-chevron-down' : 'fas fa-chevron-up';
        }
    }
}

// Auto Grow Textarea
function autoGrowTextarea(element) {
    element.style.height = 'auto';
    element.style.height = Math.min(element.scrollHeight, 120) + 'px';
}

// Keydown handler (Enter to send on desktop)
function handleKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey && window.innerWidth > 768) {
        e.preventDefault();
        document.getElementById('chatComposerForm').dispatchEvent(new Event('submit'));
    }
}

// File Input Handler
function handleFileSelected(input) {
    const chip = document.getElementById('fileSelectedChip');
    const text = document.getElementById('fileNameText');
    if (input.files && input.files[0]) {
        text.textContent = input.files[0].name;
        chip.style.display = 'inline-flex';
    } else {
        chip.style.display = 'none';
    }
}

function clearSelectedFile() {
    const input = document.getElementById('replyAttachment');
    const chip = document.getElementById('fileSelectedChip');
    if (input) input.value = '';
    if (chip) chip.style.display = 'none';
}

// Scroll to bottom helper
function scrollToBottom(smooth = true) {
    const container = document.getElementById('chatMessageContainer');
    if (container) {
        container.scrollTo({
            top: container.scrollHeight,
            behavior: smooth ? 'smooth' : 'auto'
        });
    }
    hideScrollPill();
}

function showScrollPill() {
    const pill = document.getElementById('scrollPill');
    if (pill) pill.style.display = 'flex';
}

function hideScrollPill() {
    const pill = document.getElementById('scrollPill');
    if (pill) pill.style.display = 'none';
}

// Lightbox Modal
function openLightbox(src) {
    const modal = document.getElementById('imageLightbox');
    const img = document.getElementById('lightboxImg');
    if (modal && img) {
        img.src = src;
        modal.classList.add('active');
    }
}

function closeLightbox() {
    const modal = document.getElementById('imageLightbox');
    if (modal) modal.classList.remove('active');
}

// Render Batch of Messages
function renderMessagesBatch(rawList, isInitial = false) {
    const listContainer = document.getElementById('messagesList');
    if (!listContainer) return;

    const emptyState = document.getElementById('emptyMsgState');
    if (emptyState && rawList.length > 0) {
        emptyState.remove();
    }

    rawList.forEach(m => {
        const msgId = parseInt(m.id || 0);
        
        // 1. Strict Duplicate Check: If message with this ID is already in the DOM, skip!
        if (msgId > 0 && listContainer.querySelector(`.chat-bubble-row[data-id="${msgId}"]`)) {
            if (msgId > lastReplyId) lastReplyId = msgId;
            return;
        }

        if (msgId > lastReplyId) {
            lastReplyId = msgId;
        }

        // 2. Date Separator Check
        const msgDateStr = m.created_at ? m.created_at.split(' ')[0] : '';
        if (msgDateStr) {
            const todayStr = new Date().toISOString().split('T')[0];
            let label = m.formatted_date || msgDateStr;
            if (msgDateStr === todayStr) label = 'Today';

            const lastSeparator = listContainer.querySelector('.chat-date-separator:last-of-type span');
            const lastSepLabel = lastSeparator ? lastSeparator.textContent.trim().toUpperCase() : '';
            const currentLabelUpper = label.trim().toUpperCase();

            if (lastSepLabel !== currentLabelUpper) {
                const dateDivider = document.createElement('div');
                dateDivider.className = 'chat-date-separator';
                dateDivider.innerHTML = `<span>${escapeHtml(label)}</span>`;
                listContainer.appendChild(dateDivider);
            }
        }

        const isMine = (parseInt(m.sender_id) === CURRENT_USER_ID);
        const isInternal = Boolean(m.is_internal_note == 1);
        const isSupport = (m.sender_role === 'admin');

        let rowClass = isMine ? 'mine' : 'other';
        if (isInternal) rowClass = 'internal';

        const bubbleRow = document.createElement('div');
        bubbleRow.className = `chat-bubble-row ${rowClass}`;
        bubbleRow.setAttribute('data-id', msgId);

        let senderTitle = isSupport ? `🎧 Support Team (${escapeHtml(m.sender_name)})` : `👤 ${escapeHtml(m.sender_name)}`;
        if (isInternal) senderTitle = `🔒 Internal Admin Note (${escapeHtml(m.sender_name)})`;

        let attachmentHtml = '';
        if (m.attachment_path) {
            const ext = m.attachment_path.split('.').pop().toLowerCase();
            if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                attachmentHtml = `
                    <div class="chat-attachment-box">
                        <img src="${escapeHtml(m.attachment_path)}" class="chat-attachment-img" alt="Attachment" onclick="openLightbox('${escapeHtml(m.attachment_path)}')">
                    </div>
                `;
            } else {
                attachmentHtml = `
                    <div class="chat-attachment-box">
                        <a href="${escapeHtml(m.attachment_path)}" target="_blank" style="color: #34d399; font-size: 0.8rem; font-weight: bold; text-decoration: underline;">
                            📄 View Attachment (${ext.toUpperCase()})
                        </a>
                    </div>
                `;
            }
        }

        const formattedTime = m.formatted_time || (m.created_at ? m.created_at.split(' ')[1] : '');

        bubbleRow.innerHTML = `
            <div class="chat-bubble-card">
                <div class="chat-sender-info">${senderTitle}</div>
                <div class="chat-msg-body">${nl2br(escapeHtml(m.message || ''))}</div>
                ${attachmentHtml}
                <div class="chat-timestamp">
                    <span>${formattedTime}</span>
                    ${isMine ? '<i class="fas fa-check" style="font-size: 0.65rem;"></i>' : ''}
                </div>
            </div>
        `;

        listContainer.appendChild(bubbleRow);
    });

    if (isUserScrolledUp && !isInitial) {
        showScrollPill();
    } else {
        scrollToBottom(true);
    }
}

// Update Ticket Status Badge dynamically
function updateTicketStatusBadge(statusStr) {
    const pill = document.getElementById('ticketStatusPill');
    if (!pill || !statusStr) return;
    
    const st = statusStr.toLowerCase();
    let bg = 'rgba(245, 158, 11, 0.18)';
    let color = '#f59e0b';
    let label = 'Open';
    
    if (st === 'in_progress') {
        bg = 'rgba(59, 130, 246, 0.18)';
        color = '#60a5fa';
        label = 'In Progress';
    } else if (st === 'waiting_customer') {
        bg = 'rgba(245, 158, 11, 0.18)';
        color = '#fbbf24';
        label = 'Waiting Customer';
    } else if (st === 'resolved') {
        bg = 'rgba(16, 185, 129, 0.18)';
        color = '#34d399';
        label = 'Resolved';
    } else if (st === 'closed') {
        bg = 'rgba(148, 163, 184, 0.15)';
        color = '#94a3b8';
        label = 'Closed';
    } else if (st === 'open') {
        bg = 'rgba(245, 158, 11, 0.18)';
        color = '#f59e0b';
        label = 'Open';
    }
    
    pill.style.background = bg;
    pill.style.color = color;
    pill.style.borderColor = color;
    pill.innerHTML = `<i class="fas fa-circle" style="font-size: 0.5rem;"></i> ${escapeHtml(label)}`;
}

// Fetch Live Messages via AJAX
function fetchLiveMessages() {
    fetch(`api_support_chat.php?action=fetch_messages&ticket_id=${TICKET_ID}&last_id=${lastReplyId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                if (data.ticket_status) {
                    updateTicketStatusBadge(data.ticket_status);
                }
                if (data.messages && data.messages.length > 0) {
                    renderMessagesBatch(data.messages, false);
                }
            }
        })
        .catch(err => console.log('Polling notice:', err));
}

// Handle Form Submission via AJAX
function handleSendReply(e) {
    e.preventDefault();

    const textarea = document.getElementById('replyMessage');
    const sendBtn = document.getElementById('sendBtn');
    const fileInput = document.getElementById('replyAttachment');
    const internalCheckbox = document.getElementById('isInternalNote');

    const messageText = textarea ? textarea.value.trim() : '';
    if (!messageText && (!fileInput || !fileInput.files[0])) return;
    if (sendBtn && sendBtn.disabled) return;

    // Loading State
    sendBtn.disabled = true;
    sendBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> <span>Sending...</span>`;

    const formData = new FormData();
    formData.append('action', 'send_reply');
    formData.append('ticket_id', TICKET_ID);
    formData.append('reply_message', messageText);
    if (internalCheckbox && internalCheckbox.checked) {
        formData.append('is_internal_note', '1');
    }
    if (fileInput && fileInput.files[0]) {
        formData.append('reply_attachment', fileInput.files[0]);
    }

    fetch('api_support_chat.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = `<span>Send</span> <i class="fas fa-paper-plane"></i>`;

        if (data.success && data.reply) {
            textarea.value = '';
            textarea.style.height = 'auto';
            clearSelectedFile();

            if (internalCheckbox) internalCheckbox.checked = false;

            if (data.ticket_status) {
                updateTicketStatusBadge(data.ticket_status);
            }

            renderMessagesBatch([data.reply], false);
            scrollToBottom(true);
        } else {
            alert('⚠️ Message Error: ' + (data.message || 'Could not send reply.'));
        }
    })
    .catch(err => {
        sendBtn.disabled = false;
        sendBtn.innerHTML = `<span>Send</span> <i class="fas fa-paper-plane"></i>`;
        alert('⚠️ Network Interrupted. Message was not sent. Please try again.');
    });
}

// Utility Helpers
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}

function nl2br(str) {
    if (!str) return '';
    return str.replace(/\r\n|\r|\n/g, '<br>');
}
</script>

<?php include 'includes/footer.php'; ?>
