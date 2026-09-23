<?php
require_once __DIR__ . '/../config.php';

// Auto-initialize Notifications System Table
function init_notification_tables() {
    global $conn;

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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        read_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_read (user_id, is_read),
        INDEX idx_user_created (user_id, created_at)
    )");

    // Auto-migrate columns if table existed prior
    $col_res = $conn->query("SHOW COLUMNS FROM user_notifications");
    if ($col_res) {
        $existing_cols = [];
        while ($col_row = $col_res->fetch_assoc()) {
            $existing_cols[] = strtolower($col_row['Field']);
        }
        if (!empty($existing_cols)) {
            if (!in_array('type', $existing_cols)) {
                $conn->query("ALTER TABLE user_notifications ADD COLUMN type VARCHAR(50) DEFAULT 'info' AFTER message");
            }
            if (!in_array('related_entity_type', $existing_cols)) {
                $conn->query("ALTER TABLE user_notifications ADD COLUMN related_entity_type VARCHAR(50) DEFAULT NULL AFTER type");
            }
            if (!in_array('related_entity_id', $existing_cols)) {
                $conn->query("ALTER TABLE user_notifications ADD COLUMN related_entity_id VARCHAR(100) DEFAULT NULL AFTER related_entity_type");
            }
            if (!in_array('role', $existing_cols)) {
                $conn->query("ALTER TABLE user_notifications ADD COLUMN role VARCHAR(50) DEFAULT NULL AFTER user_id");
            }
            if (!in_array('read_at', $existing_cols)) {
                $conn->query("ALTER TABLE user_notifications ADD COLUMN read_at TIMESTAMP NULL AFTER created_at");
            }
        }
    }
}

// Run Table Initialization conditionally to optimize performance
if (!isset($GLOBALS['notification_tables_inited'])) {
    $GLOBALS['notification_tables_inited'] = true;
    $check_notif_tbl = @$conn->query("SELECT 1 FROM user_notifications LIMIT 1");
    if (!$check_notif_tbl) {
        init_notification_tables();
    }
}

// Create Notification with Duplicate Prevention
function create_notification($user_id, $title, $message, $type = 'info', $related_entity_type = null, $related_entity_id = null, $role = null) {
    global $conn;
    $user_id = (int)$user_id;
    if ($user_id <= 0 || empty($title) || empty($message)) return false;

    // Idempotency Check: Prevent duplicate notification within 30 seconds for same entity & user
    if (!empty($related_entity_type) && !empty($related_entity_id)) {
        $check = $conn->prepare("
            SELECT id FROM user_notifications 
            WHERE user_id = ? AND title = ? AND related_entity_type = ? AND related_entity_id = ? 
            AND created_at >= NOW() - INTERVAL 30 SECOND
        ");
        $check->bind_param("isss", $user_id, $title, $related_entity_type, $related_entity_id);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            return false; // Prevent duplicate emission
        }
    }

    $stmt = $conn->prepare("
        INSERT INTO user_notifications (user_id, role, title, message, type, related_entity_type, related_entity_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("issssss", $user_id, $role, $title, $message, $type, $related_entity_type, $related_entity_id);
    return $stmt->execute();
}

// Get User Notifications List
function get_user_notifications($user_id, $limit = 20, $filter = 'all') {
    global $conn;
    $user_id = (int)$user_id;
    $limit = (int)$limit;

    $query = "SELECT * FROM user_notifications WHERE user_id = ?";
    if ($filter === 'unread') {
        $query .= " AND is_read = 0";
    } elseif ($filter === 'read') {
        $query .= " AND is_read = 1";
    }
    $query .= " ORDER BY created_at DESC LIMIT " . max(1, min(100, $limit));

    $stmt = $conn->prepare($query);
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

// Get Unread Notification Count
function get_unread_notification_count($user_id) {
    global $conn;
    $user_id = (int)$user_id;

    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        return (int)$row['count'];
    }
    return 0;
}

// Mark Single Notification as Read
function mark_notification_as_read($user_id, $notification_id) {
    global $conn;
    $user_id = (int)$user_id;
    $notif_id = (int)$notification_id;

    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notif_id, $user_id);
    return $stmt->execute();
}

// Mark All Notifications as Read for User
function mark_all_notifications_read($user_id) {
    global $conn;
    $user_id = (int)$user_id;

    $stmt = $conn->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}
