<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';

// Fetch platform statistics for the dashboard
$total_patients = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='patient'")->fetch_assoc()['count'];
$total_doctors = $conn->query("SELECT COUNT(*) as count FROM users WHERE role='doctor'")->fetch_assoc()['count'];
$total_appointments = $conn->query("SELECT COUNT(*) as count FROM appointments")->fetch_assoc()['count'];
$total_orders = $conn->query("SELECT COUNT(*) as count FROM orders")->fetch_assoc()['count'];

// Fetch latest registered users
$latest_users = $conn->query("SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <h3 style="margin-bottom: 2rem;">Admin Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-chart-pie"></i> Overview</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="admin_verify.php"><i class="fas fa-user-md"></i> Verify Doctors & RMPs</a></li>
            <li><a href="admin_bookings.php"><i class="fas fa-calendar-check"></i> All Bookings</a></li>
            <li><a href="admin_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="admin_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback & Complaints</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Admin Management Dashboard</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Monitor platform activities, oversee doctors, and control services.</p>
        
        <div class="features-grid" style="margin-top: 1rem;">
            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: var(--primary-color);"><i class="fas fa-users"></i> Total Patients</h4>
                <p style="font-size: 2.5rem; font-weight: bold; margin: 0.5rem 0;"><?php echo $total_patients; ?></p>
            </div>
            
            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: var(--secondary-color);"><i class="fas fa-user-md"></i> Total Doctors</h4>
                <p style="font-size: 2.5rem; font-weight: bold; margin: 0.5rem 0;"><?php echo $total_doctors; ?></p>
            </div>
            
            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: var(--accent);"><i class="fas fa-calendar-alt"></i> Total Appointments</h4>
                <p style="font-size: 2.5rem; font-weight: bold; margin: 0.5rem 0;"><?php echo $total_appointments; ?></p>
            </div>
            
            <div class="feature-card glass-panel" style="padding: 1.5rem; text-align: center;">
                <h4 style="color: #fff;"><i class="fas fa-truck"></i> Total Orders</h4>
                <p style="font-size: 2.5rem; font-weight: bold; margin: 0.5rem 0;"><?php echo $total_orders; ?></p>
            </div>
        </div>

        <h3 style="margin-top: 3rem; margin-bottom: 1rem;">Recently Registered Users</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">ID</th>
                        <th style="padding: 1rem;">Name</th>
                        <th style="padding: 1rem;">Email</th>
                        <th style="padding: 1rem;">Role</th>
                        <th style="padding: 1rem;">Registration Date</th>
                        <th style="padding: 1rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($latest_users && $latest_users->num_rows > 0): ?>
                        <?php while($u = $latest_users->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#<?php echo $u['id']; ?></td>
                                <td style="padding: 1rem; font-weight: bold;"><?php echo htmlspecialchars($u['name']); ?></td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo htmlspecialchars($u['email']); ?></td>
                                <td style="padding: 1rem;">
                                    <span style="background: rgba(255,255,255,0.1); padding: 0.3rem 0.8rem; border-radius: 20px; font-size: 0.8rem; text-transform: capitalize;">
                                        <?php echo htmlspecialchars($u['role']); ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                <td style="padding: 1rem;"><a href="#" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 1rem;">View / Edit</a></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 1rem; text-align: center;">No users found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
