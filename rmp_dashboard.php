<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rmp') {
    header("Location: login.php");
    exit;
}

$rmp_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle Follow-up Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_followup'])) {
        $p_name = trim($conn->real_escape_string($_POST['patient_name']));
        $phone = trim($conn->real_escape_string($_POST['phone']));
        $f_date = trim($conn->real_escape_string($_POST['followup_date']));
        $reason = trim($conn->real_escape_string($_POST['reason']));

        if ($p_name && $phone && $f_date) {
            $stmt = $conn->prepare("INSERT INTO rmp_followups (rmp_id, patient_name, phone, followup_date, reason) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $rmp_id, $p_name, $phone, $f_date, $reason);
            if ($stmt->execute()) {
                $success_msg = "Follow-up reminder scheduled successfully!";
            } else {
                $error_msg = "Failed to schedule follow-up.";
            }
        } else {
            $error_msg = "Please fill in all required fields.";
        }
    } elseif (isset($_POST['toggle_followup'])) {
        $fid = (int)$_POST['followup_id'];
        $new_st = $_POST['new_status'] === 'completed' ? 'completed' : 'pending';
        $conn->query("UPDATE rmp_followups SET status = '$new_st' WHERE id = $fid AND rmp_id = $rmp_id");
        $success_msg = "Follow-up status updated.";
    } elseif (isset($_POST['delete_followup'])) {
        $fid = (int)$_POST['followup_id'];
        $conn->query("DELETE FROM rmp_followups WHERE id = $fid AND rmp_id = $rmp_id");
        $success_msg = "Follow-up deleted.";
    }
}

// Stats calculation (Feature 15)
$total_tests = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM test_bookings WHERE rmp_id = $rmp_id");
if ($res) $total_tests = $res->fetch_assoc()['cnt'];

$pending_checkups = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM test_bookings WHERE rmp_id = $rmp_id AND status = 'pending'");
if ($res) $pending_checkups = $res->fetch_assoc()['cnt'];

$total_referrals = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM referrals WHERE rmp_id = $rmp_id");
if ($res) $total_referrals = $res->fetch_assoc()['cnt'];

$completed_referrals = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM referrals WHERE rmp_id = $rmp_id AND referral_status = 'completed'");
if ($res) $completed_referrals = $res->fetch_assoc()['cnt'];

$completed_tests = 0;
$res = $conn->query("SELECT COUNT(*) as cnt FROM test_bookings WHERE rmp_id = $rmp_id AND status = 'completed'");
if ($res) $completed_tests = $res->fetch_assoc()['cnt'];

$total_commission = ($completed_tests * 100) + ($completed_referrals * 150);

