<?php
require_once __DIR__ . '/../config.php';

if (!function_exists('sanitize_utf8mb4_text')) {
    function sanitize_utf8mb4_text($str) {
        if ($str === null || $str === '') return '';
        return preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $str);
    }
}

// Auto-initialize Notifications System Table
function init_notification_tables() {
    global $conn;
    if (!isset($conn) || !$conn) {
        $conn = $GLOBALS['conn'] ?? null;
    }
    if (!$conn || !($conn instanceof mysqli)) return;

    @$conn->set_charset("utf8mb4");

    $conn->query("CREATE TABLE IF NOT EXISTS user_notifications (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        role VARCHAR(50) DEFAULT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        type VARCHAR(50) DEFAULT 'info',
        related_entity_type VARCHAR(50) DEFAULT NULL,
        related_entity_id VARCHAR(100) DEFAULT NULL,
        is_read TINYINT(1) DEFAULT 0,
        is_pinned TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_user_created (user_id, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // Ensure utf8mb4 table conversion if created previously with utf8/latin1
    try {
        @$conn->query("ALTER TABLE user_notifications CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (Throwable $t) {}

    // Auto-migrate columns if table existed prior
    $col_res = @$conn->query("SHOW COLUMNS FROM user_notifications");
    if ($col_res) {
        $existing_cols = [];
        while ($col_row = $col_res->fetch_assoc()) {
            $existing_cols[] = strtolower($col_row['Field']);
        }
        if (!empty($existing_cols)) {
            if (!in_array('type', $existing_cols)) {
                @$conn->query("ALTER TABLE user_notifications ADD COLUMN type VARCHAR(50) DEFAULT 'info' AFTER message");
            }
            if (!in_array('related_entity_type', $existing_cols)) {
                @$conn->query("ALTER TABLE user_notifications ADD COLUMN related_entity_type VARCHAR(50) DEFAULT NULL AFTER type");
            }
            if (!in_array('related_entity_id', $existing_cols)) {
                @$conn->query("ALTER TABLE user_notifications ADD COLUMN related_entity_id VARCHAR(100) DEFAULT NULL AFTER related_entity_type");
            }
            if (!in_array('role', $existing_cols)) {
                @$conn->query("ALTER TABLE user_notifications ADD COLUMN role VARCHAR(50) DEFAULT NULL AFTER user_id");
            }
            if (!in_array('is_pinned', $existing_cols)) {
                @$conn->query("ALTER TABLE user_notifications ADD COLUMN is_pinned TINYINT(1) DEFAULT 0 AFTER is_read");
            }
            if (!in_array('read_at', $existing_cols)) {
                @$conn->query("ALTER TABLE user_notifications ADD COLUMN read_at TIMESTAMP NULL AFTER created_at");
            }
        }
    }
}

// Run Table Initialization conditionally to optimize performance
if (!isset($GLOBALS['notification_tables_inited'])) {
    $GLOBALS['notification_tables_inited'] = true;
    global $conn;
    if (!isset($conn) || !$conn) {
        $conn = $GLOBALS['conn'] ?? null;
    }
    if ($conn && $conn instanceof mysqli) {
        $check_notif_tbl = @$conn->query("SELECT 1 FROM user_notifications LIMIT 1");
        if (!$check_notif_tbl) {
            init_notification_tables();
        }
    }
}

// Create Notification with Duplicate Prevention
function create_notification($user_id, $title, $message, $type = 'info', $related_entity_type = null, $related_entity_id = null, $role = null) {
    global $conn;
    if (!isset($conn) || !$conn) {
        $conn = $GLOBALS['conn'] ?? null;
    }
    if (!$conn || !($conn instanceof mysqli)) return false;
    $user_id = (int)$user_id;
    if ($user_id <= 0 || empty($title) || empty($message)) return false;

    @$conn->set_charset("utf8mb4");

    // Idempotency Check: Prevent duplicate notification within 30 seconds for same title & user
    try {
        $check = $conn->prepare("
            SELECT id FROM user_notifications 
            WHERE user_id = ? AND title = ? AND created_at >= NOW() - INTERVAL 30 SECOND
        ");
        if ($check) {
            $check->bind_param("is", $user_id, $title);
            $check->execute();
            $res = $check->get_result();
            if ($res && $res->num_rows > 0) {
                return false; // Prevent duplicate emission
            }
        }
    } catch (Throwable $e) {
        try {
            $clean_title = sanitize_utf8mb4_text($title);
            $check = $conn->prepare("
                SELECT id FROM user_notifications 
                WHERE user_id = ? AND title = ? AND created_at >= NOW() - INTERVAL 30 SECOND
            ");
            if ($check) {
                $check->bind_param("is", $user_id, $clean_title);
                $check->execute();
                $res = $check->get_result();
                if ($res && $res->num_rows > 0) return false;
            }
        } catch (Throwable $e2) {}
    }

    try {
        $stmt = $conn->prepare("
            INSERT INTO user_notifications (user_id, role, title, message, type, related_entity_type, related_entity_id) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        if (!$stmt) return false;
        $stmt->bind_param("issssss", $user_id, $role, $title, $message, $type, $related_entity_type, $related_entity_id);
        return $stmt->execute();
    } catch (Throwable $e) {
        // Fallback: If MySQL rejects 4-byte emojis due to legacy charset limits, insert sanitized text
        try {
            $clean_title = sanitize_utf8mb4_text($title);
            $clean_msg   = sanitize_utf8mb4_text($message);
            if (empty($clean_title)) $clean_title = "Notification";
            if (empty($clean_msg)) $clean_msg = "You have a new update.";

            $stmt = $conn->prepare("
                INSERT INTO user_notifications (user_id, role, title, message, type, related_entity_type, related_entity_id) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            if (!$stmt) return false;
            $stmt->bind_param("issssss", $user_id, $role, $clean_title, $clean_msg, $type, $related_entity_type, $related_entity_id);
            return $stmt->execute();
        } catch (Throwable $e2) {
            return false;
        }
    }
}

// Alias for send_user_notification used by doctor/admin modules
function send_user_notification($user_id, $type, $title, $message, $related_entity_type = null, $related_entity_id = null, $role = null) {
    return create_notification($user_id, $title, $message, $type, $related_entity_type, $related_entity_id, $role);
}

// Order Status Notification Helper
function notify_order_status_change($conn, $order_id, $patient_id, $new_status, $reason = '') {
    $order_id = (int)$order_id;
    $patient_id = (int)$patient_id;
    $st = strtolower(trim($new_status));
    $formatted_ord_id = "#ORD-" . str_pad($order_id, 4, '0', STR_PAD_LEFT);

    $title = "";
    $message = "";

    if ($st === 'packing' || $st === 'packed' || $st === 'processing') {
        $title = "Your Order Is Being Packed 📦";
        $message = "Your order {$formatted_ord_id} is now being packed.";
    } elseif ($st === 'shipped') {
        $title = "Your Order Has Been Shipped 🚚";
        $message = "Your order {$formatted_ord_id} has been shipped.";
    } elseif ($st === 'out for delivery') {
        $title = "Out for Delivery 🚴";
        $message = "Your order {$formatted_ord_id} is on the way.";
    } elseif ($st === 'delivered') {
        $title = "Order Delivered 🎉";
        $message = "Your order {$formatted_ord_id} has been delivered successfully.";
    } elseif ($st === 'cancelled' || $st === 'rejected') {
        $title = "Order Status Update ⚠️";
        $message = "Your order {$formatted_ord_id} status is now " . ucfirst($st) . ($reason ? ". Reason: " . $reason : ".");
    } else {
        $title = "Order Status Update";
        $message = "Your order {$formatted_ord_id} status updated to: " . ucfirst($st);
    }

    return create_notification(
        $patient_id,
        $title,
        $message,
        'order',
        'order',
        (string)$order_id
    );
}

// Get User Notifications List
function get_user_notifications($user_id, $limit = 20, $filter = 'all') {
    global $conn;
    if (!isset($conn) || !$conn) {
        $conn = $GLOBALS['conn'] ?? null;
    }
    if (!$conn || !($conn instanceof mysqli)) return [];

    $user_id = (int)$user_id;
    $limit = (int)$limit;

    $query = "SELECT * FROM user_notifications WHERE user_id = ?";
    if ($filter === 'unread') {
        $query .= " AND is_read = 0";
    } elseif ($filter === 'read') {
        $query .= " AND is_read = 1";
    }
    $query .= " ORDER BY is_pinned DESC, created_at DESC LIMIT " . max(1, min(100, $limit));

    $stmt = $conn->prepare($query);
    if (!$stmt) return [];
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();

    $list = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $list[] = $row;
        }
    }
    return $list;
}

// Toggle Notification Pin Status (Feature 30)
function toggle_pin_notification($user_id, $notification_id) {
    global $conn;
    if (!isset($conn) || !$conn) {
        $conn = $GLOBALS['conn'] ?? null;
    }
    if (!$conn || !($conn instanceof mysqli)) return false;

    $user_id = (int)$user_id;
    $notif_id = (int)$notification_id;

    $stmt = $conn->prepare("UPDATE user_notifications SET is_pinned = IF(is_pinned = 1, 0, 1) WHERE id = ? AND user_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $notif_id, $user_id);
    return $stmt->execute();
}

// Get Unread Notification Count
function get_unread_notification_count($user_id) {
    global $conn;
    if (!isset($conn) || !$conn) {
        $conn = $GLOBALS['conn'] ?? null;
    }
    if (!$conn || !($conn instanceof mysqli)) return 0;

    $user_id = (int)$user_id;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        return max(0, (int)$row['count']);
    }
    return 0;
}

// Mark Single Notification as Read
function mark_notification_as_read($user_id, $notification_id) {
    global $conn;
    if (!isset($conn) || !$conn) {
        $conn = $GLOBALS['conn'] ?? null;
    }
    if (!$conn || !($conn instanceof mysqli)) return false;

    $user_id = (int)$user_id;
    $notif_id = (int)$notification_id;

    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $notif_id, $user_id);
    return $stmt->execute();
}

// Mark All Notifications as Read for User
function mark_all_notifications_read($user_id) {
    global $conn;
    if (!isset($conn) || !$conn) {
        $conn = $GLOBALS['conn'] ?? null;
    }
    if (!$conn || !($conn instanceof mysqli)) return false;

    $user_id = (int)$user_id;

    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    if (!$stmt) return false;
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// Clear All Notifications for Current User
function clear_user_notifications($user_id) {
    global $conn;
    if (!isset($conn) || !$conn) {
        $conn = $GLOBALS['conn'] ?? null;
    }
    if (!$conn || !($conn instanceof mysqli)) return false;

    $user_id = (int)$user_id;
    if ($user_id <= 0) return false;

    $stmt = $conn->prepare("DELETE FROM user_notifications WHERE user_id = ? AND is_pinned = 0");
    if (!$stmt) return false;
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}



