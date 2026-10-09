<?php
require_once 'config.php';

// Allow viewing by authenticated patient or through valid session/token
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$patient_id = (int)$_SESSION['user_id'];

// Fetch patient user info
$user_stmt = $conn->prepare("SELECT id, name, email, phone, profile_image, blood_group, allergies, health_id FROM users WHERE id = ?");
$user_stmt->bind_param("i", $patient_id);
$user_stmt->execute();
$patient = $user_stmt->get_result()->fetch_assoc();

// Fetch Emergency Card info
$emg_stmt = $conn->prepare("SELECT * FROM emergency_cards WHERE user_id = ?");
$emg_stmt->bind_param("i", $patient_id);
$emg_stmt->execute();
$emg_res = $emg_stmt->get_result();
$emg_card = ($emg_res && $emg_res->num_rows > 0) ? $emg_res->fetch_assoc() : null;

// Resolve Profile Image
$profile_img_src = get_profile_image_url($patient);
if (empty($profile_img_src)) {
    $profile_img_src = 'https://ui-avatars.com/api/?name=' . urlencode($patient['name']) . '&background=ff4757&color=fff&size=150';
}

$health_id = !empty($patient['health_id']) ? $patient['health_id'] : ('MAK-' . str_pad($patient_id, 6, '0', STR_PAD_LEFT));
$blood_group = !empty($emg_card['blood_group']) ? $emg_card['blood_group'] : (!empty($patient['blood_group']) ? $patient['blood_group'] : '');
$dob = !empty($emg_card['date_of_birth']) ? $emg_card['date_of_birth'] : '';

// Parse Allergies
$allergies_list = [];
$no_allergies_confirmed = false;

if ($emg_card) {
    $no_allergies_confirmed = (bool)($emg_card['no_allergies_confirmed'] ?? 0);
    if (!empty($emg_card['allergies_json'])) {
        $decoded = json_decode($emg_card['allergies_json'], true);
        if (is_array($decoded)) {
            $allergies_list = $decoded;
        }
    }
}

if (empty($allergies_list) && !$no_allergies_confirmed && !empty($patient['allergies'])) {
    if (stristr($patient['allergies'], 'No known allergies') !== false || stristr($patient['allergies'], 'Confirmed') !== false) {
        $no_allergies_confirmed = true;
    } else {
        $items = array_map('trim', explode(',', $patient['allergies']));
        foreach ($items as $it) {
            if (!empty($it)) {
                $allergies_list[] = ['allergy' => $it, 'severity' => 'Unspecified', 'reaction' => ''];
            }
        }
    }
}

// Contacts
$primary_name = $emg_card['primary_contact_name'] ?? '';
$primary_rel  = $emg_card['primary_contact_rel'] ?? '';
$primary_phone = $emg_card['primary_contact_phone'] ?? ($patient['phone'] ?? '');

$secondary_name = $emg_card['secondary_contact_name'] ?? '';
$secondary_rel  = $emg_card['secondary_contact_rel'] ?? '';
$secondary_phone = $emg_card['secondary_contact_phone'] ?? '';

$conditions_text = $emg_card['conditions_text'] ?? '';
$medications_text = $emg_card['medications_text'] ?? '';
$medical_devices = $emg_card['medical_devices'] ?? '';
$emergency_instructions = $emg_card['emergency_instructions'] ?? '';

$last_updated = !empty($emg_card['last_reviewed_at']) ? $emg_card['last_reviewed_at'] : ($emg_card['updated_at'] ?? date('Y-m-d H:i:s'));
$formatted_updated = date('M d, Y h:i A', strtotime($last_updated));

include 'includes/header.php';
?>

