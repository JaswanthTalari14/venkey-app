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
ob_start();

$host   = getenv('DB_HOST') ?: "localhost";
$user   = getenv('DB_USER') ?: "root";
$pass   = getenv('DB_PASS') ?: "";
$dbname = getenv('DB_NAME') ?: "medicalak";
$port   = getenv('DB_PORT') ? intval(getenv('DB_PORT')) : 3306;

$conn = @new mysqli($host, $user, $pass, $dbname, $port);

if ($conn->connect_error) {
    $conn = new mysqli($host, $user, $pass, "", $port);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $conn->query("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $conn->select_db($dbname);
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
    if (isset($GLOBALS['db_indexes_checked'])) return;
    $GLOBALS['db_indexes_checked'] = true;

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
}

ensure_database_indexes($conn);
?>
