<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Admin Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Admin Menu</span>
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
            <li><a href="admin_referral_settings.php"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php" class="active"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Feedback & Complaints</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Read user feedback and resolve platform issues.</p>

        <div class="glass-panel" style="padding: 2rem; text-align: center;">
            <i class="fas fa-smile-beam" style="font-size: 4rem; color: var(--secondary-color); margin-bottom: 1rem;"></i>
            <h3>No Complaints!</h3>
            <p style="color: var(--text-secondary); margin-top: 0.5rem;">Your platform is running smoothly and users are happy.</p>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
