<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctor_id = $_SESSION['user_id'];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $appointment_id = $_POST['appointment_id'];
    $new_status = $_POST['status'];
    $conn->query("UPDATE appointments SET status='$new_status' WHERE id=$appointment_id AND doctor_id=$doctor_id");
    $success = "Appointment status updated successfully.";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_prescription'])) {
    $patient_id = (int)$_POST['patient_id'];
    $appointment_id = (int)$_POST['appointment_id'];
    $notes = trim($conn->real_escape_string($_POST['notes'] ?? ''));
    $med_names = $_POST['med_name'] ?? [];
    $dosages = $_POST['dosage'] ?? [];
    $frequencies = $_POST['frequency'] ?? [];
    $durations = $_POST['duration'] ?? [];

    $stmt = $conn->prepare("INSERT INTO prescriptions (patient_id, doctor_id, appointment_id, consultation_date, notes) VALUES (?, ?, ?, CURDATE(), ?)");
    $stmt->bind_param("iiis", $patient_id, $doctor_id, $appointment_id, $notes);
    if ($stmt->execute()) {
        $p_id = $stmt->insert_id;
        for ($i = 0; $i < count($med_names); $i++) {
            $m = trim($med_names[$i]);
            $d = trim($dosages[$i] ?? '1 Tab');
            $f = trim($frequencies[$i] ?? 'Twice daily');
            $dur = trim($durations[$i] ?? '5 Days');
            if (!empty($m)) {
                $i_stmt = $conn->prepare("INSERT INTO prescription_items (prescription_id, medicine_name, dosage, frequency, duration) VALUES (?, ?, ?, ?, ?)");
                $i_stmt->bind_param("issss", $p_id, $m, $d, $f, $dur);
                $i_stmt->execute();
            }
        }
        $conn->query("UPDATE appointments SET status='completed' WHERE id=$appointment_id AND doctor_id=$doctor_id");
        $success = "Digital Prescription issued successfully!";
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_notes'])) {
    $patient_id = (int)$_POST['patient_id'];
    $appointment_id = (int)$_POST['appointment_id'];
    $notes_content = trim($conn->real_escape_string($_POST['notes_content'] ?? ''));
    $is_shared = isset($_POST['is_shared']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO consultation_notes (doctor_id, patient_id, appointment_id, notes, is_shared_with_patient) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iiisi", $doctor_id, $patient_id, $appointment_id, $notes_content, $is_shared);
    $stmt->execute();
    $success = "Consultation notes saved successfully.";
}

$appointments = $conn->query("
    SELECT a.id, a.patient_id, a.appointment_date, a.appointment_time, a.type, a.status, a.notes, p.name as patient_name, p.phone as patient_phone
    FROM appointments a
    JOIN users p ON a.patient_id = p.id
    WHERE a.doctor_id = $doctor_id
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Doctor Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Doctor Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Doctor Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="doctor_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="doctor_appointments.php" class="active"><i class="fas fa-calendar-day"></i> Appointments</a></li>
            <li><a href="doctor_referrals.php"><i class="fas fa-exchange-alt"></i> Patient Referrals</a></li>
            <li><a href="doctor_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="doctor_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="doctor_queries.php"><i class="fas fa-user-secret"></i> Anonymous Queries</a></li>
            <li><a href="profile.php"><i class="fas fa-cog"></i> Settings & Availability</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>My Appointments & Digital Prescriptions</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Manage consultations, issue digital prescriptions, and record consultation notes.</p>

        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem; padding: 0.8rem; background: rgba(46, 213, 115, 0.1); border-radius: 8px; border-left: 4px solid #2ed573;"><?php echo htmlspecialchars($success); ?></p><?php endif; ?>

        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Patient Name</th>
                        <th style="padding: 1rem;">Contact</th>
                        <th style="padding: 1rem;">Date & Time</th>
                        <th style="padding: 1rem;">Type</th>
                        <th style="padding: 1rem;">Status</th>
                        <th style="padding: 1rem;">Clinical Tools</th>
                        <th style="padding: 1rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($appointments && $appointments->num_rows > 0): ?>
                        <?php while($a = $appointments->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem; font-weight: bold; color: var(--text-primary);"><?php echo htmlspecialchars($a['patient_name']); ?></td>
                                <td style="padding: 1rem;">
                                    <a href="tel:<?php echo htmlspecialchars($a['patient_phone']); ?>" style="color: var(--primary-color); font-size: 0.9rem;"><i class="fas fa-phone"></i> Call</a>
                                </td>
                                <td style="padding: 1rem; color: var(--secondary-color); font-size: 0.9rem;">
                                    <?php echo date('M d', strtotime($a['appointment_date'])); ?> @ <?php echo date('h:i A', strtotime($a['appointment_time'])); ?>
                                </td>
                                <td style="padding: 1rem; text-transform: capitalize; font-size: 0.9rem;"><?php echo $a['type']; ?></td>
                                <td style="padding: 1rem;">
                                    <span style="color: <?php echo $a['status'] == 'pending' ? 'var(--accent)' : 'var(--text-primary)'; ?>; text-transform: capitalize; font-weight: 600; font-size: 0.85rem;"><?php echo $a['status']; ?></span>
                                </td>
                                <td style="padding: 1rem;">
                                    <div style="display: flex; gap: 0.4rem; flex-wrap: wrap;">
                                        <button type="button" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.55rem; color: var(--secondary-color); border-color: var(--secondary-color);" onclick="openPrescriptionModal(<?php echo $a['id']; ?>, <?php echo $a['patient_id']; ?>, '<?php echo htmlspecialchars(addslashes($a['patient_name'])); ?>')">
                                            <i class="fas fa-file-medical"></i> Rx
                                        </button>
                                        <button type="button" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.25rem 0.55rem;" onclick="openNotesModal(<?php echo $a['id']; ?>, <?php echo $a['patient_id']; ?>, '<?php echo htmlspecialchars(addslashes($a['patient_name'])); ?>')">
                                            <i class="fas fa-sticky-note"></i> Notes
                                        </button>
                                    </div>
                                </td>
                                <td style="padding: 1rem;">
                                    <form method="POST" style="display: flex; gap: 0.5rem;">
                                        <input type="hidden" name="appointment_id" value="<?php echo $a['id']; ?>">
                                        <select name="status" class="form-control" style="width: auto; padding: 0.3rem; font-size: 0.82rem;" required>
                                            <option value="pending" <?php if($a['status'] == 'pending') echo 'selected'; ?>>Pending</option>
                                            <option value="confirmed" <?php if($a['status'] == 'confirmed') echo 'selected'; ?>>Confirm</option>
                                            <option value="completed" <?php if($a['status'] == 'completed') echo 'selected'; ?>>Completed</option>
                                            <option value="cancelled" <?php if($a['status'] == 'cancelled') echo 'selected'; ?>>Cancel</option>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-primary" style="padding: 0.3rem 0.7rem; font-size: 0.8rem;">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="padding: 1.5rem; text-align: center; color: var(--text-secondary);">No appointments found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<!-- ========================================================= -->
<!-- FEATURE 12: DIGITAL PRESCRIPTION BUILDER MODAL -->
<!-- ========================================================= -->
<div id="rxModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto;">
    <div class="glass-panel" style="background: var(--darker-bg); border: 1px solid var(--glass-border); width: 100%; max-width: 600px; padding: 1.8rem; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.7); max-height: 90vh; overflow-y: auto;">
        
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.2rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.8rem;">
            <h3 style="color: var(--text-primary); margin: 0; font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-file-prescription" style="color: var(--primary-color);"></i> Issue Digital Prescription
            </h3>
            <button type="button" onclick="closePrescriptionModal()" style="background: transparent; border: none; color: var(--text-secondary); font-size: 1.4rem; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="save_prescription" value="1">
            <input type="hidden" id="rx_appointment_id" name="appointment_id" value="">
            <input type="hidden" id="rx_patient_id" name="patient_id" value="">

            <p style="color: var(--secondary-color); font-weight: 600; margin-bottom: 1rem;" id="rxPatientLabel">Patient: </p>

            <div id="rxMedicinesContainer" style="display: flex; flex-direction: column; gap: 0.8rem; margin-bottom: 1.2rem;">
                <div class="rx-row" style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 0.5rem;">
                    <input type="text" name="med_name[]" class="form-control" placeholder="Medicine Name" required>
                    <input type="text" name="dosage[]" class="form-control" placeholder="Dosage (1 Tab)">
                    <input type="text" name="frequency[]" class="form-control" placeholder="Freq (Twice/day)">
                    <input type="text" name="duration[]" class="form-control" placeholder="Duration (5 Days)">
                </div>
            </div>

            <button type="button" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.3rem 0.8rem; margin-bottom: 1.2rem;" onclick="addRxRow()">
                <i class="fas fa-plus"></i> Add Another Medicine
            </button>

            <div class="form-group">
                <label>Special Instructions / Diagnosis</label>
                <textarea name="notes" class="form-control" rows="2" placeholder="Take after food, drink plenty of water..."></textarea>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.8rem; margin-top: 1rem;">
                <button type="button" onclick="closePrescriptionModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Issue Prescription</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================= -->
<!-- FEATURE 8: CONSULTATION NOTES MODAL -->
<!-- ========================================================= -->
<div id="notesModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem;">
    <div class="glass-panel" style="background: var(--darker-bg); border: 1px solid var(--glass-border); width: 100%; max-width: 500px; padding: 1.5rem; border-radius: 18px; box-shadow: 0 25px 60px rgba(0,0,0,0.7);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.6rem;">
            <h3 style="color: var(--text-primary); margin: 0; font-size: 1.15rem;"><i class="fas fa-sticky-note" style="color: var(--accent);"></i> Add Consultation Notes</h3>
            <button type="button" onclick="closeNotesModal()" style="background: transparent; border: none; color: var(--text-secondary); font-size: 1.3rem; cursor: pointer;">&times;</button>
        </div>

        <form method="POST" action="">
            <input type="hidden" name="save_notes" value="1">
            <input type="hidden" id="note_appointment_id" name="appointment_id" value="">
            <input type="hidden" id="note_patient_id" name="patient_id" value="">

            <p style="color: var(--secondary-color); font-weight: 600; margin-bottom: 0.8rem;" id="notePatientLabel">Patient: </p>

            <div class="form-group">
                <label>Clinical Notes / Observation</label>
                <textarea name="notes_content" class="form-control" rows="4" placeholder="Record patient symptoms, diagnosis summary, or private doctor notes..." required></textarea>
            </div>

            <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="checkbox" name="is_shared" id="is_shared_chk" value="1" checked>
                <label for="is_shared_chk" style="margin: 0; font-size: 0.88rem; color: var(--text-secondary);">Share notes with Patient</label>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 0.8rem; margin-top: 1rem;">
                <button type="button" onclick="closeNotesModal()" class="btn btn-outline">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Notes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPrescriptionModal(appId, patientId, name) {
    document.getElementById('rx_appointment_id').value = appId;
    document.getElementById('rx_patient_id').value = patientId;
    document.getElementById('rxPatientLabel').innerText = 'Patient: ' + name;
    document.getElementById('rxModal').style.display = 'flex';
}
function closePrescriptionModal() { document.getElementById('rxModal').style.display = 'none'; }

function addRxRow() {
    var container = document.getElementById('rxMedicinesContainer');
    var div = document.createElement('div');
    div.className = 'rx-row';
    div.style.cssText = 'display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 0.5rem;';
    div.innerHTML = '<input type="text" name="med_name[]" class="form-control" placeholder="Medicine Name" required><input type="text" name="dosage[]" class="form-control" placeholder="Dosage"><input type="text" name="frequency[]" class="form-control" placeholder="Freq"><input type="text" name="duration[]" class="form-control" placeholder="Duration">';
    container.appendChild(div);
}

function openNotesModal(appId, patientId, name) {
    document.getElementById('note_appointment_id').value = appId;
    document.getElementById('note_patient_id').value = patientId;
    document.getElementById('notePatientLabel').innerText = 'Patient: ' + name;
    document.getElementById('notesModal').style.display = 'flex';
}
function closeNotesModal() { document.getElementById('notesModal').style.display = 'none'; }
</script>

<?php include 'includes/footer.php'; ?>

