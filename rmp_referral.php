<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rmp') {
    header("Location: login.php");
    exit;
}

$rmp_id = $_SESSION['user_id'];
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['refer'])) {
    $doctor_id = $_POST['doctor_id'];
    $patient_name = $conn->real_escape_string($_POST['patient_name']);
    $patient_phone = $conn->real_escape_string($_POST['patient_phone']);
    $notes = $conn->real_escape_string($_POST['notes']);
    
    // In a real scenario, here we would integrate Razorpay or Stripe.
    // We are mocking a successful payment gateway transaction right here.
    $payment_status = 'completed'; 
    $referral_status = 'pending';

    $query = "INSERT INTO referrals (rmp_id, doctor_id, patient_name, patient_phone, notes, payment_status, referral_status) 
              VALUES ($rmp_id, $doctor_id, '$patient_name', '$patient_phone', '$notes', '$payment_status', '$referral_status')";
    
    if ($conn->query($query)) {
        $success = "Referral created and payment successful! The doctor has been notified.";
    } else {
        $error = "Failed to create referral: " . $conn->error;
    }
}

// Fetch all doctors
$doctors = $conn->query("SELECT * FROM users WHERE role='doctor'");

// Fetch referrals made by this RMP
$referrals = $conn->query("
    SELECT r.id, r.patient_name, r.patient_phone, r.referral_status, r.created_at, d.name as doctor_name, d.specialization
    FROM referrals r
    JOIN users d ON r.doctor_id = d.id
    WHERE r.rmp_id = $rmp_id
    ORDER BY r.created_at DESC
");
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle RMP Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> RMP Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">RMP Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="rmp_dashboard.php"><i class="fas fa-flask"></i> My Booked Tests</a></li>
            <li><a href="rmp_upload.php"><i class="fas fa-file-upload"></i> Upload Results</a></li>
            <li><a href="rmp_referral.php" class="active"><i class="fas fa-user-md"></i> Doctor Referrals</a></li>
            <li><a href="profile.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Refer Patient to Specialist</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Refer patients to doctors. A referral fee is processed via our secure payment gateway.</p>
        
        <?php if($error): ?><p style="color: #ff4757; margin-bottom: 1rem;"><?php echo $error; ?></p><?php endif; ?>
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem; padding: 1rem; background: rgba(46, 213, 115, 0.1); border-radius: 8px;"><i class="fas fa-check-circle"></i> <?php echo $success; ?></p><?php endif; ?>

        <div class="form-container glass-panel" style="margin: 0; max-width: 600px;">
            <form id="referralForm" method="POST" action="">
                <div id="step1">
                    <div class="form-group">
                        <label>Select Specialist</label>
                        <select name="doctor_id" class="form-control" required>
                            <option value="">-- Choose Doctor --</option>
                            <?php while($doc = $doctors->fetch_assoc()): ?>
                                <option value="<?php echo $doc['id']; ?>">Dr. <?php echo htmlspecialchars($doc['name']); ?> (<?php echo htmlspecialchars($doc['specialization'] ?? 'General'); ?>)</option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div style="display: flex; gap: 1rem;">
                        <div class="form-group" style="flex: 1;">
                            <label>Patient Name</label>
                            <input type="text" name="patient_name" class="form-control" placeholder="e.g. John Doe" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label>Patient Phone</label>
                            <input type="text" name="patient_phone" class="form-control" placeholder="e.g. 9876543210" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Case Notes / Symptoms</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Describe the reason for referral..."></textarea>
                    </div>
                    
                    <button type="button" class="btn btn-primary" style="width: 100%;" onclick="showPayment()">Proceed to Pay <i class="fas fa-arrow-right"></i></button>
                </div>

                <div id="step2" style="display: none;">
                    <h4 style="margin-bottom: 1.5rem; color: var(--primary-color); border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 0.5rem;"><i class="fas fa-lock"></i> Secure Payment Gateway</h4>
                    
                    <div class="form-group">
                        <label>Amount to Pay (₹)</label>
                        <input type="number" class="form-control" value="150" readonly style="background: rgba(255,255,255,0.02); font-weight: bold; color: var(--secondary-color);">
                    </div>
                    
                    <div class="form-group">
                        <label>Card Number</label>
                        <input type="text" class="form-control" placeholder="xxxx xxxx xxxx xxxx" required>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-bottom: 1rem;">
                        <div class="form-group" style="flex: 1; margin: 0;">
                            <label>Expiry Date</label>
                            <input type="text" class="form-control" placeholder="MM/YY" required>
                        </div>
                        <div class="form-group" style="flex: 1; margin: 0;">
                            <label>CVV</label>
                            <input type="password" class="form-control" placeholder="•••" required>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="button" class="btn btn-outline" style="flex: 1;" onclick="hidePayment()">Back</button>
                        <button type="button" id="payBtn" class="btn btn-primary" style="flex: 2;" onclick="processPayment()">Pay Now <i class="fas fa-credit-card"></i></button>
                    </div>
                </div>

                <button type="submit" id="submitBtn" name="refer" style="display: none;"></button>
            </form>
        </div>

        <script>
        function showPayment() {
            const form = document.getElementById('referralForm');
            // Temporarily disable the required constraint on step 2 inputs to validate step 1
            const step2Inputs = document.getElementById('step2').querySelectorAll('input[required]');
            step2Inputs.forEach(input => input.removeAttribute('required'));

            if (!form.checkValidity()) {
                form.reportValidity();
                // Re-add required constraints
                step2Inputs.forEach(input => input.setAttribute('required', 'true'));
                return;
            }
            
            // Re-add required constraints before hiding step 1
            step2Inputs.forEach(input => input.setAttribute('required', 'true'));

            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = 'block';
        }

        function hidePayment() {
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step1').style.display = 'block';
        }

        function processPayment() {
            const form = document.getElementById('referralForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            const btn = document.getElementById('payBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            btn.disabled = true;
            btn.style.opacity = '0.7';
            
            setTimeout(() => {
                btn.innerHTML = '<i class="fas fa-check-circle"></i> Payment Successful!';
                btn.style.backgroundColor = '#2ed573';
                btn.style.borderColor = '#2ed573';
                
                setTimeout(() => {
                    document.getElementById('submitBtn').click();
                }, 800);
            }, 2000);
        }
        </script>

        <h3 style="margin-top: 3rem; margin-bottom: 1rem;">My Referral Status</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Referral ID</th>
                        <th style="padding: 1rem;">Patient</th>
                        <th style="padding: 1rem;">Doctor</th>
                        <th style="padding: 1rem;">Specialization</th>
                        <th style="padding: 1rem;">Date</th>
                        <th style="padding: 1rem;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($referrals && $referrals->num_rows > 0): ?>
                        <?php while($r = $referrals->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#REF-<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td style="padding: 1rem; font-weight: bold;">
                                    <?php echo htmlspecialchars($r['patient_name']); ?><br>
                                    <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($r['patient_phone']); ?></small>
                                </td>
                                <td style="padding: 1rem; color: var(--primary-color);">Dr. <?php echo htmlspecialchars($r['doctor_name']); ?></td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo htmlspecialchars($r['specialization'] ?? 'General'); ?></td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                                <td style="padding: 1rem;">
                                    <?php
                                        $status_color = 'var(--text-primary)';
                                        if ($r['referral_status'] == 'pending') $status_color = 'var(--accent)';
                                        if ($r['referral_status'] == 'accepted') $status_color = '#3498db';
                                        if ($r['referral_status'] == 'completed') $status_color = '#2ed573';
                                        if ($r['referral_status'] == 'rejected') $status_color = '#ff4757';
                                    ?>
                                    <span style="color: <?php echo $status_color; ?>; font-weight: bold; text-transform: capitalize; background: rgba(255,255,255,0.05); padding: 0.3rem 0.8rem; border-radius: 12px; font-size: 0.8rem;">
                                        <?php echo $r['referral_status']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 1rem; text-align: center;">You have not referred any patients yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