<style>
.emergency-view-wrapper {
    max-width: 680px;
    margin: 1.5rem auto;
    padding: 0 1rem 4rem;
    font-family: 'Inter', system-ui, -apple-system, sans-serif;
}
.emergency-header-card {
    background: linear-gradient(135deg, #1e0b10 0%, #0f172a 100%);
    border: 2px solid #ff4757;
    border-radius: 20px;
    padding: 1.5rem;
    box-shadow: 0 15px 35px rgba(255, 71, 87, 0.25);
    margin-bottom: 1.5rem;
    position: relative;
    overflow: hidden;
}
.emergency-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #ff4757;
    color: #ffffff;
    padding: 0.35rem 1rem;
    border-radius: 30px;
    font-size: 0.8rem;
    font-weight: 800;
    letter-spacing: 1px;
    text-transform: uppercase;
    box-shadow: 0 4px 12px rgba(255, 71, 87, 0.4);
    animation: pulseGlow 2s infinite;
}
@keyframes pulseGlow {
    0% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0.6); }
    70% { box-shadow: 0 0 0 10px rgba(255, 71, 87, 0); }
    100% { box-shadow: 0 0 0 0 rgba(255, 71, 87, 0); }
}
.patient-identity-box {
    display: flex;
    align-items: center;
    gap: 1.25rem;
    margin-top: 1.2rem;
}
.patient-avatar {
    width: 85px;
    height: 85px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #ff4757;
    background: #1e293b;
    box-shadow: 0 6px 16px rgba(0,0,0,0.4);
}
.patient-name-title {
    font-size: 1.6rem;
    font-weight: 800;
    color: #ffffff;
    margin: 0;
    line-height: 1.2;
}
.meta-pill {
    display: inline-block;
    background: rgba(255, 255, 255, 0.08);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: #cbd5e1;
    padding: 0.25rem 0.6rem;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    margin-right: 0.4rem;
    margin-top: 0.4rem;
}
.blood-pill {
    background: rgba(255, 71, 87, 0.2);
    border-color: #ff4757;
    color: #ff6b81;
    font-weight: 800;
}
.emg-section {
    background: rgba(15, 23, 42, 0.75);
    backdrop-filter: blur(12px);
    border: 1px solid var(--glass-border);
    border-radius: 16px;
    padding: 1.4rem;
    margin-bottom: 1.2rem;
}
.emg-section-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #ffffff;
    margin: 0 0 1rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    padding-bottom: 0.6rem;
}
.allergy-item {
    background: rgba(255, 71, 87, 0.08);
    border: 1px solid rgba(255, 71, 87, 0.3);
    border-left: 4px solid #ff4757;
    border-radius: 10px;
    padding: 0.85rem 1rem;
    margin-bottom: 0.6rem;
}
.allergy-name {
    font-size: 1.05rem;
    font-weight: 800;
    color: #ffffff;
}
.severity-tag {
    display: inline-block;
    padding: 0.2rem 0.6rem;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 800;
    text-transform: uppercase;
    margin-left: 0.5rem;
}
.severity-severe { background: #ff4757; color: #ffffff; }
.severity-moderate { background: #ffa502; color: #000000; }
.severity-mild { background: #eccc68; color: #000000; }
.severity-unspecified { background: rgba(255,255,255,0.2); color: #ffffff; }

.call-btn-large {
    width: 100%;
    background: linear-gradient(135deg, #2ed573 0%, #1e90ff 100%);
    color: #ffffff;
    border: none;
    padding: 1rem 1.5rem;
    border-radius: 14px;
    font-size: 1.15rem;
    font-weight: 800;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.7rem;
    box-shadow: 0 8px 25px rgba(46, 213, 115, 0.3);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    text-decoration: none;
}
.call-btn-large:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 30px rgba(46, 213, 115, 0.4);
    color: #ffffff;
}
.call-btn-secondary {
    background: linear-gradient(135deg, #4a90e2 0%, #3742fa 100%);
    box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
    margin-top: 0.75rem;
}
.notice-box {
    background: rgba(255, 255, 255, 0.03);
    border: 1px dashed rgba(255, 255, 255, 0.2);
    padding: 0.85rem;
    border-radius: 10px;
    font-size: 0.85rem;
    color: var(--text-secondary);
}
</style>

<div class="emergency-view-wrapper">
    <!-- Offline Sync Banner -->
    <div id="offlineSyncBanner" style="display: none; background: #ffa502; color: #000000; padding: 0.75rem 1rem; border-radius: 12px; font-weight: 700; margin-bottom: 1.2rem; text-align: center; font-size: 0.9rem;">
        <i class="fas fa-wifi-slash"></i> Offline Emergency Mode • Displaying locally cached data.
    </div>

    <!-- Header Box -->
    <div class="emergency-header-card">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
            <div class="emergency-badge">
                <i class="fas fa-ambulance"></i> Emergency Quick View
            </div>
            <div style="font-size: 0.78rem; color: #94a3b8;">
                <i class="fas fa-clock"></i> Synced: <span id="syncedTime"><?php echo $formatted_updated; ?></span>
            </div>
        </div>

        <div class="patient-identity-box">
            <img src="<?php echo $profile_img_src; ?>" alt="<?php echo htmlspecialchars($patient['name']); ?>" class="patient-avatar">
            <div>
                <h1 class="patient-name-title"><?php echo htmlspecialchars($patient['name']); ?></h1>
                <div>
                    <?php if (!empty($blood_group)): ?>
                        <span class="meta-pill blood-pill"><i class="fas fa-tint"></i> Blood Group: <?php echo htmlspecialchars($blood_group); ?></span>
                    <?php else: ?>
                        <span class="meta-pill" style="color: #ffa502;"><i class="fas fa-exclamation-triangle"></i> Blood Group Not Specified</span>
                    <?php endif; ?>

                    <span class="meta-pill"><i class="fas fa-id-card"></i> ID: <?php echo htmlspecialchars($health_id); ?></span>
                    <?php if (!empty($dob)): ?>
                        <span class="meta-pill"><i class="fas fa-birthday-cake"></i> DOB: <?php echo htmlspecialchars($dob); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Priority 1: Known Serious Allergies -->
    <div class="emg-section" style="border-left: 4px solid #ff4757;">
        <h2 class="emg-section-title" style="color: #ff4757;">
            <i class="fas fa-allergies"></i> 1. Known Allergies & Reactions
        </h2>
        <?php if (!empty($allergies_list)): ?>
            <?php foreach ($allergies_list as $alg): ?>
                <?php 
                    $alg_name = is_array($alg) ? ($alg['allergy'] ?? 'Unknown') : (string)$alg;
                    $severity = is_array($alg) ? ($alg['severity'] ?? 'Unspecified') : 'Unspecified';
                    $reaction = is_array($alg) ? ($alg['reaction'] ?? '') : '';
                    $sev_class = 'severity-unspecified';
                    if (stristr($severity, 'severe') !== false || stristr($severity, 'life') !== false) $sev_class = 'severity-severe';
                    elseif (stristr($severity, 'moderate') !== false) $sev_class = 'severity-moderate';
                    elseif (stristr($severity, 'mild') !== false) $sev_class = 'severity-mild';
                ?>
                <div class="allergy-item">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span class="allergy-name"><i class="fas fa-exclamation-circle" style="color: #ff4757;"></i> <?php echo htmlspecialchars($alg_name); ?></span>
                        <span class="severity-tag <?php echo $sev_class; ?>"><?php echo htmlspecialchars($severity); ?></span>
                    </div>
                    <?php if (!empty($reaction)): ?>
                        <div style="font-size: 0.88rem; color: #cbd5e1; margin-top: 0.35rem;">
                            <strong>Reaction:</strong> <?php echo htmlspecialchars($reaction); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php elseif ($no_allergies_confirmed): ?>
            <div class="notice-box" style="border-color: #2ed573; color: #2ed573;">
                <i class="fas fa-check-circle"></i> <strong>Confirmed by Patient:</strong> No known medical allergies.
            </div>
        <?php else: ?>
            <div class="notice-box" style="border-color: #ffa502; color: #ffa502;">
                <i class="fas fa-exclamation-triangle"></i> <strong>Allergy information not provided.</strong> Do not infer absence of allergies without confirmation.
            </div>
        <?php endif; ?>
    </div>

    <!-- Priority 2: Important Medical Conditions -->
    <div class="emg-section">
        <h2 class="emg-section-title">
            <i class="fas fa-notes-medical" style="color: #4a90e2;"></i> 2. Important Medical Conditions
        </h2>
        <?php if (!empty($conditions_text)): ?>
            <div style="font-size: 1rem; color: #ffffff; line-height: 1.6; white-space: pre-line;">
                <?php echo htmlspecialchars($conditions_text); ?>
            </div>
        <?php else: ?>
            <div class="notice-box">
                <i class="fas fa-info-circle"></i> Medical condition information not provided.
            </div>
        <?php endif; ?>
    </div>

    <!-- Priority 3: Current Medications -->
    <div class="emg-section">
        <h2 class="emg-section-title">
            <i class="fas fa-pills" style="color: #50e3c2;"></i> 3. Current Medications
        </h2>
        <?php if (!empty($medications_text)): ?>
            <div style="font-size: 1rem; color: #ffffff; line-height: 1.6; white-space: pre-line;">
                <?php echo htmlspecialchars($medications_text); ?>
            </div>
            <div style="margin-top: 0.5rem; font-size: 0.78rem; color: #94a3b8;">
                <i class="fas fa-user-check"></i> Patient-confirmed current medications.
            </div>
        <?php else: ?>
            <div class="notice-box">
                <i class="fas fa-info-circle"></i> Current medication information not provided.
            </div>
        <?php endif; ?>
    </div>

    <!-- Priority 4: Emergency Contacts & 1-Tap Calling -->
    <div class="emg-section" style="border-left: 4px solid #2ed573;">
        <h2 class="emg-section-title" style="color: #2ed573;">
            <i class="fas fa-phone-alt"></i> 4. Emergency Contacts & Calling
        </h2>

        <?php if (!empty($primary_phone)): ?>
            <div style="background: rgba(46, 213, 115, 0.08); border: 1px solid rgba(46, 213, 115, 0.3); padding: 1.2rem; border-radius: 14px; margin-bottom: 1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem;">
                    <div>
                        <span style="font-size: 0.75rem; color: #2ed573; text-transform: uppercase; font-weight: 800; letter-spacing: 1px;">PRIMARY EMERGENCY CONTACT</span>
                        <h3 style="margin: 0.2rem 0 0; color: #ffffff; font-size: 1.2rem;"><?php echo htmlspecialchars(!empty($primary_name) ? $primary_name : 'Primary Contact'); ?> <?php echo !empty($primary_rel) ? '(' . htmlspecialchars($primary_rel) . ')' : ''; ?></h3>
                    </div>
                    <span style="font-size: 1.1rem; color: #2ed573; font-weight: 800; font-family: monospace;"><?php echo htmlspecialchars($primary_phone); ?></span>
                </div>
                <button onclick="confirmAndCall('<?php echo addslashes(!empty($primary_name) ? $primary_name : 'Primary Contact'); ?>', '<?php echo addslashes($primary_phone); ?>')" class="call-btn-large">
                    <i class="fas fa-phone"></i> Call Primary Contact Now
                </button>
            </div>
        <?php else: ?>
            <div class="notice-box" style="border-color: #ff4757; color: #ff4757;">
                <i class="fas fa-exclamation-circle"></i> <strong>No primary emergency contact number saved!</strong> Please update your Emergency Card.
            </div>
        <?php endif; ?>

        <?php if (!empty($secondary_phone)): ?>
            <div style="background: rgba(74, 144, 226, 0.08); border: 1px solid rgba(74, 144, 226, 0.3); padding: 1.2rem; border-radius: 14px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.8rem;">
                    <div>
                        <span style="font-size: 0.75rem; color: #4a90e2; text-transform: uppercase; font-weight: 800; letter-spacing: 1px;">SECONDARY EMERGENCY CONTACT</span>
                        <h3 style="margin: 0.2rem 0 0; color: #ffffff; font-size: 1.1rem;"><?php echo htmlspecialchars(!empty($secondary_name) ? $secondary_name : 'Secondary Contact'); ?> <?php echo !empty($secondary_rel) ? '(' . htmlspecialchars($secondary_rel) . ')' : ''; ?></h3>
                    </div>
                    <span style="font-size: 1.05rem; color: #4a90e2; font-weight: 800; font-family: monospace;"><?php echo htmlspecialchars($secondary_phone); ?></span>
                </div>
                <button onclick="confirmAndCall('<?php echo addslashes(!empty($secondary_name) ? $secondary_name : 'Secondary Contact'); ?>', '<?php echo addslashes($secondary_phone); ?>')" class="call-btn-large call-btn-secondary">
                    <i class="fas fa-phone-volume"></i> Call Secondary Contact
                </button>
            </div>
        <?php endif; ?>
    </div>

    <!-- Priority 5: Medical Devices & Emergency Notes -->
    <?php if (!empty($medical_devices) || !empty($emergency_instructions)): ?>
        <div class="emg-section">
            <h2 class="emg-section-title">
                <i class="fas fa-heartbeat" style="color: #eccc68;"></i> 5. Devices & Special Instructions
            </h2>
            <?php if (!empty($medical_devices)): ?>
                <div style="margin-bottom: 0.8rem;">
                    <strong style="color: #eccc68; font-size: 0.9rem;">Medical Devices:</strong>
                    <div style="color: #ffffff; margin-top: 0.2rem;"><?php echo htmlspecialchars($medical_devices); ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($emergency_instructions)): ?>
                <div>
                    <strong style="color: #ff6b81; font-size: 0.9rem;">Emergency Instructions:</strong>
                    <div style="color: #ffffff; margin-top: 0.2rem;"><?php echo htmlspecialchars($emergency_instructions); ?></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Navigation Footer -->
    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; margin-top: 2rem;">
        <a href="profile.php" class="btn btn-outline" style="font-size: 0.95rem; border-radius: 12px; padding: 0.8rem 1.5rem;"><i class="fas fa-user"></i> Return to Profile</a>
        <a href="health_vault.php?tab=emergency" class="btn btn-primary" style="font-size: 0.95rem; border-radius: 12px; padding: 0.8rem 1.5rem;"><i class="fas fa-edit"></i> Edit Emergency Card</a>
        <a href="download_emergency_card.php" target="_blank" class="btn btn-outline" style="font-size: 0.95rem; border-radius: 12px; padding: 0.8rem 1.5rem; color: #2ed573; border-color: #2ed573;"><i class="fas fa-file-pdf"></i> Download PDF</a>
    </div>
</div>

<!-- Contact Calling Confirmation Modal -->
<div id="callConfirmModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 9999; backdrop-filter: blur(8px); align-items: center; justify-content: center; padding: 1rem;">
    <div class="glass-panel" style="max-width: 420px; width: 100%; padding: 2rem; border-radius: 20px; border-top: 4px solid #2ed573; text-align: center;">
        <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(46, 213, 115, 0.15); color: #2ed573; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 1rem;">
            <i class="fas fa-phone-alt"></i>
        </div>
        <h3 style="margin: 0 0 0.5rem; color: #ffffff;" id="modalCallTitle">Confirm Emergency Call</h3>
        <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 1.5rem;" id="modalCallBody">Are you sure you want to call this contact?</p>
        
        <div style="display: flex; gap: 1rem; justify-content: center;">
            <button onclick="closeCallModal()" class="btn btn-outline" style="flex: 1;">Cancel</button>
            <a id="modalCallLink" href="#" class="btn btn-primary" style="flex: 1; background: #2ed573; border-color: #2ed573; color: #000000; font-weight: 800;">
                <i class="fas fa-phone"></i> Call Now
            </a>
        </div>
    </div>
</div>

<script>
let targetCallPhone = '';

function confirmAndCall(name, phone) {
    if (!phone) {
        alert("Emergency contact phone number is missing.");
        return;
    }
    targetCallPhone = phone;
    document.getElementById('modalCallTitle').innerText = "Call " + name;
    document.getElementById('modalCallBody').innerText = "Confirm opening your phone dialer to call " + name + " (" + phone + ")?";
    document.getElementById('modalCallLink').href = "tel:" + encodeURIComponent(phone);
    document.getElementById('callConfirmModal').style.display = 'flex';
}

function closeCallModal() {
    document.getElementById('callConfirmModal').style.display = 'none';
}

// Offline Caching Mechanism for Emergency Quick View
(function cacheEmergencyData() {
    const dataToCache = {
        name: <?php echo json_encode($patient['name']); ?>,
        blood_group: <?php echo json_encode($blood_group); ?>,
        health_id: <?php echo json_encode($health_id); ?>,
        dob: <?php echo json_encode($dob); ?>,
        allergies: <?php echo json_encode($allergies_list); ?>,
        no_allergies: <?php echo json_encode($no_allergies_confirmed); ?>,
        conditions: <?php echo json_encode($conditions_text); ?>,
        medications: <?php echo json_encode($medications_text); ?>,
        primary_name: <?php echo json_encode($primary_name); ?>,
        primary_phone: <?php echo json_encode($primary_phone); ?>,
        secondary_name: <?php echo json_encode($secondary_name); ?>,
        secondary_phone: <?php echo json_encode($secondary_phone); ?>,
        devices: <?php echo json_encode($medical_devices); ?>,
        instructions: <?php echo json_encode($emergency_instructions); ?>,
        timestamp: new Date().toLocaleString()
    };
    try {
        localStorage.setItem('medicaliak_emg_quickview_cache', JSON.stringify(dataToCache));
    } catch(e) {}
})();

// Detect offline status
window.addEventListener('offline', function() {
    document.getElementById('offlineSyncBanner').style.display = 'block';
});
if (!navigator.onLine) {
    document.getElementById('offlineSyncBanner').style.display = 'block';
}
</script>

<?php include 'includes/footer.php'; ?>
