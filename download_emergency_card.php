<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(403);
    die("<div style='font-family:sans-serif; padding:2rem; text-align:center;'><h2>Unauthorized Access</h2><p>Please log in to your patient account.</p></div>");
}

$patient_id = (int)$_SESSION['user_id'];

// Fetch patient user info
$u_stmt = $conn->prepare("SELECT id, name, email, phone, profile_image, blood_group, allergies, health_id FROM users WHERE id = ?");
$u_stmt->bind_param("i", $patient_id);
$u_stmt->execute();
$patient = $u_stmt->get_result()->fetch_assoc();

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
$blood_group = !empty($emg_card['blood_group']) ? $emg_card['blood_group'] : (!empty($patient['blood_group']) ? $patient['blood_group'] : 'Not specified');
$dob = !empty($emg_card['date_of_birth']) ? $emg_card['date_of_birth'] : '';

// Check Privacy selected fields
$public_fields = $emg_card ? json_decode($emg_card['public_fields_json'], true) : ['name', 'blood_group', 'allergies', 'emergency_contact', 'conditions', 'medications'];
if (!is_array($public_fields)) $public_fields = [];

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
$formatted_updated = date('d M Y', strtotime($last_updated));

$qr_token = $emg_card['qr_token'] ?? md5('EMG_' . $patient_id);
$qr_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/emergency_view.php?qr=' . $qr_token;
$qr_code_src = "https://api.qrserver.com/v1/create-qr-code/?size=100x100&data=" . urlencode($qr_url);

