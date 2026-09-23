<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['book_test'])) {
    $rmp_id = $_POST['rmp_id'];
    $test_name = $conn->real_escape_string($_POST['test_name']);
    $date = $conn->real_escape_string($_POST['date']);
    $patient_id = $_SESSION['user_id'];
    
    $query = "INSERT INTO test_bookings (patient_id, rmp_id, test_name, scheduled_date) 
              VALUES ($patient_id, $rmp_id, '$test_name', '$date')";
    
    if ($conn->query($query)) {
        $success = "Lab test booked successfully! An RMP will contact you shortly.";
    } else {
        $error = "Failed to book lab test.";
    }
}

// Fetch all RMPs
$rmps = $conn->query("SELECT * FROM users WHERE role='rmp'");

// Fetch patient's test history
$patient_id_for_history = $_SESSION['user_id'];
$my_tests = $conn->query("
    SELECT t.id, t.test_name, t.scheduled_date, t.status, t.result_path, r.name as rmp_name 
    FROM test_bookings t
    JOIN users r ON t.rmp_id = r.id
    WHERE t.patient_id = $patient_id_for_history
    ORDER BY t.scheduled_date DESC
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
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php" class="active"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="refer_earn.php"><i class="fas fa-gift"></i> Refer & Earn</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>RMP Lab Tests & Checkups</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Book basic health checkups, blood tests, and diagnostics directly with Registered Medical Practitioners.</p>
        
        <?php if($error): ?><p style="color: #ff4757; margin-bottom: 1rem;"><?php echo $error; ?></p><?php endif; ?>
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem;"><?php echo $success; ?></p><?php endif; ?>

        <div class="form-container glass-panel" style="margin: 0; max-width: 600px;">
            <form method="POST" action="">
                <div class="form-group">
                    <label>Select Registered Medical Practitioner (RMP)</label>
                    <select name="rmp_id" class="form-control" required>
                        <option value="">-- Choose RMP --</option>
                        <?php if ($rmps && $rmps->num_rows > 0): ?>
                            <?php while($rmp = $rmps->fetch_assoc()): ?>
                                <option value="<?php echo $rmp['id']; ?>"><?php echo htmlspecialchars($rmp['name']); ?> (Phone: <?php echo htmlspecialchars($rmp['phone']); ?>)</option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" disabled>No RMPs registered currently.</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Select Lab Test / Checkup</label>
                    <select name="test_name" class="form-control" required>
                        <option value="Complete Blood Count (CBC)">Complete Blood Count (CBC)</option>
                        <option value="Blood Sugar Fasting">Blood Sugar Fasting</option>
                        <option value="Lipid Profile">Lipid Profile</option>
                        <option value="Thyroid Profile (TSH)">Thyroid Profile (TSH)</option>
                        <option value="Urine Routine">Urine Routine</option>
                        <option value="General Health Checkup">General Health Checkup (Vitals & BP)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Scheduled Date</label>
                    <input type="date" name="date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <button type="submit" name="book_test" class="btn btn-primary">Schedule Booking <i class="fas fa-calendar-check"></i></button>
            </form>
        </div>

        <h3 style="margin-top: 3rem; margin-bottom: 1rem;">Your Lab Test History</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Booking ID</th>
                        <th style="padding: 1rem;">RMP</th>
                        <th style="padding: 1rem;">Test Name</th>
                        <th style="padding: 1rem;">Date</th>
                        <th style="padding: 1rem;">Status</th>
                        <th style="padding: 1rem;">Result Document</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($my_tests && $my_tests->num_rows > 0): ?>
                        <?php while($t = $my_tests->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#LAB-<?php echo str_pad($t['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td style="padding: 1rem; color: var(--primary-color);"><?php echo htmlspecialchars($t['rmp_name']); ?></td>
                                <td style="padding: 1rem; font-weight: bold;"><?php echo htmlspecialchars($t['test_name']); ?></td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($t['scheduled_date'])); ?></td>
                                <td style="padding: 1rem;">
                                    <span style="color: <?php echo $t['status'] == 'completed' ? 'var(--secondary-color)' : 'var(--accent)'; ?>; text-transform: capitalize;">
                                        <?php echo $t['status']; ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem;">
                                    <?php if($t['status'] == 'completed' && !empty($t['result_path'])): ?>
                                        <a href="<?php echo htmlspecialchars($t['result_path']); ?>" download class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; border-color: #2ed573; color: #2ed573;"><i class="fas fa-download"></i> Download</a>
                                    <?php else: ?>
                                        <span style="color: var(--text-secondary); font-size: 0.8rem;">Pending Upload</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 1rem; text-align: center;">You have not booked any tests yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
