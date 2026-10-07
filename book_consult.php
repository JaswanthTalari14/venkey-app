<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book'])) {
    $doctor_id = (int)$_POST['doctor_id'];
    $date = $conn->real_escape_string($_POST['date']);
    $time = $conn->real_escape_string($_POST['time']);
    $type = $conn->real_escape_string($_POST['type']);
    $notes = $conn->real_escape_string($_POST['notes']);
    $patient_id = (int)$_SESSION['user_id'];

    if ($doctor_id <= 0 || empty($date) || empty($time)) {
        $error = "Please select a valid doctor, date, and time slot.";
    } else {
        // Slot Double Booking Protection Check
        $slot_chk = $conn->query("SELECT id FROM appointments WHERE doctor_id = $doctor_id AND appointment_date = '$date' AND appointment_time = '$time' AND (status IS NULL OR LOWER(status) NOT IN ('cancelled', 'rejected'))");
        if ($slot_chk && $slot_chk->num_rows > 0) {
            $error = "This appointment time slot (" . date('h:i A', strtotime($time)) . " on " . date('M d, Y', strtotime($date)) . ") is already booked. Please choose a different date or time.";
        } else {
            $token_cnt_q = $conn->query("SELECT COUNT(*) as cnt FROM appointments WHERE doctor_id = $doctor_id AND appointment_date = '$date' AND (status IS NULL OR LOWER(status) NOT IN ('cancelled', 'rejected'))");
            $token_num = ($token_cnt_q ? (int)$token_cnt_q->fetch_assoc()['cnt'] : 0) + 1;
            $token_no = 'Q-' . str_pad($token_num, 2, '0', STR_PAD_LEFT);

            $query = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, type, notes, token_no) 
                      VALUES ($patient_id, $doctor_id, '$date', '$time', '$type', '$notes', '$token_no')";
            
            if ($conn->query($query)) {
                $success = "Appointment booked successfully! Your Token Number is " . htmlspecialchars($token_no);
            } else {
                $error = "Failed to book appointment.";
            }
        }
    }
}

// Fetch all doctors
$doctors = $conn->query("SELECT * FROM users WHERE role='doctor'");

