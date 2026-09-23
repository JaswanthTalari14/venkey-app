<?php
require_once 'config.php';
require_once 'includes/referral_functions.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $program_enabled = isset($_POST['program_enabled']) ? 1 : 0;
    $referrer_reward = floatval($_POST['referrer_reward']);
    $referred_reward = floatval($_POST['referred_reward']);
    $min_order_amount = floatval($_POST['min_order_amount']);
    $expiry_days = intval($_POST['expiry_days']);
    $max_rewards = intval($_POST['max_rewards']);

    $stmt = $conn->prepare("UPDATE referral_settings SET program_enabled = ?, referrer_reward = ?, referred_reward = ?, min_order_amount = ?, expiry_days = ?, max_rewards = ? WHERE id = 1");
    $stmt->bind_param("idddii", $program_enabled, $referrer_reward, $referred_reward, $min_order_amount, $expiry_days, $max_rewards);

    if ($stmt->execute()) {
        $msg = "Referral Program Settings updated successfully!";
    } else {
        $error = "Failed to update settings. Please try again.";
    }
}

$settings = get_referral_settings();
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
            <li><a href="admin_referrals.php"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_referral_settings.php" class="active"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-sliders-h" style="color: var(--primary-color);"></i> Referral Program Configuration</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Configure reward amounts, qualification rules, minimum order totals, and referral program status.</p>

        <?php if ($msg): ?>
            <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-weight: 600;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid #ff4757; color: #ff4757; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; font-weight: 600;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="form-container glass-panel" style="max-width: 650px; margin: 0 0 2rem 0; padding: 2rem;">
            <form method="POST" action="">
                <!-- Program Switch -->
                <div class="form-group" style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--glass-border); padding-bottom: 1.25rem; margin-bottom: 1.5rem;">
                    <div>
                        <label style="font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.2rem;">Enable Referral Program</label>
                        <span style="font-size: 0.85rem; color: var(--text-secondary); display: block;">Turn referral rewards and tracking ON or OFF site-wide</span>
                    </div>
                    <label style="position: relative; display: inline-block; width: 50px; height: 26px;">
                        <input type="checkbox" name="program_enabled" value="1" <?php echo $settings['program_enabled'] ? 'checked' : ''; ?> style="opacity: 0; width: 0; height: 0;">
                        <span style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--glass-bg); border: 1px solid var(--glass-border); transition: .4s; border-radius: 34px;" id="switchSlider"></span>
                    </label>
                </div>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem;">
                    <!-- Referrer Reward -->
                    <div class="form-group">
                        <label><i class="fas fa-coins" style="color: var(--primary-color);"></i> Referrer Reward Amount (₹)</label>
                        <input type="number" step="0.01" name="referrer_reward" class="form-control" value="<?php echo htmlspecialchars($settings['referrer_reward']); ?>" required>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Reward earned by inviter after friend's first order</span>
                    </div>

                    <!-- Referred Customer Reward -->
                    <div class="form-group">
                        <label><i class="fas fa-gift" style="color: var(--secondary-color);"></i> Referred Customer Reward (₹)</label>
                        <input type="number" step="0.01" name="referred_reward" class="form-control" value="<?php echo htmlspecialchars($settings['referred_reward']); ?>" required>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Welcome bonus for newly referred customer</span>
                    </div>

                    <!-- Minimum Order Amount -->
                    <div class="form-group">
                        <label><i class="fas fa-shopping-basket" style="color: var(--accent);"></i> Minimum Qualifying Order Amount (₹)</label>
                        <input type="number" step="0.01" name="min_order_amount" class="form-control" value="<?php echo htmlspecialchars($settings['min_order_amount']); ?>" required>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Order total required to qualify for referral rewards</span>
                    </div>

                    <!-- Expiry Days -->
                    <div class="form-group">
                        <label><i class="fas fa-hourglass-half"></i> Referral Expiry (Days)</label>
                        <input type="number" name="expiry_days" class="form-control" value="<?php echo htmlspecialchars($settings['expiry_days']); ?>" required>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Time limit for referred user to complete first order</span>
                    </div>

                    <!-- Max Rewards Limit -->
                    <div class="form-group">
                        <label><i class="fas fa-user-shield"></i> Max Rewards per User (0 = Unlimited)</label>
                        <input type="number" name="max_rewards" class="form-control" value="<?php echo htmlspecialchars($settings['max_rewards']); ?>" required>
                        <span style="font-size: 0.8rem; color: var(--text-secondary);">Cap maximum reward claims per inviter</span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1.5rem; font-size: 1rem;"><i class="fas fa-save"></i> Save Settings</button>
            </form>
        </div>
    </main>
</div>

<style>
input[type="checkbox"]:checked + #switchSlider {
    background-color: var(--primary-color) !important;
}
input[type="checkbox"]:checked + #switchSlider:before {
    transform: translateX(24px);
}
#switchSlider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 4px;
    bottom: 3px;
    background-color: white;
    transition: .4s;
    border-radius: 50%;
}
</style>

<?php include 'includes/footer.php'; ?>
