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

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['result_file'])) {
    $booking_id = $_POST['booking_id'];
    
    // Simple file upload logic
    $target_dir = "uploads/results/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    
    $file_name = time() . '_' . basename($_FILES["result_file"]["name"]);
    $target_file = $target_dir . $file_name;
    
    if (move_uploaded_file($_FILES["result_file"]["tmp_name"], $target_file)) {
        $query = "UPDATE test_bookings SET result_path='$target_file', status='completed' WHERE id=$booking_id AND rmp_id=$rmp_id";
        if ($conn->query($query)) {
            $success = "Test result uploaded and marked as completed successfully!";
        } else {
            $error = "Database error: could not update booking status.";
        }
    } else {
        $error = "Failed to safely upload the file.";
    }
}

// Fetch pending tests for this RMP
$pending_tests = $conn->query("
    SELECT t.id, t.test_name, t.scheduled_date, p.name as patient_name 
    FROM test_bookings t
    JOIN users p ON t.patient_id = p.id
    WHERE t.rmp_id = $rmp_id AND t.status = 'pending'
    ORDER BY t.scheduled_date ASC
");
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <h3 style="margin-bottom: 2rem;">RMP Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="rmp_dashboard.php"><i class="fas fa-flask"></i> My Booked Tests</a></li>
            <li><a href="rmp_upload.php" class="active"><i class="fas fa-file-upload"></i> Upload Results</a></li>
            <li><a href="rmp_referral.php"><i class="fas fa-user-md"></i> Doctor Referrals</a></li>
            <li><a href="profile.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Upload Lab Results</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Attach and upload PDF or Image results for completed patient lab tests.</p>
        
        <?php if($error): ?><p style="color: #ff4757; margin-bottom: 1rem;"><?php echo $error; ?></p><?php endif; ?>
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem;"><?php echo $success; ?></p><?php endif; ?>

        <div class="form-container glass-panel" style="margin: 0; max-width: 600px;">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Select Pending Patient Test</label>
                    <select name="booking_id" class="form-control" required>
                        <option value="">-- Choose a booking --</option>
                        <?php if ($pending_tests && $pending_tests->num_rows > 0): ?>
                            <?php while($test = $pending_tests->fetch_assoc()): ?>
                                <option value="<?php echo $test['id']; ?>">
                                    LAB-<?php echo str_pad($test['id'], 4, '0', STR_PAD_LEFT); ?>: <?php echo htmlspecialchars($test['patient_name']); ?> - <?php echo htmlspecialchars($test['test_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" disabled>No pending tests available.</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Upload Result Document (PDF / Image)</label>
                    <input type="file" name="result_file" class="form-control" accept=".pdf,image/*" required>
                </div>
                
                <button type="submit" class="btn btn-primary">Securely Upload File <i class="fas fa-upload"></i></button>
            </form>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
