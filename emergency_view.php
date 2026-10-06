<?php
require_once 'config.php';

$qr = isset($_GET['qr']) ? trim($_GET['qr']) : '';
$error = '';
$public_info = [];

if (empty($qr)) {
    $error = "Invalid or missing emergency QR token.";
} else {
    $stmt = $conn->prepare("SELECT e.*, u.name, u.phone, u.blood_group, u.allergies, u.emergency_contact FROM emergency_cards e JOIN users u ON e.user_id = u.id WHERE e.qr_token = ?");
    $stmt->bind_param("s", $qr);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        $error = "Emergency Card not found or has been disabled.";
    } else {
        $data = $res->fetch_assoc();
        $fields = json_decode($data['public_fields_json'], true);
        if (!is_array($fields)) $fields = [];

        if (in_array('name', $fields)) $public_info['Full Name'] = $data['name'];
        if (in_array('blood_group', $fields)) $public_info['Blood Group'] = !empty($data['blood_group']) ? $data['blood_group'] : 'Not specified';
        if (in_array('allergies', $fields)) $public_info['Allergies'] = !empty($data['allergies']) ? $data['allergies'] : 'None listed';
        if (in_array('emergency_contact', $fields)) $public_info['Emergency Contact'] = !empty($data['emergency_contact']) ? $data['emergency_contact'] : (!empty($data['phone']) ? $data['phone'] : 'Not specified');
    }
}

include 'includes/header.php';
?>

<div style="max-width: 600px; margin: 3rem auto; padding: 0 1rem;">
    <?php if ($error): ?>
        <div class="glass-panel" style="padding: 2.5rem; text-align: center; border-left: 4px solid #ff4757;">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(255, 71, 87, 0.15); color: #ff4757; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 1.5rem;">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 style="margin-top: 0; color: var(--text-primary);">Emergency Card Unavailable</h2>
            <p style="color: var(--text-secondary); font-size: 1rem;"><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php else: ?>
        <div class="glass-panel" style="padding: 2rem; border-top: 4px solid #ff4757; box-shadow: 0 15px 35px rgba(255, 71, 87, 0.15);">
            <div style="text-align: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
                <div style="width: 50px; height: 50px; border-radius: 50%; background: rgba(255, 71, 87, 0.15); color: #ff4757; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 0.8rem;">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h2 style="margin: 0; color: var(--text-primary);"><i class="fas fa-id-card" style="color: #ff4757;"></i> Emergency Medical Card</h2>
                <p style="color: var(--text-secondary); font-size: 0.85rem; margin: 0.4rem 0 0;">Public Authorized Emergency Responder Card</p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
                <?php foreach($public_info as $label => $val): ?>
                    <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1rem 1.25rem; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                        <span style="font-size: 0.9rem; color: var(--text-secondary); font-weight: bold;"><?php echo htmlspecialchars($label); ?>:</span>
                        <span style="font-size: 1.1rem; color: var(--text-primary); font-weight: 700;">
                            <?php if ($label === 'Emergency Contact'): ?>
                                <a href="tel:<?php echo htmlspecialchars($val); ?>" style="color: #2ed573; text-decoration: underline;"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($val); ?></a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($val); ?>
                            <?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="text-align: center; font-size: 0.8rem; color: var(--text-secondary); border-top: 1px solid var(--glass-border); padding-top: 1rem;">
                🔒 MedicalAk Emergency Protocol • Only patient-selected public fields are visible. Private financial & medical history is strictly protected.
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
