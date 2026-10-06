<?php
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$patient_id = (int)$_SESSION['user_id'];

// Handle Data Export Request
if (isset($_GET['action']) && $_GET['action'] === 'export_data') {
    // Collect all patient records
    $u_res = $conn->query("SELECT id, name, email, phone, role, blood_group, allergies, emergency_contact, created_at FROM users WHERE id = $patient_id");
    $p_data = $u_res ? $u_res->fetch_assoc() : [];

    $app_res = $conn->query("SELECT * FROM appointments WHERE patient_id = $patient_id");
    $app_list = [];
    if ($app_res) while($r = $app_res->fetch_assoc()) $app_list[] = $r;

    $rx_res = $conn->query("SELECT * FROM prescriptions WHERE patient_id = $patient_id");
    $rx_list = [];
    if ($rx_res) while($r = $rx_res->fetch_assoc()) $rx_list[] = $r;

    $doc_res = $conn->query("SELECT * FROM medical_documents WHERE patient_id = $patient_id");
    $doc_list = [];
    if ($doc_res) while($r = $doc_res->fetch_assoc()) $doc_list[] = $r;

    $export_payload = [
        'export_timestamp' => date('Y-m-d H:i:s'),
        'platform' => 'MedicalAk Healthcare Platform',
        'profile' => $p_data,
        'appointments' => $app_list,
        'prescriptions' => $rx_list,
        'documents' => $doc_list
    ];

    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="MedicalAk_Health_Export_' . $patient_id . '_' . date('Ymd') . '.json"');
    echo json_encode($export_payload, JSON_PRETTY_PRINT);
    exit;
}

// Fetch notification preferences
$notif_pref_res = $conn->query("SELECT * FROM notification_preferences WHERE user_id = $patient_id");
$notif_pref = ($notif_pref_res && $notif_pref_res->num_rows > 0) ? $notif_pref_res->fetch_assoc() : [
    'order_notif' => 1,
    'appointment_notif' => 1,
    'referral_notif' => 1,
    'payment_notif' => 1,
    'system_notif' => 1
];

include 'includes/header.php';
?>

<div class="dashboard-layout">
    <aside class="sidebar glass-panel">
        <button class="sidebar-toggle" aria-label="Toggle Menu">
            <span><i class="fas fa-bars" style="margin-right: 0.5rem;"></i> Menu</span>
            <i class="fas fa-chevron-down toggle-icon"></i>
        </button>
        <h3 class="sidebar-title" style="margin-bottom: 2rem;">Menu</h3>
        <ul class="sidebar-menu">
            <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile Settings</a></li>
            <li><a href="security_center.php"><i class="fas fa-shield-alt"></i> Security Center</a></li>
            <li><a href="data_privacy.php" class="active"><i class="fas fa-user-lock"></i> Data & Privacy</a></li>
            <li><a href="health_vault.php"><i class="fas fa-vault"></i> Health Vault</a></li>
        </ul>
    </aside>

    <main class="dashboard-content">
        <h2><i class="fas fa-user-lock" style="color: var(--primary-color);"></i> Data & Privacy Controls</h2>
        <p style="color: var(--text-secondary); margin-bottom: 1.5rem;">Manage your medical data exports, notification preferences, and privacy settings.</p>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <!-- Health Data Export Card -->
            <div class="glass-panel" style="padding: 1.5rem; border-left: 4px solid #2ed573;">
                <h3 style="margin-top: 0; color: #2ed573;"><i class="fas fa-download"></i> Export Personal Health Data</h3>
                <p style="font-size: 0.88rem; color: var(--text-secondary); margin-bottom: 1.2rem;">Download a complete JSON data package containing your profile details, appointment records, prescriptions, and medical document metadata.</p>
                <a href="data_privacy.php?action=export_data" class="btn btn-primary" style="width: 100%; text-align: center;">
                    <i class="fas fa-file-download"></i> Download Health Package (.JSON)
                </a>
            </div>

            <!-- Data Retention Note -->
            <div class="glass-panel" style="padding: 1.5rem; border-left: 4px solid var(--accent);">
                <h3 style="margin-top: 0; color: var(--accent);"><i class="fas fa-balance-scale"></i> Healthcare Compliance & Retention</h3>
                <p style="font-size: 0.88rem; color: var(--text-secondary); margin: 0; line-height: 1.5;">
                    In accordance with applicable healthcare regulations, medical diagnostic reports and digital doctor prescriptions are retained securely for clinical continuity. Account deletion requests preserve required clinical audit records per legal retention standards.
                </p>
            </div>
        </div>

        <!-- Notification Preferences Form -->
        <div class="glass-panel" style="padding: 1.5rem;">
            <h3 style="margin-top: 0; color: var(--text-primary);"><i class="fas fa-bell"></i> Notification & Communication Preferences</h3>
            <form id="notifPrefForm">
                <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.8rem; cursor: pointer; color: var(--text-primary);">
                    <input type="checkbox" name="order_notif" value="1" <?php echo $notif_pref['order_notif'] ? 'checked' : ''; ?>>
                    <span>Medicine Orders & Shipping Updates</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.8rem; cursor: pointer; color: var(--text-primary);">
                    <input type="checkbox" name="appointment_notif" value="1" <?php echo $notif_pref['appointment_notif'] ? 'checked' : ''; ?>>
                    <span>Doctor Appointment Reminders</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.8rem; cursor: pointer; color: var(--text-primary);">
                    <input type="checkbox" name="wallet_notif" value="1" <?php echo $notif_pref['payment_notif'] ? 'checked' : ''; ?>>
                    <span>Wallet Top-ups & Financial Transactions</span>
                </label>
                <label style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.8rem; cursor: pointer; color: var(--text-primary);">
                    <input type="checkbox" name="referral_notif" value="1" <?php echo $notif_pref['referral_notif'] ? 'checked' : ''; ?>>
                    <span>Referral Rewards & Bonus Alerts</span>
                </label>

                <button type="submit" class="btn btn-primary" style="margin-top: 1rem;">Save Preferences</button>
            </form>
        </div>
    </main>
</div>

<script>
document.getElementById('notifPrefForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    formData.append('action', 'save_notification_preferences');

    fetch('api_patient_features.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            alert(res.message);
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
