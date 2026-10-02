<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 86400);
    ini_set('session.cookie_lifetime', 86400);
    
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 86400,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        session_set_cookie_params(86400, '/');
    }
    session_start();
}
@ini_set('zlib.output_compression', 'On');
ob_start();

mysqli_report(MYSQLI_REPORT_OFF);

$host   = getenv('DB_HOST') ?: "localhost";
$user   = getenv('DB_USER') ?: "root";
$pass   = getenv('DB_PASS') ?: "";
$dbname = getenv('DB_NAME') ?: "medicalak";
$port   = getenv('DB_PORT') ? intval(getenv('DB_PORT')) : 3306;

$conn = null;
try {
    $conn = @new mysqli($host, $user, $pass, $dbname, $port);
} catch (Throwable $e) {
    $conn = null;
}

if (!$conn || $conn->connect_error) {
    try {
        $conn = @new mysqli($host, $user, $pass, "", $port);
        if ($conn && !$conn->connect_error) {
            @$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname`");
            @$conn->select_db($dbname);
        }
    } catch (Throwable $e2) {
        $conn = null;
    }
}

if (!$conn || $conn->connect_error) {
    $err_msg = ($conn && $conn->connect_error) ? $conn->connect_error : "Connection refused/failed";
    die("Database Connection Error: " . htmlspecialchars($err_msg) . ". Please check database credentials.");
}

$gemini_api_key = getenv('GEMINI_API_KEY') ?: 'AIzaSyB2jB_N-GL6O0ad_lgtD5xxOlv6h0xcA2Q';

// Payment Gateway Constants
define('RAZORPAY_KEY_ID', getenv('RAZORPAY_KEY_ID') ?: 'rzp_test_TfMdjlTs740X2h');
define('RAZORPAY_KEY_SECRET', getenv('RAZORPAY_KEY_SECRET') ?: 'GgVEpf3udM2Jr3KWU7aOzikB');
define('RAZORPAY_WEBHOOK_SECRET', getenv('RAZORPAY_WEBHOOK_SECRET') ?: 'samplewebhooksecret');

// PhonePe Payment Gateway Constants
define('PHONEPE_MERCHANT_ID', getenv('PHONEPE_MERCHANT_ID') ?: '');
define('PHONEPE_SALT_KEY', getenv('PHONEPE_SALT_KEY') ?: '');
define('PHONEPE_SALT_INDEX', getenv('PHONEPE_SALT_INDEX') ?: '1');
define('PHONEPE_ENV', getenv('PHONEPE_ENV') ?: 'PROD');

// Auto-Ensure Database Query Indexes for High-Speed Navigation
function ensure_database_indexes($conn) {
    if (isset($GLOBALS['db_indexes_checked']) || (isset($_SESSION) && !empty($_SESSION['db_indexes_checked']))) return;
    $GLOBALS['db_indexes_checked'] = true;
    if (isset($_SESSION)) {
        $_SESSION['db_indexes_checked'] = true;
    }

    $conn->query("CREATE TABLE IF NOT EXISTS patient_addresses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address_line TEXT NOT NULL,
        city VARCHAR(50) NOT NULL,
        state VARCHAR(50) NOT NULL,
        pincode VARCHAR(10) NOT NULL,
        is_default TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $add_index_if_missing = function($table, $index_name, $columns) use ($conn) {
        $check = @$conn->query("SHOW INDEX FROM `$table` WHERE Key_name = '$index_name'");
        if ($check && $check->num_rows === 0) {
            @$conn->query("CREATE INDEX `$index_name` ON `$table` ($columns)");
        }
    };

    $add_index_if_missing('users', 'idx_users_role', 'role');
    $add_index_if_missing('appointments', 'idx_app_patient', 'patient_id, status');
    $add_index_if_missing('appointments', 'idx_app_doctor', 'doctor_id, status');
    $add_index_if_missing('orders', 'idx_ord_patient', 'patient_id, status');
    $add_index_if_missing('orders', 'idx_ord_created', 'created_at');
    $add_index_if_missing('order_items', 'idx_oi_order', 'order_id');
    $add_index_if_missing('order_items', 'idx_oi_medicine', 'medicine_id');
    $add_index_if_missing('privacy_consultations', 'idx_pc_patient', 'patient_id');
    $add_index_if_missing('test_bookings', 'idx_tb_patient', 'patient_id');
    $add_index_if_missing('test_bookings', 'idx_tb_rmp', 'rmp_id');
    $add_index_if_missing('referrals', 'idx_ref_rmp', 'rmp_id');
    $add_index_if_missing('referrals', 'idx_ref_doctor', 'doctor_id');
    $add_index_if_missing('customer_referrals', 'idx_cr_referrer', 'referrer_customer_id');
    $add_index_if_missing('customer_referrals', 'idx_cr_referred', 'referred_customer_id');
    $add_index_if_missing('customer_referrals', 'idx_cr_code', 'referral_code');
    $add_index_if_missing('referral_rewards', 'idx_rr_customer', 'customer_id');
    $add_index_if_missing('wallets', 'idx_wal_customer', 'customer_id');
    $add_index_if_missing('wallet_transactions', 'idx_wt_customer', 'customer_id, status');
    $add_index_if_missing('wallet_transactions', 'idx_wt_wallet', 'wallet_id');
    $add_index_if_missing('wallet_topups', 'idx_wtup_customer', 'customer_id, status');
    $add_index_if_missing('user_notifications', 'idx_un_user_read', 'user_id, is_read');
    $add_index_if_missing('medicines', 'idx_med_name', 'name');
    $add_index_if_missing('patient_addresses', 'idx_pa_patient', 'patient_id');

    // Auto-migrate columns for orders table
    @$conn->query("ALTER TABLE orders MODIFY COLUMN status VARCHAR(50) DEFAULT 'pending'");
    $col_res = @$conn->query("SHOW COLUMNS FROM orders");
    if ($col_res) {
        $existing_cols = [];
        while ($col_row = $col_res->fetch_assoc()) {
            $existing_cols[] = strtolower($col_row['Field']);
        }
        if (!empty($existing_cols)) {
            if (!in_array('cancellation_reason', $existing_cols)) {
                @$conn->query("ALTER TABLE orders ADD COLUMN cancellation_reason TEXT DEFAULT NULL AFTER status");
            }
            if (!in_array('estimated_delivery_time', $existing_cols)) {
                @$conn->query("ALTER TABLE orders ADD COLUMN estimated_delivery_time VARCHAR(100) DEFAULT NULL AFTER cancellation_reason");
            }
            if (!in_array('is_deleted', $existing_cols)) {
                @$conn->query("ALTER TABLE orders ADD COLUMN is_deleted TINYINT(1) DEFAULT 0 AFTER estimated_delivery_time");
            }
        }
    }

    // Auto-Migrate Tables for 39 Features
    $conn->query("CREATE TABLE IF NOT EXISTS refill_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        medicine_name VARCHAR(255) NOT NULL,
        dosage VARCHAR(100) NOT NULL,
        frequency VARCHAR(100) NOT NULL,
        reminder_time TIME NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (patient_id, status)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS prescriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        appointment_id INT DEFAULT NULL,
        consultation_date DATE NOT NULL,
        notes TEXT DEFAULT NULL,
        file_path VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (patient_id),
        INDEX (doctor_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS prescription_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        prescription_id INT NOT NULL,
        medicine_name VARCHAR(255) NOT NULL,
        dosage VARCHAR(100) NOT NULL,
        frequency VARCHAR(100) NOT NULL,
        duration VARCHAR(100) NOT NULL,
        instructions TEXT DEFAULT NULL,
        INDEX (prescription_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS favorite_doctors (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        doctor_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_fav (patient_id, doctor_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS consultation_notes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        patient_id INT NOT NULL,
        appointment_id INT DEFAULT NULL,
        notes TEXT NOT NULL,
        is_shared_with_patient TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (doctor_id),
        INDEX (patient_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS doctor_availability (
        id INT AUTO_INCREMENT PRIMARY KEY,
        doctor_id INT NOT NULL,
        day_of_week VARCHAR(20) NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        is_available TINYINT(1) DEFAULT 1,
        INDEX (doctor_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS rmp_followups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rmp_id INT NOT NULL,
        patient_name VARCHAR(150) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        followup_date DATE NOT NULL,
        reason TEXT DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (rmp_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS verification_history (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        admin_id INT NOT NULL,
        previous_status TINYINT(1) DEFAULT 0,
        new_status TINYINT(1) DEFAULT 1,
        reason TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS refund_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        order_id INT DEFAULT NULL,
        transaction_id VARCHAR(100) DEFAULT NULL,
        amount DECIMAL(10,2) NOT NULL,
        reason TEXT NOT NULL,
        status VARCHAR(30) DEFAULT 'Requested',
        admin_note TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (patient_id),
        INDEX (status)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS admin_activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action VARCHAR(100) NOT NULL,
        entity_type VARCHAR(50) DEFAULT NULL,
        entity_id INT DEFAULT NULL,
        details TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (admin_id),
        INDEX (action)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS notification_preferences (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        order_notif TINYINT(1) DEFAULT 1,
        appointment_notif TINYINT(1) DEFAULT 1,
        referral_notif TINYINT(1) DEFAULT 1,
        payment_notif TINYINT(1) DEFAULT 1,
        system_notif TINYINT(1) DEFAULT 1
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS user_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        session_token VARCHAR(128) NOT NULL UNIQUE,
        user_agent TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS otp_rate_limits (
        id INT AUTO_INCREMENT PRIMARY KEY,
        phone_or_email VARCHAR(150) NOT NULL UNIQUE,
        attempts INT DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        locked_until TIMESTAMP NULL DEFAULT NULL
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip_address VARCHAR(45) NOT NULL,
        email_or_phone VARCHAR(150) NOT NULL,
        attempts INT DEFAULT 1,
        last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        locked_until TIMESTAMP NULL DEFAULT NULL,
        INDEX (ip_address, email_or_phone)
    )");

    // Auto-migrate columns for users table
    $ucol_res = @$conn->query("SHOW COLUMNS FROM users");
    if ($ucol_res) {
        $u_cols = [];
        while ($ucol_row = $ucol_res->fetch_assoc()) {
            $u_cols[] = strtolower($ucol_row['Field']);
        }
        if (!in_array('profile_image', $u_cols) && !in_array('image', $u_cols) && !in_array('avatar', $u_cols)) {
            @$conn->query("ALTER TABLE users ADD COLUMN profile_image VARCHAR(255) DEFAULT NULL");
        }
        if (!in_array('is_verified', $u_cols)) {
            @$conn->query("ALTER TABLE users ADD COLUMN is_verified TINYINT(1) DEFAULT 0");
        }
        if (!in_array('verification_document', $u_cols) && !in_array('license_document', $u_cols) && !in_array('document_path', $u_cols)) {
            @$conn->query("ALTER TABLE users ADD COLUMN verification_document VARCHAR(255) DEFAULT NULL");
        }
        if (!in_array('qualification', $u_cols)) {
            @$conn->query("ALTER TABLE users ADD COLUMN qualification VARCHAR(100) DEFAULT NULL");
        }
        if (!in_array('experience', $u_cols)) {
            @$conn->query("ALTER TABLE users ADD COLUMN experience VARCHAR(50) DEFAULT NULL");
        }
        if (!in_array('is_online_available', $u_cols)) {
            @$conn->query("ALTER TABLE users ADD COLUMN is_online_available TINYINT(1) DEFAULT 1");
        }
    }

    // Auto-migrate columns for user_notifications table
    $un_col_res = @$conn->query("SHOW COLUMNS FROM user_notifications");
    if ($un_col_res) {
        $un_cols = [];
        while ($un_col_row = $un_col_res->fetch_assoc()) {
            $un_cols[] = strtolower($un_col_row['Field']);
        }
        if (!in_array('is_pinned', $un_cols)) {
            @$conn->query("ALTER TABLE user_notifications ADD COLUMN is_pinned TINYINT(1) DEFAULT 0");
        }
    }

    // Auto-migrate columns for medicines table
    $m_col_res = @$conn->query("SHOW COLUMNS FROM medicines");
    if ($m_col_res) {
        $m_cols = [];
        while ($m_col_row = $m_col_res->fetch_assoc()) {
            $m_cols[] = strtolower($m_col_row['Field']);
        }
        if (!in_array('generic_name', $m_cols)) {
            @$conn->query("ALTER TABLE medicines ADD COLUMN generic_name VARCHAR(255) DEFAULT NULL");
        }
        if (!in_array('brand', $m_cols)) {
            @$conn->query("ALTER TABLE medicines ADD COLUMN brand VARCHAR(255) DEFAULT NULL");
        }
        if (!in_array('category', $m_cols)) {
            @$conn->query("ALTER TABLE medicines ADD COLUMN category VARCHAR(100) DEFAULT 'General'");
        }
    }
}

ensure_database_indexes($conn);
?>
