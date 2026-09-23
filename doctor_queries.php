<?php
require_once 'config.php';
include 'includes/header.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'doctor') {
    header("Location: login.php");
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['answer_query'])) {
    $query_id = $_POST['query_id'];
    $suggestion = $conn->real_escape_string($_POST['suggestion']);
    
    // Update the privacy question with doctor's suggestion
    $update = "UPDATE privacy_consultations SET suggestion='$suggestion', status='answered' WHERE id=$query_id";
    if ($conn->query($update)) {
        $success = "Patient query answered securely and anonymously.";
    } else {
        $error = "Failed to submit response.";
    }
}

// Fetch all unanswered privacy queries
$queries = $conn->query("SELECT * FROM privacy_consultations WHERE status='pending' ORDER BY created_at ASC");
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
            <li><a href="doctor_appointments.php"><i class="fas fa-calendar-day"></i> Appointments</a></li>
            <li><a href="doctor_referrals.php"><i class="fas fa-exchange-alt"></i> Patient Referrals</a></li>
            <li><a href="doctor_medicines.php"><i class="fas fa-pills"></i> Manage Medicines</a></li>
            <li><a href="doctor_orders.php"><i class="fas fa-box"></i> Medicine Orders</a></li>
            <li><a href="doctor_queries.php" class="active"><i class="fas fa-user-secret"></i> Anonymous Queries</a></li>
            <li><a href="profile.php"><i class="fas fa-cog"></i> Settings & Availability</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Respond to Anonymous Medical Queries</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Help patients securely by reviewing their uploaded symptoms and providing direct medical suggestions. Patients will remain anonymous.</p>

        <?php if($error): ?><p style="color: #ff4757; margin-bottom: 1rem;"><?php echo $error; ?></p><?php endif; ?>
        <?php if($success): ?><p style="color: #2ed573; margin-bottom: 1rem;"><?php echo $success; ?></p><?php endif; ?>

        <div class="features-grid">
            <?php if ($queries && $queries->num_rows > 0): ?>
                <?php while($q = $queries->fetch_assoc()): ?>
                    <div class="glass-panel" style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; border-left: 4px solid var(--accent);">
                        <div>
                            <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem;"><i class="fas fa-clock"></i> Uploaded: <?php echo date('M d, h:i A', strtotime($q['created_at'])); ?></p>
                            <p style="font-weight: 500; font-size: 1.1rem; margin-bottom: 1rem;">"<?php echo htmlspecialchars($q['description']); ?>"</p>
                            
                            <a href="<?php echo htmlspecialchars($q['image_path']); ?>" target="_blank" class="btn btn-outline" style="font-size: 0.8rem; display: inline-block; margin-bottom: 1rem;"><i class="fas fa-image"></i> View Symptom Photo</a>
                        </div>
                        
                        <form method="POST" action="" style="background: rgba(0,0,0,0.2); padding: 1rem; border-radius: 8px;">
                            <input type="hidden" name="query_id" value="<?php echo $q['id']; ?>">
                            <div class="form-group" style="margin-bottom: 0.5rem;">
                                <label style="font-weight: bold; color: var(--secondary-color);">Your Professional Suggestion:</label>
                                <textarea name="suggestion" class="form-control" rows="3" placeholder="Provide anonymous medical advice or prescribe a specific action..." required></textarea>
                            </div>
                            <button type="submit" name="answer_query" class="btn btn-primary" style="font-size: 0.9rem;">Submit Medical Response <i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: var(--text-secondary); width: 100%;">There are no pending privacy questions right now.</p>
            <?php endif; ?>
        </div>
    </main>
</div>
<?php include 'includes/footer.php'; ?>
