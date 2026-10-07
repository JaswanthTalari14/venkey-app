<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    header("Location: login.php");
    exit;
}

include 'includes/header.php';

// Patient location (default coordinates)
$patient_lat = 28.7042;
$patient_lon = 77.1026;

// Pagination setup
$per_page = 6;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Query to count total doctors
$count_res = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'doctor'");
$total_doctors = ($count_res) ? (int)$count_res->fetch_assoc()['total'] : 0;
$total_pages = max(1, ceil($total_doctors / $per_page));

// Query with distance calculation and pagination
$query = "SELECT *, 
          ( CASE 
              WHEN latitude IS NOT NULL AND longitude IS NOT NULL AND latitude != 0 AND longitude != 0 
              THEN ( 6371 * acos( LEAST(1.0, GREATEST(-1.0, cos( radians($patient_lat) ) * cos( radians( latitude ) ) 
                   * cos( radians( longitude ) - radians($patient_lon) ) + sin( radians($patient_lat) ) 
                   * sin( radians( latitude ) ) )) ) )
              ELSE 2.5 
            END ) AS distance 
          FROM users 
          WHERE role = 'doctor' 
          ORDER BY distance ASC, id DESC
          LIMIT $per_page OFFSET $offset";

$nearby_doctors = $conn->query($query);