// Fetch test bookings
$tests = $conn->query("
    SELECT t.id, t.test_name, t.scheduled_date, t.status, p.name as patient_name, p.phone
    FROM test_bookings t
    JOIN users p ON t.patient_id = p.id
    WHERE t.rmp_id = $rmp_id
    ORDER BY t.scheduled_date DESC
");

// Fetch follow-ups (Feature 16)
$followups = $conn->query("
    SELECT * FROM rmp_followups
    WHERE rmp_id = $rmp_id
    ORDER BY followup_date ASC
");

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle RMP Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> RMP Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">RMP Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="rmp_dashboard.php" class="active"><i class="fas fa-flask"></i> Dashboard & Tests</a></li>
            <li><a href="rmp_upload.php"><i class="fas fa-file-upload"></i> Upload Results</a></li>
            <li><a href="rmp_referral.php"><i class="fas fa-user-md"></i> Doctor Referrals</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="profile.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>RMP Operations Dashboard</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Welcome back, <?php echo htmlspecialchars($_SESSION['name']); ?>. Overview of tests, referrals, statistics & follow-ups.</p>

        <?php if ($success_msg): ?>
            <div style="background: rgba(46, 213, 115, 0.15); border: 1px solid #2ed573; color: #2ed573; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div style="background: rgba(255, 71, 87, 0.15); border: 1px solid #ff4757; color: #ff4757; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <!-- Feature 15: RMP Statistics -->
        <div class="features-grid" style="margin-bottom: 2.5rem;">
            <div class="feature-card glass-panel" style="padding: 1.25rem; text-align: center;">
                <h4 style="color: var(--primary-color); font-size: 0.95rem; margin-bottom: 0.5rem;"><i class="fas fa-vials"></i> Total Booked Tests</h4>
                <p style="font-size: 2.2rem; font-weight: bold; margin: 0;"><?php echo $total_tests; ?></p>
            </div>
            
            <div class="feature-card glass-panel" style="padding: 1.25rem; text-align: center;">
                <h4 style="color: var(--accent); font-size: 0.95rem; margin-bottom: 0.5rem;"><i class="fas fa-clock"></i> Pending Checkups</h4>
                <p style="font-size: 2.2rem; font-weight: bold; margin: 0;"><?php echo $pending_checkups; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.25rem; text-align: center;">
                <h4 style="color: var(--secondary-color); font-size: 0.95rem; margin-bottom: 0.5rem;"><i class="fas fa-user-md"></i> Total Referrals</h4>
                <p style="font-size: 2.2rem; font-weight: bold; margin: 0;"><?php echo $total_referrals; ?></p>
            </div>

            <div class="feature-card glass-panel" style="padding: 1.25rem; text-align: center;">
                <h4 style="color: #2ed573; font-size: 0.95rem; margin-bottom: 0.5rem;"><i class="fas fa-coins"></i> Total Earnings</h4>
                <p style="font-size: 2.2rem; font-weight: bold; margin: 0;">₹<?php echo number_format($total_commission, 2); ?></p>
            </div>
        </div>

        <!-- Feature 16: Follow-up Reminders Manager -->
        <div class="glass-panel" style="padding: 1.5rem; margin-bottom: 2.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <h3><i class="fas fa-calendar-check" style="color: var(--primary-color);"></i> Patient Follow-up Reminders</h3>
                <button onclick="document.getElementById('addFollowupForm').style.display = document.getElementById('addFollowupForm').style.display === 'none' ? 'block' : 'none'" class="btn btn-primary" style="font-size: 0.85rem; padding: 0.5rem 1rem;">
                    <i class="fas fa-plus-circle"></i> Add Follow-up
                </button>
            </div>

            <!-- Form for Adding Follow-up -->
            <form id="addFollowupForm" method="POST" style="display: none; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px; margin-bottom: 1.5rem;">
                <input type="hidden" name="add_followup" value="1">
                <h4 style="margin-bottom: 1rem; color: var(--secondary-color);">Schedule New Patient Follow-up</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group" style="margin:0;">
                        <label>Patient Name *</label>
                        <input type="text" name="patient_name" class="form-control" required placeholder="e.g. Rahul Kumar">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Phone Number *</label>
                        <input type="text" name="phone" class="form-control" required placeholder="e.g. 9876543210">
                    </div>
                    <div class="form-group" style="margin:0;">
                        <label>Follow-up Date *</label>
                        <input type="date" name="followup_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="form-group" style="margin-top: 1rem;">
                    <label>Reason / Reminders Notes</label>
                    <input type="text" name="reason" class="form-control" placeholder="e.g. Check blood sugar test results & medication reaction">
                </div>
                <div style="display: flex; gap: 1rem; margin-top: 1rem;">
                    <button type="submit" class="btn btn-primary" style="font-size: 0.85rem;">Save Follow-up</button>
                    <button type="button" class="btn btn-outline" style="font-size: 0.85rem;" onclick="document.getElementById('addFollowupForm').style.display='none'">Cancel</button>
                </div>
            </form>

            <div style="overflow-x: auto;">
                <table style="width: 100%; text-align: left; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 1px solid var(--glass-border);">
                            <th style="padding: 0.8rem;">Patient Details</th>
                            <th style="padding: 0.8rem;">Follow-up Date</th>
                            <th style="padding: 0.8rem;">Reason / Notes</th>
                            <th style="padding: 0.8rem;">Status</th>
                            <th style="padding: 0.8rem;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($followups && $followups->num_rows > 0): ?>
                            <?php while($f = $followups->fetch_assoc()): ?>
                                <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                    <td style="padding: 0.8rem;">
                                        <strong><?php echo htmlspecialchars($f['patient_name']); ?></strong><br>
                                        <a href="tel:<?php echo htmlspecialchars($f['phone']); ?>" style="color: var(--primary-color); font-size: 0.85rem;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($f['phone']); ?></a>
                                    </td>
                                    <td style="padding: 0.8rem; color: var(--secondary-color); font-weight: 500;">
                                        <?php 
                                            $is_past = strtotime($f['followup_date']) < strtotime(date('Y-m-d')) && $f['status'] !== 'completed';
                                            echo date('M d, Y', strtotime($f['followup_date'])); 
                                            if ($is_past) echo ' <span style="color: #ff4757; font-size: 0.75rem;">(Overdue)</span>';
                                        ?>
                                    </td>
                                    <td style="padding: 0.8rem; color: var(--text-secondary); font-size: 0.9rem;"><?php echo htmlspecialchars($f['reason'] ?: 'Routine follow-up'); ?></td>
                                    <td style="padding: 0.8rem;">
                                        <span style="padding: 0.25rem 0.6rem; border-radius: 12px; font-size: 0.8rem; font-weight: 600; background: <?php echo $f['status'] === 'completed' ? 'rgba(46, 213, 115, 0.15)' : 'rgba(255, 171, 0, 0.15)'; ?>; color: <?php echo $f['status'] === 'completed' ? '#2ed573' : 'var(--accent)'; ?>;">
                                            <?php echo ucfirst($f['status']); ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.8rem;">
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="toggle_followup" value="1">
                                                <input type="hidden" name="followup_id" value="<?php echo $f['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $f['status'] === 'completed' ? 'pending' : 'completed'; ?>">
                                                <button type="submit" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem;">
                                                    <?php echo $f['status'] === 'completed' ? 'Mark Pending' : 'Mark Done'; ?>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this follow-up reminder?')">
                                                <input type="hidden" name="delete_followup" value="1">
                                                <input type="hidden" name="followup_id" value="<?php echo $f['id']; ?>">
                                                <button type="submit" class="btn btn-outline" style="font-size: 0.75rem; padding: 0.3rem 0.6rem; border-color: #ff4757; color: #ff4757;">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" style="padding: 1rem; text-align: center; color: var(--text-secondary);">No follow-ups scheduled yet. Click "Add Follow-up" to create one.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Booked Lab Tests & Checkups Table -->
        <h3><i class="fas fa-flask" style="color: var(--secondary-color);"></i> Booked Lab Tests & Checkups</h3>
        <div class="glass-panel" style="overflow-x: auto; padding: 1rem; margin-top: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">Booking ID</th>
                        <th style="padding: 1rem;">Patient Name</th>
                        <th style="padding: 1rem;">Phone</th>
                        <th style="padding: 1rem;">Test / Checkup Name</th>
                        <th style="padding: 1rem;">Scheduled Date</th>
                        <th style="padding: 1rem;">Status</th>
                        <th style="padding: 1rem;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tests && $tests->num_rows > 0): ?>
                        <?php while($t = $tests->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#LAB-<?php echo str_pad($t['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td style="padding: 1rem; font-weight: bold;"><?php echo htmlspecialchars($t['patient_name']); ?></td>
                                <td style="padding: 1rem;"><a href="tel:<?php echo htmlspecialchars($t['phone']); ?>" style="color: var(--primary-color);"><?php echo htmlspecialchars($t['phone']); ?></a></td>
                                <td style="padding: 1rem; color: var(--secondary-color);"><?php echo htmlspecialchars($t['test_name']); ?></td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($t['scheduled_date'])); ?></td>
                                <td style="padding: 1rem;"><span style="color: <?php echo $t['status'] == 'pending' ? 'var(--accent)' : '#2ed573'; ?>; text-transform: capitalize; font-weight: bold;"><?php echo $t['status']; ?></span></td>
                                <td style="padding: 1rem;">
                                    <a href="rmp_upload.php" class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                                        <i class="fas fa-upload"></i> Upload Result
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="padding: 1rem; text-align: center; color: var(--text-secondary);">No test bookings currently.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>

