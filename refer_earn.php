<?php
require_once 'config.php';
require_once 'includes/referral_functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Ensure user has a referral code
$my_referral_code = get_or_create_referral_code($user_id);
$my_referral_link = get_referral_link($user_id);
$available_balance = get_customer_referral_balance($user_id);

// Fetch patient's referral statistics & history
$total_referrals = 0;
$successful_referrals = 0;
$pending_referrals = 0;
$total_rewards_earned = 0.00;

$ref_history = [];
$stmt = $conn->prepare("
    SELECT r.*, u.name as referred_name, u.phone as referred_phone, u.created_at as reg_date
    FROM referrals r
    JOIN users u ON r.referred_customer_id = u.id
    WHERE r.referrer_customer_id = ?
    ORDER BY r.created_at DESC
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $ref_history[] = $row;
    $total_referrals++;
    $st = $row['status'];
    if ($st === 'Reward Earned' || $st === 'Qualified') {
        $successful_referrals++;
    } elseif ($st === 'Registered' || $st === 'Order Pending') {
        $pending_referrals++;
    }
}

// Calculate total earned rewards
$rw_stmt = $conn->prepare("SELECT SUM(amount) as total FROM referral_rewards WHERE customer_id = ? AND amount > 0");
$rw_stmt->bind_param("i", $user_id);
$rw_stmt->execute();
$rw_res = $rw_stmt->get_result();
if ($rw_res && $rw_row = $rw_res->fetch_assoc()) {
    $total_rewards_earned = (float)($rw_row['total'] ?: 0);
}

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Navigation Menu</span>
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
            <li><a href="refer_earn.php" class="active"><i class="fas fa-gift"></i> Refer & Earn</a></li>
            <li><a href="my_wallet.php"><i class="fas fa-wallet"></i> My Wallet</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-gift" style="color: var(--primary-color);"></i> Refer & Earn Rewards</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Invite your friends to MedicalAk. Earn rewards on their first medicine order!</p>

        <!-- Referral Link & Code Box -->
        <div class="glass-panel" style="padding: 2rem; margin-bottom: 2rem;">
            <div style="display: flex; flex-wrap: wrap; gap: 2rem; justify-content: space-between; align-items: center;">
                <div style="flex: 1; min-width: 260px;">
                    <span style="font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Your Unique Referral Code</span>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                        <input type="text" readonly value="<?php echo htmlspecialchars($my_referral_code); ?>" id="refCodeInput" class="form-control" style="font-weight: 800; font-size: 1.4rem; letter-spacing: 2px; color: var(--secondary-color); text-align: center; max-width: 200px;">
                        <button class="btn btn-primary" onclick="copyRefCode()" style="padding: 0.75rem 1.2rem;"><i class="fas fa-copy"></i> Copy</button>
                    </div>
                </div>

                <div style="flex: 2; min-width: 280px;">
                    <span style="font-size: 0.85rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Your Referral Link</span>
                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                        <input type="text" readonly value="<?php echo htmlspecialchars($my_referral_link); ?>" id="refLinkInput" class="form-control" style="font-size: 0.95rem;">
                        <button class="btn btn-outline" onclick="copyRefLink()" style="padding: 0.75rem 1.2rem; white-space: nowrap;"><i class="fas fa-link"></i> Copy Link</button>
                    </div>
                </div>
            </div>

            <!-- Share Buttons -->
            <div style="margin-top: 1.75rem; border-top: 1px solid var(--glass-border); padding-top: 1.25rem;">
                <span style="font-size: 0.9rem; color: var(--text-secondary); margin-right: 1rem; font-weight: 600;">Quick Share:</span>
                <div style="display: inline-flex; flex-wrap: wrap; gap: 0.6rem; margin-top: 0.5rem; align-items: center;">
                    <?php 
                        $share_msg = urlencode("Join MedicalAk using my referral link and get your instant referral discount on medicine orders! Register here: " . $my_referral_link);
                    ?>
                    <a href="https://api.whatsapp.com/send?text=<?php echo $share_msg; ?>" target="_blank" class="btn" style="background: #25D366; color: #fff; padding: 0.5rem 1rem; font-size: 0.85rem;"><i class="fab fa-whatsapp"></i> WhatsApp</a>
                    <a href="sms:?body=<?php echo $share_msg; ?>" class="btn" style="background: #4a90e2; color: #fff; padding: 0.5rem 1rem; font-size: 0.85rem;"><i class="fas fa-comment-dots"></i> SMS</a>
                    <a href="mailto:?subject=MedicalAk Referral&body=<?php echo $share_msg; ?>" class="btn" style="background: #ea4335; color: #fff; padding: 0.5rem 1rem; font-size: 0.85rem;"><i class="fas fa-envelope"></i> Email</a>
                    <button type="button" onclick="nativeShare()" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.85rem;"><i class="fas fa-share-alt"></i> Native Share</button>
                </div>
            </div>
        </div>

        <!-- Metrics Overview Cards -->
        <div class="features-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-top: 0; margin-bottom: 2rem;">
            <div class="feature-card glass-panel" style="padding: 1.25rem;">
                <h4 style="color: var(--primary-color); font-size: 0.9rem;"><i class="fas fa-users"></i> Total Invited</h4>
                <p style="font-size: 1.8rem; font-weight: bold; margin: 0.5rem 0;"><?php echo $total_referrals; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.25rem;">
                <h4 style="color: #2ed573; font-size: 0.9rem;"><i class="fas fa-check-circle"></i> Successful</h4>
                <p style="font-size: 1.8rem; font-weight: bold; margin: 0.5rem 0; color: #2ed573;"><?php echo $successful_referrals; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.25rem;">
                <h4 style="color: var(--accent); font-size: 0.9rem;"><i class="fas fa-clock"></i> Pending</h4>
                <p style="font-size: 1.8rem; font-weight: bold; margin: 0.5rem 0; color: var(--accent);"><?php echo $pending_referrals; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.25rem;">
                <h4 style="color: var(--secondary-color); font-size: 0.9rem;"><i class="fas fa-coins"></i> Total Rewards</h4>
                <p style="font-size: 1.8rem; font-weight: bold; margin: 0.5rem 0; color: var(--secondary-color);">₹<?php echo number_format($total_rewards_earned, 2); ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.25rem;">
                <h4 style="color: #50e3c2; font-size: 0.9rem;"><i class="fas fa-wallet"></i> Available Balance</h4>
                <p style="font-size: 1.8rem; font-weight: bold; margin: 0.5rem 0; color: #50e3c2;">₹<?php echo number_format($available_balance, 2); ?></p>
            </div>
        </div>

        <!-- Referral History Table -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="font-size: 1.2rem; margin-bottom: 1.5rem;">Your Referral History</h3>
            <div class="table-responsive" style="width: 100%; overflow-x: auto;">
                <table style="width: 100%; min-width: 750px; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 1rem; white-space: nowrap;">Referred Customer</th>
                            <th style="padding: 1rem; white-space: nowrap;">Mobile Number</th>
                            <th style="padding: 1rem; white-space: nowrap;">Registration Date</th>
                            <th style="padding: 1rem; white-space: nowrap;">Qualifying Order ID</th>
                            <th style="padding: 1rem; white-space: nowrap;">Status</th>
                            <th style="padding: 1rem; white-space: nowrap;">Reward Earned</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($ref_history) > 0): ?>
                            <?php foreach ($ref_history as $ref): ?>
                                <?php 
                                    $st = $ref['status'];
                                    $badge = "background: rgba(245, 166, 35, 0.2); color: var(--accent); border: 1px solid var(--accent);";
                                    if ($st === 'Reward Earned' || $st === 'Qualified') {
                                        $badge = "background: rgba(46, 213, 115, 0.2); color: #2ed573; border: 1px solid #2ed573;";
                                    } elseif ($st === 'Reward Reversed' || $st === 'Expired' || $st === 'Rejected') {
                                        $badge = "background: rgba(255, 71, 87, 0.2); color: #ff4757; border: 1px solid #ff4757;";
                                    }
                                    
                                    // Mask Phone number (e.g. 9876****12)
                                    $phone = $ref['referred_phone'];
                                    $masked_phone = (strlen($phone) >= 10) 
                                        ? substr($phone, 0, 4) . '****' . substr($phone, -2) 
                                        : 'Secret';
                                ?>
                                <tr style="border-bottom: 1px solid var(--glass-border);">
                                    <td style="padding: 1rem; font-weight: 600; white-space: nowrap;">
                                        <?php echo htmlspecialchars($ref['referred_name']); ?>
                                    </td>
                                    <td style="padding: 1rem; color: var(--text-secondary); white-space: nowrap;">
                                        <?php echo htmlspecialchars($masked_phone); ?>
                                    </td>
                                    <td style="padding: 1rem; color: var(--text-secondary); font-size: 0.85rem; white-space: nowrap;">
                                        <?php echo date('M d, Y', strtotime($ref['registered_at'])); ?>
                                    </td>
                                    <td style="padding: 1rem; white-space: nowrap; font-family: monospace;">
                                        <?php echo $ref['qualifying_order_id'] ? ('#' . $ref['qualifying_order_id']) : '-'; ?>
                                    </td>
                                    <td style="padding: 1rem; white-space: nowrap;">
                                        <span style="padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.8rem; font-weight: 600; <?php echo $badge; ?>">
                                            <?php echo htmlspecialchars($st); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 1rem; font-weight: 700; color: #2ed573; white-space: nowrap;">
                                        <?php echo ($st === 'Reward Earned') ? '₹50.00' : '₹0.00'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-secondary); padding: 2.5rem 1rem;">
                                    You haven't referred anyone yet. Share your referral link above to start earning!
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
function copyRefCode() {
    const input = document.getElementById('refCodeInput');
    input.select();
    navigator.clipboard.writeText(input.value);
    alert('Referral Code copied to clipboard: ' + input.value);
}

function copyRefLink() {
    const input = document.getElementById('refLinkInput');
    input.select();
    navigator.clipboard.writeText(input.value);
    alert('Referral Link copied to clipboard!');
}

function nativeShare() {
    const link = document.getElementById('refLinkInput').value;
    if (navigator.share) {
        navigator.share({
            title: 'MedicalAk Referral',
            text: 'Join MedicalAk using my referral link and get instant referral rewards on medicine orders!',
            url: link
        }).catch(() => {});
    } else {
        copyRefLink();
    }
}
</script>

<?php include 'includes/footer.php'; ?>
