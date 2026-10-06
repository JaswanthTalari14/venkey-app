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
$max_retries = 3;
$retry_count = 0;

while ($retry_count < $max_retries) {
    try {
        $conn = @new mysqli($host, $user, $pass, $dbname, $port);
    } catch (Throwable $e) {
        $conn = null;
    }

    if ($conn && !$conn->connect_error) {
        break; // Connected successfully!
    }

    $err_str = ($conn && $conn->connect_error) ? $conn->connect_error : "";
    if ($conn) {
        @$conn->close();
        $conn = null;
    }

    // Auto-retry if max_user_connections resource limit is reached
    if (strpos($err_str, 'max_user_connections') !== false || strpos($err_str, 'Too many connections') !== false) {
        $retry_count++;
        usleep(150000); // Wait 150ms for a connection slot to free up
        continue;
    }

    // Fallback: If database does not exist on localhost, attempt creation
    if (($host === 'localhost' || $host === '127.0.0.1') && (strpos($err_str, 'Unknown database') !== false || strpos($err_str, '1049') !== false)) {
        try {
            $conn = @new mysqli($host, $user, $pass, "", $port);
            if ($conn && !$conn->connect_error) {
                @$conn->query("CREATE DATABASE IF NOT EXISTS `$dbname`");
                @$conn->select_db($dbname);
                break;
            }
        } catch (Throwable $e2) {
            if ($conn) { @$conn->close(); $conn = null; }
        }
    }
    break;
}

