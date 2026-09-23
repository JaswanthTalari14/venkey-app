<?php
require_once __DIR__ . '/../config.php';

// Auto-initialize Customer Wallet System Tables
function init_wallet_tables() {
    global $conn;

    // 1. Wallet Settings Table
    $conn->query("CREATE TABLE IF NOT EXISTS wallet_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        min_topup DECIMAL(10,2) DEFAULT 50.00,
        max_topup DECIMAL(10,2) DEFAULT 10000.00,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");

    $res = $conn->query("SELECT id FROM wallet_settings LIMIT 1");
    if ($res && $res->num_rows === 0) {
        $conn->query("INSERT INTO wallet_settings (min_topup, max_topup) VALUES (50.00, 10000.00)");
    }

    // 2. Wallets Table
    $conn->query("CREATE TABLE IF NOT EXISTS wallets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        customer_id INT UNIQUE NOT NULL,
        available_balance DECIMAL(10,2) DEFAULT 0.00,
        pending_balance DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('active', 'frozen') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 3. Wallet Transactions Table (Ledger)
    $conn->query("CREATE TABLE IF NOT EXISTS wallet_transactions (
        id INT PRIMARY KEY AUTO_INCREMENT,
        wallet_id INT NOT NULL,
        customer_id INT NOT NULL,
        transaction_id VARCHAR(100) UNIQUE NOT NULL,
        transaction_type ENUM('topup', 'topup_pending', 'topup_approved', 'topup_rejected', 'payment', 'refund', 'referral_reward', 'referral_reversal', 'admin_credit', 'admin_debit', 'correction') NOT NULL,
        direction ENUM('credit', 'debit') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        previous_balance DECIMAL(10,2) NOT NULL,
        new_balance DECIMAL(10,2) NOT NULL,
        status ENUM('completed', 'pending', 'failed', 'reversed') DEFAULT 'completed',
        order_id INT DEFAULT NULL,
        payment_id VARCHAR(100) DEFAULT NULL,
        referral_id INT DEFAULT NULL,
        reason TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    // 4. Wallet Topups Table
    $conn->query("CREATE TABLE IF NOT EXISTS wallet_topups (
        id INT PRIMARY KEY AUTO_INCREMENT,
        wallet_id INT NOT NULL,
        customer_id INT NOT NULL,
        topup_id VARCHAR(100) UNIQUE NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) DEFAULT 'online',
        payment_id VARCHAR(100) DEFAULT NULL,
        status ENUM('pending', 'approved', 'rejected', 'failed', 'completed') DEFAULT 'pending',
        gateway_reference VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (wallet_id) REFERENCES wallets(id) ON DELETE CASCADE,
        FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE
    )");
}

// Run Table Initialization
init_wallet_tables();

// Fetch Wallet Settings
function get_wallet_settings() {
    global $conn;
    $res = $conn->query("SELECT * FROM wallet_settings ORDER BY id ASC LIMIT 1");
    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }
    return ['min_topup' => 50.00, 'max_topup' => 10000.00];
}

// Update Wallet Settings
function update_wallet_settings($min_topup, $max_topup) {
    global $conn;
    $stmt = $conn->prepare("UPDATE wallet_settings SET min_topup = ?, max_topup = ? WHERE id = 1");
    $stmt->bind_param("dd", $min_topup, $max_topup);
    return $stmt->execute();
}

// Get or Create Wallet for Customer
function get_or_create_wallet($customer_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT * FROM wallets WHERE customer_id = ?");
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        return $res->fetch_assoc();
    }

    $insert = $conn->prepare("INSERT INTO wallets (customer_id, available_balance, pending_balance, status) VALUES (?, 0.00, 0.00, 'active')");
    $insert->bind_param("i", $customer_id);
    $insert->execute();

    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get Current Wallet Available Balance
function get_wallet_balance($customer_id) {
    $wallet = get_or_create_wallet($customer_id);
    return ($wallet['status'] === 'active') ? floatval($wallet['available_balance']) : 0.00;
}

// Generate Unique Transaction ID
function generate_wallet_tx_id($prefix = 'WAL') {
    return $prefix . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
}

// Post a Wallet Transaction & Atomically Update Balance
function add_wallet_transaction($customer_id, $type, $direction, $amount, $reason = '', $order_id = null, $payment_id = null, $referral_id = null, $status = 'completed') {
    global $conn;
    $amount = floatval($amount);
    if ($amount <= 0) return false;

    // Start Transaction for Locking
    $conn->begin_transaction();

    try {
        // Lock Wallet Record FOR UPDATE
        $stmt = $conn->prepare("SELECT * FROM wallets WHERE customer_id = ? FOR UPDATE");
        $stmt->bind_param("i", $customer_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $wallet = $res->fetch_assoc();

        if (!$wallet) {
            // Auto-create if not existing
            $conn->query("INSERT INTO wallets (customer_id, available_balance, pending_balance, status) VALUES ($customer_id, 0.00, 0.00, 'active')");
            $stmt->execute();
            $wallet = $stmt->get_result()->fetch_assoc();
        }

        if ($wallet['status'] === 'frozen' && $type !== 'correction' && $type !== 'admin_debit') {
            $conn->rollback();
            return ['success' => false, 'message' => 'Wallet is currently frozen.'];
        }

        $prev_balance = floatval($wallet['available_balance']);
        $new_balance = $prev_balance;

        if ($direction === 'credit') {
            $new_balance += $amount;
        } else if ($direction === 'debit') {
            if ($prev_balance < $amount) {
                $conn->rollback();
                return ['success' => false, 'message' => 'Insufficient wallet balance.'];
            }
            $new_balance -= $amount;
        }

        // Generate Transaction ID prefix based on type
        $prefix = 'WALTX';
        if ($type === 'topup' || $type === 'topup_approved') $prefix = 'WALTOP';
        else if ($type === 'payment') $prefix = 'WALPAY';
        else if ($type === 'refund') $prefix = 'WALREF';
        else if ($type === 'referral_reward') $prefix = 'REF';
        else if ($type === 'admin_credit' || $type === 'admin_debit') $prefix = 'WALADM';

        $tx_id = generate_wallet_tx_id($prefix);
        $wallet_id = $wallet['id'];

        // Update Wallet Balance
        $upd = $conn->prepare("UPDATE wallets SET available_balance = ? WHERE id = ?");
        $upd->bind_param("di", $new_balance, $wallet_id);
        $upd->execute();

        // Insert Transaction Record
        $tx_stmt = $conn->prepare("
            INSERT INTO wallet_transactions 
            (wallet_id, customer_id, transaction_id, transaction_type, direction, amount, previous_balance, new_balance, status, order_id, payment_id, referral_id, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $tx_stmt->bind_param(
            "iisssdddsiiss",
            $wallet_id,
            $customer_id,
            $tx_id,
            $type,
            $direction,
            $amount,
            $prev_balance,
            $new_balance,
            $status,
            $order_id,
            $payment_id,
            $referral_id,
            $reason
        );
        $tx_stmt->execute();

        $conn->commit();
        return [
            'success' => true,
            'tx_id' => $tx_id,
            'previous_balance' => $prev_balance,
            'new_balance' => $new_balance,
            'amount' => $amount
        ];

    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Request Wallet Topup
function process_wallet_topup_request($customer_id, $amount, $payment_method = 'online') {
    global $conn;
    $amount = floatval($amount);
    $settings = get_wallet_settings();

    if ($amount < $settings['min_topup'] || $amount > $settings['max_topup']) {
        return ['success' => false, 'message' => "Top-up amount must be between ₹{$settings['min_topup']} and ₹{$settings['max_topup']}."];
    }

    $wallet = get_or_create_wallet($customer_id);
    if ($wallet['status'] === 'frozen') {
        return ['success' => false, 'message' => 'Wallet is frozen. Cannot add funds.'];
    }

    $topup_id = generate_wallet_tx_id('WALTOP');
    $wallet_id = $wallet['id'];

    $stmt = $conn->prepare("INSERT INTO wallet_topups (wallet_id, customer_id, topup_id, amount, payment_method, status) VALUES (?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param("iisds", $wallet_id, $customer_id, $topup_id, $amount, $payment_method);
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'topup_id' => $topup_id,
            'amount' => $amount
        ];
    }

    return ['success' => false, 'message' => 'Failed to initiate wallet top-up request.'];
}

// Complete & Credit Wallet Topup (Server Verification)
function verify_and_complete_topup($topup_id, $gateway_reference = 'verified_payment') {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM wallet_topups WHERE topup_id = ?");
    $stmt->bind_param("s", $topup_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        return ['success' => false, 'message' => 'Top-up transaction not found.'];
    }

    $topup = $res->fetch_assoc();
    if ($topup['status'] === 'completed' || $topup['status'] === 'approved') {
        return ['success' => true, 'message' => 'Top-up already credited.'];
    }

    $customer_id = $topup['customer_id'];
    $amount = floatval($topup['amount']);

    // Perform atomic credit
    $res_tx = add_wallet_transaction(
        $customer_id,
        'topup_approved',
        'credit',
        $amount,
        "Wallet Top-Up via " . ucfirst($topup['payment_method']) . " ({$gateway_reference})",
        null,
        $topup_id
    );

    if ($res_tx['success']) {
        $upd = $conn->prepare("UPDATE wallet_topups SET status = 'completed', gateway_reference = ? WHERE topup_id = ?");
        $upd->bind_param("ss", $gateway_reference, $topup_id);
        $upd->execute();

        return ['success' => true, 'message' => "₹{$amount} credited to wallet successfully.", 'new_balance' => $res_tx['new_balance']];
    }

    return $res_tx;
}

// Reject Topup Request
function reject_topup($topup_id, $reason = 'Payment Verification Failed') {
    global $conn;
    $upd = $conn->prepare("UPDATE wallet_topups SET status = 'rejected', gateway_reference = ? WHERE topup_id = ? AND status = 'pending'");
    $upd->bind_param("ss", $reason, $topup_id);
    return $upd->execute();
}

// Process Wallet Payment for Order Checkout
function process_wallet_payment($customer_id, $order_id, $amount) {
    return add_wallet_transaction(
        $customer_id,
        'payment',
        'debit',
        $amount,
        "Payment for Medicine Order #{$order_id}",
        $order_id
    );
}

// Process Wallet Refund for Cancelled / Returned Order
function process_wallet_refund($customer_id, $order_id, $amount, $reason = 'Order Refund') {
    global $conn;
    $amount = floatval($amount);
    if ($amount <= 0) return ['success' => false, 'message' => 'Invalid refund amount.'];

    // Protection: Calculate total already refunded for this order
    $stmt = $conn->prepare("SELECT SUM(amount) as total_refunded FROM wallet_transactions WHERE order_id = ? AND transaction_type = 'refund' AND status = 'completed'");
    $stmt->bind_param("i", $order_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $already_refunded = 0;
    if ($res && $res->num_rows > 0) {
        $already_refunded = floatval($res->fetch_assoc()['total_refunded']);
    }

    // Find original wallet payment for this order
    $stmt2 = $conn->prepare("SELECT SUM(amount) as total_paid FROM wallet_transactions WHERE order_id = ? AND transaction_type = 'payment' AND status = 'completed'");
    $stmt2->bind_param("i", $order_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $total_paid = 0;
    if ($res2 && $res2->num_rows > 0) {
        $total_paid = floatval($res2->fetch_assoc()['total_paid']);
    }

    if ($already_refunded + $amount > $total_paid && $total_paid > 0) {
        return ['success' => false, 'message' => 'Refund amount exceeds maximum refundable wallet payment.'];
    }

    return add_wallet_transaction(
        $customer_id,
        'refund',
        'credit',
        $amount,
        $reason,
        $order_id
    );
}

// Admin Adjust Wallet (Credit / Debit)
function admin_adjust_wallet($admin_id, $customer_id, $adjustment_type, $amount, $reason) {
    $type = ($adjustment_type === 'credit') ? 'admin_credit' : 'admin_debit';
    $direction = ($adjustment_type === 'credit') ? 'credit' : 'debit';
    $full_reason = "Admin (ID: {$admin_id}) Adjustment: " . $reason;

    return add_wallet_transaction(
        $customer_id,
        $type,
        $direction,
        $amount,
        $full_reason
    );
}

// Toggle Wallet Freeze / Unfreeze
function toggle_wallet_freeze($admin_id, $customer_id, $new_status) {
    global $conn;
    if (!in_array($new_status, ['active', 'frozen'])) return false;

    $stmt = $conn->prepare("UPDATE wallets SET status = ? WHERE customer_id = ?");
    $stmt->bind_param("si", $new_status, $customer_id);
    return $stmt->execute();
}