$filename = "Emergency-Information-Card-" . preg_replace('/[^A-Za-z0-9\-]/', '', $patient['name']) . ".pdf";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $filename; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #0b0f19; color: #ffffff; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; padding: 1.5rem; }
        .card-wrapper {
            width: 100%;
            max-width: 600px;
            background: #0f172a;
            border: 3px solid #ff4757;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 50px rgba(255, 71, 87, 0.25);
            position: relative;
            background-image: radial-gradient(circle at top right, rgba(255, 71, 87, 0.15) 0%, transparent 60%);
        }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #ff4757; padding-bottom: 1rem; margin-bottom: 1.2rem; }
        .brand-title { color: #ffffff; font-size: 1.3rem; font-weight: 800; letter-spacing: 0.5px; }
        .brand-sub { font-size: 0.75rem; color: #ff4757; text-transform: uppercase; font-weight: 800; letter-spacing: 1px; }
        .patient-row { display: flex; align-items: center; gap: 1rem; margin-bottom: 1.2rem; }
        .photo { width: 65px; height: 65px; border-radius: 50%; object-fit: cover; border: 2.5px solid #ff4757; background: #1e293b; }
        .p-name { font-size: 1.3rem; font-weight: 800; color: #ffffff; }
        .pill { display: inline-block; background: #1e293b; color: #50e3c2; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.78rem; font-weight: 700; margin-right: 0.3rem; margin-top: 0.3rem; border: 1px solid rgba(255,255,255,0.1); }
        .pill-red { background: rgba(255, 71, 87, 0.2); color: #ff6b81; border-color: #ff4757; }
        
        .section-box { background: #1e293b; border-radius: 12px; padding: 1rem; margin-bottom: 1rem; border: 1px solid rgba(255,255,255,0.08); }
        .sec-title { font-size: 0.85rem; font-weight: 800; text-transform: uppercase; color: #ff4757; margin-bottom: 0.5rem; letter-spacing: 0.5px; }
        
        .contact-card { background: rgba(46, 213, 115, 0.1); border: 1px solid #2ed573; border-radius: 10px; padding: 0.75rem 1rem; margin-bottom: 0.5rem; }
        .c-name { font-size: 1rem; font-weight: 800; color: #ffffff; }
        .c-phone { font-size: 1rem; font-weight: 800; color: #2ed573; font-family: monospace; }
        
        .alg-item { background: rgba(255, 71, 87, 0.15); border-left: 3px solid #ff4757; padding: 0.5rem 0.8rem; border-radius: 6px; margin-bottom: 0.4rem; font-size: 0.9rem; }
        
        .footer-note { font-size: 0.75rem; color: #94a3b8; border-top: 1px dashed rgba(255, 255, 255, 0.2); padding-top: 0.8rem; margin-top: 1rem; text-align: center; }
        
        .actions-row { margin-top: 1.5rem; display: flex; gap: 1rem; justify-content: center; }
        .btn-act { background: #ff4757; color: #ffffff; border: none; padding: 0.8rem 1.6rem; border-radius: 12px; font-weight: 700; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        .btn-green { background: #2ed573; color: #000000; }
        
        @media print {
            body { background: #ffffff; padding: 0; }
            .card-wrapper { background: #0f172a !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; box-shadow: none; border-color: #ff4757 !important; }
            .actions-row { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="card-wrapper" id="printableEmergencyCard">
        <div class="header">
            <div>
                <div class="brand-title"><i class="fas fa-heartbeat" style="color:#ff4757;"></i> MedicalAk</div>
                <div class="brand-sub">Emergency Information Card</div>
            </div>
            <div style="text-align: right;">
                <span class="pill pill-red"><i class="fas fa-ambulance"></i> EMERGENCY CARD</span>
                <div style="font-size: 0.72rem; color: #94a3b8; margin-top: 0.3rem;">Updated: <?php echo $formatted_updated; ?></div>
            </div>
        </div>

        <div class="patient-row">
            <img src="<?php echo $profile_img_src; ?>" alt="<?php echo htmlspecialchars($patient['name']); ?>" class="photo">
            <div>
                <div class="p-name"><?php echo htmlspecialchars($patient['name']); ?></div>
                <div>
                    <span class="pill pill-red">Blood Group: <?php echo htmlspecialchars($blood_group); ?></span>
                    <span class="pill">ID: <?php echo htmlspecialchars($health_id); ?></span>
                    <?php if (!empty($dob)): ?>
                        <span class="pill">DOB: <?php echo htmlspecialchars($dob); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Emergency Contacts -->
        <?php if (in_array('emergency_contact', $public_fields) || empty($public_fields)): ?>
            <div class="section-box" style="border-left: 3px solid #2ed573;">
                <div class="sec-title" style="color: #2ed573;"><i class="fas fa-phone"></i> Primary Emergency Contact</div>
                <?php if (!empty($primary_phone)): ?>
                    <div class="contact-card">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="c-name"><?php echo htmlspecialchars(!empty($primary_name) ? $primary_name : 'Primary Contact'); ?> <?php echo !empty($primary_rel) ? '(' . htmlspecialchars($primary_rel) . ')' : ''; ?></div>
                            <div class="c-phone"><?php echo htmlspecialchars($primary_phone); ?></div>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="font-size: 0.85rem; color: #ff4757;">No primary contact provided.</div>
                <?php endif; ?>

                <?php if (!empty($secondary_phone)): ?>
                    <div class="contact-card" style="border-color: #4a90e2; background: rgba(74, 144, 226, 0.1); margin-bottom: 0;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div class="c-name" style="color:#ffffff;"><?php echo htmlspecialchars(!empty($secondary_name) ? $secondary_name : 'Secondary Contact'); ?> <?php echo !empty($secondary_rel) ? '(' . htmlspecialchars($secondary_rel) . ')' : ''; ?></div>
                            <div class="c-phone" style="color:#4a90e2;"><?php echo htmlspecialchars($secondary_phone); ?></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Allergies -->
        <?php if (in_array('allergies', $public_fields) || empty($public_fields)): ?>
            <div class="section-box" style="border-left: 3px solid #ff4757;">
                <div class="sec-title"><i class="fas fa-allergies"></i> Known Allergies & Reactions</div>
                <?php if (!empty($allergies_list)): ?>
                    <?php foreach ($allergies_list as $alg): ?>
                        <?php 
                            $alg_n = is_array($alg) ? ($alg['allergy'] ?? '') : (string)$alg;
                            $alg_s = is_array($alg) ? ($alg['severity'] ?? 'Unspecified') : 'Unspecified';
                            $alg_r = is_array($alg) ? ($alg['reaction'] ?? '') : '';
                        ?>
                        <div class="alg-item">
                            <strong><?php echo htmlspecialchars($alg_n); ?></strong> (<?php echo htmlspecialchars($alg_s); ?>)
                            <?php if (!empty($alg_r)): ?> - <em><?php echo htmlspecialchars($alg_r); ?></em><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php elseif ($no_allergies_confirmed): ?>
                    <div style="font-size: 0.85rem; color: #2ed573;">Confirmed: No known allergies.</div>
                <?php else: ?>
                    <div style="font-size: 0.85rem; color: #ffa502;">Allergy information not provided.</div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Conditions & Medications -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem; margin-bottom: 1rem;">
            <?php if (in_array('conditions', $public_fields) || empty($public_fields)): ?>
                <div class="section-box" style="margin-bottom: 0;">
                    <div class="sec-title" style="color: #4a90e2;"><i class="fas fa-stethoscope"></i> Medical Conditions</div>
                    <div style="font-size: 0.85rem; color: #ffffff; white-space: pre-line;">
                        <?php echo !empty($conditions_text) ? htmlspecialchars($conditions_text) : 'Not provided'; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (in_array('medications', $public_fields) || empty($public_fields)): ?>
                <div class="section-box" style="margin-bottom: 0;">
                    <div class="sec-title" style="color: #50e3c2;"><i class="fas fa-pills"></i> Current Medications</div>
                    <div style="font-size: 0.85rem; color: #ffffff; white-space: pre-line;">
                        <?php echo !empty($medications_text) ? htmlspecialchars($medications_text) : 'Not provided'; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($medical_devices) || !empty($emergency_instructions)): ?>
            <div class="section-box">
                <div class="sec-title" style="color: #eccc68;"><i class="fas fa-exclamation-triangle"></i> Devices & Emergency Instructions</div>
                <?php if (!empty($medical_devices)): ?>
                    <div style="font-size: 0.85rem; margin-bottom: 0.3rem;"><strong>Devices:</strong> <?php echo htmlspecialchars($medical_devices); ?></div>
                <?php endif; ?>
                <?php if (!empty($emergency_instructions)): ?>
                    <div style="font-size: 0.85rem;"><strong>Instructions:</strong> <?php echo htmlspecialchars($emergency_instructions); ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="footer-note">
            🔒 MedicalAk Emergency Protocol • Authorized Patient-Shared Information • Static document copy may be retained by recipients.
        </div>
    </div>

    <div class="actions-row">
        <button onclick="window.print()" class="btn-act"><i class="fas fa-print"></i> Print Card</button>
        <button onclick="downloadPDF()" class="btn-act btn-green"><i class="fas fa-file-pdf"></i> Download PDF</button>
    </div>

<script>
function downloadPDF() {
    const element = document.getElementById('printableEmergencyCard');
    const opt = {
        margin:       0.2,
        filename:     <?php echo json_encode($filename); ?>,
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true, backgroundColor: '#0b0f19' },
        jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
    };
    html2pdf().set(opt).from(element).save();
}
</script>

</body>
</html>
