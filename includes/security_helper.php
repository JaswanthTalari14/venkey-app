<?php
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config.php';
}

/**
 * Log Admin Actions for Feature 22 (Admin Activity Log)
 */
function log_admin_activity($conn, $admin_id, $action, $entity_type = null, $entity_id = null, $details = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $stmt = $conn->prepare("INSERT INTO admin_activity_logs (admin_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("ississ", $admin_id, $action, $entity_type, $entity_id, $details, $ip);
        $stmt->execute();
    }
}

/**
 * Feature 37: Login Attempt Protection & Rate Limiting (5 max attempts, 15m lock)
 */
function is_login_locked($conn, $ip, $email_or_phone) {
    $stmt = $conn->prepare("SELECT attempts, locked_until FROM login_attempts WHERE ip_address = ? AND email_or_phone = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $ip, $email_or_phone);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
                return true;
            }
        }
    }
    return false;
}

function record_failed_login($conn, $ip, $email_or_phone) {
    $stmt = $conn->prepare("SELECT id, attempts FROM login_attempts WHERE ip_address = ? AND email_or_phone = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $ip, $email_or_phone);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $new_attempts = $row['attempts'] + 1;
            $locked_until = ($new_attempts >= 5) ? date('Y-m-d H:i:s', time() + 900) : null;
            $id = $row['id'];
            $u_stmt = $conn->prepare("UPDATE login_attempts SET attempts = ?, locked_until = ? WHERE id = ?");
            $u_stmt->bind_param("isi", $new_attempts, $locked_until, $id);
            $u_stmt->execute();
        } else {
            $ins_stmt = $conn->prepare("INSERT INTO login_attempts (ip_address, email_or_phone, attempts) VALUES (?, ?, 1)");
            $ins_stmt->bind_param("ss", $ip, $email_or_phone);
            $ins_stmt->execute();
        }
    }
}

function reset_login_attempts($conn, $ip, $email_or_phone) {
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ? AND email_or_phone = ?");
    if ($stmt) {
        $stmt->bind_param("ss", $ip, $email_or_phone);
        $stmt->execute();
    }
}

/**
 * Feature 36: OTP Rate Limiting (Cooldown enforcement)
 */
function is_otp_rate_limited($conn, $identifier) {
    $stmt = $conn->prepare("SELECT attempts, locked_until, last_attempt FROM otp_rate_limits WHERE phone_or_email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
                return true;
            }
            if (strtotime($row['last_attempt']) > (time() - 30)) { // 30 sec cooldown
                return true;
            }
        }
    }
    return false;
}

function record_otp_request($conn, $identifier) {
    $stmt = $conn->prepare("SELECT id, attempts FROM otp_rate_limits WHERE phone_or_email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $identifier);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $row = $res->fetch_assoc()) {
            $attempts = $row['attempts'] + 1;
            $locked = ($attempts >= 5) ? date('Y-m-d H:i:s', time() + 1800) : null;
            $u = $conn->prepare("UPDATE otp_rate_limits SET attempts = ?, locked_until = ? WHERE phone_or_email = ?");
            $u->bind_param("iss", $attempts, $locked, $identifier);
            $u->execute();
        } else {
            $ins = $conn->prepare("INSERT INTO otp_rate_limits (phone_or_email, attempts) VALUES (?, 1)");
            $ins->bind_param("s", $identifier);
            $ins->execute();
        }
    }
}

/**
 * Feature 35: Login Session Tracking
 */
function register_current_session($conn, $user_id) {
    if (!isset($_SESSION['session_token'])) {
        $_SESSION['session_token'] = bin2hex(random_bytes(32));
    }
    $token = $_SESSION['session_token'];
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown Browser';
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    $stmt = $conn->prepare("INSERT INTO user_sessions (user_id, session_token, user_agent, ip_address) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE last_active = CURRENT_TIMESTAMP, user_agent = VALUES(user_agent), ip_address = VALUES(ip_address)");
    if ($stmt) {
        $stmt->bind_param("isss", $user_id, $token, $ua, $ip);
        $stmt->execute();
    }
}
?>
