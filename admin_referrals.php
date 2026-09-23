<?php
require_once 'config.php';
require_once 'includes/referral_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$settings = get_referral_settings();

// Metrics calculation
$total_ref = $conn->query("SELECT COUNT(*) as count FROM referrals")->fetch_assoc()['count'];
$total_invited = $conn->query("SELECT COUNT(*) as count FROM referrals WHERE status = 'Invited'")->fetch_assoc()['count'];
$total_registered = $conn->query("SELECT COUNT(*) as count FROM referrals WHERE status = 'Registered'")->fetch_assoc()['count'];
$total_pending = $conn->query("SELECT COUNT(*) as count FROM referrals WHERE status = 'Order Pending'")->fetch_assoc()['count'];
$total_qualified = $conn->query("SELECT COUNT(*) as count FROM referrals WHERE status IN ('Qualified', 'Reward Earned')")->fetch_assoc()['count'];
$total_reversed = $conn->query("SELECT COUNT(*) as count FROM referrals WHERE status = 'Reward Reversed'")->fetch_assoc()['count'];

$total_rewards_paid = $conn->query("SELECT SUM(amount) as total FROM referral_rewards WHERE amount > 0")->fetch_assoc()['total'] ?: 0;

// Fetch all referrals with details
$query = "
    SELECT r.*, 
           u1.name as referrer_name, u1.phone as referrer_phone,
           u2.name as referred_name, u2.phone as referred_phone, u2.email as referred_email
    FROM referrals r
    JOIN users u1 ON r.referrer_customer_id = u1.id
    JOIN users u2 ON r.referred_customer_id = u2.id
    ORDER BY r.created_at DESC
