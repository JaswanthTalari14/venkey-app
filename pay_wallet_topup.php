<?php
require_once 'config.php';
require_once 'includes/wallet_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = (int)$_SESSION['user_id'];
$topup_id = isset($_REQUEST['topup_id']) ? trim($_REQUEST['topup_id']) : '';

if (empty($topup_id)) {
    header("Location: my_wallet.php");
    exit;
}

// Fetch top-up record
$stmt = $conn->prepare("SELECT * FROM wallet_topups WHERE topup_id = ? AND customer_id = ?");
$stmt->bind_param("ss", $topup_id, $patient_id);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    header("Location: my_wallet.php");
    exit;
}

$topup = $res->fetch_assoc();
$amount = floatval($topup['amount']);

// Handle Return / Completion from Payment Gateway
if (isset($_POST['action']) && $_POST['action'] === 'confirm_payment') {
    $paid_amount = isset($_POST['paid_amount']) ? floatval($_POST['paid_amount']) : $amount;
    $payment_id = 'PAY_' . strtoupper(substr(md5(uniqid()), 0, 10));
    $gateway_ref = 'GW_REF_' . time() . '_' . rand(1000, 9999);

    // Call server-side verification helper to record payment received
    // IMPORTANT: This moves status to 'pending_approval' or 'amount_mismatch' and does NOT credit available_balance
    $res = mark_topup_payment_received($topup_id, $paid_amount, $gateway_ref, $payment_id);

    if ($res['success']) {
        $_SESSION['wallet_msg'] = "🎉 Payment of ₹" . number_format($paid_amount, 2) . " received successfully! Your top-up is waiting for mandatory Admin verification and approval.";
        $_SESSION['wallet_msg_type'] = "info";
    } else {
        $_SESSION['wallet_msg'] = "Payment verification error: " . $res['message'];
        $_SESSION['wallet_msg_type'] = "danger";
    }

    header("Location: my_wallet.php");
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'fail_payment') {
    mark_topup_payment_failed($topup_id, 'Payment declined or cancelled by user');
    $_SESSION['wallet_msg'] = "Payment failed or was cancelled. You can retry anytime.";
    $_SESSION['wallet_msg_type'] = "danger";
    header("Location: my_wallet.php");
    exit;
}

include 'includes/header.php';
?>

<div class="dashboard-layout" style="display: flex; justify-content: center; align-items: center; min-height: 70vh; padding: 2rem;">
    <div class="glass-panel" style="max-width: 500px; width: 100%; padding: 2.5rem; text-align: center; border-radius: 20px;">
        <div style="font-size: 3rem; color: var(--primary-color); margin-bottom: 1rem;">
            <i class="fas fa-shield-alt"></i>
        </div>
        
        <h2>Secure Payment Gateway</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">
            Completing payment for Top-Up Request <strong style="color: var(--secondary-color);"><?php echo htmlspecialchars($topup_id); ?></strong>
        </p>

        <div style="background: rgba(255,255,255,0.05); padding: 1.5rem; border-radius: 12px; margin-bottom: 2rem; border: 1px solid var(--glass-border);">
            <span style="font-size: 0.9rem; color: var(--text-secondary); text-transform: uppercase;">Requested Amount</span>
            <div style="font-size: 2.5rem; font-weight: bold; color: #2ed573; margin-top: 0.5rem;">
                ₹<?php echo number_format($amount, 2); ?>
            </div>
            <small style="color: var(--text-secondary);">Note: Payment success alone will send request for mandatory Admin approval before wallet is credited.</small>
        </div>

        <form method="POST" action="pay_wallet_topup.php" style="margin-bottom: 1rem;">
            <input type="hidden" name="topup_id" value="<?php echo htmlspecialchars($topup_id); ?>">
            <input type="hidden" name="action" value="confirm_payment">
            <input type="hidden" name="paid_amount" value="<?php echo $amount; ?>">

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.1rem; font-weight: bold; margin-bottom: 1rem;">
                <i class="fas fa-lock"></i> Pay ₹<?php echo number_format($amount, 2); ?> via Gateway
            </button>
        </form>

        <form method="POST" action="pay_wallet_topup.php">
            <input type="hidden" name="topup_id" value="<?php echo htmlspecialchars($topup_id); ?>">
            <input type="hidden" name="action" value="fail_payment">

            <button type="submit" class="btn btn-outline" style="width: 100%; padding: 0.75rem; color: #ff4757; border-color: #ff4757;">
                <i class="fas fa-times-circle"></i> Cancel Payment
            </button>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