// Fetch today's booked slots for all doctors to accurately reflect booked vs available consultation slots
$today_date = date('Y-m-d');
$booked_slots_res = $conn->query("
    SELECT doctor_id, appointment_time 
    FROM appointments 
    WHERE appointment_date = '$today_date' AND status != 'cancelled'
");

$booked_slots_by_doctor = [];
if ($booked_slots_res) {
    while ($b_row = $booked_slots_res->fetch_assoc()) {
        $doc_id_val = (int)$b_row['doctor_id'];
        $time_formatted = date('h:i A', strtotime($b_row['appointment_time']));
        if (!isset($booked_slots_by_doctor[$doc_id_val])) {
            $booked_slots_by_doctor[$doc_id_val] = [];
        }
        $booked_slots_by_doctor[$doc_id_val][] = strtolower(trim($time_formatted));
    }
}
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
            <li><a href="digital_medical_card.php"><i class="fas fa-id-card"></i> Digital Medical Card</a></li>
            <li><a href="book_consult.php"><i class="fas fa-calendar-check"></i> Consultations</a></li>
            <li><a href="medicines.php"><i class="fas fa-pills"></i> Order Medicines</a></li>
            <li><a href="your_orders.php"><i class="fas fa-boxes"></i> Your Orders</a></li>
            <li><a href="nearby_doctors.php" class="active"><i class="fas fa-map-marker-alt"></i> Find Doctors (10km)</a></li>
            <li><a href="privacy_consult.php"><i class="fas fa-user-secret"></i> Privacy Consult</a></li>
            <li><a href="book_tests.php"><i class="fas fa-vial"></i> Book Labs (RMP)</a></li>
            <li><a href="payment_history.php"><i class="fas fa-receipt"></i> Payment History</a></li>
            <li><a href="refer_earn.php"><i class="fas fa-gift"></i> Refer & Earn</a></li>
            <li><a href="my_wallet.php"><i class="fas fa-wallet"></i> My Wallet</a></li>
            <li><a href="chatbot.php"><i class="fas fa-robot"></i> AI Chatbot</a></li>
            <li><a href="javascript:void(0);" class="pwaInstallBtn"><i class="fas fa-download"></i> Install App</a></li>
        </ul>
    </aside>
    
    <main class="dashboard-content">
        <h2>Nearby Doctors (Within 10km)</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Using your current location, here are doctors available nearby for urgent visits.</p>
        
        <div class="features-grid" style="margin-top: 1rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; width: 100%;">
            <?php if ($nearby_doctors && $nearby_doctors->num_rows > 0): ?>
                <?php while($doc = $nearby_doctors->fetch_assoc()): ?>
                    <?php
                        // Check for doctor uploaded profile photo via Centralized Resolver
                        $doc_img_url = get_profile_image_url($doc);
                        $doc_id = (int)$doc['id'];
                        $doc_booked = isset($booked_slots_by_doctor[$doc_id]) ? $booked_slots_by_doctor[$doc_id] : [];
                    ?>
                    <div class="feature-card glass-panel" style="padding: 1.5rem; cursor: pointer; transition: transform 0.2s ease;" onclick="openDoctorProfile(<?php echo htmlspecialchars(json_encode([
                        'id' => $doc['id'],
                        'name' => $doc['name'],
                        'specialization' => $doc['specialization'] ?? 'General Practitioner',
                        'qualification' => $doc['qualification'] ?? 'MBBS, MD',
                        'experience' => $doc['experience'] ?? '5+ Years Experience',
                        'phone' => $doc['phone'],
                        'email' => $doc['email'] ?? '',
                        'distance' => round($doc['distance'], 2),
                        'is_verified' => (int)($doc['is_verified'] ?? 0),
                        'photo' => $doc_img_url,
                        'booked_slots' => $doc_booked
                    ])); ?>)">
                        
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 0.8rem;">
                            <!-- Doctor Profile Image Display -->
                            <?php if (!empty($doc_img_url)): ?>
                                <img src="<?php echo $doc_img_url; ?>" alt="Dr. <?php echo htmlspecialchars($doc['name']); ?>" style="width: 54px; height: 54px; border-radius: 50%; object-fit: cover; border: 2px solid var(--primary-color); flex-shrink: 0; background: rgba(0,0,0,0.15);">
                            <?php else: ?>
                                <div style="width: 54px; height: 54px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 1.3rem; border: 2px solid var(--primary-color); flex-shrink: 0; box-shadow: 0 4px 10px rgba(0,0,0,0.15);">
                                    <i class="fas fa-user-md"></i>
                                </div>
                            <?php endif; ?>

                            <div>
                                <h4 style="color: var(--text-primary); font-weight: 700; font-size: 1.05rem; margin: 0; display: flex; align-items: center; gap: 0.4rem; flex-wrap: wrap;">
                                    <span>Dr. <?php echo htmlspecialchars($doc['name']); ?></span>
                                    <?php if (!empty($doc['is_verified'])): ?>
                                        <span style="font-size: 0.72rem; background: rgba(46, 213, 115, 0.15); color: #2ed573; border: 1px solid #2ed573; padding: 0.1rem 0.5rem; border-radius: 12px; font-weight: 600;" title="Admin Verified Doctor">
                                            <i class="fas fa-check-circle"></i> Verified
                                        </span>
                                    <?php endif; ?>
                                </h4>
                                <p style="color: var(--text-secondary); font-size: 0.88rem; margin-top: 0.2rem;"><?php echo htmlspecialchars($doc['specialization'] ?? 'General Practitioner'); ?></p>
                            </div>
                        </div>
                        
                        <div style="margin: 1rem 0; border-top: 1px solid var(--glass-border); padding-top: 1rem;">
                            <p style="font-weight: bold; color: var(--secondary-color); margin-bottom: 0.5rem;">
                                <i class="fas fa-location-arrow"></i> <?php echo round($doc['distance'], 2); ?> km away
                            </p>
                            <p style="color: var(--text-primary); margin: 0;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($doc['phone']); ?></p>
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;" onclick="event.stopPropagation();">
                            <button type="button" class="btn btn-primary" style="flex: 1; font-size: 0.85rem;" onclick="openDoctorProfile(<?php echo htmlspecialchars(json_encode([
                                'id' => $doc['id'],
                                'name' => $doc['name'],
                                'specialization' => $doc['specialization'] ?? 'General Practitioner',
                                'qualification' => $doc['qualification'] ?? 'MBBS, MD',
                                'experience' => $doc['experience'] ?? '5+ Years Experience',
                                'phone' => $doc['phone'],
                                'email' => $doc['email'] ?? '',
                                'distance' => round($doc['distance'], 2),
                                'is_verified' => (int)($doc['is_verified'] ?? 0),
                                'photo' => $doc_img_url,
                                'booked_slots' => $doc_booked
                            ])); ?>)">
                                <i class="fas fa-user-circle"></i> Profile & Slots
                            </button>
                            <a href="book_consult.php?doctor_id=<?php echo $doc['id']; ?>" class="btn btn-outline" style="flex: 1; font-size: 0.85rem; text-align: center;">Book Directly</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p style="color: var(--text-secondary);">No doctors found.</p>
            <?php endif; ?>
        </div>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem; pt: 1rem; border-top: 1px solid var(--glass-border); flex-wrap: wrap; gap: 1rem;">
                <span style="font-size: 0.88rem; color: var(--text-secondary);">
                    Showing <?php echo min($offset + 1, $total_doctors); ?>–<?php echo min($offset + $per_page, $total_doctors); ?> of <?php echo $total_doctors; ?> doctors
                </span>
                <div style="display: flex; gap: 0.4rem; align-items: center;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo ($page - 1); ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;"><i class="fas fa-chevron-left"></i> Previous</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="btn <?php echo ($i === $page) ? 'btn-primary' : 'btn-outline'; ?>" style="padding: 0.4rem 0.75rem; font-size: 0.85rem; min-width: 36px; text-align: center;">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?php echo ($page + 1); ?>" class="btn btn-outline" style="padding: 0.4rem 0.8rem; font-size: 0.85rem;">Next <i class="fas fa-chevron-right"></i></a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
