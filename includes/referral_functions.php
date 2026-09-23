<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/wallet_functions.php';

// Auto-initialize Referral System Tables
function init_referral_tables() {
    global $conn;

    // 1. Referral Settings Table
    $conn->query("CREATE TABLE IF NOT EXISTS referral_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        program_enabled TINYINT(1) DEFAULT 1,
        referrer_reward DECIMAL(10,2) DEFAULT 50.00,
        referred_reward DECIMAL(10,2) DEFAULT 25.00,
        min_order_amount DECIMAL(10,2) DEFAULT 199.00,
        expiry_days INT DEFAULT 30,
        max_rewards INT DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    // Insert default settings if empty
    $res = $conn->query("SELECT id FROM referral_settings LIMIT 1");
    if ($res && $res->num_rows === 0) {
        $conn->query("INSERT INTO referral_settings (program_enabled, referrer_reward, referred_reward, min_order_amount, expiry_days) 
                      VALUES (1, 50.00, 25.00, 199.00, 30)");
    }

    // 2. Referral Codes Table
    $conn->query("CREATE TABLE IF NOT EXISTS referral_codes (
        id INT PRIMARY KEY AUTO_INCREMENT,
        customer_id INT UNIQUE NOT NULL,
        referral_code VARCHAR(50) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 3. Customer Referrals Table (Distinct from RMP doctor referrals table)
    $conn->query("CREATE TABLE IF NOT EXISTS customer_referrals (
        id INT PRIMARY KEY AUTO_INCREMENT,
        referrer_customer_id INT NOT NULL,
        referred_customer_id INT UNIQUE NOT NULL,
        referral_code VARCHAR(50) NOT NULL,
        status ENUM('Invited', 'Registered', 'Order Pending', 'Qualified', 'Reward Earned', 'Reward Reversed', 'Expired', 'Rejected') DEFAULT 'Registered',
        qualifying_order_id INT DEFAULT NULL,
        invited_at TIMESTAMP NULL,
        registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        qualified_at TIMESTAMP NULL,
        reward_earned_at TIMESTAMP NULL,
        expires_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (referrer_customer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (referred_customer_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // Migration Check: Ensure required columns exist if customer_referrals was created with an older/different schema
    $conn->query("ALTER TABLE customer_referrals MODIFY COLUMN status VARCHAR(50) DEFAULT 'Registered'");
    $col_res = $conn->query("SHOW COLUMNS FROM customer_referrals");
    if ($col_res) {
        $existing_cols = [];
        while ($col_row = $col_res->fetch_assoc()) {
            $existing_cols[] = strtolower($col_row['Field']);
        }

        if (!empty($existing_cols)) {
            if (!in_array('referrer_customer_id', $existing_cols)) {
                if (in_array('referrer_id', $existing_cols)) {
                    $conn->query("ALTER TABLE customer_referrals CHANGE COLUMN referrer_id referrer_customer_id INT NOT NULL");
                } else {
                    $conn->query("ALTER TABLE customer_referrals ADD COLUMN referrer_customer_id INT NOT NULL AFTER id");
                }
            }

            if (!in_array('referred_customer_id', $existing_cols)) {
                if (in_array('referred_id', $existing_cols)) {
                    $conn->query("ALTER TABLE customer_referrals CHANGE COLUMN referred_id referred_customer_id INT NOT NULL");
                } elseif (in_array('user_id', $existing_cols)) {
                    $conn->query("ALTER TABLE customer_referrals CHANGE COLUMN user_id referred_customer_id INT NOT NULL");
                } else {
                    $conn->query("ALTER TABLE customer_referrals ADD COLUMN referred_customer_id INT NOT NULL AFTER referrer_customer_id");
                }
            }

            if (!in_array('referral_code', $existing_cols)) {
                $conn->query("ALTER TABLE customer_referrals ADD COLUMN referral_code VARCHAR(50) NOT NULL");
            }

            if (!in_array('status', $existing_cols)) {
                $conn->query("ALTER TABLE customer_referrals ADD COLUMN status ENUM('Invited', 'Registered', 'Order Pending', 'Qualified', 'Reward Earned', 'Reward Reversed', 'Expired', 'Rejected') DEFAULT 'Registered'");
            }

            if (!in_array('qualifying_order_id', $existing_cols)) {
                $conn->query("ALTER TABLE customer_referrals ADD COLUMN qualifying_order_id INT DEFAULT NULL");
            }

            if (!in_array('invited_at', $existing_cols)) {
                $conn->query("ALTER TABLE customer_referrals ADD COLUMN invited_at TIMESTAMP NULL");
            }

            if (!in_array('registered_at', $existing_cols)) {
                $conn->query("ALTER TABLE customer_referrals ADD COLUMN registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            }

            if (!in_array('qualified_at', $existing_cols)) {
                $conn->query("ALTER TABLE customer_referrals ADD COLUMN qualified_at TIMESTAMP NULL");
            }

            if (!in_array('reward_earned_at', $existing_cols)) {
                $conn->query("ALTER TABLE customer_referrals ADD COLUMN reward_earned_at TIMESTAMP NULL");
            }

            if (!in_array('expires_at', $existing_cols)) {
                $conn->query("ALTER TABLE customer_referrals ADD COLUMN expires_at TIMESTAMP NULL");
            }

            if (!in_array('created_at', $existing_cols)) {
                $conn->query("ALTER TABLE customer_referrals ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            }

            if (!in_array('updated_at', $existing_cols)) {
                $conn->query("ALTER TABLE customer_referrals ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }
        }
    }

    // 4. Referral Rewards (Wallet Ledger) Table
    $conn->query("CREATE TABLE IF NOT EXISTS referral_rewards (
        id INT PRIMARY KEY AUTO_INCREMENT,
        referral_id INT DEFAULT NULL,
        customer_id INT NOT NULL,
        reward_type ENUM('referrer_reward', 'referred_reward', 'reversal', 'redemption') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('earned', 'reversed', 'redeemed') DEFAULT 'earned',
        related_order_id INT DEFAULT NULL,
        transaction_id VARCHAR(100) DEFAULT NULL,
        description TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
}

// Run Table Initialization conditionally to optimize performance
if (!isset($GLOBALS['referral_tables_inited'])) {
    $GLOBALS['referral_tables_inited'] = true;
    $check_ref_tbl = @$conn->query("SELECT 1 FROM referral_settings LIMIT 1");
    if (!$check_ref_tbl) {
        init_referral_tables();
    }
}

// Fetch active referral settings
function get_referral_settings() {
    global $conn;
    $res = $conn->query("SELECT * FROM referral_settings ORDER BY id ASC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }
    return [
        'program_enabled' => 1,
        'referrer_reward' => 50.00,
        'referred_reward' => 25.00,
        'min_order_amount' => 199.00,
        'expiry_days' => 30,
        'max_rewards' => 0
    ];
}

// Get or auto-generate unique referral code for a customer
function get_or_create_referral_code($customer_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT referral_code FROM referral_codes WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc()['referral_code'];
    }

    // Generate unique code MEDxxxxxx
    do {
        $code = 'MED' . str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        $check = $conn->query("SELECT id FROM referral_codes WHERE referral_code = '$code'");
    } while ($check && $check->num_rows > 0);

    $insert = $conn->prepare("INSERT INTO referral_codes (customer_id, referral_code) VALUES (?, ?)");
    $insert->bind_param("is", $customer_id, $code);
    $insert->execute();

    return $code;
}

// Generate Full Referral Link
function get_referral_link($customer_id) {
    $code = get_or_create_referral_code($customer_id);
    $host = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'];
    $script_dir = dirname($_SERVER['SCRIPT_NAME']);
    $base_url = rtrim($host . $script_dir, '/\\');
    return $base_url . "/register.php?ref=" . $code;
}

// Get customer available referral balance
function get_customer_referral_balance($customer_id) {
    global $conn;
    sync_pending_referrals($customer_id);
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM referral_rewards WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        return max(0.00, (float)($row['total'] ?: 0));
    }
    return 0.00;
}

// Automatically synchronize pending referrals based on order delivery status
function sync_pending_referrals($referrer_id = null) {
    global $conn;

    $settings = get_referral_settings();
    if (!$settings['program_enabled']) return false;

    $min_amt = (float)$settings['min_order_amount'];

    // Select pending referrals (Registered or Order Pending)
    $sql = "SELECT * FROM customer_referrals WHERE status IN ('Registered', 'Order Pending')";
    if ($referrer_id !== null) {
        $sql .= " AND referrer_customer_id = " . (int)$referrer_id;
    }

    $res = $conn->query($sql);
    if (!$res || $res->num_rows === 0) return true;

    while ($referral = $res->fetch_assoc()) {
        $ref_id = (int)$referral['id'];
        $patient_id = (int)$referral['referred_customer_id'];
        $referrer_customer_id = (int)$referral['referrer_customer_id'];

        // Check if referral has expired
        if (!empty($referral['expires_at']) && strtotime($referral['expires_at']) < time()) {
            $conn->query("UPDATE customer_referrals SET status = 'Expired' WHERE id = $ref_id");
            continue;
        }

        // Find qualifying order
        $qualifying_order_id = !empty($referral['qualifying_order_id']) ? (int)$referral['qualifying_order_id'] : 0;

        if ($qualifying_order_id > 0) {
            $ord_stmt = $conn->query("SELECT id, total_amount, status, payment_status FROM orders WHERE id = $qualifying_order_id");
            $ord = $ord_stmt ? $ord_stmt->fetch_assoc() : null;
        } else {
            // Find first order placed by referred customer
            $ord_stmt = $conn->query("SELECT id, total_amount, status, payment_status FROM orders WHERE patient_id = $patient_id AND status NOT IN ('cancelled', 'Cancelled', 'rejected') ORDER BY id ASC LIMIT 1");
            $ord = $ord_stmt ? $ord_stmt->fetch_assoc() : null;
        }

        if (!$ord) {
            continue;
        }

        $order_id = (int)$ord['id'];
        $total_amount = (float)$ord['total_amount'];
        $order_status = strtolower(trim($ord['status']));

        // Update qualifying_order_id and status to Order Pending if currently Registered
        if ($referral['status'] === 'Registered') {
            $conn->query("UPDATE customer_referrals SET status = 'Order Pending', qualifying_order_id = $order_id WHERE id = $ref_id");
        }

        // Qualification check: Order status is 'delivered' (case-insensitive) AND total_amount >= min_order_amount
        if ($order_status === 'delivered' && $total_amount >= $min_amt) {
            // Idempotency Check: Prevent duplicate reward grant
            $reward_check = $conn->query("SELECT id FROM referral_rewards WHERE referral_id = $ref_id AND reward_type = 'referrer_reward'");
            if ($reward_check && $reward_check->num_rows > 0) {
                // Already rewarded, ensure referral status is 'Reward Earned'
                $conn->query("UPDATE customer_referrals SET status = 'Reward Earned' WHERE id = $ref_id");
                continue;
            }

            $referrer_reward = (float)$settings['referrer_reward'];
            $referred_reward = (float)$settings['referred_reward'];

            // Grant Referrer Reward & Post to Wallet Ledger
            $tx1 = 'REF_RWD_' . time() . '_' . rand(1000, 9999);
            $conn->query("INSERT INTO referral_rewards (referral_id, customer_id, reward_type, amount, status, related_order_id, transaction_id, description) 
                          VALUES ($ref_id, $referrer_customer_id, 'referrer_reward', $referrer_reward, 'earned', $order_id, '$tx1', 'Referral reward for successful invite')");
            add_wallet_transaction($referrer_customer_id, 'referral_reward', 'credit', $referrer_reward, "Referral reward for successful invite", $order_id, null, $ref_id);

            // Notification for Referrer Customer
            if (function_exists('create_notification')) {
                create_notification($referrer_customer_id, "🎉 Referral Reward Earned!", "Your referred friend's order has been delivered! ₹" . number_format($referrer_reward, 2) . " has been credited to your wallet.", 'success', 'referral', $ref_id);
            }

            // Grant Referred Customer Reward & Post to Wallet Ledger (if applicable)
            if ($referred_reward > 0) {
                $reward_check2 = $conn->query("SELECT id FROM referral_rewards WHERE referral_id = $ref_id AND reward_type = 'referred_reward'");
                if (!$reward_check2 || $reward_check2->num_rows === 0) {
                    $tx2 = 'REF_RWD_' . time() . '_' . rand(1000, 9999);
                    $conn->query("INSERT INTO referral_rewards (referral_id, customer_id, reward_type, amount, status, related_order_id, transaction_id, description) 
                                  VALUES ($ref_id, $patient_id, 'referred_reward', $referred_reward, 'earned', $order_id, '$tx2', 'Welcome referral bonus on first order')");
                    add_wallet_transaction($patient_id, 'referral_reward', 'credit', $referred_reward, "Welcome referral bonus on first order", $order_id, null, $ref_id);

                    if (function_exists('create_notification')) {
                        create_notification($patient_id, "🎉 Welcome Referral Bonus!", "Your order has been delivered! ₹" . number_format($referred_reward, 2) . " welcome bonus has been credited to your wallet.", 'success', 'referral', $ref_id);
                    }
                }
            }

            // Update referral record status to 'Reward Earned'
            $conn->query("UPDATE customer_referrals SET status = 'Reward Earned', qualified_at = NOW(), reward_earned_at = NOW() WHERE id = $ref_id");
        }
    }

    return true;
}

// Evaluate and process Referral Rewards on Order Confirmation
function process_referral_order_qualification($order_id, $patient_id, $total_amount, $is_confirmed = false) {
    global $conn;

    $settings = get_referral_settings();
    if (!$settings['program_enabled']) return false;

    // Find active referral for this patient
    $stmt = $conn->prepare("SELECT * FROM customer_referrals WHERE referred_customer_id = ? AND status IN ('Registered', 'Order Pending')");
    $stmt->bind_param("i", $patient_id);
    $stmt->execute();
    $ref_res = $stmt->get_result();
    if (!$ref_res || $ref_res->num_rows === 0) return false;

    $referral = $ref_res->fetch_assoc();

    // Check expiry
    if (!empty($referral['expires_at']) && strtotime($referral['expires_at']) < time()) {
        $conn->query("UPDATE customer_referrals SET status = 'Expired' WHERE id = " . $referral['id']);
        return false;
    }

    // Check if this is their FIRST order
    $order_check = $conn->prepare("SELECT COUNT(*) as cnt FROM orders WHERE patient_id = ? AND id < ?");
    $order_check->bind_param("ii", $patient_id, $order_id);
    $order_check->execute();
    $prev_orders = $order_check->get_result()->fetch_assoc()['cnt'];

    if ($prev_orders > 0) {
        return false;
    }

    // Update status to Order Pending
    if ($referral['status'] === 'Registered') {
        $conn->query("UPDATE customer_referrals SET status = 'Order Pending', qualifying_order_id = $order_id WHERE id = " . $referral['id']);
    }

    // Sync referral qualification
    sync_pending_referrals($referral['referrer_customer_id']);

    return true;
}

// Process Referral Reward Reversal (on cancellation / refund)
function process_referral_reversal($order_id) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM customer_referrals WHERE qualifying_order_id = ? AND status = 'Reward Earned'");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $referral = $res->fetch_assoc();
        $ref_id = $referral['id'];

        // Get earned rewards
        $r_stmt = $conn->query("SELECT * FROM referral_rewards WHERE referral_id = $ref_id AND status = 'earned'");
        while ($r = $r_stmt->fetch_assoc()) {
            $customer_id = $r['customer_id'];
            $reward_amt = abs($r['amount']);
            $neg_amount = -1 * $reward_amt;
            $tx_rev = 'REV_' . time() . '_' . rand(1000, 9999);
            $conn->query("INSERT INTO referral_rewards (referral_id, customer_id, reward_type, amount, status, related_order_id, transaction_id, description) 
                          VALUES ($ref_id, $customer_id, 'reversal', $neg_amount, 'reversed', $order_id, '$tx_rev', 'Referral reward reversal due to order cancellation/refund')");
            add_wallet_transaction($customer_id, 'referral_reversal', 'debit', $reward_amt, "Referral reward reversal due to order cancellation/refund", $order_id, null, $ref_id);
        }

        $conn->query("UPDATE customer_referrals SET status = 'Reward Reversed' WHERE id = $ref_id");
        return true;
    }
    return false;
}
?>
