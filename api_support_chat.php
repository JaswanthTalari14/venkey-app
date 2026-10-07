<?php
require_once 'config.php';
require_once 'includes/notification_functions.php';
require_once 'includes/security_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please login.']);
    exit;
}

$user_id   = (int)$_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'patient';
$user_name = $_SESSION['name'] ?? 'User';
$is_admin  = ($user_role === 'admin');

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$ticket_id = isset($_REQUEST['ticket_id']) ? (int)$_REQUEST['ticket_id'] : 0;

if ($ticket_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ticket ID']);
    exit;
}

// Security Check: Verify ticket ownership or admin permission
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
    echo json_encode(['success' => false, 'message' => 'Ticket not found or access denied']);
    exit;
}

$ticket = $ticket_res->fetch_assoc();

// ACTION 1: FETCH NEW / ALL MESSAGES
if ($action === 'fetch_messages') {
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    $replies_where = $is_admin ? "1=1" : "is_internal_note = 0";
    
    $replies_stmt = $conn->prepare("
        SELECT r.*, u.name as sender_name 
        FROM support_ticket_replies r
        JOIN users u ON r.sender_id = u.id
        WHERE r.ticket_id = ? AND r.id > ? AND $replies_where
        ORDER BY r.id ASC
    ");
    $replies_stmt->bind_param("ii", $ticket_id, $last_id);
    $replies_stmt->execute();
    $res = $replies_stmt->get_result();

    $messages = [];
    while ($r = $res->fetch_assoc()) {
        $messages[] = [
            'id'               => (int)$r['id'],
            'ticket_id'        => (int)$r['ticket_id'],
            'sender_id'        => (int)$r['sender_id'],
            'sender_name'      => $r['sender_name'],
            'sender_role'      => $r['sender_role'],
            'message'          => $r['message'],
            'is_internal_note' => (bool)$r['is_internal_note'],
            'attachment_path'  => $r['attachment_path'],
            'created_at'       => $r['created_at'],
            'formatted_time'   => date('h:i A', strtotime($r['created_at'])),
            'formatted_date'   => date('M d, Y', strtotime($r['created_at'])),
            'is_today'         => (date('Y-m-d', strtotime($r['created_at'])) === date('Y-m-d')),
            'is_mine'          => ((int)$r['sender_id'] === $user_id)
        ];
    }

    echo json_encode([
        'success'       => true,
        'ticket_status' => $ticket['status'],
        'messages'      => $messages,
        'user_id'       => $user_id
    ]);
    exit;
}

// ACTION 1B: FETCH OLDER MESSAGES (PAGINATION)
if ($action === 'fetch_older_messages') {
    $before_id = isset($_GET['before_id']) ? (int)$_GET['before_id'] : 0;
    $limit     = isset($_GET['limit']) ? min(50, max(10, (int)$_GET['limit'])) : 20;

    $replies_where = $is_admin ? "1=1" : "is_internal_note = 0";
    $before_sql = ($before_id > 0) ? " AND r.id < $before_id " : "";

    $replies_stmt = $conn->prepare("
        SELECT r.*, u.name as sender_name 
        FROM support_ticket_replies r
        JOIN users u ON r.sender_id = u.id
        WHERE r.ticket_id = ? $before_sql AND $replies_where
        ORDER BY r.id DESC
        LIMIT ?
    ");
    $replies_stmt->bind_param("ii", $ticket_id, $limit);
    $replies_stmt->execute();
    $res = $replies_stmt->get_result();

    $messages = [];
    while ($r = $res->fetch_assoc()) {
        $messages[] = [
            'id'               => (int)$r['id'],
            'ticket_id'        => (int)$r['ticket_id'],
            'sender_id'        => (int)$r['sender_id'],
            'sender_name'      => $r['sender_name'],
            'sender_role'      => $r['sender_role'],
            'message'          => $r['message'],
            'is_internal_note' => (bool)$r['is_internal_note'],
            'attachment_path'  => $r['attachment_path'],
            'created_at'       => $r['created_at'],
            'formatted_time'   => date('h:i A', strtotime($r['created_at'])),
            'formatted_date'   => date('M d, Y', strtotime($r['created_at'])),
            'is_today'         => (date('Y-m-d', strtotime($r['created_at'])) === date('Y-m-d')),
            'is_mine'          => ((int)$r['sender_id'] === $user_id)
        ];
    }
    $messages = array_reverse($messages);

    echo json_encode([
        'success'       => true,
        'ticket_status' => $ticket['status'],
        'messages'      => $messages,
        'user_id'       => $user_id,
        'has_more'      => (count($messages) >= $limit)
    ]);
    exit;
}

// ACTION 2: SEND NEW REPLY MESSAGE
if ($action === 'send_reply') {
    if (strtolower($ticket['status']) === 'closed') {
        echo json_encode(['success' => false, 'message' => 'Cannot send messages on a closed ticket.']);
        exit;
    }

    $reply_msg   = trim($_POST['reply_message'] ?? '');
    $is_internal = ($is_admin && isset($_POST['is_internal_note']) && $_POST['is_internal_note'] == 1) ? 1 : 0;

    if (empty($reply_msg) && empty($_FILES['reply_attachment'])) {
        echo json_encode(['success' => false, 'message' => 'Please enter a reply message.']);
        exit;
    }

    // Attachment processing
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
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid file format or size over 5MB. Allowed: JPG, PNG, WEBP, PDF']);
            exit;
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
        $reply_id = $conn->insert_id;

        // Update Ticket Status & Timestamps
        if ($is_admin && !$is_internal) {
            $new_st = 'waiting_customer';
            $conn->query("UPDATE support_tickets SET status = '$new_st', updated_at = NOW() WHERE id = $ticket_id");
            create_notification((int)$ticket['customer_id'], "Support Team Replied", "Support team replied to ticket #{$ticket['ticket_number']}.", 'system', 'ticket', (string)$ticket_id);
        } elseif (!$is_admin) {
            $new_st = ($ticket['status'] === 'waiting_customer') ? 'in_progress' : $ticket['status'];
            $conn->query("UPDATE support_tickets SET status = '$new_st', updated_at = NOW() WHERE id = $ticket_id");

            // Notify Admins
            $admins_q = $conn->query("SELECT id FROM users WHERE role = 'admin'");
            if ($admins_q) {
                while ($adm = $admins_q->fetch_assoc()) {
                    create_notification((int)$adm['id'], "Customer Replied #{$ticket['ticket_number']}", "Customer $user_name replied on ticket #{$ticket['ticket_number']}.", 'system', 'ticket', (string)$ticket_id, 'admin');
                }
            }
        }

        // Fetch inserted message object for immediate DOM rendering
        $inserted_stmt = $conn->prepare("
            SELECT r.*, u.name as sender_name 
            FROM support_ticket_replies r
            JOIN users u ON r.sender_id = u.id
            WHERE r.id = ?
        ");
        $inserted_stmt->bind_param("i", $reply_id);
        $inserted_stmt->execute();
        $new_msg = $inserted_stmt->get_result()->fetch_assoc();

        echo json_encode([
            'success' => true,
            'message' => 'Reply sent successfully.',
            'reply'   => [
                'id'               => (int)$new_msg['id'],
                'ticket_id'        => (int)$new_msg['ticket_id'],
                'sender_id'        => (int)$new_msg['sender_id'],
                'sender_name'      => $new_msg['sender_name'],
                'sender_role'      => $new_msg['sender_role'],
                'message'          => $new_msg['message'],
                'is_internal_note' => (bool)$new_msg['is_internal_note'],
                'attachment_path'  => $new_msg['attachment_path'],
                'created_at'       => $new_msg['created_at'],
                'formatted_time'   => date('h:i A', strtotime($new_msg['created_at'])),
                'formatted_date'   => date('M d, Y', strtotime($new_msg['created_at'])),
                'is_today'         => true,
                'is_mine'          => true
            ]
        ]);
        exit;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to record message in database.']);
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit;
