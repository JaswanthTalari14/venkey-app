<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = (int)$_SESSION['user_id'];
$patient_name = $_SESSION['name'] ?? 'Patient';

// Fetch Latest Journey Nodes from actual DB
$latest_appt = $conn->query("SELECT a.*, u.name as doctor_name FROM appointments a JOIN users u ON a.doctor_id = u.id WHERE a.patient_id = $patient_id ORDER BY a.created_at DESC LIMIT 1");
$appt_data = ($latest_appt && $latest_appt->num_rows > 0) ? $latest_appt->fetch_assoc() : null;

$latest_rx = $conn->query("SELECT p.*, u.name as doctor_name FROM prescriptions p JOIN users u ON p.doctor_id = u.id WHERE p.patient_id = $patient_id ORDER BY p.created_at DESC LIMIT 1");
$rx_data = ($latest_rx && $latest_rx->num_rows > 0) ? $latest_rx->fetch_assoc() : null;

$latest_ord = $conn->query("SELECT * FROM orders WHERE patient_id = $patient_id ORDER BY created_at DESC LIMIT 1");
$ord_data = ($latest_ord && $latest_ord->num_rows > 0) ? $latest_ord->fetch_assoc() : null;

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Patient Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="health_journey.php" class="active"><i class="fas fa-route"></i> Healthcare Journey</a></li>
            <li><a href="health_vault.php"><i class="fas fa-shield-alt"></i> Health Vault</a></li>
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-route" style="color: var(--primary-color);"></i> Connected Healthcare Journey</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Data-driven lifecycle connecting your symptoms, consultations, prescriptions, pharmacy orders, and health records.</p>

        <!-- Journey Pipeline Steps -->
        <div class="glass-panel" style="padding: 2rem; margin-bottom: 2rem;">
            <div style="display: flex; flex-direction: column; gap: 1.5rem; position: relative; padding-left: 1rem;">

                <!-- Step 1: Symptom / Concern -->
                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #ffab00; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(255,171,0,0.4);">
                        <i class="fas fa-notes-medical"></i>
                    </div>
                    <div style="flex: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px;">
                        <h4 style="margin: 0 0 0.4rem; color: var(--text-primary); font-size: 1.05rem;">1. Symptom & Health Concern</h4>
                        <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 0.8rem;">Log your health query or submit anonymous symptoms.</p>
                        <a href="privacy_consult.php" class="btn btn-outline" style="font-size: 0.78rem;">Submit Symptom Query</a>
                    </div>
                </div>

                <!-- Step 2: Appointment -->
                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #4a90e2; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(74,144,226,0.4);">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div style="flex: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px;">
                        <h4 style="margin: 0 0 0.4rem; color: var(--text-primary); font-size: 1.05rem;">2. Doctor Appointment</h4>
                        <?php if ($appt_data): ?>
                            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 0.8rem;">
                                Active Visit with Dr. <strong><?php echo htmlspecialchars($appt_data['doctor_name']); ?></strong> on <?php echo date('M d, Y', strtotime($appt_data['appointment_date'])); ?>.
                            </p>
                        <?php else: ?>
                            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 0.8rem;">No active appointment scheduled.</p>
                        <?php endif; ?>
                        <a href="book_consult.php" class="btn btn-outline" style="font-size: 0.78rem;">Book Doctor Visit</a>
                    </div>
                </div>

                <!-- Step 3: Consultation & Prescription -->
                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #2ed573; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(46,213,115,0.4);">
                        <i class="fas fa-prescription"></i>
                    </div>
                    <div style="flex: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px;">
                        <h4 style="margin: 0 0 0.4rem; color: var(--text-primary); font-size: 1.05rem;">3. Clinical Prescription & Diagnosis</h4>
                        <?php if ($rx_data): ?>
                            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 0.8rem;">
                                Rx #<?php echo $rx_data['id']; ?> issued by Dr. <?php echo htmlspecialchars($rx_data['doctor_name']); ?>.
                            </p>
                        <?php else: ?>
                            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 0.8rem;">No prescription records yet.</p>
                        <?php endif; ?>
                        <a href="prescription_vault.php" class="btn btn-outline" style="font-size: 0.78rem;">View Digital Rx</a>
                    </div>
                </div>

                <!-- Step 4: Medicine Order & Delivery -->
                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #50e3c2; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(80,227,194,0.4);">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <div style="flex: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px;">
                        <h4 style="margin: 0 0 0.4rem; color: var(--text-primary); font-size: 1.05rem;">4. Pharmacy Order & Doorstep Delivery</h4>
                        <?php if ($ord_data): ?>
                            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 0.8rem;">
                                Order #ORD-<?php echo $ord_data['id']; ?> (₹<?php echo $ord_data['total_amount']; ?>) — Status: <strong><?php echo ucfirst($ord_data['status']); ?></strong>.
                            </p>
                        <?php else: ?>
                            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 0.8rem;">No active medicine orders.</p>
                        <?php endif; ?>
                        <a href="your_orders.php" class="btn btn-outline" style="font-size: 0.78rem;">Track Order</a>
                    </div>
                </div>

                <!-- Step 5: Health Vault Record -->
                <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                    <div style="width: 44px; height: 44px; border-radius: 50%; background: #ff4757; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; flex-shrink: 0; box-shadow: 0 4px 12px rgba(255,71,87,0.4);">
                        <i class="fas fa-vault"></i>
                    </div>
                    <div style="flex: 1; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px;">
                        <h4 style="margin: 0 0 0.4rem; color: var(--text-primary); font-size: 1.05rem;">5. Permanent Health Vault Record</h4>
                        <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 0.8rem;">All records safely stored in your encrypted personal Health Vault.</p>
                        <a href="health_vault.php" class="btn btn-primary" style="font-size: 0.78rem;">Open Health Vault</a>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
