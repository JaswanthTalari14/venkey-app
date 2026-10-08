<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Doctor Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Doctor Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Doctor Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="doctor_dashboard.php" class="active"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="guidelines.php" style="color: #10B981; font-weight: 600;"><i class="fas fa-book-open" style="color: #10B981;"></i> How to Use Guideline</a></li>
            <li><a href="doctor_appointments.php"><i class="fas fa-calendar-day"></i> Appointments</a></li>
            <li><a href="doctor_referrals.php"><i class="fas fa-exchange-alt"></i> Patient Referrals</a></li>
            <li><a href="doctor_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="doctor_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="doctor_queries.php"><i class="fas fa-user-secret"></i> Anonymous Queries</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="profile.php"><i class="fas fa-cog"></i> Settings & Availability</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <!-- Sleek & Small Green Gradient Guideline Card -->
        <div style="background: linear-gradient(135deg, #059669 0%, #10B981 50%, #047857 100%); border-radius: 14px; color: #ffffff; padding: 0.85rem 1.25rem; margin-bottom: 1.25rem; box-shadow: 0 6px 20px -3px rgba(16, 185, 129, 0.4); position: relative; overflow: hidden;">
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; position: relative; z-index: 2; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <div style="width: 38px; height: 38px; border-radius: 10px; background: rgba(255, 255, 255, 0.2); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; flex-shrink: 0;">
                        📖
                    </div>
                    <div>
                        <h4 style="font-size: 1rem; font-weight: 800; margin: 0; color: #ffffff; line-height: 1.2;">How to Use MedicalAk</h4>
                        <p style="margin: 0.15rem 0 0 0; font-size: 0.8rem; color: rgba(255, 255, 255, 0.9); line-height: 1.2;">Complete Website Guideline — Learn every feature</p>
                    </div>
                </div>
                <a href="guidelines.php" class="btn" style="background: #ffffff; color: #047857; font-weight: 700; font-size: 0.82rem; padding: 0.45rem 0.9rem; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; white-space: nowrap; box-shadow: 0 2px 8px rgba(0,0,0,0.15); flex-shrink: 0;">
                    <span>View Guide</span>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

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
