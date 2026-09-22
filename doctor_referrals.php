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
    $referral_id = $_POST['referral_id'];
    $new_status = $_POST['status'];
    
    $query = "UPDATE referrals SET referral_status = '$new_status' WHERE id = $referral_id AND doctor_id = $doctor_id";
    if ($conn->query($query)) {
        $success = "Referral status updated to ".htmlspecialchars($new_status).".";
    }
}

// Fetch all referrals sent to this doctor
$referrals = $conn->query("
    SELECT r.id, r.patient_name, r.patient_phone, r.notes, r.referral_status, r.created_at, u.name as rmp_name
    FROM referrals r
    JOIN users u ON r.rmp_id = u.id
    WHERE r.doctor_id = $doctor_id
    ORDER BY r.created_at DESC
");
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <h3 style="margin-bottom: 2rem;">Doctor Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="doctor_dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="doctor_appointments.php"><i class="fas fa-calendar-day"></i> Appointments</a></li>
            <li><a href="doctor_referrals.php" class="active"><i class="fas fa-exchange-alt"></i> Patient Referrals</a></li>
            <li><a href="doctor_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="doctor_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="doctor_queries.php"><i class="fas fa-user-secret"></i> Anonymous Queries</a></li>
            <li><a href="profile.php"><i class="fas fa-cog"></i> Settings & Availability</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Patient Referrals</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Manage incoming patients referred by RMPs.</p>
        
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem; padding: 1rem; background: rgba(46, 213, 115, 0.1); border-radius: 8px;"><i class="fas fa-check-circle"></i> <?php echo $success; ?></p><?php endif; ?>

        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
            <table style="width: 100%; text-align: left; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--glass-border);">
                        <th style="padding: 1rem;">ID</th>
                        <th style="padding: 1rem;">Patient Details</th>
                        <th style="padding: 1rem;">Referred By (RMP)</th>
                        <th style="padding: 1rem;">Notes</th>
                        <th style="padding: 1rem;">Date</th>
                        <th style="padding: 1rem;">Status / Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($referrals && $referrals->num_rows > 0): ?>
                        <?php while($r = $referrals->fetch_assoc()): ?>
                            <tr style="border-bottom: 1px solid rgba(255,255,255,0.05);">
                                <td style="padding: 1rem;">#REF-<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                <td style="padding: 1rem;">
                                    <strong><?php echo htmlspecialchars($r['patient_name']); ?></strong><br>
                                    <a href="tel:<?php echo htmlspecialchars($r['patient_phone']); ?>" style="color: var(--primary-color); font-size: 0.9rem;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($r['patient_phone']); ?></a>
                                </td>
                                <td style="padding: 1rem;">Dr. <?php echo htmlspecialchars($r['rmp_name']); ?></td>
                                <td style="padding: 1rem; color: var(--text-secondary); font-size: 0.9rem; max-width: 200px;"><?php echo htmlspecialchars($r['notes']); ?></td>
                                <td style="padding: 1rem; color: var(--text-secondary);"><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                                <td style="padding: 1rem;">
                                    <form method="POST" action="" style="display: flex; gap: 0.5rem; flex-direction: column;">
                                        <input type="hidden" name="referral_id" value="<?php echo $r['id']; ?>">
                                        <div style="display: flex; gap: 0.5rem; align-items: center;">
                                            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?php 
                                                if ($r['referral_status'] == 'pending') echo 'var(--accent)'; 
                                                elseif ($r['referral_status'] == 'accepted') echo '#3498db'; 
                                                elseif ($r['referral_status'] == 'completed') echo '#2ed573'; 
                                                elseif ($r['referral_status'] == 'rejected') echo '#ff4757'; 
                                            ?>;"></span>
                                            <select name="status" class="form-control" style="padding: 0.3rem; font-size: 0.8rem; flex: 1;">
                                                <option value="pending" <?php if($r['referral_status']=='pending') echo 'selected'; ?>>Pending</option>
                                                <option value="accepted" <?php if($r['referral_status']=='accepted') echo 'selected'; ?>>Accepted</option>
                                                <option value="completed" <?php if($r['referral_status']=='completed') echo 'selected'; ?>>Completed</option>
                                                <option value="rejected" <?php if($r['referral_status']=='rejected') echo 'selected'; ?>>Rejected</option>
                                            </select>
                                        </div>
                                        <button type="submit" name="update_status" class="btn btn-outline" style="padding: 0.3rem 0.5rem; font-size: 0.7rem;">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="padding: 1rem; text-align: center;">No referrals received yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
