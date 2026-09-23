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

// Run Table Initialization
init_referral_tables();

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
    $stmt = $conn->prepare("SELECT SUM(amount) as total FROM referral_rewards WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        return max(0.00, (float)($row['total'] ?: 0));
    }
    return 0.00;
}

// Process Registration Referral Linkage
function register_referral_claim($new_customer_id, $referral_code_input) {
    global $conn;
    $code = trim(strtoupper($referral_code_input));
    if (empty($code)) return false;

    $settings = get_referral_settings();
    if (!$settings['program_enabled']) return false;

    // Find referrer
    $stmt = $conn->prepare("SELECT customer_id FROM referral_codes WHERE referral_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $res = $stmt->get_result();
    if (!$res || $res->num_rows === 0) return false;

    $referrer_id = (int)$res->fetch_assoc()['customer_id'];

    // Self-Referral Prevention
    if ($referrer_id === (int)$new_customer_id) return false;

    // Check referrer & referred identity match
    $check_stmt = $conn->prepare("SELECT email FROM users WHERE id IN (?, ?)");
    $check_stmt->bind_param("ii", $referrer_id, $new_customer_id);
    $check_stmt->execute();
    $u_res = $check_stmt->get_result();
    $users = [];
    while ($u = $u_res->fetch_assoc()) {
        $users[] = $u;
    }
    if (count($users) === 2) {
        if (strtolower($users[0]['email']) === strtolower($users[1]['email'])) {
            return false; // Prevent duplicate email self referral
        }
    }

    // Check if new customer was already referred
    $dup_check = $conn->prepare("SELECT id FROM customer_referrals WHERE referred_customer_id = ?");
    $dup_check->bind_param("i", $new_customer_id);
    $dup_check->execute();
    if ($dup_check->get_result()->num_rows > 0) return false;

    $expiry_days = (int)$settings['expiry_days'];
    $ins = $conn->prepare("INSERT INTO customer_referrals (referrer_customer_id, referred_customer_id, referral_code, status, registered_at, expires_at) 
                           VALUES (?, ?, ?, 'Registered', NOW(), DATE_ADD(NOW(), INTERVAL ? DAY))");
    $ins->bind_param("iisi", $referrer_id, $new_customer_id, $code, $expiry_days);
    return $ins->execute();
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
        // Not first order
        return false;
    }

    // Update status to Order Pending
    if ($referral['status'] === 'Registered') {
        $conn->query("UPDATE customer_referrals SET status = 'Order Pending', qualifying_order_id = $order_id WHERE id = " . $referral['id']);
    }

    // Check if order qualifies
    $min_amt = (float)$settings['min_order_amount'];
    if ($total_amount >= $min_amt && $is_confirmed) {
        // QUALIFIED & REWARD EARNED!
        $ref_id = $referral['id'];
        $referrer_id = $referral['referrer_customer_id'];
        $referrer_reward = (float)$settings['referrer_reward'];
        $referred_reward = (float)$settings['referred_reward'];

        // Prevent duplicate reward grant
        $reward_check = $conn->query("SELECT id FROM referral_rewards WHERE referral_id = $ref_id AND reward_type = 'referrer_reward'");
        if ($reward_check && $reward_check->num_rows > 0) {
            return false;
        }

        // Grant Referrer Reward & Post to Wallet Ledger
        $tx1 = 'REF_RWD_' . time() . '_' . rand(1000, 9999);
        $conn->query("INSERT INTO referral_rewards (referral_id, customer_id, reward_type, amount, status, related_order_id, transaction_id, description) 
                      VALUES ($ref_id, $referrer_id, 'referrer_reward', $referrer_reward, 'earned', $order_id, '$tx1', 'Referral reward for successful invite')");
        add_wallet_transaction($referrer_id, 'referral_reward', 'credit', $referrer_reward, "Referral reward for successful invite", $order_id, null, $ref_id);

        // Grant Referred Customer Reward & Post to Wallet Ledger
        if ($referred_reward > 0) {
            $tx2 = 'REF_RWD_' . time() . '_' . rand(1000, 9999);
            $conn->query("INSERT INTO referral_rewards (referral_id, customer_id, reward_type, amount, status, related_order_id, transaction_id, description) 
                          VALUES ($ref_id, $patient_id, 'referred_reward', $referred_reward, 'earned', $order_id, '$tx2', 'Welcome referral bonus on first order')");
            add_wallet_transaction($patient_id, 'referral_reward', 'credit', $referred_reward, "Welcome referral bonus on first order", $order_id, null, $ref_id);
        }

        // Update referral record
        $conn->query("UPDATE customer_referrals SET status = 'Reward Earned', qualified_at = NOW(), reward_earned_at = NOW() WHERE id = $ref_id");
        return true;
    }

    return false;
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
