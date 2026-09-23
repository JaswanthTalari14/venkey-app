<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

$success = '';
if (isset($_POST['delete_user'])) {
    $uid = $_POST['user_id'];
    $conn->query("DELETE FROM users WHERE id=$uid");
    $success = "User deleted successfully.";
}

$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC");
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
            <li><a href="admin_users.php" class="active"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
            <li><a href="admin_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Manage Users</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">View all registered patients, doctors, and staff.</p>
        
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem;"><?php echo $success; ?></p><?php endif; ?>

        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">ID</th>
                        <th style="padding: 1rem;">Name</th>
                        <th style="padding: 1rem;">Email</th>
                        <th style="padding: 1rem;">Role</th>
                        <th style="padding: 1rem;">Registered</th>
                        <th style="padding: 1rem;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($u = $users->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                            <td style="padding: 1rem;">#<?php echo $u['id']; ?></td>
                            <td style="padding: 1rem; font-weight: bold;"><?php echo htmlspecialchars($u['name']); ?></td>
                            <td style="padding: 1rem; color: var(--text-secondary);"><?php echo htmlspecialchars($u['email']); ?></td>
                            <td style="padding: 1rem;"><span style="text-transform: capitalize; background: rgba(255,255,255,0.1); padding: 0.2rem 0.6rem; border-radius: 12px; font-size: 0.8rem;"><?php echo $u['role']; ?></span></td>
                            <td style="padding: 1rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                            <td style="padding: 1rem;">
                                <?php if($u['role'] !== 'admin'): ?>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Delete user completely?');">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" name="delete_user" class="btn btn-outline" style="border-color: #ff4757; color: #ff4757; font-size: 0.8rem; padding: 0.3rem 0.8rem;">Delete</button>
                                    </form>
                                <?php else: ?>
                                    <span style="color: var(--text-secondary); font-size: 0.8rem;">Admin</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
