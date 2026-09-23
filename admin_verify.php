<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// In a real system, there'd be an 'is_verified' column in users table.
// Using a simple mockup where we just list unverified (or all) doctors for review.
$professionals = $conn->query("SELECT * FROM users WHERE role IN ('doctor', 'rmp') ORDER BY created_at DESC");
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
            <li><a href="admin_verify.php" class="active"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
            <li><a href="admin_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_referrals.php"><i class="fas fa-gift"></i> Referral Management</a></li>
            <li><a href="admin_referral_settings.php"><i class="fas fa-sliders-h"></i> Referral Settings</a></li>
            <li><a href="admin_wallets.php"><i class="fas fa-wallet"></i> Wallet Management</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Verify Medical Professionals</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Review licenses and verify Doctors & RMPs before they appear on the main platform.</p>

        <div class="glass-panel" style="padding: 1.5rem;">
            <?php if ($professionals && $professionals->num_rows > 0): ?>
                <?php while($p = $professionals->fetch_assoc()): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding: 1rem 0;">
                        <div>
                            <h4 style="color: #fff;"><?php echo htmlspecialchars($p['name']); ?> <span style="font-size: 0.8rem; background: var(--primary-color); padding: 0.1rem 0.5rem; border-radius: 8px; margin-left: 0.5rem; text-transform: uppercase;"><?php echo $p['role']; ?></span></h4>
                            <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.3rem;">Specialization: <?php echo htmlspecialchars($p['specialization'] ?? 'General / RMP'); ?> | Phone: <?php echo htmlspecialchars($p['phone']); ?></p>
                        </div>
                        <div>
                            <button class="btn btn-outline" style="border-color: #2ed573; color: #2ed573; font-size: 0.9rem;" onclick="alert('Verification logic mockup done!');">Mark Verified</button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: var(--text-secondary);">No medical professionals registered.</p>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
