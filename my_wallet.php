<?php
require_once 'config.php';
require_once 'includes/wallet_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = $_SESSION['user_id'];
$wallet = get_or_create_wallet($patient_id);
$settings = get_wallet_settings();

// Check session messages
if (isset($_SESSION['wallet_msg'])) {
    $message = $_SESSION['wallet_msg'];
    $msg_type = isset($_SESSION['wallet_msg_type']) ? $_SESSION['wallet_msg_type'] : 'info';
    unset($_SESSION['wallet_msg'], $_SESSION['wallet_msg_type']);
}

// Handle Add Money Request -> Redirects to payment gateway bridge
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_money') {
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    
    if ($amount < $settings['min_topup'] || $amount > $settings['max_topup']) {
        $message = "Please enter an amount between ₹" . number_format($settings['min_topup'], 2) . " and ₹" . number_format($settings['max_topup'], 2) . ".";
        $msg_type = 'danger';
    } else {
        $topup = process_wallet_topup_request($patient_id, $amount, 'online');
        if ($topup['success']) {
            $topup_id = $topup['topup_id'];
            // Redirect to payment gateway bridge
            header("Location: pay_wallet_topup.php?topup_id=" . urlencode($topup_id));
            exit;
        } else {
            $message = $topup['message'];
            $msg_type = 'danger';
        }
    }
}

// Fetch Metrics
$available_balance = get_wallet_balance($patient_id);
$pending_balance = floatval($wallet['pending_balance']);

$added_res = $conn->query("SELECT SUM(amount) as total FROM wallet_transactions WHERE customer_id = $patient_id AND direction = 'credit' AND status = 'completed' AND transaction_type IN ('topup', 'topup_approved')");
$total_added = floatval($added_res->fetch_assoc()['total'] ?? 0);

$used_res = $conn->query("SELECT SUM(amount) as total FROM wallet_transactions WHERE customer_id = $patient_id AND direction = 'debit' AND status = 'completed' AND transaction_type = 'payment'");
$total_used = floatval($used_res->fetch_assoc()['total'] ?? 0);

$ref_res = $conn->query("SELECT SUM(amount) as total FROM wallet_transactions WHERE customer_id = $patient_id AND direction = 'credit' AND status = 'completed' AND transaction_type = 'referral_reward'");
$total_referral_rewards = floatval($ref_res->fetch_assoc()['total'] ?? 0);

// Fetch Transaction History
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$query = "SELECT * FROM wallet_transactions WHERE customer_id = $patient_id";

if ($filter === 'credits') $query .= " AND direction = 'credit'";
else if ($filter === 'debits') $query .= " AND direction = 'debit'";
else if ($filter === 'topups') $query .= " AND transaction_type IN ('topup', 'topup_approved')";
else if ($filter === 'payments') $query .= " AND transaction_type = 'payment'";
else if ($filter === 'refunds') $query .= " AND transaction_type = 'refund'";
else if ($filter === 'referrals') $query .= " AND transaction_type IN ('referral_reward', 'referral_reversal')";
else if ($filter === 'admin') $query .= " AND transaction_type IN ('admin_credit', 'admin_debit')";