";
$referrals_res = $conn->query($query);
$referrals_list = [];
if ($referrals_res) {
    while ($row = $referrals_res->fetch_assoc()) {
        $referrals_list[] = $row;
    }
}

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Navigation Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Admin Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-chart-pie"></i> Overview</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
            <li><a href="admin_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_referrals.php" class="active"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_referral_settings.php"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <h2><i class="fas fa-gift" style="color: var(--primary-color);"></i> Referral Management & Ledger</h2>
                <p style="color: var(--text-secondary);">Monitor all user referral activity, rewards granted, and system qualifications.</p>
            </div>
            <a href="admin_referral_settings.php" class="btn btn-primary"><i class="fas fa-cog"></i> Program Settings</a>
        </div>

        <!-- Metric Cards -->
        <div class="features-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); margin-top: 0; margin-bottom: 2rem;">
            <div class="feature-card glass-panel" style="padding: 1.25rem;">
                <h4 style="color: var(--primary-color); font-size: 0.85rem;"><i class="fas fa-list"></i> Total Referrals</h4>
                <p style="font-size: 1.7rem; font-weight: bold; margin: 0.4rem 0;"><?php echo $total_ref; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.25rem;">
                <h4 style="color: #50e3c2; font-size: 0.85rem;"><i class="fas fa-user-check"></i> Registered</h4>
                <p style="font-size: 1.7rem; font-weight: bold; margin: 0.4rem 0; color: #50e3c2;"><?php echo $total_registered; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.25rem;">
                <h4 style="color: var(--accent); font-size: 0.85rem;"><i class="fas fa-clock"></i> Order Pending</h4>
                <p style="font-size: 1.7rem; font-weight: bold; margin: 0.4rem 0; color: var(--accent);"><?php echo $total_pending; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.25rem;">
                <h4 style="color: #2ed573; font-size: 0.85rem;"><i class="fas fa-trophy"></i> Qualified</h4>
                <p style="font-size: 1.7rem; font-weight: bold; margin: 0.4rem 0; color: #2ed573;"><?php echo $total_qualified; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.25rem;">
                <h4 style="color: #ff4757; font-size: 0.85rem;"><i class="fas fa-undo"></i> Reversed</h4>
                <p style="font-size: 1.7rem; font-weight: bold; margin: 0.4rem 0; color: #ff4757;"><?php echo $total_reversed; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.25rem;">
                <h4 style="color: var(--secondary-color); font-size: 0.85rem;"><i class="fas fa-coins"></i> Total Rewards Paid</h4>
                <p style="font-size: 1.7rem; font-weight: bold; margin: 0.4rem 0; color: var(--secondary-color);">₹<?php echo number_format($total_rewards_paid, 2); ?></p>
            </div>
        </div>

        <!-- Referral Logs Table -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h3 style="font-size: 1.2rem;">Referral Activity Logs (<?php echo count($referrals_list); ?>)</h3>
                <input type="text" id="adminRefSearch" class="form-control" placeholder="Search code, referrer, referred..." style="max-width: 300px; font-size: 0.9rem; padding: 0.5rem 1rem;" onkeyup="filterAdminRefs()">
            </div>

            <div class="table-responsive" style="width: 100%; overflow-x: auto;">
                <table id="adminRefTable" style="width: 100%; min-width: 850px; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 1rem; white-space: nowrap;">Referral Code</th>
                            <th style="padding: 1rem; white-space: nowrap;">Referrer (Inviter)</th>
                            <th style="padding: 1rem; white-space: nowrap;">Referred Customer</th>
                            <th style="padding: 1rem; white-space: nowrap;">Reg Date</th>
                            <th style="padding: 1rem; white-space: nowrap;">First Order ID</th>
                            <th style="padding: 1rem; white-space: nowrap;">Status</th>
                            <th style="padding: 1rem; white-space: nowrap;">Expiry Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($referrals_list) > 0): ?>
                            <?php foreach ($referrals_list as $ref): ?>
                                <?php 
                                    $st = $ref['status'];
                                    $badge = "background: rgba(245, 166, 35, 0.2); color: var(--accent); border: 1px solid var(--accent);";
                                    if ($st === 'Reward Earned' || $st === 'Qualified') {
                                        $badge = "background: rgba(46, 213, 115, 0.2); color: #2ed573; border: 1px solid #2ed573;";
                                    } elseif ($st === 'Reward Reversed' || $st === 'Expired' || $st === 'Rejected') {
                                        $badge = "background: rgba(255, 71, 87, 0.2); color: #ff4757; border: 1px solid #ff4757;";
                                    }
                                ?>
                                <tr style="border-bottom: 1px solid var(--glass-border);">
                                    <td style="padding: 1rem; font-weight: 700; font-family: monospace; color: var(--primary-color); white-space: nowrap;">
                                        <?php echo htmlspecialchars($ref['referral_code']); ?>
                                    </td>
                                    <td style="padding: 1rem; white-space: nowrap;">
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($ref['referrer_name']); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($ref['referrer_phone']); ?></div>
                                    </td>
                                    <td style="padding: 1rem; white-space: nowrap;">
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($ref['referred_name']); ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-secondary);"><?php echo htmlspecialchars($ref['referred_phone']); ?></div>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-secondary); white-space: nowrap;">
                                        <?php echo date('M d, Y h:i A', strtotime($ref['registered_at'])); ?>
                                    </td>
                                    <td style="padding: 1rem; white-space: nowrap; font-family: monospace;">
                                        <?php echo $ref['qualifying_order_id'] ? ('#' . $ref['qualifying_order_id']) : '-'; ?>
                                    </td>
                                    <td style="padding: 1rem; white-space: nowrap;">
                                        <span style="padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; <?php echo $badge; ?>">
                                            <?php echo htmlspecialchars($st); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-secondary); white-space: nowrap;">
                                        <?php echo $ref['expires_at'] ? date('M d, Y', strtotime($ref['expires_at'])) : 'No Expiry'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-secondary); padding: 3rem 1rem;">
                                    No referrals recorded yet in the system.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>
function filterAdminRefs() {
    const input = document.getElementById('adminRefSearch');
    const filter = input.value.toLowerCase();
    const table = document.getElementById('adminRefTable');
    if (!table) return;
    const trs = table.getElementsByTagName('tr');

    for (let i = 1; i < trs.length; i++) {
        let text = trs[i].textContent || trs[i].innerText;
        if (text.toLowerCase().indexOf(filter) > -1) {
            trs[i].style.display = "";
        } else {
            trs[i].style.display = "none";
        }
    }
}
</script>

<?php include 'includes/footer.php'; ?>
