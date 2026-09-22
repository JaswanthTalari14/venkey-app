<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';

$patient_id = $_SESSION['user_id'];

// Fetch real counts from Database
$app_res = $conn->query("SELECT COUNT(*) as count FROM appointments WHERE patient_id=$patient_id AND status IN ('pending', 'confirmed')");
$active_appointments = $app_res->fetch_assoc()['count'];

$ord_res = $conn->query("SELECT COUNT(*) as count FROM orders WHERE patient_id=$patient_id AND status IN ('pending', 'shipped')");
$active_orders = $ord_res->fetch_assoc()['count'];

$priv_res = $conn->query("SELECT COUNT(*) as count FROM privacy_consultations WHERE patient_id=$patient_id");
$privacy_queries = $priv_res->fetch_assoc()['count'];

// Fetch patient's recent medicine orders
$my_orders = $conn->query("
    SELECT o.id, o.created_at, o.status, o.total_amount, m.name as medicine_name, oi.quantity 
    FROM orders o
    JOIN order_items oi ON o.id = oi.order_id
    JOIN medicines m ON oi.medicine_id = m.id
    WHERE o.patient_id = $patient_id
    ORDER BY o.created_at DESC
    LIMIT 5
");
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <h3 style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php" class="active"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>!</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Manage your health, appointments, and secure consultations.</p>
        
        <div class="features-grid" style="margin-top: 1rem;">
            <div class="feature-card glass-panel" style="padding: 1.5rem;">
                <h4 style="color: var(--primary-color);"><i class="fas fa-clock"></i> Upcoming Appointments</h4>
                <p style="font-size: 2rem; font-weight: bold; margin: 1rem 0;"><?php echo $active_appointments; ?></p>
                <a href="book_consult.php" class="btn btn-outline" style="font-size: 0.8rem;">Book New +</a>
            </div>
            
            <div class="feature-card glass-panel" style="padding: 1.5rem;">
                <h4 style="color: var(--secondary-color);"><i class="fas fa-box-open"></i> Active Medicine Orders</h4>
                <p style="font-size: 2rem; font-weight: bold; margin: 1rem 0;"><?php echo $active_orders; ?></p>
                <a href="medicines.php" class="btn btn-outline" style="font-size: 0.8rem;">Order More</a>
            </div>
            
            <div class="feature-card glass-panel" style="padding: 1.5rem;">
                <h4 style="color: var(--accent);"><i class="fas fa-user-secret"></i> Privacy Queries</h4>
                <p style="font-size: 2rem; font-weight: bold; margin: 1rem 0;"><?php echo $privacy_queries; ?></p>
                <a href="privacy_consult.php" class="btn btn-outline" style="font-size: 0.8rem;">New Query</a>
            </div>
        </div>

        <h3 style="margin-top: 3rem; margin-bottom: 1rem;">Recent Medicine Orders tracker</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Order ID</th>
                        <th style="padding: 1rem;">Medicine</th>
                        <th style="padding: 1rem;">Qty</th>
                        <th style="padding: 1rem;">Total Amount</th>
                        <th style="padding: 1rem;">Date Ordered</th>
                        <th style="padding: 1rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($my_orders && $my_orders->num_rows > 0): ?>
                        <?php while($o = $my_orders->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#<?php echo $o['id']; ?></td>
                                <td style="padding: 1rem; font-weight: bold; color: var(--primary-color);"><?php echo htmlspecialchars($o['medicine_name']); ?></td>
                                <td style="padding: 1rem;"><?php echo $o['quantity']; ?></td>
                                <td style="padding: 1rem; color: var(--secondary-color);">₹<?php echo $o['total_amount']; ?></td>
                                <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($o['created_at'])); ?></td>
                                <td style="padding: 1rem;">
                                    <?php
                                        $status_color = 'var(--text-primary)';
                                        if ($o['status'] == 'pending') $status_color = 'var(--accent)';
                                        if ($o['status'] == 'shipped') $status_color = '#3498db';
                                        if ($o['status'] == 'delivered') $status_color = '#2ed573';
                                        if ($o['status'] == 'cancelled') $status_color = '#ff4757';
                                    ?>
                                    <span style="color: <?php echo $status_color; ?>; font-weight: bold; text-transform: capitalize; background: rgba(255,255,255,0.05); padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.8rem;">
                                        <?php echo $o['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 1rem; text-align: center;">You have not ordered any medicines yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