</div>

<!-- ========================================================= -->
<!-- FEATURE 2: DOCTOR PROFILE & AVAILABILITY SLOTS MODAL -->
<!-- ========================================================= -->
<div id="doctorProfileModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 99999; align-items: center; justify-content: center; padding: 1rem; overflow-y: auto;">
    <div class="glass-panel" style="background: var(--darker-bg); border: 1px solid var(--glass-border); width: 100%; max-width: 620px; padding: 1.8rem; border-radius: 20px; box-shadow: 0 25px 60px rgba(0,0,0,0.7); position: relative; max-height: 90vh; overflow-y: auto;">
        
        <!-- Modal Header & Close Button -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1.2rem; border-bottom: 1px solid var(--glass-border); padding-bottom: 0.8rem;">
            <h3 style="color: var(--text-primary); margin: 0; font-size: 1.2rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-user-md" style="color: var(--primary-color);"></i> Doctor Profile & Availability
            </h3>
            <button type="button" onclick="closeDoctorProfileModal()" style="background: transparent; border: none; color: var(--text-secondary); font-size: 1.4rem; cursor: pointer;">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Doctor Details Header Card -->
        <div style="display: flex; gap: 1.2rem; align-items: center; background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.2rem; border-radius: 14px; margin-bottom: 1.5rem; flex-wrap: wrap;">
            <div id="modalDocPhotoContainer" style="flex-shrink: 0;">
                <!-- Rendered dynamically -->
            </div>

            <div style="flex: 1; min-width: 220px;">
                <h3 id="modalDocName" style="color: var(--text-primary); font-size: 1.25rem; margin: 0 0 0.3rem 0; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                    Dr. Name
                </h3>
                <p id="modalDocSpec" style="color: var(--secondary-color); font-weight: 600; font-size: 0.95rem; margin: 0 0 0.4rem 0;"></p>
                <p style="color: var(--text-secondary); font-size: 0.88rem; margin: 0; line-height: 1.4;">
                    <span id="modalDocQual"><i class="fas fa-graduation-cap"></i> MBBS, MD</span>
                    <span style="opacity: 0.5; margin: 0 0.3rem;">•</span>
                    <span id="modalDocExp"><i class="fas fa-award"></i> 5+ Yrs Exp</span>
                </p>
                <p style="color: var(--text-secondary); font-size: 0.85rem; margin-top: 0.3rem;">
                    <i class="fas fa-location-arrow" style="color: var(--secondary-color);"></i> <span id="modalDocDist">2.5</span> km away
                    <span style="opacity: 0.5; margin: 0 0.3rem;">•</span>
                    <i class="fas fa-phone"></i> <span id="modalDocPhone"></span>
                </p>
            </div>
        </div>

        <!-- Today's Availability Banner -->
        <div style="background: rgba(80, 227, 194, 0.1); border: 1px solid rgba(80, 227, 194, 0.3); padding: 0.9rem 1.2rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <span style="color: var(--secondary-color); font-weight: 700; font-size: 0.95rem;">
                    <i class="fas fa-clock"></i> Today's Available Hours:
                </span>
                <p style="color: var(--text-primary); margin: 0.2rem 0 0 0; font-size: 0.88rem;">
                    10:00 AM – 01:00 PM &nbsp;|&nbsp; 04:00 PM – 08:00 PM
                </p>
            </div>
            <span style="background: #2ed573; color: #ffffff; font-size: 0.75rem; padding: 0.25rem 0.65rem; border-radius: 12px; font-weight: 700;">
                <i class="fas fa-circle" style="font-size: 0.5rem; vertical-align: middle;"></i> Available Today
            </span>
        </div>

        <!-- Consultation Slots Section -->
        <div>
            <h4 style="color: var(--text-primary); margin-bottom: 0.8rem; font-size: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-calendar-alt" style="color: var(--primary-color);"></i> Select Consultation Slot (Today, <?php echo date('M d, Y'); ?>):
            </h4>

            <div id="modalSlotsGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem;">
                <!-- Dynamically generated time slot buttons -->
            </div>
        </div>

        <!-- Direct Action Footer -->
        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--glass-border); padding-top: 1rem; margin-top: 1rem;">
            <button type="button" onclick="closeDoctorProfileModal()" class="btn btn-outline" style="font-size: 0.88rem; padding: 0.45rem 1rem;">
                Cancel
            </button>
            <a id="modalDirectBookBtn" href="#" class="btn btn-primary" style="font-size: 0.88rem; padding: 0.45rem 1.2rem;">
                Proceed to Book <i class="fas fa-arrow-right"></i>
            </a>
        </div>
    </div>
