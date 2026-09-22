<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';

// Mock Patient location (usually from html5 geolocation)
$patient_lat = 28.7042;
$patient_lon = 77.1026;

// Here we use a generic Haversine formula directly in SQL to find nearby doctors in 10km radius
$query = "SELECT id, name, specialization, phone, latitude, longitude,
          ( 6371 * acos( cos( radians($patient_lat) ) * cos( radians( latitude ) ) 
          * cos( radians( longitude ) - radians($patient_lon) ) + sin( radians($patient_lat) ) 
          * sin( radians( latitude ) ) ) ) AS distance 
          FROM users 
          WHERE role = 'doctor' 
          HAVING distance <= 10 
          ORDER BY distance ASC";

$nearby_doctors = $conn->query($query);
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
            <li><a href="nearby_doctors.php" class="active"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Nearby Doctors (Within 10km)</h2>
        <p style="color: var(--text-secondary); margin-bottom: 2rem;">Using your current location, here are doctors available nearby for urgent visits.</p>
        
        <div class="features-grid" style="margin-top: 1rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1px));">
            <?php if ($nearby_doctors && $nearby_doctors->num_rows > 0): ?>
                <?php while($doc = $nearby_doctors->fetch_assoc()): ?>
                    <div class="feature-card glass-panel" style="padding: 1.5rem;">
                        <h4 style="color: #fff;"><i class="fas fa-user-md" style="color: var(--primary-color);"></i> Dr. <?php echo htmlspecialchars($doc['name']); ?></h4>
                        <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.5rem;"><?php echo htmlspecialchars($doc['specialization'] ?? 'General Practitioner'); ?></p>
                        
                        <div style="margin: 1rem 0; border-top: 1px solid var(--glass-border); padding-top: 1rem;">
                            <p style="font-weight: bold; color: var(--secondary-color); margin-bottom: 0.5rem;">
                                <i class="fas fa-location-arrow"></i> <?php echo round($doc['distance'], 2); ?> km away
                            </p>
                            <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doc['phone']); ?></p>
                        </div>
                        
                        <a href="book_consult.php?doctor_id=<?php echo $doc['id']; ?>" class="btn btn-outline" style="width: 100%;">Book Directly</a>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: var(--text-secondary);">No doctors found within 10 km.</p>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php include 'includes/footer.php'; ?>