if (!$conn || $conn->connect_error) {
    $err_msg = ($conn && $conn->connect_error) ? $conn->connect_error : "Connection failed";
    if (strpos($err_msg, 'max_user_connections') !== false || strpos($err_msg, 'Too many connections') !== false) {
        die("<div style='font-family:sans-serif; text-align:center; padding:3rem; color:#fff; background:#121826; min-height:100vh;'>
            <h2 style='color:#ff4757;'>Server Busy (Database Connection Limit)</h2>
            <p style='color:#94a3b8;'>The database server is currently experiencing high demand. Please refresh the page in a few seconds.</p>
            <button onclick='location.reload()' style='padding:0.75rem 1.5rem; background:#4a90e2; color:#fff; border:none; border-radius:8px; font-weight:bold; cursor:pointer;'>Retry Now</button>
        </div>");
    }
    die("Database Connection Error: " . htmlspecialchars($err_msg) . ". Please check database credentials.");
}

// Auto-close MySQL connection immediately when PHP finishes response to free connection slots
register_shutdown_function(function() use (&$conn) {
    if ($conn && $conn instanceof mysqli) {
        @$conn->close();
    }
});

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
        address_type VARCHAR(20) DEFAULT 'Home',
        is_default TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (patient_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    @$conn->query("ALTER TABLE patient_addresses ADD COLUMN address_type VARCHAR(20) DEFAULT 'Home'");

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

    // Feature Groups 3, 4, 5, 9, 10, 24, 25, 30, 35 Database Structures
    $conn->query("CREATE TABLE IF NOT EXISTS family_members (
        id INT AUTO_INCREMENT PRIMARY KEY,
        primary_user_id INT NOT NULL,
        name VARCHAR(100) NOT NULL,
        relationship VARCHAR(50) NOT NULL,
        dob DATE DEFAULT NULL,
        gender VARCHAR(20) DEFAULT NULL,
        blood_group VARCHAR(10) DEFAULT NULL,
        allergies TEXT DEFAULT NULL,
        emergency_contact VARCHAR(20) DEFAULT NULL,
        medical_notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (primary_user_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS emergency_cards (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL UNIQUE,
        public_fields_json TEXT NOT NULL,
        qr_token VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS health_trends (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        metric_type VARCHAR(50) NOT NULL,
        metric_value DECIMAL(10,2) NOT NULL,
        unit VARCHAR(20) DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        measured_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (patient_id, metric_type)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS medical_documents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        category VARCHAR(50) NOT NULL,
        file_path VARCHAR(255) NOT NULL,
        doctor_id INT DEFAULT NULL,
        consultation_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (patient_id, category)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS secure_shares (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        share_token VARCHAR(100) UNIQUE NOT NULL,
        items_json TEXT NOT NULL,
        expires_at TIMESTAMP NOT NULL,
        is_revoked TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (share_token)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        target_role VARCHAR(20) DEFAULT 'all',
        status VARCHAR(20) DEFAULT 'published',
        start_time TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        end_time TIMESTAMP NULL DEFAULT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (target_role, status)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS system_risk_flags (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT DEFAULT NULL,
        entity_type VARCHAR(50) NOT NULL,
        entity_id INT DEFAULT NULL,
        severity VARCHAR(20) NOT NULL,
        flag_reason TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (status, severity)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS recently_viewed (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        item_type VARCHAR(50) NOT NULL,
        item_id INT NOT NULL,
        title VARCHAR(255) DEFAULT NULL,
        url VARCHAR(255) DEFAULT NULL,
        viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (user_id)
    )");

    $conn->query("CREATE TABLE IF NOT EXISTS doctor_prep_summaries (
        id INT AUTO_INCREMENT PRIMARY KEY,
        patient_id INT NOT NULL,
        appointment_id INT DEFAULT NULL,
        main_concern TEXT NOT NULL,
        duration VARCHAR(100) DEFAULT NULL,
        severity VARCHAR(50) DEFAULT NULL,
        current_medicines TEXT DEFAULT NULL,
        notes TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (patient_id)
    )");

    // Auto-migrate columns for appointments table
    @$conn->query("ALTER TABLE appointments MODIFY COLUMN status VARCHAR(50) DEFAULT 'pending'");
    $app_col_res = @$conn->query("SHOW COLUMNS FROM appointments");
    if ($app_col_res) {
        $app_cols = [];
        while ($acol_row = $app_col_res->fetch_assoc()) {
            $app_cols[] = strtolower($acol_row['Field']);
        }
        if (!in_array('token_no', $app_cols)) {
            @$conn->query("ALTER TABLE appointments ADD COLUMN token_no VARCHAR(20) DEFAULT NULL");
        }
        if (!in_array('chief_complaint', $app_cols)) {
            @$conn->query("ALTER TABLE appointments ADD COLUMN chief_complaint TEXT DEFAULT NULL");
        }
        if (!in_array('clinical_notes', $app_cols)) {
            @$conn->query("ALTER TABLE appointments ADD COLUMN clinical_notes TEXT DEFAULT NULL");
        }
        if (!in_array('diagnosis', $app_cols)) {
            @$conn->query("ALTER TABLE appointments ADD COLUMN diagnosis TEXT DEFAULT NULL");
        }
        if (!in_array('followup_date', $app_cols)) {
            @$conn->query("ALTER TABLE appointments ADD COLUMN followup_date DATE DEFAULT NULL");
        }
    }

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
        if (!in_array('blood_group', $u_cols)) {
            @$conn->query("ALTER TABLE users ADD COLUMN blood_group VARCHAR(10) DEFAULT NULL");
        }
        if (!in_array('allergies', $u_cols)) {
            @$conn->query("ALTER TABLE users ADD COLUMN allergies TEXT DEFAULT NULL");
        }
        if (!in_array('emergency_contact', $u_cols)) {
            @$conn->query("ALTER TABLE users ADD COLUMN emergency_contact VARCHAR(20) DEFAULT NULL");
        }
        if (!in_array('last_login', $u_cols)) {
            @$conn->query("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL DEFAULT NULL");
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

/**
 * Centralized Profile Image Path & URL Resolver
 * Resolves a profile image from a database user array or path string,
 * checks file existence on disk using absolute base directory,
 * and appends cache-busting timestamp URL parameter when valid.
 */
function get_profile_image_url($input) {
    $path = '';

    if (is_array($input)) {
        $possible_fields = ['profile_image', 'image', 'avatar', 'photo'];
        foreach ($possible_fields as $f) {
            if (!empty($input[$f])) {
                $path = $input[$f];
                break;
            }
        }
    } else if (is_string($input)) {
        $path = $input;
    }

    $path = trim($path);
    if (empty($path)) {
        return '';
    }

    // Normalize slashes and strip leading slashes/dots
    $clean_path = str_replace('\\', '/', $path);
    $clean_path = ltrim($clean_path, '/.');
    $clean_path = ltrim($clean_path, '/');

    // Base directory of the web application root
    $base_dir = __DIR__;
    $abs_disk_path = $base_dir . '/' . $clean_path;

    if (file_exists($abs_disk_path) && is_file($abs_disk_path)) {
        $version = filemtime($abs_disk_path);
        return htmlspecialchars($clean_path) . '?v=' . $version;
    }

    // Direct check if path is relative to current working dir
    if (file_exists($clean_path) && is_file($clean_path)) {
        $version = filemtime($clean_path);
        return htmlspecialchars($clean_path) . '?v=' . $version;
    }

    return '';
}
?>
