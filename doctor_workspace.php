<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit;
}

$doctor_id = (int)$_SESSION['user_id'];
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : 0;
$appt_id = isset($_GET['appointment_id']) ? (int)$_GET['appointment_id'] : 0;

if ($patient_id <= 0) {
    header("Location: doctor_appointments.php");
    exit;
}

// Fetch Patient Info
$p_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$p_stmt->bind_param("i", $patient_id);
$p_stmt->execute();
$patient = $p_stmt->get_result()->fetch_assoc();

if (!$patient) {
    die("Patient not found.");
}

// Fetch Doctor Prep Summary if available
$prep_stmt = $conn->prepare("SELECT * FROM doctor_prep_summaries WHERE patient_id = ? ORDER BY created_at DESC LIMIT 1");
$prep_stmt->bind_param("i", $patient_id);
$prep_stmt->execute();
$prep_summary = $prep_stmt->get_result()->fetch_assoc();

// Fetch Patient's Medical Documents
$docs_q = $conn->query("SELECT * FROM medical_documents WHERE patient_id = $patient_id ORDER BY created_at DESC");

// Fetch Past Consultations Notes
$notes_q = $conn->query("SELECT c.*, u.name as doctor_name FROM consultation_notes c JOIN users u ON c.doctor_id = u.id WHERE c.patient_id = $patient_id ORDER BY c.created_at DESC");

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Doctor Workspace Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Consultation Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Workspace Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="doctor_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="doctor_appointments.php"><i class="fas fa-calendar-day"></i> Today's Schedule</a></li>
            <li><a href="doctor_workspace.php?patient_id=<?php echo $patient_id; ?>&appointment_id=<?php echo $appt_id; ?>" class="active"><i class="fas fa-user-md"></i> Patient Consultation</a></li>
            <li><a href="doctor_referrals.php"><i class="fas fa-exchange-alt"></i> Patient Referrals</a></li>
            <li><a href="doctor_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0;"><i class="fas fa-user-md" style="color: var(--primary-color);"></i> Patient Consultation Workspace</h2>
                <p style="color: var(--text-secondary); margin: 0.3rem 0 0;">Consultation for <strong><?php echo htmlspecialchars($patient['name']); ?></strong> (Phone: <?php echo htmlspecialchars($patient['phone']); ?>)</p>
            </div>
            <a href="doctor_appointments.php" class="btn btn-outline" style="font-size: 0.85rem;"><i class="fas fa-arrow-left"></i> Back to Schedule</a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
            <!-- Left Column: Patient Overview & Past Records -->
            <div>
                <!-- Patient Card -->
                <div class="glass-panel" style="padding: 1.25rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-top: 0; color: var(--primary-color);"><i class="fas fa-id-card"></i> Patient Overview</h4>
                    <p style="font-size: 0.9rem; color: var(--text-primary); margin: 0.5rem 0;">
                        <strong>Name:</strong> <?php echo htmlspecialchars($patient['name']); ?><br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($patient['email']); ?><br>
                        <strong>Blood Group:</strong> <?php echo htmlspecialchars($patient['blood_group'] ?: 'Not specified'); ?><br>
                        <strong>Allergies:</strong> <?php echo htmlspecialchars($patient['allergies'] ?: 'None listed'); ?>
                    </p>
                </div>

                <!-- Pre-appointment Preparation Summary -->
                <?php if ($prep_summary): ?>
                    <div class="glass-panel" style="padding: 1.25rem; margin-bottom: 1.5rem; border-left: 4px solid var(--accent);">
                        <h4 style="margin-top: 0; color: var(--accent);"><i class="fas fa-clipboard-list"></i> AI Doctor Preparation Summary</h4>
                        <p style="font-size: 0.85rem; color: var(--text-primary); margin-bottom: 0.4rem;"><strong>Main Concern:</strong> <?php echo htmlspecialchars($prep_summary['main_concern']); ?></p>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.4rem;"><strong>Duration & Severity:</strong> <?php echo htmlspecialchars($prep_summary['duration'] . ' • ' . $prep_summary['severity']); ?></p>
                        <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0;"><strong>Current Medicines:</strong> <?php echo htmlspecialchars($prep_summary['current_medicines'] ?: 'None specified'); ?></p>
                    </div>
                <?php endif; ?>

                <!-- Medical Documents & Lab Reports -->
                <div class="glass-panel" style="padding: 1.25rem; margin-bottom: 1.5rem;">
                    <h4 style="margin-top: 0; color: var(--secondary-color);"><i class="fas fa-folder-open"></i> Patient Reports & Documents</h4>
                    <div style="display: flex; flex-direction: column; gap: 0.6rem; max-height: 250px; overflow-y: auto;">
                        <?php if ($docs_q && $docs_q->num_rows > 0): ?>
                            <?php while($d = $docs_q->fetch_assoc()): ?>
                                <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.03); padding: 0.6rem 0.8rem; border-radius: 8px;">
                                    <div>
                                        <div style="font-size: 0.85rem; font-weight: bold; color: var(--text-primary);"><?php echo htmlspecialchars($d['title']); ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-secondary);"><?php echo htmlspecialchars($d['category']) . ' • ' . date('M d, Y', strtotime($d['created_at'])); ?></div>
                                    </div>
                                    <a href="<?php echo htmlspecialchars($d['file_path']); ?>" target="_blank" class="btn btn-outline" style="font-size: 0.7rem; padding: 0.25rem 0.5rem;">Open</a>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p style="font-size: 0.85rem; color: var(--text-secondary);">No medical documents uploaded.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Today's Consultation Form & Digital Rx Generator -->
            <div>
                <div class="glass-panel" style="padding: 1.5rem;">
                    <h3 style="margin-top: 0; color: var(--primary-color);"><i class="fas fa-stethoscope"></i> Today's Clinical Consultation</h3>

                    <form id="consultationForm">
                        <input type="hidden" name="appointment_id" value="<?php echo $appt_id; ?>">
                        <input type="hidden" name="patient_id" value="<?php echo $patient_id; ?>">

                        <div class="form-group">
                            <label>Chief Complaint *</label>
                            <input type="text" name="chief_complaint" class="form-control" required placeholder="e.g. High fever, dry cough for 3 days">
                        </div>

                        <div class="form-group">
                            <label>Clinical Notes & Assessment</label>
                            <textarea name="clinical_notes" class="form-control" rows="3" placeholder="Enter clinical observations, vitals, exam details..."></textarea>
                        </div>

                        <div class="form-group">
                            <label>Diagnosis *</label>
                            <input type="text" name="diagnosis" class="form-control" required placeholder="e.g. Acute Upper Respiratory Tract Infection">
                        </div>

                        <h4 style="margin: 1.5rem 0 0.8rem; color: var(--secondary-color);"><i class="fas fa-pills"></i> Prescribed Medicines</h4>
                        <div id="medicineList" style="display: flex; flex-direction: column; gap: 0.8rem; margin-bottom: 1rem;">
                            <!-- Medicine Rows -->
                            <div class="med-row" style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 8px;">
                                <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;">
                                    <input type="text" name="medicines[0][name]" class="form-control" placeholder="Medicine Name" required>
                                    <input type="text" name="medicines[0][dosage]" class="form-control" placeholder="Dosage (e.g. 500mg)">
                                    <input type="text" name="medicines[0][frequency]" class="form-control" placeholder="Freq (e.g. 1-0-1)">
                                    <input type="text" name="medicines[0][duration]" class="form-control" placeholder="Duration (e.g. 5 days)">
                                </div>
                                <input type="text" name="medicines[0][instructions]" class="form-control" placeholder="Instructions (e.g. Take after meal)">
                            </div>
                        </div>

                        <button type="button" onclick="addMedicineRow()" class="btn btn-outline" style="font-size: 0.8rem; margin-bottom: 1.5rem;">
                            <i class="fas fa-plus"></i> Add Another Medicine
                        </button>

                        <div class="form-group">
                            <label>Follow-up Date (Optional)</label>
                            <input type="date" name="followup_date" class="form-control" min="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; font-size: 1rem; padding: 0.8rem;">
                            <i class="fas fa-check-circle"></i> Save Consultation & Issue Rx
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
let medCount = 1;
function addMedicineRow() {
    const container = document.getElementById('medicineList');
    const div = document.createElement('div');
    div.className = 'med-row';
    div.style.cssText = 'background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 0.8rem; border-radius: 8px; margin-top: 0.5rem;';
    div.innerHTML = `
        <div style="display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 0.5rem; margin-bottom: 0.5rem;">
            <input type="text" name="medicines[${medCount}][name]" class="form-control" placeholder="Medicine Name" required>
            <input type="text" name="medicines[${medCount}][dosage]" class="form-control" placeholder="Dosage">
            <input type="text" name="medicines[${medCount}][frequency]" class="form-control" placeholder="Freq">
            <input type="text" name="medicines[${medCount}][duration]" class="form-control" placeholder="Duration">
        </div>
        <input type="text" name="medicines[${medCount}][instructions]" class="form-control" placeholder="Instructions">
    `;
    container.appendChild(div);
    medCount++;
}

document.getElementById('consultationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'save_consultation');

    fetch('api_doctor_features.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(res.message);
            window.location.href = 'doctor_appointments.php';
        } else {
            alert(res.message);
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