</div>

<script>
var currentModalDocId = null;

function openDoctorProfile(doc) {
    currentModalDocId = doc.id;
    var modal = document.getElementById('doctorProfileModal');
    
    // Photo render
    var photoContainer = document.getElementById('modalDocPhotoContainer');
    if (doc.photo && doc.photo !== '') {
        photoContainer.innerHTML = '<img src="' + doc.photo + '" alt="Dr. ' + doc.name + '" style="width: 72px; height: 72px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary-color); box-shadow: 0 4px 12px rgba(0,0,0,0.3);">';
    } else {
        photoContainer.innerHTML = '<div style="width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); display: flex; align-items: center; justify-content: center; color: #ffffff; font-size: 1.8rem; border: 3px solid var(--primary-color); box-shadow: 0 4px 12px rgba(0,0,0,0.3);"><i class="fas fa-user-md"></i></div>';
    }

    // Doctor info text
    var nameElem = document.getElementById('modalDocName');
    var verifiedBadge = doc.is_verified ? '<span style="font-size: 0.72rem; background: rgba(46, 213, 115, 0.15); color: #2ed573; border: 1px solid #2ed573; padding: 0.1rem 0.55rem; border-radius: 12px; font-weight: 600;"><i class="fas fa-check-circle"></i> Verified Doctor</span>' : '';
    nameElem.innerHTML = '<span>Dr. ' + doc.name + '</span> ' + verifiedBadge;

    document.getElementById('modalDocSpec').innerText = doc.specialization;
    document.getElementById('modalDocQual').innerHTML = '<i class="fas fa-graduation-cap"></i> ' + (doc.qualification || 'MBBS, MD');
    document.getElementById('modalDocExp').innerHTML = '<i class="fas fa-award"></i> ' + (doc.experience || '5+ Years Exp');
    document.getElementById('modalDocDist').innerText = doc.distance;
    document.getElementById('modalDocPhone').innerText = doc.phone;
    
    document.getElementById('modalDirectBookBtn').href = 'book_consult.php?doctor_id=' + doc.id;

    // Generate Consultation Slots
    var slots = [
        '10:00 AM', '10:30 AM', '11:00 AM', '11:30 AM',
        '04:00 PM', '04:30 PM', '05:00 PM', '05:30 PM'
    ];
    
    var bookedList = doc.booked_slots || [];
    var slotsGrid = document.getElementById('modalSlotsGrid');
    slotsGrid.innerHTML = '';

    var todayStr = '<?php echo date('Y-m-d'); ?>';

    slots.forEach(function(slot) {
        var isBooked = bookedList.indexOf(slot.toLowerCase()) !== -1;
        var btn = document.createElement('a');
        
        if (isBooked) {
            btn.className = 'btn btn-outline';
            btn.style.cssText = 'padding: 0.5rem 0.3rem; font-size: 0.8rem; text-align: center; opacity: 0.45; cursor: not-allowed; border-color: #ff4757; color: #ff4757; background: rgba(255, 71, 87, 0.05); text-decoration: none;';
            btn.innerHTML = '<i class="fas fa-lock"></i> ' + slot + '<br><span style="font-size:0.68rem;">Booked</span>';
            btn.onclick = function(e) { e.preventDefault(); };
        } else {
            btn.className = 'btn btn-outline';
            btn.style.cssText = 'padding: 0.5rem 0.3rem; font-size: 0.82rem; text-align: center; border-color: var(--primary-color); color: var(--text-primary); text-decoration: none; font-weight: 600; transition: all 0.2s ease;';
            btn.innerHTML = '<i class="fas fa-clock" style="color:var(--secondary-color);"></i> ' + slot;
            btn.href = 'book_consult.php?doctor_id=' + doc.id + '&date=' + todayStr + '&time=' + encodeURIComponent(slot);
        }
        slotsGrid.appendChild(btn);
    });

    modal.style.display = 'flex';
}

function closeDoctorProfileModal() {
    var modal = document.getElementById('doctorProfileModal');
    modal.style.display = 'none';
}
</script>

<?php include 'includes/footer.php'; ?>

