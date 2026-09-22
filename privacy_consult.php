<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['health_image'])) {
    $description = $conn->real_escape_string($_POST['description']);
    $patient_id = $_SESSION['user_id'];
    
    // Simple file upload logic
    $target_dir = "uploads/";
    if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
    
    $file_name = time() . '_' . basename($_FILES["health_image"]["name"]);
    $target_file = $target_dir . $file_name;
    
    if (move_uploaded_file($_FILES["health_image"]["tmp_name"], $target_file)) {
        $query = "INSERT INTO privacy_consultations (patient_id, image_path, description) VALUES ($patient_id, '$target_file', '$description')";
        if ($conn->query($query)) {
            $success = "Your query has been submitted securely. A doctor will review it shortly.";
        } else {
            $error = "Database error: could not submit query.";
        }
    } else {
        $error = "Failed to upload image.";
    }
}

// Fetch user's previous queries
$patient_id = $_SESSION['user_id'];
$queries = $conn->query("SELECT * FROM privacy_consultations WHERE patient_id=$patient_id ORDER BY created_at DESC");
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <h3 style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php" class="active"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Privacy Consultation</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Upload photos of your health symptoms anonymously. Doctors will provide suggestions without knowing your identity.</p>
        
        <?php if($error): ?><p style="color: #ff4757; margin-bottom: 1rem;"><?php echo $error; ?></p><?php endif; ?>
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem;"><?php echo $success; ?></p><?php endif; ?>

        <div class="form-container glass-panel" style="margin: 0 0 2rem 0; max-width: 600px;">
            <form method="POST" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label>Upload Image (Secure)</label>
                    <input type="file" name="health_image" class="form-control" accept="image/*" required>
                </div>
                
                <div class="form-group">
                    <label>Describe your issue</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="e.g. I have this rash on my arm for 3 days..." required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">Submit Securely <i class="fas fa-lock"></i></button>
            </form>
        </div>

        <h3>Your Recent Queries</h3>
        <div style="margin-top: 1rem;">
            <?php if ($queries && $queries->num_rows > 0): ?>
                <?php while($q = $queries->fetch_assoc()): ?>
                    <div class="glass-panel" style="padding: 1rem; margin-bottom: 1rem;">
                        <p><strong>Status:</strong> <?php echo $q['status'] === 'answered' ? '<span style="color: #2ed573;">Answered</span>' : '<span style="color: #f5a623;">Pending</span>'; ?></p>
                        <p style="margin: 0.5rem 0;"><strong>Description:</strong> <?php echo htmlspecialchars($q['description']); ?></p>
                        <?php if($q['suggestion']): ?>
                            <div style="background: rgba(80, 227, 194, 0.1); padding: 1rem; border-left: 4px solid var(--secondary-color); margin-top: 1rem;">
                                <strong>Doctor's Suggestion:</strong>
                                <p><?php echo htmlspecialchars($q['suggestion']); ?></p>
                            </div>
                        <?php endif; ?>
                        <p style="font-size: 0.8rem; color: var(--text-secondary); margin-top: 1rem;"><i class="fas fa-clock"></i> <?php echo $q['created_at']; ?></p>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: var(--text-secondary);">You haven't submitted any privacy queries yet.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
