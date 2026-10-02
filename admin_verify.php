<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle Verification Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_verify') {
    $target_user_id = (int)$_POST['user_id'];
    $new_status = (int)$_POST['status'];
    $stmt = $conn->prepare("UPDATE users SET is_verified = ? WHERE id = ? AND role IN ('doctor', 'rmp')");
    if ($stmt) {
        $stmt->bind_param("ii", $new_status, $target_user_id);
        $stmt->execute();
    }
    header("Location: admin_verify.php");
    exit;
}

include 'includes/header.php';

// Retrieve all doctors and RMPs with their stored details and profile images
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
            <li><a href="admin_orders_management.php"><i class="fas fa-boxes"></i> Order Management</a></li>
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
                    <?php
                        // Check for uploaded profile image across common column names or fallback
                        $img_src = '';
                        $possible_img_fields = ['profile_image', 'image', 'avatar', 'photo'];
                        foreach ($possible_img_fields as $field) {
                            if (!empty($p[$field]) && file_exists($p[$field])) {
                                $img_src = $p[$field];
                                break;
                            }
                        }
                    ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding: 1.1rem 0; gap: 1rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 1rem; flex: 1; min-width: 260px;">
                            <!-- Doctor Profile Image / Avatar Display -->
                            <?php if (!empty($img_src)): ?>
                                <img src="<?php echo htmlspecialchars($img_src); ?>" alt="<?php echo htmlspecialchars($p['name']); ?>" style="width: 54px; height: 54px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-color); flex-shrink: 0; background: rgba(0,0,0,0.15);">
                            <?php else: ?>
                                <div style="width: 54px; height: 54px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 1.4rem; border: 2px solid var(--primary-color); flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                                    <i class="fas <?php echo ($p['role'] === 'rmp') ? 'fa-user-nurse' : 'fa-user-md'; ?>"></i>
                                </div>
                            <?php endif; ?>

                            <div>
                                <h4 style="color: var(--text-primary); font-weight: 700; font-size: 1.05rem; margin: 0 0 0.3rem 0; display: flex; align-items: center; flex-wrap: wrap; gap: 0.4rem;">
                                    <span><?php echo htmlspecialchars($p['name']); ?></span>
                                    <span style="font-size: 0.75rem; background: var(--primary-color); color: #ffffff; padding: 0.15rem 0.55rem; border-radius: 12px; text-transform: uppercase; font-weight: 700;"><?php echo htmlspecialchars($p['role']); ?></span>
                                    <?php if (!empty($p['is_verified'])): ?>
                                        <span style="font-size: 0.72rem; background: rgba(46, 213, 115, 0.15); color: #2ed573; border: 1px solid #2ed573; padding: 0.1rem 0.5rem; border-radius: 12px; font-weight: 600;">
                                            <i class="fas fa-check-circle"></i> Verified
                                        </span>
                                    <?php endif; ?>
                                </h4>
                                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0; line-height: 1.45;">
                                    Specialization: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($p['specialization'] ?? 'General / RMP'); ?></strong> 
                                    <span style="opacity: 0.6; margin: 0 0.3rem;">|</span> 
                                    Phone: <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($p['phone']); ?></strong>
                                    <?php if (!empty($p['email'])): ?>
                                        <span style="opacity: 0.6; margin: 0 0.3rem;">|</span> Email: <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($p['email']); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div>
                            <form method="POST" action="" style="margin: 0;">
                                <input type="hidden" name="action" value="toggle_verify">
                                <input type="hidden" name="user_id" value="<?php echo $p['id']; ?>">
                                <?php if (!empty($p['is_verified'])): ?>
                                    <input type="hidden" name="status" value="0">
                                    <button type="submit" class="btn btn-outline" style="border-color: #ffa502; color: #ffa502; font-size: 0.85rem; padding: 0.45rem 0.9rem;">
                                        <i class="fas fa-undo"></i> Mark Unverified
                                    </button>
                                <?php else: ?>
                                    <input type="hidden" name="status" value="1">
                                    <button type="submit" class="btn btn-outline" style="border-color: #2ed573; color: #2ed573; font-size: 0.85rem; padding: 0.45rem 0.9rem;">
                                        <i class="fas fa-check"></i> Mark Verified
                                    </button>
                                <?php endif; ?>
                            </form>
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
