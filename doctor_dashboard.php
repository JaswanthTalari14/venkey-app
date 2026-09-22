<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit;
}
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <h3 style="margin-bottom: 2rem;">Doctor Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="doctor_dashboard.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="doctor_appointments.php"><i class="fas fa-calendar-day"></i> Appointments</a></li>
            <li><a href="doctor_referrals.php"><i class="fas fa-exchange-alt"></i> Patient Referrals</a></li>
            <li><a href="doctor_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="doctor_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="doctor_queries.php"><i class="fas fa-user-secret"></i> Anonymous Queries</a></li>
            <li><a href="profile.php"><i class="fas fa-cog"></i> Settings & Availability</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Dr. <?php echo htmlspecialchars($_SESSION['name']); ?></h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">View your daily schedule and answer patient privacy queries.</p>
        
        <div class="features-grid" style="margin-top: 1rem;">
            <div class="feature-card glass-panel" style="padding: 1.5rem;">
                <h4 style="color: var(--primary-color);"><i class="fas fa-users"></i> Today's Consultations</h4>
                <p style="font-size: 2rem; font-weight: bold; margin: 1rem 0;">0</p>
                <a href="doctor_appointments.php" class="btn btn-outline" style="font-size: 0.8rem;">View Schedule</a>
            </div>
            
            <div class="feature-card glass-panel" style="padding: 1.5rem;">
                <h4 style="color: var(--accent);"><i class="fas fa-envelope-open-text"></i> Pending Privacy Queries</h4>
                <p style="font-size: 2rem; font-weight: bold; margin: 1rem 0;">0</p>
                <a href="doctor_queries.php" class="btn btn-outline" style="font-size: 0.8rem;">Answer Queries</a>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