$query .= " ORDER BY created_at DESC";
$transactions = $conn->query($query);

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Patient Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Patient Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="refer_earn.php"><i class="fas fa-gift"></i> Refer & Earn</a></li>
            <li><a href="my_wallet.php" class="active"><i class="fas fa-wallet"></i> My Wallet</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <h2><i class="fas fa-wallet" style="color: var(--primary-color);"></i> My Medicineak Wallet</h2>
                <p style="color: var(--text-secondary);">Manage your balance, add funds, and view transparent transaction ledger.</p>
            </div>
            <button onclick="document.getElementById('topup-modal').style.display='flex'" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Add Money to Wallet
            </button>
        </div>

        <?php if ($wallet['status'] === 'frozen'): ?>
            <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid #ff4757; color: #ff4757; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
                <i class="fas fa-lock" style="margin-right: 0.5rem;"></i> Your wallet is currently unavailable for transactions. Please contact customer support.
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div style="background: <?php echo $msg_type === 'success' ? 'rgba(46, 213, 115, 0.15)' : 'rgba(255, 71, 87, 0.15)'; ?>; border: 1px solid <?php echo $msg_type === 'success' ? '#2ed573' : '#ff4757'; ?>; color: <?php echo $msg_type === 'success' ? '#2ed573' : '#ff4757'; ?>; padding: 1rem; border-radius: 12px; margin-bottom: 1.5rem;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Balance Metrics Cards -->
        <div class="features-grid" style="margin-bottom: 2rem;">
            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center; border-left: 4px solid var(--primary-color);">
                <p style="font-size: 0.9rem; color: var(--text-secondary);"><i class="fas fa-coins"></i> Available Balance</p>
                <p style="font-size: 2.2rem; font-weight: bold; color: var(--primary-color); margin: 0.5rem 0;">₹<?php echo number_format($available_balance, 2); ?></p>
                <small style="color: var(--text-secondary);">Ready to use for orders</small>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center; border-left: 4px solid #f39c12;">
                <p style="font-size: 0.9rem; color: var(--text-secondary);"><i class="fas fa-clock"></i> Pending Balance</p>
                <p style="font-size: 2.2rem; font-weight: bold; color: #f39c12; margin: 0.5rem 0;">₹<?php echo number_format($pending_balance, 2); ?></p>
                <small style="color: var(--text-secondary);">Under verification</small>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center; border-left: 4px solid #2ed573;">
                <p style="font-size: 0.9rem; color: var(--text-secondary);"><i class="fas fa-arrow-down"></i> Total Added</p>
                <p style="font-size: 2.2rem; font-weight: bold; color: #2ed573; margin: 0.5rem 0;">₹<?php echo number_format($total_added, 2); ?></p>
                <small style="color: var(--text-secondary);">Online top-ups</small>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center; border-left: 4px solid #3498db;">
                <p style="font-size: 0.9rem; color: var(--text-secondary);"><i class="fas fa-shopping-cart"></i> Total Used</p>
                <p style="font-size: 2.2rem; font-weight: bold; color: #3498db; margin: 0.5rem 0;">₹<?php echo number_format($total_used, 2); ?></p>
                <small style="color: var(--text-secondary);">Medicine checkout</small>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center; border-left: 4px solid #9b59b6;">
                <p style="font-size: 0.9rem; color: var(--text-secondary);"><i class="fas fa-gift"></i> Referral Rewards</p>
                <p style="font-size: 2.2rem; font-weight: bold; color: #9b59b6; margin: 0.5rem 0;">₹<?php echo number_format($total_referral_rewards, 2); ?></p>
                <small style="color: var(--text-secondary);">Referral earnings</small>
            </div>
        </div>

        <!-- Top-Up Requests & Admin Approval Status -->
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-clock"></i> Wallet Top-Up Requests & Approval Status</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem; margin-bottom: 2rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Top-Up ID</th>
                        <th style="padding: 1rem;">Req. Amount</th>
                        <th style="padding: 1rem;">Paid Amount</th>
                        <th style="padding: 1rem;">Payment Status</th>
                        <th style="padding: 1rem;">Admin Approval Status</th>
                        <th style="padding: 1rem;">Date & Time</th>
                        <th style="padding: 1rem;">Action / Note</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $user_topups = $conn->query("SELECT * FROM wallet_topups WHERE customer_id = $patient_id ORDER BY created_at DESC");
                    if ($user_topups && $user_topups->num_rows > 0): 
                    ?>
                        <?php while ($tu = $user_topups->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem; font-family: monospace; font-weight: bold; color: var(--secondary-color);">
                                    <?php echo htmlspecialchars($tu['topup_id']); ?>
                                </td>
                                <td style="padding: 1rem; font-weight: bold;">₹<?php echo number_format($tu['amount'], 2); ?></td>
                                <td style="padding: 1rem;">
                                    <?php echo $tu['paid_amount'] > 0 ? '₹' . number_format($tu['paid_amount'], 2) : '-'; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php if ($tu['status'] === 'pending'): ?>
                                        <span style="background: rgba(241, 196, 15, 0.15); color: #f1c40f; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">
                                            Payment Pending
                                        </span>
                                    <?php elseif ($tu['status'] === 'payment_failed'): ?>
                                        <span style="background: rgba(255, 71, 87, 0.15); color: #ff4757; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">
                                            Payment Failed
                                        </span>
                                    <?php else: ?>
                                        <span style="background: rgba(46, 213, 115, 0.15); color: #2ed573; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">
                                            Payment Received
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php if ($tu['status'] === 'approved'): ?>
                                        <span style="background: rgba(46, 213, 115, 0.15); color: #2ed573; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">
                                            <i class="fas fa-check-circle"></i> Approved & Credited
                                        </span>
                                    <?php elseif ($tu['status'] === 'rejected'): ?>
                                        <span style="background: rgba(255, 71, 87, 0.15); color: #ff4757; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">
                                            <i class="fas fa-times-circle"></i> Rejected
                                        </span>
                                    <?php elseif ($tu['status'] === 'amount_mismatch'): ?>
                                        <span style="background: rgba(155, 89, 182, 0.15); color: #9b59b6; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">
                                            <i class="fas fa-exclamation-triangle"></i> Amount Mismatch (Review)
                                        </span>
                                    <?php elseif ($tu['status'] === 'pending_approval'): ?>
                                        <span style="background: rgba(230, 126, 34, 0.15); color: #e67e22; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem;">
                                            <i class="fas fa-hourglass-half"></i> Pending Admin Verification
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary); font-size: 0.85rem;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    <?php echo date('M d, Y h:i A', strtotime($tu['created_at'])); ?>
                                </td>
                                <td style="padding: 1rem; font-size: 0.85rem;">
                                    <?php if ($tu['status'] === 'pending'): ?>
                                        <a href="pay_wallet_topup.php?topup_id=<?php echo urlencode($tu['topup_id']); ?>" class="btn btn-outline" style="padding: 0.3rem 0.7rem; font-size: 0.8rem;">Pay Now</a>
                                    <?php elseif ($tu['status'] === 'rejected' && !empty($tu['rejection_reason'])): ?>
                                        <small style="color: #ff4757; display: block; max-width: 200px;">Reason: <?php echo htmlspecialchars($tu['rejection_reason']); ?></small>
                                    <?php elseif ($tu['status'] === 'pending_approval' || $tu['status'] === 'amount_mismatch'): ?>
                                        <small style="color: #e67e22;">Waiting for Admin verification</small>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary);">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="padding: 1.5rem; text-align: center; color: var(--text-secondary);">
                                No top-up requests found. Click "Add Money to Wallet" above to get started.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Filter Tabs -->
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
            <a href="my_wallet.php?filter=all" class="btn <?php echo $filter==='all'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">All</a>
            <a href="my_wallet.php?filter=credits" class="btn <?php echo $filter==='credits'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">Credits (+)</a>
            <a href="my_wallet.php?filter=debits" class="btn <?php echo $filter==='debits'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">Debits (-)</a>
            <a href="my_wallet.php?filter=topups" class="btn <?php echo $filter==='topups'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">Top-Ups</a>
            <a href="my_wallet.php?filter=payments" class="btn <?php echo $filter==='payments'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">Payments</a>
            <a href="my_wallet.php?filter=refunds" class="btn <?php echo $filter==='refunds'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">Refunds</a>
            <a href="my_wallet.php?filter=referrals" class="btn <?php echo $filter==='referrals'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">Referrals</a>
            <a href="my_wallet.php?filter=admin" class="btn <?php echo $filter==='admin'?'btn-primary':'btn-outline'; ?>" style="font-size: 0.85rem; padding: 0.4rem 1rem;">Admin Adjustments</a>
        </div>

        <!-- Transaction History Table -->
        <h3 style="margin-bottom: 1rem;"><i class="fas fa-list-alt"></i> Transaction History Ledger</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Tx ID</th>
                        <th style="padding: 1rem;">Type & Description</th>
                        <th style="padding: 1rem;">Direction</th>
                        <th style="padding: 1rem;">Amount</th>
                        <th style="padding: 1rem;">Balance After</th>
                        <th style="padding: 1rem;">Date & Time</th>
                        <th style="padding: 1rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions && $transactions->num_rows > 0): ?>
                        <?php while ($tx = $transactions->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem; font-family: monospace; font-size: 0.85rem; font-weight: bold; color: var(--primary-color);">
                                    <?php echo htmlspecialchars($tx['transaction_id']); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <div style="font-weight: bold; text-transform: capitalize; font-size: 0.9rem;">
                                        <?php echo str_replace('_', ' ', $tx['transaction_type']); ?>
                                    </div>
                                    <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($tx['reason'] ?? '-'); ?></small>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php if ($tx['direction'] === 'credit'): ?>
                                        <span style="color: #2ed573; font-weight: bold;"><i class="fas fa-arrow-down"></i> Credit</span>
                                    <?php else: ?>
                                        <span style="color: #ff4757; font-weight: bold;"><i class="fas fa-arrow-up"></i> Debit</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem; font-weight: bold; color: <?php echo $tx['direction'] === 'credit' ? '#2ed573' : '#ff4757'; ?>;">
                                    <?php echo $tx['direction'] === 'credit' ? '+' : '-'; ?>₹<?php echo number_format($tx['amount'], 2); ?>
                                </td>
                                <td style="padding: 1rem;">₹<?php echo number_format($tx['new_balance'], 2); ?></td>
                                <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                                    <?php echo date('M d, Y h:i A', strtotime($tx['created_at'])); ?>
                                </td>
                                <td style="padding: 1rem;">
                                    <span style="background: rgba(46, 213, 115, 0.15); color: #2ed573; padding: 0.3rem 0.8rem; border-radius: 12px; font-weight: bold; font-size: 0.8rem; text-transform: capitalize;">
                                        <?php echo htmlspecialchars($tx['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                                No wallet transactions found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- Add Money Modal -->
<div id="topup-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center; padding: 1rem;">
    <div class="glass-panel" style="background: var(--bg-card); border: 1px solid var(--glass-border); width: 100%; max-width: 450px; padding: 2rem; border-radius: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h3><i class="fas fa-plus-circle" style="color: var(--primary-color);"></i> Add Money to Wallet</h3>
            <button onclick="document.getElementById('topup-modal').style.display='none'" style="background: none; border: none; color: var(--text-primary); font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="my_wallet.php">
            <input type="hidden" name="action" value="add_money">

            <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">Select or Enter Amount (₹)</label>
            <input type="number" id="topup_amount_input" name="amount" min="<?php echo $settings['min_topup']; ?>" max="<?php echo $settings['max_topup']; ?>" step="0.01" value="500" class="glass-panel" style="width: 100%; padding: 0.8rem; border-radius: 8px; margin-bottom: 1rem; color: var(--text-primary); font-size: 1.2rem; font-weight: bold;" required>

            <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
                <button type="button" onclick="setTopupAmount(100)" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">+ ₹100</button>
                <button type="button" onclick="setTopupAmount(250)" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">+ ₹250</button>
                <button type="button" onclick="setTopupAmount(500)" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">+ ₹500</button>
                <button type="button" onclick="setTopupAmount(1000)" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">+ ₹1,000</button>
                <button type="button" onclick="setTopupAmount(2000)" class="btn btn-outline" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;">+ ₹2,000</button>
            </div>

            <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1.5rem;">
                Allowed limits: ₹<?php echo number_format($settings['min_topup'], 2); ?> to ₹<?php echo number_format($settings['max_topup'], 2); ?>.
            </p>

            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-size: 1rem;">
                <i class="fas fa-lock"></i> Proceed to Pay & Credit Wallet
            </button>
        </form>
    </div>
</div>

<script>
function setTopupAmount(amt) {
    document.getElementById('topup_amount_input').value = amt;
}
</script>

<?php include 'includes/footer.php'; ?>
