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
    $doctor_id = $_POST['doctor_id'];
    $date = $conn->real_escape_string($_POST['date']);
    $time = $conn->real_escape_string($_POST['time']);
    $type = $_POST['type'];
    $notes = $conn->real_escape_string($_POST['notes']);
    $patient_id = $_SESSION['user_id'];
    
    $query = "INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, type, notes) 
              VALUES ($patient_id, $doctor_id, '$date', '$time', '$type', '$notes')";
    
    if ($conn->query($query)) {
        $success = "Appointment booked successfully!";
    } else {
        $error = "Failed to book appointment.";
    }
}

// Fetch all doctors
$doctors = $conn->query("SELECT * FROM users WHERE role='doctor'");

// Fetch patient's appointment history
$patient_id_for_history = $_SESSION['user_id'];
$my_appointments = $conn->query("
    SELECT a.id, a.appointment_date, a.appointment_time, a.type, a.status, d.name as doctor_name, d.specialization 
    FROM appointments a
    JOIN users d ON a.doctor_id = d.id
    WHERE a.patient_id = $patient_id_for_history
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <h3 style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="book_consult.php" class="active"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Book a Consultation</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Schedule an online or doortodoor offline visit with top doctors.</p>
        
        <?php if($error): ?><p style="color: #ff4757; margin-bottom: 1rem;"><?php echo $error; ?></p><?php endif; ?>
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem;"><?php echo $success; ?></p><?php endif; ?>

        <div class="form-container glass-panel" style="margin: 0; max-width: 600px;">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Select Doctor & Specialization</label>
                    <select name="doctor_id" class="form-control" required>
                        <option value="">-- Choose Doctor --</option>
                        <?php while($doc = $doctors->fetch_assoc()): ?>
                            <option value="<?php echo $doc['id']; ?>">Dr. <?php echo htmlspecialchars($doc['name']); ?> (<?php echo htmlspecialchars($doc['specialization'] ?? 'General'); ?>)</option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div style="display: flex; gap: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label>Date</label>
                        <input type="date" name="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>Time</label>
                        <input type="time" name="time" class="form-control" required>
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

        <h3 style="margin-top: 3rem; margin-bottom: 1rem;">Your Appointment History</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
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
                        <tr><td colspan="5" style="padding: 1rem; text-align: center;">You have no scheduled appointments yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
