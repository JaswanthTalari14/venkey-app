<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$tx_id = isset($_GET['tx_id']) ? trim($_GET['tx_id']) : '';

if (empty($tx_id)) {
    die("Invalid Transaction ID.");
}

// Fetch Transaction Details (Server-side authorization check)
$stmt = $conn->prepare("
    SELECT wt.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
    FROM wallet_transactions wt
    JOIN users u ON wt.customer_id = u.id
    WHERE wt.transaction_id = ? AND (wt.customer_id = ? OR ? = 'admin')
");
$role_check = $_SESSION['role'] ?? 'patient';
$stmt->bind_param("sis", $tx_id, $user_id, $role_check);
$stmt->execute();
$res = $stmt->get_result();

if (!$res || $res->num_rows === 0) {
    die("Transaction record not found or access denied.");
}

$tx = $res->fetch_assoc();
include 'includes/header.php';
?>

<div style="max-width: 650px; margin: 2rem auto; padding: 0 1rem;">
    <div class="glass-panel" style="padding: 2rem; border-top: 4px solid var(--primary-color);">
        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0; color: var(--text-primary);"><i class="fas fa-receipt" style="color: var(--primary-color);"></i> Wallet Transaction Receipt</h2>
                <p style="color: var(--text-secondary); margin: 0.3rem 0 0;">Transaction #<?php echo htmlspecialchars($tx['transaction_id']); ?></p>
            </div>
            <button onclick="window.print()" class="btn btn-outline" style="font-size: 0.85rem;"><i class="fas fa-print"></i> Print Receipt</button>
        </div>

        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <span style="font-size: 0.8rem; color: var(--text-secondary);">Customer Name:</span>
                    <div style="font-weight: bold; color: var(--text-primary); font-size: 1rem;"><?php echo htmlspecialchars($tx['customer_name']); ?></div>
                </div>
                <div>
                    <span style="font-size: 0.8rem; color: var(--text-secondary);">Transaction Date:</span>
                    <div style="font-weight: bold; color: var(--text-primary); font-size: 0.95rem;"><?php echo date('M d, Y — h:i A', strtotime($tx['created_at'])); ?></div>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <span style="font-size: 0.8rem; color: var(--text-secondary);">Transaction Type:</span>
                    <div style="font-weight: bold; color: var(--secondary-color); text-transform: uppercase; font-size: 0.95rem;"><?php echo str_replace('_', ' ', $tx['transaction_type']); ?></div>
                </div>
                <div>
                    <span style="font-size: 0.8rem; color: var(--text-secondary);">Status:</span>
                    <div>
                        <span style="display: inline-block; padding: 0.2rem 0.6rem; border-radius: 10px; font-size: 0.8rem; font-weight: bold; background: <?php echo $tx['status'] === 'completed' ? 'rgba(46, 213, 115, 0.15)' : 'rgba(255, 171, 0, 0.15)'; ?>; color: <?php echo $tx['status'] === 'completed' ? '#2ed573' : 'var(--accent)'; ?>;">
                            <?php echo ucfirst($tx['status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div style="border-top: 1px dashed var(--glass-border); margin: 1rem 0; padding-top: 1rem; display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div>
                    <span style="font-size: 0.85rem; color: var(--text-secondary);">Amount Transacted:</span>
                    <div style="font-size: 1.5rem; font-weight: bold; color: <?php echo $tx['direction'] === 'credit' ? '#2ed573' : '#ff4757'; ?>;">
                        <?php echo $tx['direction'] === 'credit' ? '+' : '-'; ?>₹<?php echo number_format($tx['amount'], 2); ?>
                    </div>
                </div>
                <div>
                    <span style="font-size: 0.85rem; color: var(--text-secondary);">Wallet Balance After Tx:</span>
                    <div style="font-size: 1.5rem; font-weight: bold; color: var(--text-primary);">
                        ₹<?php echo number_format($tx['new_balance'], 2); ?>
                    </div>
                </div>
            </div>

            <?php if (!empty($tx['payment_id'])): ?>
                <div style="margin-top: 0.8rem; font-size: 0.85rem; color: var(--text-secondary);">
                    <strong>Payment Reference ID:</strong> <?php echo htmlspecialchars($tx['payment_id']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($tx['reason'])): ?>
                <div style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--text-secondary);">
                    <strong>Description / Note:</strong> <?php echo htmlspecialchars($tx['reason']); ?>
                </div>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 1rem; justify-content: space-between;">
            <a href="my_wallet.php" class="btn btn-outline" style="font-size: 0.85rem;"><i class="fas fa-arrow-left"></i> Back to Wallet</a>
            <button onclick="window.print()" class="btn btn-primary" style="font-size: 0.85rem;"><i class="fas fa-download"></i> Download Receipt</button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
