<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'rmp') {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';

$rmp_id = $_SESSION['user_id'];
$tests = $conn->query("
    SELECT t.id, t.test_name, t.scheduled_date, t.status, p.name as patient_name, p.phone
    FROM test_bookings t
    JOIN users p ON t.patient_id = p.id
    WHERE t.rmp_id = $rmp_id
    ORDER BY t.scheduled_date DESC
");
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <h3 style="margin-bottom: 2rem;">RMP Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="rmp_dashboard.php" class="active"><i class="fas fa-flask"></i> My Booked Tests</a></li>
            <li><a href="rmp_upload.php"><i class="fas fa-file-upload"></i> Upload Results</a></li>
            <li><a href="rmp_referral.php"><i class="fas fa-user-md"></i> Doctor Referrals</a></li>
            <li><a href="profile.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>RMP Dashboard (Lab Tests & Checkups)</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>. View patients who booked lab tests and basic checkups with you.</p>

        <div class="glass-panel" style="overflow-x: auto; padding: 1rem;">
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
                                <td style="padding: 1rem;"><span style="color: <?php echo $t['status'] == 'pending' ? 'var(--accent)' : 'var(--text-primary)'; ?>; text-transform: capitalize;"><?php echo $t['status']; ?></span></td>
                                <td style="padding: 1rem;"><button class="btn btn-outline" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;" onclick="alert('Upload result mockup')">Upload Result</button></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="7" style="padding: 1rem; text-align: center;">No test bookings currently.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
