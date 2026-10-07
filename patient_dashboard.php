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
        <button class="sidebar-toggle" aria-label="Toggle Patient Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Patient Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php" class="active"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="digital_medical_card.php"><i class="fas fa-id-card"></i> Digital Medical Card</a></li>
            <li><a href="health_vault.php"><i class="fas fa-vault"></i> Health Vault</a></li>
            <li><a href="health_journey.php"><i class="fas fa-route"></i> Healthcare Journey</a></li>
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="your_orders.php"><i class="fas fa-boxes"></i> Your Orders</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="refer_earn.php"><i class="fas fa-gift"></i> Refer & Earn</a></li>
            <li><a href="my_wallet.php"><i class="fas fa-wallet"></i> My Wallet</a></li>
            <li><a href="customer_support.php"><i class="fas fa-headset"></i> Customer Support & Tickets</a></li>
            <li><a href="create_ticket.php"><i class="fas fa-plus-circle"></i> Create Support Ticket</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
            <li><a href="javascript:void(0);" class="pwaInstallBtn"><i class="fas fa-download"></i> Install App</a></li>
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

            <div class="feature-card glass-panel" style="padding: 1.5rem; border-left: 4px solid var(--primary-color);">
                <h4 style="color: var(--primary-color);"><i class="fas fa-headset"></i> Customer Support & Tickets</h4>
                <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 0.5rem 0 1rem 0;">Direct WhatsApp help & tracked support tickets.</p>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="create_ticket.php" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;"><i class="fas fa-plus-circle"></i> Create Ticket</a>
                    <a href="customer_support.php" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;"><i class="fas fa-headset"></i> Support Center</a>
                </div>
            </div>
        </div>

        <?php
        $queue_data = getPatientQueueData($conn, $patient_id);
        if ($queue_data['has_appointment']):
        ?>
        <!-- DIGITAL QUEUE TRACKING (Feature Group 10) -->
        <div class="glass-panel" id="live-queue-card" style="margin-top: 2rem; padding: 1.5rem; border: 1px solid rgba(74, 144, 226, 0.3); background: linear-gradient(135deg, rgba(16, 26, 43, 0.85), rgba(22, 33, 62, 0.95)); border-radius: 16px;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.08); padding-bottom: 0.8rem; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <h3 style="margin: 0; font-size: 1.15rem; color: #3498db; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="fas fa-digital-tachograph" style="color: #2ed573;"></i> 
                    Live Digital Queue Tracker
                </h3>
                <span style="font-size: 0.75rem; color: var(--text-secondary); background: rgba(255,255,255,0.05); padding: 0.25rem 0.6rem; border-radius: 12px; display: inline-flex; align-items: center; gap: 0.4rem;">
                    <i class="fas fa-sync-alt" id="queue-spinner"></i> Real DB Queue Data
                </span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; margin-bottom: 1rem; text-align: center;">
                <!-- Patient Token -->
                <div style="background: rgba(255,255,255,0.03); padding: 1rem 0.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Patient Token</div>
                    <div id="q-patient-token" style="font-size: 1.8rem; font-weight: 800; color: var(--primary-color); margin-top: 0.2rem;">
                        <?php echo htmlspecialchars($queue_data['patient_token']); ?>
                    </div>
                </div>

                <!-- Current Serving Token -->
                <div style="background: rgba(255,255,255,0.03); padding: 1rem 0.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Current Serving</div>
                    <div id="q-serving-token" style="font-size: 1.8rem; font-weight: 800; color: #2ed573; margin-top: 0.2rem;">
                        <?php echo htmlspecialchars($queue_data['current_serving_token']); ?>
                    </div>
                </div>

                <!-- Position -->
                <div style="background: rgba(255,255,255,0.03); padding: 1rem 0.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Position</div>
                    <div id="q-position" style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin-top: 0.5rem;">
                        <?php echo htmlspecialchars($queue_data['position']); ?>
                    </div>
                </div>

                <!-- Estimated Position / Wait -->
                <div style="background: rgba(255,255,255,0.03); padding: 1rem 0.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Estimated Wait</div>
                    <div id="q-est-wait" style="font-size: 0.95rem; font-weight: 700; color: var(--accent); margin-top: 0.5rem;">
                        <?php echo htmlspecialchars($queue_data['estimated_wait']); ?>
                    </div>
                </div>
            </div>

            <!-- Doctor Info & Consultation Status -->
            <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.25); padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.85rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <span style="color: var(--text-secondary);">Doctor:</span>
                    <strong style="color: var(--text-primary); margin-left: 0.3rem;">Dr. <?php echo htmlspecialchars($queue_data['doctor_name']); ?></strong>
                    <span style="color: var(--text-secondary); font-size: 0.75rem;">(<?php echo htmlspecialchars($queue_data['specialization']); ?>)</span>
                    <span style="margin-left: 0.6rem; font-size: 0.75rem; color: var(--secondary-color);"><?php echo $queue_data['appointment_date']; ?> @ <?php echo $queue_data['appointment_time']; ?></span>
                </div>
                <div>
                    <span style="color: var(--text-secondary); margin-right: 0.4rem;">Consultation Status:</span>
                    <span id="q-status-badge" style="padding: 0.25rem 0.7rem; background: rgba(52, 152, 219, 0.2); color: #3498db; border-radius: 8px; font-weight: 700; font-size: 0.8rem; text-transform: capitalize;">
                        <?php echo htmlspecialchars($queue_data['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <script>
        (function() {
            function refreshLiveQueue() {
                const spinner = document.getElementById('queue-spinner');
                if (spinner) spinner.classList.add('fa-spin');
                fetch('api_patient_features.php?action=get_queue_tracker&appointment_id=<?php echo $queue_data['appointment_id']; ?>')
                    .then(r => r.json())
                    .then(res => {
                        if (spinner) spinner.classList.remove('fa-spin');
                        if (res.success && res.data && res.data.has_appointment) {
                            const d = res.data;
                            const patEl = document.getElementById('q-patient-token');
                            const srvEl = document.getElementById('q-serving-token');
                            const posEl = document.getElementById('q-position');
                            const estEl = document.getElementById('q-est-wait');
                            const stEl = document.getElementById('q-status-badge');

                            if (patEl) patEl.innerText = d.patient_token;
                            if (srvEl) srvEl.innerText = d.current_serving_token;
                            if (posEl) posEl.innerText = d.position;
                            if (estEl) estEl.innerText = d.estimated_wait;
                            if (stEl) stEl.innerText = d.status;
                        }
                    })
                    .catch(() => { if (spinner) spinner.classList.remove('fa-spin'); });
            }
            // Poll real database every 15 seconds (no fake movement)
            setInterval(refreshLiveQueue, 15000);
        })();
        </script>
        <?php endif; ?>

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