// Fetch patient's appointment history
$patient_id_for_history = $_SESSION['user_id'];
$my_appointments = $conn->query("
    SELECT a.id, a.token_no, a.appointment_date, a.appointment_time, a.type, a.status, d.name as doctor_name, d.specialization 
    FROM appointments a
    JOIN users d ON a.doctor_id = d.id
    WHERE a.patient_id = $patient_id_for_history
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
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
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="digital_medical_card.php"><i class="fas fa-id-card"></i> Digital Medical Card</a></li>
            <li><a href="book_consult.php" class="active"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="your_orders.php"><i class="fas fa-boxes"></i> Your Orders</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="refer_earn.php"><i class="fas fa-gift"></i> Refer & Earn</a></li>
            <li><a href="my_wallet.php"><i class="fas fa-wallet"></i> My Wallet</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
            <li><a href="javascript:void(0);" class="pwaInstallBtn"><i class="fas fa-download"></i> Install App</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Book a Consultation</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Schedule an online or doortodoor offline visit with top doctors.</p>
        
        <?php if($error): ?><p style="color: #ff4757; margin-bottom: 1rem;"><?php echo $error; ?></p><?php endif; ?>
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem;"><?php echo $success; ?></p><?php endif; ?>

        <?php
            $patient_active_card = get_patient_active_medical_card($conn, $_SESSION['user_id']);
            $card_settings = get_medical_card_settings($conn);
            if ($patient_active_card):
                $consult_discount_pct = (float)$card_settings['consultation_discount_percent'];
        ?>
        <div class="glass-panel" style="padding: 0.8rem 1.2rem; margin-bottom: 1.5rem; max-width: 600px; border-left: 4px solid #2ed573; background: rgba(46, 213, 115, 0.08); display: flex; align-items: center; gap: 0.8rem; border-radius: 12px;">
            <i class="fas fa-id-card" style="font-size: 1.5rem; color: #2ed573;"></i>
            <div>
                <div style="font-weight: 700; color: #2ed573; font-size: 0.95rem;">Digital Medical Card Active</div>
                <div style="font-size: 0.82rem; color: var(--text-secondary);">Your <?php echo $consult_discount_pct; ?>% Doctor Consultation Discount is automatically applied! (Card: <?php echo htmlspecialchars($patient_active_card['card_number']); ?>)</div>
            </div>
        </div>
        <?php endif; ?>

        <?php
            $pre_doc_id = isset($_GET['doctor_id']) ? (int)$_GET['doctor_id'] : 0;
            $pre_date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
            $pre_time_raw = isset($_GET['time']) ? trim($_GET['time']) : '';
            $pre_time = '';
            if (!empty($pre_time_raw)) {
                $time_ts = strtotime($pre_time_raw);
                if ($time_ts !== false) {
                    $pre_time = date('H:i', $time_ts);
                }
            }
        ?>
        <div class="form-container glass-panel" style="margin: 0; max-width: 600px;">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Select Doctor & Specialization</label>
                    <select name="doctor_id" class="form-control" required>
                        <option value="">-- Choose Doctor --</option>
                        <?php while($doc = $doctors->fetch_assoc()): ?>
                            <option value="<?php echo $doc['id']; ?>" <?php echo ($doc['id'] == $pre_doc_id) ? 'selected' : ''; ?>>
                                Dr. <?php echo htmlspecialchars($doc['name']); ?> (<?php echo htmlspecialchars($doc['specialization'] ?? 'General'); ?>)
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($pre_date); ?>">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Time</label>
                        <input type="time" name="time" class="form-control" required value="<?php echo htmlspecialchars($pre_time); ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Type of Consultation</label>
                    <select name="type" class="form-control" required>
                        <option value="online">Online (Video/Audio)</option>
                        <option value="offline">Offline (Door-to-door Home Visit)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Symptoms / Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Describe your health issue briefly..."></textarea>
                </div>
                
                <button type="submit" name="book" class="btn btn-primary">Book Appointment <i class="fas fa-check-circle"></i></button>
            </form>
        </div>

        <?php
        $queue_data = getPatientQueueData($conn, $_SESSION['user_id']);
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
                    <i class="fas fa-sync-alt" id="bc-queue-spinner"></i> Real DB Queue Data
                </span>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 1rem; margin-bottom: 1rem; text-align: center;">
                <div style="background: rgba(255,255,255,0.03); padding: 1rem 0.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Patient Token</div>
                    <div id="bc-patient-token" style="font-size: 1.8rem; font-weight: 800; color: var(--primary-color); margin-top: 0.2rem;">
                        <?php echo htmlspecialchars($queue_data['patient_token']); ?>
                    </div>
                </div>

                <div style="background: rgba(255,255,255,0.03); padding: 1rem 0.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Current Serving</div>
                    <div id="bc-serving-token" style="font-size: 1.8rem; font-weight: 800; color: #2ed573; margin-top: 0.2rem;">
                        <?php echo htmlspecialchars($queue_data['current_serving_token']); ?>
                    </div>
                </div>

                <div style="background: rgba(255,255,255,0.03); padding: 1rem 0.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Position</div>
                    <div id="bc-position" style="font-size: 0.95rem; font-weight: 700; color: var(--text-primary); margin-top: 0.5rem;">
                        <?php echo htmlspecialchars($queue_data['position']); ?>
                    </div>
                </div>

                <div style="background: rgba(255,255,255,0.03); padding: 1rem 0.5rem; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05);">
                    <div style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase;">Estimated Wait</div>
                    <div id="bc-est-wait" style="font-size: 0.95rem; font-weight: 700; color: var(--accent); margin-top: 0.5rem;">
                        <?php echo htmlspecialchars($queue_data['estimated_wait']); ?>
                    </div>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.25); padding: 0.75rem 1rem; border-radius: 10px; font-size: 0.85rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                    <span style="color: var(--text-secondary);">Doctor:</span>
                    <strong style="color: var(--text-primary); margin-left: 0.3rem;">Dr. <?php echo htmlspecialchars($queue_data['doctor_name']); ?></strong>
                    <span style="color: var(--text-secondary); font-size: 0.75rem;">(<?php echo htmlspecialchars($queue_data['specialization']); ?>)</span>
                </div>
                <div>
                    <span style="color: var(--text-secondary); margin-right: 0.4rem;">Status:</span>
                    <span id="bc-status-badge" style="padding: 0.25rem 0.7rem; background: rgba(52, 152, 219, 0.2); color: #3498db; border-radius: 8px; font-weight: 700; font-size: 0.8rem;">
                        <?php echo htmlspecialchars($queue_data['status']); ?>
                    </span>
                </div>
            </div>
        </div>

        <script>
        (function() {
            function refreshBcQueue() {
                const spinner = document.getElementById('bc-queue-spinner');
                if (spinner) spinner.classList.add('fa-spin');
                fetch('api_patient_features.php?action=get_queue_tracker&appointment_id=<?php echo $queue_data['appointment_id']; ?>')
                    .then(r => r.json())
                    .then(res => {
                        if (spinner) spinner.classList.remove('fa-spin');
                        if (res.success && res.data && res.data.has_appointment) {
                            const d = res.data;
                            if (document.getElementById('bc-patient-token')) document.getElementById('bc-patient-token').innerText = d.patient_token;
                            if (document.getElementById('bc-serving-token')) document.getElementById('bc-serving-token').innerText = d.current_serving_token;
                            if (document.getElementById('bc-position')) document.getElementById('bc-position').innerText = d.position;
                            if (document.getElementById('bc-est-wait')) document.getElementById('bc-est-wait').innerText = d.estimated_wait;
                            if (document.getElementById('bc-status-badge')) document.getElementById('bc-status-badge').innerText = d.status;
                        }
                    })
                    .catch(() => { if (spinner) spinner.classList.remove('fa-spin'); });
            }
            setInterval(refreshBcQueue, 15000);
        })();
        </script>
        <?php endif; ?>

        <h3 style="margin-top: 3rem; margin-bottom: 1rem;">Your Appointment History</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Token</th>
                        <th style="padding: 1rem;">Doctor</th>
                        <th style="padding: 1rem;">Specialization</th>
                        <th style="padding: 1rem;">Date & Time</th>
                        <th style="padding: 1rem;">Type</th>
                        <th style="padding: 1rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($my_appointments && $my_appointments->num_rows > 0): ?>
                        <?php while($a = $my_appointments->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;"><span style="padding: 0.2rem 0.5rem; background: rgba(74, 144, 226, 0.15); color: var(--primary-color); border-radius: 6px; font-weight: bold; font-size: 0.85rem;"><?php echo htmlspecialchars($a['token_no'] ?: 'Q-01'); ?></span></td>
                                <td style="padding: 1rem; font-weight: bold; color: var(--primary-color);">Dr. <?php echo htmlspecialchars($a['doctor_name']); ?></td>
                                <td style="padding: 1rem; font-size: 0.9rem; color: var(--text-secondary);"><?php echo htmlspecialchars($a['specialization'] ?? 'General Practitioner'); ?></td>
                                <td style="padding: 1rem;"><?php echo date('M d, Y', strtotime($a['appointment_date'])); ?> @ <?php echo date('h:i A', strtotime($a['appointment_time'])); ?></td>
                                <td style="padding: 1rem; text-transform: capitalize; color: var(--secondary-color);"><?php echo $a['type']; ?></td>
                                <td style="padding: 1rem;">
                                    <?php
                                        $status_color = 'var(--text-primary)';
                                        if ($a['status'] == 'pending') $status_color = 'var(--accent)';
                                        if ($a['status'] == 'confirmed') $status_color = '#3498db';
                                        if ($a['status'] == 'completed') $status_color = '#2ed573';
                                        if ($a['status'] == 'cancelled') $status_color = '#ff4757';
                                    ?>
                                    <span style="color: <?php echo $status_color; ?>; font-weight: bold; text-transform: capitalize; background: rgba(255,255,255,0.05); padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.8rem;">
                                        <?php echo $a['status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 1rem; text-align: center;">You have no scheduled appointments yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
