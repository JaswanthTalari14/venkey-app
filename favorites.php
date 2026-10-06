<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

$patient_id = (int)$_SESSION['user_id'];

// Fetch Favorited Doctors
$fav_docs_q = $conn->query("
    SELECT u.id as doctor_id, u.name, u.email, u.phone, u.specialization, u.profile_image
    FROM favorite_doctors f
    JOIN users u ON f.doctor_id = u.id
    WHERE f.patient_id = $patient_id
");

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Patient Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Patient Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="patient_dashboard.php"><i class="fas fa-home"></i> Overview</a></li>
            <li><a href="favorites.php" class="active"><i class="fas fa-heart"></i> Favorites</a></li>
            <li><a href="recently_viewed.php"><i class="fas fa-history"></i> Recently Viewed</a></li>
            <li><a href="nearby_doctors.php"><i class="fas fa-user-md"></i> Find Doctors</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-heart" style="color: #ff4757;"></i> My Favorites</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Quickly access your favorited doctors, medicines, and saved healthcare services.</p>

        <h3 style="color: var(--text-primary); margin-bottom: 1rem;"><i class="fas fa-user-md" style="color: var(--primary-color);"></i> Favorite Doctors</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
            <?php if ($fav_docs_q && $fav_docs_q->num_rows > 0): ?>
                <?php while($d = $fav_docs_q->fetch_assoc()): ?>
                    <div class="glass-panel" style="padding: 1.25rem; border-radius: 14px;">
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(74, 144, 226, 0.2); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: bold;">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <div>
                                <h4 style="margin: 0; font-size: 1rem; color: var(--text-primary);">Dr. <?php echo htmlspecialchars($d['name']); ?></h4>
                                <span style="font-size: 0.8rem; color: var(--secondary-color); font-weight: bold;"><?php echo htmlspecialchars($d['specialization'] ?: 'General Practitioner'); ?></span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <a href="book_consult.php?doctor_id=<?php echo $d['doctor_id']; ?>" class="btn btn-primary" style="flex: 1; font-size: 0.8rem; text-align: center;">Book Consult</a>
                            <button onclick="removeFavorite(<?php echo $d['doctor_id']; ?>)" class="btn btn-outline" style="font-size: 0.8rem; color: #ff4757; border-color: #ff4757;"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="glass-panel" style="grid-column: 1 / -1; padding: 2rem; text-align: center; color: var(--text-secondary);">
                    No favorite doctors added yet. Browse <a href="nearby_doctors.php" style="color: var(--primary-color);">Nearby Doctors</a> to add to your favorites.
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
function removeFavorite(docId) {
    const fd = new FormData();
    fd.append('action', 'toggle_favorite');
    fd.append('doctor_id', docId);
    fetch('api_patient_features.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(res => { if(res.success) location.reload(); });
}
</script>

<?php include 'includes/footer.php'; ?>
