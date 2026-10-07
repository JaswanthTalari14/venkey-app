<?php
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    http_response_code(403);
    die("<div style='font-family:sans-serif; padding:2rem; text-align:center;'><h2>Unauthorized Access</h2><p>Please log in to your patient account.</p></div>");
}

$patient_id = (int)$_SESSION['user_id'];
$active_card = get_patient_active_medical_card($conn, $patient_id);

if (!$active_card || $active_card['status'] !== 'active') {
    http_response_code(403);
    die("<div style='font-family:sans-serif; padding:2rem; text-align:center; color:#ff4757;'><h2>Unable to Download Medical Card</h2><p>Your Digital Medical Card is not active or has expired. Only approved active cards can be downloaded.</p><a href='digital_medical_card.php' style='color:#4a90e2;'>Return to Medical Card Page</a></div>");
}

// Fetch Patient Info
$u_stmt = $conn->prepare("SELECT name, phone, email, profile_image FROM users WHERE id = ?");
$u_stmt->bind_param("i", $patient_id);
$u_stmt->execute();
$patient = $u_stmt->get_result()->fetch_assoc();

$card_settings = get_medical_card_settings($conn);
$card_number = htmlspecialchars($active_card['card_number']);
$patient_name = htmlspecialchars($patient['name']);
$valid_from = date('d M Y', strtotime($active_card['valid_from']));
$valid_until = date('d M Y', strtotime($active_card['valid_until']));
$consult_disc = (float)$card_settings['consultation_discount_percent'];
$med_disc = (float)$card_settings['medicine_discount_percent'];

// Resolve profile image URL
$profile_img_src = get_profile_image_url($patient);
if (empty($profile_img_src)) {
    $profile_img_src = 'https://ui-avatars.com/api/?name=' . urlencode($patient['name']) . '&background=4a90e2&color=fff&size=128';
}

$filename = "Digital-Medical-Card-" . preg_replace('/[^A-Za-z0-9\-]/', '', $card_number) . ".pdf";

// Handle print/download parameter
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $filename; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #0b0f19; color: #ffffff; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; padding: 1.5rem; }
        .card-container { width: 100%; max-width: 540px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); border: 2px solid #50e3c2; border-radius: 20px; padding: 2rem; box-shadow: 0 20px 50px rgba(0,0,0,0.6); position: relative; overflow: hidden; }
        .glow-overlay { position: absolute; top: -60px; right: -60px; width: 200px; height: 200px; background: radial-gradient(circle, rgba(80, 227, 194, 0.25) 0%, transparent 70%); pointer-events: none; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed rgba(255, 255, 255, 0.15); padding-bottom: 1.2rem; margin-bottom: 1.4rem; }
        .brand { display: flex; align-items: center; gap: 0.8rem; }
        .brand-icon { color: #50e3c2; font-size: 2rem; }
        .brand-title { color: #ffffff; font-size: 1.35rem; font-weight: 800; letter-spacing: 0.5px; }
        .brand-subtitle { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 1.5px; font-weight: 700; }
        .status-badge { background: rgba(46, 213, 115, 0.18); color: #2ed573; border: 1px solid #2ed573; padding: 0.4rem 0.95rem; border-radius: 20px; font-size: 0.82rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.4rem; }
        .body-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 1.2rem; }
        .patient-details { display: flex; align-items: center; gap: 1rem; }
        .patient-photo { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; border: 2px solid #50e3c2; background: rgba(0,0,0,0.3); }
        .patient-label { font-size: 0.75rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .patient-name { font-size: 1.25rem; font-weight: 800; color: #ffffff; margin-top: 0.2rem; }
        .card-no-val { font-size: 1.2rem; font-weight: 800; color: #4a90e2; font-family: monospace; letter-spacing: 1px; margin-top: 0.2rem; }
        .dates-box { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; background: rgba(0, 0, 0, 0.3); padding: 1rem 1.2rem; border-radius: 14px; border: 1px solid rgba(255, 255, 255, 0.08); margin-bottom: 1.2rem; }
        .date-label { font-size: 0.72rem; color: #94a3b8; text-transform: uppercase; }
        .date-val { font-size: 0.95rem; font-weight: 700; color: #ffffff; margin-top: 0.2rem; }
        .benefits-box { border-top: 1px dashed rgba(255, 255, 255, 0.15); padding-top: 1rem; }
        .benefits-title { font-size: 0.78rem; font-weight: 700; color: #94a3b8; text-transform: uppercase; margin-bottom: 0.6rem; }
        .benefit-item { display: flex; align-items: center; gap: 0.6rem; font-size: 0.9rem; color: #ffffff; margin-bottom: 0.45rem; }
        .benefit-item i { color: #2ed573; }
        .footer-action { margin-top: 1.8rem; display: flex; gap: 1rem; }
        .btn-print { background: #4a90e2; color: #ffffff; border: none; padding: 0.85rem 1.8rem; border-radius: 12px; font-weight: 700; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; text-decoration: none; }
        @media print {
            body { background: #ffffff; padding: 0; }
            .card-container { background: #0f172a !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; border-color: #50e3c2 !important; box-shadow: none; }
            .footer-action { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="card-container" id="printableCard">
        <div class="glow-overlay"></div>
        
        <div class="header">
            <div class="brand">
                <i class="fas fa-heartbeat brand-icon"></i>
                <div>
                    <div class="brand-title">MedicalAk</div>
                    <div class="brand-subtitle">Digital Medical Card</div>
                </div>
            </div>
            <div class="status-badge">
                <i class="fas fa-circle" style="font-size: 0.5rem;"></i> ACTIVE
            </div>
        </div>

        <div class="body-row">
            <div class="patient-details">
                <img src="<?php echo htmlspecialchars($profile_img_src); ?>" alt="<?php echo $patient_name; ?>" class="patient-photo">
                <div>
                    <div class="patient-label">Patient Name</div>
                    <div class="patient-name"><?php echo $patient_name; ?></div>
                </div>
            </div>
            <div style="text-align: right;">
                <div class="patient-label">Medical Card No</div>
                <div class="card-no-val"><?php echo $card_number; ?></div>
            </div>
        </div>

        <div class="dates-box">
            <div>
                <div class="date-label">Valid From</div>
                <div class="date-val"><?php echo $valid_from; ?></div>
            </div>
            <div>
                <div class="date-label">Valid Until</div>
                <div class="date-val" style="color: #50e3c2;"><?php echo $valid_until; ?></div>
            </div>
        </div>

        <div class="benefits-box">
            <div class="benefits-title">Active Member Benefits</div>
            <div class="benefit-item">
                <i class="fas fa-check-circle"></i>
                <span><strong><?php echo $consult_disc; ?>% Discount</strong> on Doctor Consultations (Auto Applied)</span>
            </div>
            <div class="benefit-item">
                <i class="fas fa-check-circle"></i>
                <span><strong><?php echo $med_disc; ?>% Discount</strong> on All Medicine Purchases (Auto Applied)</span>
            </div>
        </div>
    </div>

    <div class="footer-action">
        <button onclick="window.print()" class="btn-print"><i class="fas fa-print"></i> Print / Save as PDF</button>
        <a href="digital_medical_card.php" class="btn-print" style="background: rgba(255,255,255,0.1); color: #ffffff;"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
    </div>

    <script>
        // Auto trigger print to save PDF if opened in dedicated mode
        window.addEventListener('load', function() {
            if (window.location.search.indexOf('print=1') !== -1) {
                setTimeout(function() { window.print(); }, 500);
            }
        });
    </script>
</body>
</html>
