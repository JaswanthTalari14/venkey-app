<?php
require_once 'config.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$error = '';
$share_data = null;
$patient_data = null;
$records = [];

if (empty($token)) {
    $error = "Invalid or missing share token.";
} else {
    $stmt = $conn->prepare("SELECT * FROM secure_shares WHERE share_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $res = $stmt->get_result();

    if (!$res || $res->num_rows === 0) {
        $error = "The requested medical share link does not exist or has been deleted.";
    } else {
        $share_data = $res->fetch_assoc();

        if ($share_data['is_revoked']) {
            $error = "Access to these medical records has been explicitly revoked by the patient.";
        } elseif (strtotime($share_data['expires_at']) < time()) {
            $error = "This secure medical share link expired on " . date('M d, Y h:i A', strtotime($share_data['expires_at'])) . ".";
        } else {
            $patient_id = (int)$share_data['patient_id'];
            $p_stmt = $conn->prepare("SELECT name, email, phone, blood_group, allergies FROM users WHERE id = ?");
            $p_stmt->bind_param("i", $patient_id);
            $p_stmt->execute();
            $patient_data = $p_stmt->get_result()->fetch_assoc();

            $items = json_decode($share_data['items_json'], true);
            if (!is_array($items)) $items = [];

            // Fetch included records
            if (in_array('latest_prescription', $items)) {
                $rx_q = $conn->query("SELECT p.*, u.name as doctor_name FROM prescriptions p JOIN users u ON p.doctor_id = u.id WHERE p.patient_id = $patient_id ORDER BY p.created_at DESC LIMIT 3");
                if ($rx_q) {
                    while($r = $rx_q->fetch_assoc()) $records['prescriptions'][] = $r;
                }
            }

            if (in_array('lab_reports', $items)) {
                $doc_q = $conn->query("SELECT * FROM medical_documents WHERE patient_id = $patient_id ORDER BY created_at DESC LIMIT 5");
                if ($doc_q) {
                    while($d = $doc_q->fetch_assoc()) $records['documents'][] = $d;
                }
            }
        }
    }
}

include 'includes/header.php';
?>

<div style="max-width: 800px; margin: 2rem auto; padding: 0 1rem;">
    <?php if ($error): ?>
        <div class="glass-panel" style="padding: 2.5rem; text-align: center; border-left: 4px solid #ff4757;">
            <div style="width: 60px; height: 60px; border-radius: 50%; background: rgba(255, 71, 87, 0.15); color: #ff4757; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin: 0 auto 1.5rem;">
                <i class="fas fa-lock"></i>
            </div>
            <h2 style="margin-top: 0; color: var(--text-primary);">Access Restricted</h2>
            <p style="color: var(--text-secondary); font-size: 1rem; max-width: 500px; margin: 0 auto 1.5rem;"><?php echo htmlspecialchars($error); ?></p>
            <a href="index.php" class="btn btn-primary">Return to Home</a>
        </div>
    <?php else: ?>
        <div class="glass-panel" style="padding: 2rem; margin-bottom: 2rem; border-top: 4px solid var(--primary-color);">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--glass-border); padding-bottom: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin: 0; color: var(--text-primary);"><i class="fas fa-file-medical" style="color: var(--primary-color);"></i> Authorized Shared Health Record</h2>
                    <p style="color: var(--text-secondary); margin: 0.3rem 0 0;">Patient: <strong><?php echo htmlspecialchars($patient_data['name']); ?></strong></p>
                </div>
                <span style="font-size: 0.8rem; font-weight: bold; background: rgba(46, 213, 115, 0.15); color: #2ed573; padding: 0.4rem 0.8rem; border-radius: 12px;">
                    <i class="fas fa-check-circle"></i> Link Valid until <?php echo date('h:i A, M d', strtotime($share_data['expires_at'])); ?>
                </span>
            </div>

            <?php if (!empty($records['prescriptions'])): ?>
                <h3 style="color: var(--secondary-color); margin-top: 1.5rem;"><i class="fas fa-prescription"></i> Shared Prescriptions</h3>
                <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
                    <?php foreach($records['prescriptions'] as $rx): ?>
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1.25rem; border-radius: 12px;">
                            <h4 style="margin: 0 0 0.5rem; color: var(--text-primary);">Rx #<?php echo $rx['id']; ?> — Dr. <?php echo htmlspecialchars($rx['doctor_name']); ?></h4>
                            <p style="font-size: 0.85rem; color: var(--text-secondary); margin: 0 0 0.5rem;">Date: <?php echo date('M d, Y', strtotime($rx['consultation_date'])); ?></p>
                            <?php if ($rx['notes']): ?>
                                <p style="font-size: 0.9rem; color: var(--text-primary); background: rgba(255,255,255,0.02); padding: 0.8rem; border-radius: 8px; margin: 0;"><?php echo nl2br(htmlspecialchars($rx['notes'])); ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($records['documents'])): ?>
                <h3 style="color: var(--accent); margin-top: 1.5rem;"><i class="fas fa-folder-open"></i> Shared Lab & Diagnostic Documents</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                    <?php foreach($records['documents'] as $doc): ?>
                        <div style="background: rgba(255,255,255,0.03); border: 1px solid var(--glass-border); padding: 1rem; border-radius: 12px;">
                            <h4 style="margin: 0 0 0.3rem; font-size: 0.95rem; color: var(--text-primary);"><?php echo htmlspecialchars($doc['title']); ?></h4>
                            <p style="font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.8rem;"><?php echo htmlspecialchars($doc['category']) . ' • ' . date('M d, Y', strtotime($doc['created_at'])); ?></p>
                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="btn btn-outline" style="font-size: 0.75rem; width: 100%; text-align: center;">View Document</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="border-top: 1px solid var(--glass-border); padding-top: 1rem; font-size: 0.8rem; color: var(--text-secondary); text-align: center;">
                MedicalAk Secure Healthcare Sharing Protocol • All rights reserved.
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
